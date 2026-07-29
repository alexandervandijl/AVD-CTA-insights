<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Template_Manager {

    /*
     * Bestaande optionnaam bewust behouden.
     * Zo blijven eerder opgeslagen CTA-templates beschikbaar.
     */
    const OPTION_TEMPLATES = 'avd_uber_cta_templates';

    public static function get_templates(): array {
        $saved = get_option(
            self::OPTION_TEMPLATES,
            array()
        );

        if (!is_array($saved)) {
            $saved = array();
        }

        return wp_parse_args(
            $saved,
            self::defaults()
        );
    }

    public static function get_template(string $intent): array {
        $intent = sanitize_key($intent);
        $templates = self::get_templates();

        if (
            $intent === '' ||
            !isset($templates[$intent]) ||
            !is_array($templates[$intent])
        ) {
            return self::default_template();
        }

        return wp_parse_args(
            $templates[$intent],
            self::default_template()
        );
    }

    public static function save_templates(array $input): void {
        $clean = array();

        foreach (self::defaults() as $intent => $default) {
            $intent_input = isset($input[$intent]) && is_array($input[$intent])
                ? $input[$intent]
                : array();

            $clean[$intent] = array(
                'title' => isset($intent_input['title'])
                    ? sanitize_text_field(
                        wp_unslash($intent_input['title'])
                    )
                    : $default['title'],

                'text' => isset($intent_input['text'])
                    ? sanitize_textarea_field(
                        wp_unslash($intent_input['text'])
                    )
                    : $default['text'],

                'button' => isset($intent_input['button'])
                    ? sanitize_text_field(
                        wp_unslash($intent_input['button'])
                    )
                    : $default['button'],

                'url' => isset($intent_input['url'])
                    ? esc_url_raw(
                        wp_unslash($intent_input['url'])
                    )
                    : $default['url'],

                'enabled' => !empty($intent_input['enabled'])
                    ? 1
                    : 0,
            );
        }

        update_option(
            self::OPTION_TEMPLATES,
            $clean,
            false
        );
    }

    public static function defaults(): array {
        return array(
            'homepage_intent' => array(
                'title' => 'Wat wil je bereiken?',
                'text' => 'Kies direct de route die het beste past bij je bezoek.',
                'button' => 'Bekijk de beste optie',
                'url' => home_url('/'),
                'enabled' => 1,
            ),

            'phone_intent' => array(
                'title' => 'Bellen lukt niet?',
                'text' => 'Probeer een alternatieve route of maak het makkelijker voor bezoekers om direct contact op te nemen.',
                'button' => 'Probeer direct',
                'url' => home_url('/contact/'),
                'enabled' => 1,
            ),

            'contact_problem_intent' => array(
                'title' => 'Kom je er niet doorheen?',
                'text' => 'Help bezoekers sneller naar de juiste contactroute.',
                'button' => 'Bekijk hulpopties',
                'url' => home_url('/contact/'),
                'enabled' => 1,
            ),

            'business_service_intent' => array(
                'title' => 'Meer aanvragen uit je website?',
                'text' => 'Laat bezoekers laagdrempelig een scan, adviesgesprek of offerte aanvragen.',
                'button' => 'Vraag advies aan',
                'url' => home_url('/contact/'),
                'enabled' => 1,
            ),

            'plugin_intent' => array(
                'title' => 'Verbeter je WordPress-conversie',
                'text' => 'Laat zien welke pagina’s bezoekers krijgen en welke CTA’s beter kunnen.',
                'button' => 'Download of vraag hulp',
                'url' => home_url('/'),
                'enabled' => 1,
            ),

            'pricing_intent' => array(
                'title' => 'Twijfel je over de kosten?',
                'text' => 'Maak het makkelijk om eerst vrijblijvend contact op te nemen.',
                'button' => 'Laat gratis meekijken',
                'url' => home_url('/contact/'),
                'enabled' => 1,
            ),

            'category_or_tag_intent' => array(
                'title' => 'Zoek je de juiste pagina?',
                'text' => 'Help bezoekers snel naar de belangrijkste vervolgstappen.',
                'button' => 'Bekijk hulpopties',
                'url' => home_url('/'),
                'enabled' => 1,
            ),

            'content_intent' => array(
                'title' => 'Hulp nodig met dit onderwerp?',
                'text' => 'Voeg een logische vervolgstap toe die past bij de inhoud van de pagina.',
                'button' => 'Bekijk mogelijkheden',
                'url' => home_url('/contact/'),
                'enabled' => 1,
            ),
        );
    }

    public static function default_template(): array {
        return array(
            'title' => '',
            'text' => '',
            'button' => '',
            'url' => '',
            'enabled' => 0,
        );
    }

    public static function labels(): array {
        return array(
            'homepage_intent' => 'Homepage / algemene instap',
            'phone_intent' => 'Belintentie',
            'contact_problem_intent' => 'Contact- of bereikbaarheidsprobleem',
            'business_service_intent' => 'Zakelijke dienstinteresse',
            'plugin_intent' => 'Plugin- of WordPress-interesse',
            'pricing_intent' => 'Prijsinteresse',
            'category_or_tag_intent' => 'Routepagina',
            'content_intent' => 'Informatieve intentie',
        );
    }
}
