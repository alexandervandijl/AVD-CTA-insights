<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Action_Generator {

    /*
     * Bestaande optionnaam bewust behouden.
     * Zo blijft een eerder opgeslagen actiewachtrij beschikbaar.
     */
    const OPTION_QUEUE = 'avd_uber_cta_action_queue';

    /**
     * Genereert een nieuwe actiewachtrij op basis van de huidige statistieken.
     */
    public static function generate(): array {
        $payload = self::get_payload();

        $attention = isset($payload['needs_attention']) && is_array($payload['needs_attention'])
            ? $payload['needs_attention']
            : array();

        $existing_queue = self::get_queue();
        $existing_statuses = self::existing_statuses($existing_queue);

        $actions = array();

        foreach (array_slice($attention, 0, 12) as $page) {
            if (!is_array($page)) {
                continue;
            }

            $page_name = isset($page['page'])
                ? sanitize_text_field((string) $page['page'])
                : 'onbekend';

            $intent = self::detect_page_intent($page_name);
            $action_id = self::build_action_id($page_name, $intent);
            $template = self::get_template($intent);

            $actions[] = array(
                'id' => $action_id,

                'status' => isset($existing_statuses[$action_id])
                    ? $existing_statuses[$action_id]
                    : 'pending',

                'page' => $page_name,

                'views' => isset($page['views'])
                    ? (int) $page['views']
                    : 0,

                'cta' => isset($page['cta'])
                    ? (int) $page['cta']
                    : 0,

                'conversion' => isset($page['conversion'])
                    ? (float) $page['conversion']
                    : 0,

                'intent' => $intent,
                'intent_label' => self::intent_label($intent),

                'action' => self::action_key_for_intent($intent),
                'action_label' => self::action_label_for_intent($intent),

                'impact' => self::impact_for_page($page, $intent),
                'confidence' => self::confidence_for_page($page, $intent),

                'template' => $template,

                'template_enabled' => !empty($template['enabled'])
                    ? 1
                    : 0,

                'created' => time(),
            );
        }

        if (empty($actions)) {
            $intent = 'homepage_intent';
            $action_id = self::build_action_id('homepage', $intent);
            $template = self::get_template($intent);

            $actions[] = array(
                'id' => $action_id,

                'status' => isset($existing_statuses[$action_id])
                    ? $existing_statuses[$action_id]
                    : 'pending',

                'page' => 'homepage',
                'views' => 0,
                'cta' => 0,
                'conversion' => 0,

                'intent' => $intent,
                'intent_label' => self::intent_label($intent),

                'action' => self::action_key_for_intent($intent),
                'action_label' => self::action_label_for_intent($intent),

                'impact' => 'Middel',
                'confidence' => 50,

                'template' => $template,

                'template_enabled' => !empty($template['enabled'])
                    ? 1
                    : 0,

                'created' => time(),
            );
        }

        update_option(
            self::OPTION_QUEUE,
            $actions,
            false
        );

        return $actions;
    }

    /**
     * Geeft de huidige opgeslagen wachtrij terug.
     */
    public static function get_queue(): array {
        $queue = get_option(
            self::OPTION_QUEUE,
            array()
        );

        return is_array($queue)
            ? $queue
            : array();
    }

    /**
     * Registreert een nieuwe status voor één actie.
     */
    public static function update_status(
        string $action_id,
        string $status
    ): bool {
        $allowed_statuses = array(
            'pending',
            'ignored',
            'approved',
            'applied',
        );

        $action_id = sanitize_key($action_id);
        $status = sanitize_key($status);

        if (
            $action_id === '' ||
            !in_array($status, $allowed_statuses, true)
        ) {
            return false;
        }

        $queue = self::get_queue();
        $updated = false;

        foreach ($queue as &$action) {
            if (!is_array($action)) {
                continue;
            }

            if (
                isset($action['id']) &&
                sanitize_key((string) $action['id']) === $action_id
            ) {
                $action['status'] = $status;
                $action['updated'] = time();
                $updated = true;
                break;
            }
        }

        unset($action);

        if (!$updated) {
            return false;
        }

        update_option(
            self::OPTION_QUEUE,
            $queue,
            false
        );

        return true;
    }

    /**
     * Geeft de statistiekenpayload terug.
     */
    private static function get_payload(): array {
        if (
            !class_exists('AVDCTAI_Stats') ||
            !class_exists('AVDCTAI_Plugin')
        ) {
            return array();
        }

        $stats = new AVDCTAI_Stats(
            AVDCTAI_Plugin::instance()
        );

        $payload = $stats->get_payload();

        return is_array($payload)
            ? $payload
            : array();
    }

    /**
     * Bewaart bestaande statussen wanneer de queue opnieuw wordt gegenereerd.
     */
    private static function existing_statuses(array $queue): array {
        $statuses = array();

        foreach ($queue as $action) {
            if (
                !is_array($action) ||
                empty($action['id']) ||
                empty($action['status'])
            ) {
                continue;
            }

            $action_id = sanitize_key(
                (string) $action['id']
            );

            $status = sanitize_key(
                (string) $action['status']
            );

            if ($action_id === '') {
                continue;
            }

            $statuses[$action_id] = $status;
        }

        return $statuses;
    }

    /**
     * Maakt een stabiele unieke actie-ID.
     */
    private static function build_action_id(
        string $page,
        string $intent
    ): string {
        return 'act_' . substr(
            md5(
                strtolower(trim($page)) . '|' . $intent
            ),
            0,
            12
        );
    }

    /**
     * Haalt een configureerbaar CTA-template op.
     */
    private static function get_template(string $intent): array {
        if (class_exists('AVDCTAI_Template_Manager')) {
            $template = AVDCTAI_Template_Manager::get_template(
                $intent
            );

            if (is_array($template)) {
                return wp_parse_args(
                    $template,
                    self::empty_template()
                );
            }
        }

        return self::empty_template();
    }

    private static function empty_template(): array {
        return array(
            'title' => '',
            'text' => '',
            'button' => '',
            'url' => '',
            'enabled' => 0,
        );
    }

    public static function detect_page_intent(string $page): string {
        $page = strtolower(trim($page));

        if (
            $page === '' ||
            $page === 'homepage' ||
            $page === '/' ||
            $page === 'home'
        ) {
            return 'homepage_intent';
        }

        if (
            strpos($page, 'tag/') !== false ||
            strpos($page, 'category/') !== false
        ) {
            return 'category_or_tag_intent';
        }

        if (
            strpos($page, '-bellen') !== false ||
            strpos($page, 'bellen') !== false ||
            strpos($page, 'telefoon') !== false ||
            strpos($page, 'bel') !== false
        ) {
            return 'phone_intent';
        }

        if (
            strpos($page, 'woningnet') !== false ||
            strpos($page, 'gemeente') !== false ||
            strpos($page, 'amazon') !== false ||
            strpos($page, 'ikea') !== false ||
            strpos($page, 'contact') !== false ||
            strpos($page, 'klantenservice') !== false ||
            strpos($page, 'bereikbaar') !== false ||
            strpos($page, 'werkt-niet') !== false
        ) {
            return 'contact_problem_intent';
        }

        if (
            strpos($page, 'avd-uber-cta') !== false ||
            strpos($page, 'avd-cta-insights') !== false ||
            strpos($page, 'wordpress') !== false ||
            strpos($page, 'plugin') !== false ||
            strpos($page, 'ai-conversion') !== false ||
            strpos($page, 'conversie') !== false
        ) {
            return 'plugin_intent';
        }

        if (
            strpos($page, 'prijzen') !== false ||
            strpos($page, 'tarief') !== false ||
            strpos($page, 'kosten') !== false ||
            strpos($page, 'prijs') !== false
        ) {
            return 'pricing_intent';
        }

        if (
            strpos($page, 'diensten') !== false ||
            strpos($page, 'bedrijfsscan') !== false ||
            strpos($page, 'bereikbaarheidscheck') !== false ||
            strpos($page, 'bedrijfspagina') !== false ||
            strpos($page, 'zakelijk') !== false ||
            strpos($page, 'telecom') !== false ||
            strpos($page, 'voip') !== false ||
            strpos($page, 'internet') !== false ||
            strpos($page, 'ai-assistent') !== false
        ) {
            return 'business_service_intent';
        }

        return 'content_intent';
    }

    public static function intent_label(string $intent): string {
        $labels = array(
            'homepage_intent' => 'Homepage / algemene instap',
            'phone_intent' => 'Belintentie',
            'contact_problem_intent' => 'Contact- of bereikbaarheidsprobleem',
            'business_service_intent' => 'Zakelijke dienstinteresse',
            'plugin_intent' => 'Plugin- of WordPress-interesse',
            'pricing_intent' => 'Prijsinteresse',
            'category_or_tag_intent' => 'Routepagina',
            'content_intent' => 'Informatieve intentie',
        );

        return $labels[$intent] ?? 'Onbekend';
    }

    public static function action_key_for_intent(
        string $intent
    ): string {
        $actions = array(
            'homepage_intent' => 'clarify_homepage_choice',
            'phone_intent' => 'insert_phone_cta',
            'contact_problem_intent' => 'insert_contact_help_cta',
            'business_service_intent' => 'insert_business_scan_cta',
            'plugin_intent' => 'insert_plugin_download_cta',
            'pricing_intent' => 'insert_low_friction_contact_cta',
            'category_or_tag_intent' => 'insert_route_links',
            'content_intent' => 'insert_contextual_cta',
        );

        return $actions[$intent] ?? 'review_page_cta';
    }

    public static function action_label_for_intent(
        string $intent
    ): string {
        $actions = array(
            'homepage_intent' => 'Maak bovenaan een duidelijke keuze tussen de belangrijkste bezoekersdoelen.',
            'phone_intent' => 'Voeg bovenaan een korte bel- of contact-CTA toe.',
            'contact_problem_intent' => 'Voeg bovenaan een CTA naar hulp, contact of een assistent toe.',
            'business_service_intent' => 'Voeg bovenaan een CTA naar een scan, adviesgesprek of offerte toe.',
            'plugin_intent' => 'Maak download, installatiehulp of conversiescan prominenter.',
            'pricing_intent' => 'Voeg een laagdrempelige contact-CTA toe.',
            'category_or_tag_intent' => 'Maak dit een routepagina met duidelijke vervolgstappen.',
            'content_intent' => 'Voeg een contextuele CTA halverwege of onderaan de pagina toe.',
        );

        return $actions[$intent]
            ?? 'Controleer de eerste zichtbare CTA op deze pagina.';
    }

    public static function impact_for_page(
        array $page,
        string $intent
    ): string {
        $views = isset($page['views'])
            ? (int) $page['views']
            : 0;

        $cta = isset($page['cta'])
            ? (int) $page['cta']
            : 0;

        if ($views >= 10 && $cta === 0) {
            return 'Hoog';
        }

        if (
            in_array(
                $intent,
                array(
                    'phone_intent',
                    'contact_problem_intent',
                    'plugin_intent',
                    'business_service_intent',
                ),
                true
            )
        ) {
            return 'Hoog';
        }

        if ($views >= 3) {
            return 'Middel';
        }

        return 'Laag';
    }

    public static function confidence_for_page(
        array $page,
        string $intent
    ): int {
        $views = isset($page['views'])
            ? (int) $page['views']
            : 0;

        $cta = isset($page['cta'])
            ? (int) $page['cta']
            : 0;

        $confidence = 45;

        if ($views >= 3) {
            $confidence += 10;
        }

        if ($views >= 10) {
            $confidence += 15;
        }

        if ($cta === 0) {
            $confidence += 10;
        }

        if (
            in_array(
                $intent,
                array(
                    'phone_intent',
                    'contact_problem_intent',
                    'plugin_intent',
                    'business_service_intent',
                ),
                true
            )
        ) {
            $confidence += 15;
        }

        return min(
            95,
            max(10, $confidence)
        );
    }
}
