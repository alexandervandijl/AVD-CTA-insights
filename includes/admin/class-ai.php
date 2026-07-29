<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_AI {

    public static function init() {
        // AI-briefing en ChatGPT-export.
    }

    public static function render($plugin = null) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $stats = new AVDCTAI_Stats(AVDCTAI_Plugin::instance());
        $payload = $stats->get_payload();

        $today = isset($payload['today']) ? $payload['today'] : array();
        $week = isset($payload['week']) ? $payload['week'] : array();
        $attention = isset($payload['needs_attention']) ? $payload['needs_attention'] : array();

        $events = self::get_recent_events();

        $today_start = strtotime(current_time('Y-m-d') . ' 00:00:00');
        $week_start = $today_start - (6 * DAY_IN_SECONDS);
        $now = time();

        $money_today = self::summarize_money_events($events, $today_start, $now);
        $money_week = self::summarize_money_events($events, $week_start, $now);

        $business_today = self::summarize_business_intent($events, $today_start, $now);
        $business_week = self::summarize_business_intent($events, $week_start, $now);

        $export = self::build_ai_briefing($payload);
        ?>
        <div class="wrap">
            <h1>AVD AI Analyse</h1>
            <p>Hier zie je de belangrijkste cijfers, zakelijke signalen en verbeterpunten voor ChatGPT of je eigen AI-analyse.</p>

            <div class="avd-section">
                <h2>✅ Tracking status</h2>
                <p>Gebruik deze knop om snel een test-event te registreren. Dit test-event telt niet mee als pageview of CTA.</p>
                <p>
                    <button class="button button-secondary" id="avd-test-tracking">Test tracking-event</button>
                    <span id="avd-test-tracking-result"></span>
                </p>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var button = document.getElementById('avd-test-tracking');
                var result = document.getElementById('avd-test-tracking-result');

                if (!button) {
                    return;
                }

                button.addEventListener('click', function() {
                    result.textContent = ' Bezig...';

                    fetch(ajaxurl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            action: 'avd_uber_cta_event',
                            nonce: '<?php echo esc_js(wp_create_nonce('avd_uber_cta_event')); ?>',
                            type: 'admin_tracking_test',
                            source: 'admin_test',
                            context: 'admin_test',
                            device: 'desktop',
                            pageUrl: window.location.href,
                            targetUrl: '',
                            label: 'Handmatige trackingtest',
                            sessionId: 'admin-test-' + Date.now()
                        })
                    })
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(data) {
                        result.textContent = data.success ? ' Gelukt ✅' : ' Mislukt ❌';
                    })
                    .catch(function() {
                        result.textContent = ' Fout ❌';
                    });
                });
            });
            </script>

            <div class="avd-dashboard-grid">
                <div class="avd-card">
                    <h2>Vandaag views</h2>
                    <p class="avd-number"><?php echo esc_html($today['views'] ?? 0); ?></p>
                </div>

                <div class="avd-card">
                    <h2>Vandaag CTA's</h2>
                    <p class="avd-number"><?php echo esc_html($today['cta'] ?? 0); ?></p>
                </div>

                <div class="avd-card">
                    <h2>Conversie vandaag</h2>
                    <p class="avd-number"><?php echo esc_html($today['conversion_views'] ?? 0); ?>%</p>
                </div>

                <div class="avd-card">
                    <h2>Week CTA's</h2>
                    <p class="avd-number"><?php echo esc_html($week['cta'] ?? 0); ?></p>
                </div>

                <div class="avd-card">
                    <h2>Money Events vandaag</h2>
                    <p class="avd-number"><?php echo esc_html($money_today['count']); ?></p>
                </div>

                <div class="avd-card">
                    <h2>Zakelijke intentie vandaag</h2>
                    <p class="avd-number"><?php echo esc_html($business_today['count']); ?></p>
                </div>

                <div class="avd-card">
                    <h2>Money score week</h2>
                    <p class="avd-number"><?php echo esc_html($money_week['score']); ?></p>
                </div>

                <div class="avd-card">
                    <h2>Zakelijke score week</h2>
                    <p class="avd-number"><?php echo esc_html($business_week['score']); ?></p>
                </div>
            </div>

            <div class="avd-section">
                <h2>🔥 Verbeterpunten</h2>

                <?php if (!empty($attention)) : ?>
                    <ol>
                        <?php foreach (array_slice($attention, 0, 5) as $page) : ?>
                            <?php
                            $page_name = isset($page['page']) ? (string) $page['page'] : 'Onbekende pagina';
                            $advice = self::page_intent_advice($page_name);
                            ?>
                            <li>
                                <strong><?php echo esc_html($page_name); ?></strong>
                                — <?php echo esc_html($page['views'] ?? 0); ?> views,
                                <?php echo esc_html($page['cta'] ?? 0); ?> CTA's,
                                <?php echo esc_html($page['conversion'] ?? 0); ?>% conversie.
                                <br>
                                <small><strong>Advies:</strong> <?php echo esc_html($advice); ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else : ?>
                    <p>Nog geen verbeterpunten gevonden.</p>
                <?php endif; ?>
            </div>

            <div class="avd-section">
                <h2>📋 AI Briefing voor ChatGPT</h2>
                <p>Kopieer deze briefing en plak hem in ChatGPT voor een diepere analyse.</p>

                <p>
                    <button class="button button-primary" id="avd-copy-ai-export">Kopieer AI Briefing</button>
                </p>

                <textarea id="avd-ai-export" readonly style="width:100%;min-height:520px;font-family:monospace;"><?php echo esc_textarea($export); ?></textarea>

                <script>
                document.getElementById('avd-copy-ai-export').addEventListener('click', function(){
                    var textarea = document.getElementById('avd-ai-export');
                    textarea.select();
                    document.execCommand('copy');
                    this.textContent = 'Gekopieerd!';
                });
                </script>
            </div>
        </div>
        <?php
    }

    private static function build_ai_briefing(array $payload): string {
        $today = isset($payload['today']) ? $payload['today'] : array();
        $week = isset($payload['week']) ? $payload['week'] : array();
        $top_pages = isset($payload['top_pages']) ? $payload['top_pages'] : array();
        $attention = isset($payload['needs_attention']) ? $payload['needs_attention'] : array();

        $events = self::get_recent_events();

        $today_start = strtotime(current_time('Y-m-d') . ' 00:00:00');
        $week_start = $today_start - (6 * DAY_IN_SECONDS);
        $now = time();

        $cta_today = self::summarize_cta_breakdown($events, $today_start, $now);
        $cta_week = self::summarize_cta_breakdown($events, $week_start, $now);

        $money_today = self::summarize_money_events($events, $today_start, $now);
        $money_week = self::summarize_money_events($events, $week_start, $now);

        $business_today = self::summarize_business_intent($events, $today_start, $now);
        $business_week = self::summarize_business_intent($events, $week_start, $now);

        $out = "=== AVD AI ANALYSE ===\n";
        $out .= "Site: AlexandervanDijl.nl\n";
        $out .= "Pluginversie: " . AVDCTAI_Plugin::VERSION . "\n";
        $out .= "Doel: meer CTA-kliks, plugin-downloads, zakelijke leads, donaties en omzetkansen uit bestaand verkeer halen.\n\n";

        $out .= "=== VANDAAG ===\n";
        $out .= "Views: " . ($today['views'] ?? 0) . "\n";
        $out .= "Sessies: " . ($today['sessions'] ?? 0) . "\n";
        $out .= "CTA-kliks: " . ($today['cta'] ?? 0) . "\n";
        $out .= "Conversie op views: " . ($today['conversion_views'] ?? 0) . "%\n";
        $out .= "Conversie op sessies: " . ($today['conversion_sessions'] ?? 0) . "%\n";
        $out .= "Money Events vandaag: " . $money_today['count'] . "\n";
        $out .= "Money score vandaag: " . $money_today['score'] . "\n";
        $out .= "Zakelijke intentie vandaag: " . $business_today['count'] . "\n";
        $out .= "Zakelijke intentiescore vandaag: " . $business_today['score'] . "\n\n";

        $out .= "=== DEZE WEEK ===\n";
        $out .= "Views: " . ($week['views'] ?? 0) . "\n";
        $out .= "Sessies: " . ($week['sessions'] ?? 0) . "\n";
        $out .= "CTA-kliks: " . ($week['cta'] ?? 0) . "\n";
        $out .= "Conversie op views: " . ($week['conversion_views'] ?? 0) . "%\n";
        $out .= "Money Events deze week: " . $money_week['count'] . "\n";
        $out .= "Money score deze week: " . $money_week['score'] . "\n";
        $out .= "Zakelijke intentie deze week: " . $business_week['count'] . "\n";
        $out .= "Zakelijke intentiescore deze week: " . $business_week['score'] . "\n\n";

        $out .= "=== CTA BREAKDOWN VANDAAG ===\n";
        $out .= self::format_count_list($cta_today['types'], 'Nog geen CTA-types vandaag.');
        $out .= "\n";

        $out .= "=== CTA BREAKDOWN DEZE WEEK ===\n";
        $out .= self::format_count_list($cta_week['types'], 'Nog geen CTA-types deze week.');
        $out .= "\n";

        $out .= "=== CTA BRONNEN DEZE WEEK ===\n";
        $out .= self::format_count_list($cta_week['sources'], 'Nog geen CTA-bronnen deze week.');
        $out .= "\n";

        $out .= "=== MONEY EVENTS DEZE WEEK ===\n";
        $out .= self::format_count_list($money_week['types'], 'Nog geen Money Events deze week.');
        $out .= "\n";

        $out .= "=== ZAKELIJKE INTENTIE DEZE WEEK ===\n";
        $out .= self::format_count_list($business_week['signals'], 'Nog geen zakelijke intentie deze week.');
        $out .= "\n";

        $out .= "=== ZAKELIJKE BRONNEN DEZE WEEK ===\n";
        $out .= self::format_count_list($business_week['sources'], 'Nog geen zakelijke bronnen deze week.');
        $out .= "\n";

        $out .= "=== TOP PAGINA'S ===\n";
        if (!empty($top_pages)) {
            foreach (array_slice($top_pages, 0, 10) as $page) {
                $out .= "- " . ($page['page'] ?? 'onbekend') . ": "
                    . ($page['views'] ?? 0) . " views, "
                    . ($page['cta'] ?? 0) . " CTA, "
                    . ($page['conversion'] ?? 0) . "% conversie\n";
            }
        } else {
            $out .= "Geen top pagina's beschikbaar.\n";
        }

        $out .= "\n=== VERBETERPUNTEN ===\n";
        if (!empty($attention)) {
            foreach (array_slice($attention, 0, 10) as $page) {
                $page_name = isset($page['page']) ? (string) $page['page'] : 'onbekend';
                $advice = self::page_intent_advice($page_name);

                $out .= "- " . $page_name . ": "
                    . ($page['views'] ?? 0) . " views, "
                    . ($page['cta'] ?? 0) . " CTA, "
                    . ($page['conversion'] ?? 0) . "% conversie. Advies: " . $advice . "\n";
            }
        } else {
            $out .= "Geen verbeterpunten beschikbaar.\n";
        }

        $out .= "\n=== INTERPRETATIEHULP ===\n";
        $out .= "- CTA-kliks tonen alle meetbare acties.\n";
        $out .= "- Money Events zijn acties met mogelijke omzetwaarde, zoals downloads, mailkliks, leadkliks, donaties, prijzen en diensten.\n";
        $out .= "- Zakelijke intentie is geen echte lead, maar een signaal dat een bezoeker mogelijk zakelijk interessant is.\n";
        $out .= "- Echte leads blijven aanvragen, formulieracties, mailkliks of expliciete leadkliks.\n\n";

        $out .= "=== OPDRACHT AAN CHATGPT ===\n";
        $out .= "Analyseer deze cijfers. Geef prioriteit aan acties met de grootste kans op extra CTA-kliks, plugin-downloads, zakelijke leads, donaties of omzet. Kijk specifiek naar CTA Breakdown, Money Events en Zakelijke Intentie. Geef een concreet actieplan voor vandaag met maximaal 5 acties.\n";

        return $out;
    }

    private static function get_recent_events(): array {
        if (!class_exists('AVDCTAI_Plugin')) {
            return array();
        }

        $events = get_option(AVDCTAI_Plugin::OPTION_RECENT_EVENTS, array());

        return is_array($events) ? $events : array();
    }

    private static function summarize_cta_breakdown(array $events, int $from, int $to): array {
        $types = array();
        $sources = array();
        $total = 0;

        foreach ($events as $event) {
            $timestamp = self::event_timestamp($event);

            if ($timestamp < $from || $timestamp > $to) {
                continue;
            }

            $type = strtolower(self::event_field($event, array('type')));

            if (!self::is_cta_event($type)) {
                continue;
            }

            $source = strtolower(self::event_field($event, array('source'), 'unknown'));

            $total++;
            $types[$type] = isset($types[$type]) ? $types[$type] + 1 : 1;
            $sources[$source] = isset($sources[$source]) ? $sources[$source] + 1 : 1;
        }

        arsort($types);
        arsort($sources);

        return array(
            'total' => $total,
            'types' => $types,
            'sources' => $sources,
        );
    }

    private static function summarize_money_events(array $events, int $from, int $to): array {
        $types = array();
        $count = 0;
        $score = 0;

        foreach ($events as $event) {
            $timestamp = self::event_timestamp($event);

            if ($timestamp < $from || $timestamp > $to) {
                continue;
            }

            $type = strtolower(self::event_field($event, array('type')));

            if (!self::is_money_event($type)) {
                continue;
            }

            $count++;
            $score += self::money_event_score($type);
            $types[$type] = isset($types[$type]) ? $types[$type] + 1 : 1;
        }

        arsort($types);

        return array(
            'count' => $count,
            'score' => $score,
            'types' => $types,
        );
    }

    private static function summarize_business_intent(array $events, int $from, int $to): array {
        $count = 0;
        $score = 0;
        $signals = array();
        $sources = array();

        foreach ($events as $event) {
            $timestamp = self::event_timestamp($event);

            if ($timestamp < $from || $timestamp > $to) {
                continue;
            }

            $signal = self::detect_business_signal($event);

            if ($signal === '') {
                continue;
            }

            $source = strtolower(self::event_field($event, array('source'), 'unknown'));
            $signal_score = self::business_signal_score($signal);

            $count++;
            $score += $signal_score;

            $signals[$signal] = isset($signals[$signal]) ? $signals[$signal] + 1 : 1;
            $sources[$source] = isset($sources[$source]) ? $sources[$source] + 1 : 1;
        }

        arsort($signals);
        arsort($sources);

        return array(
            'count' => $count,
            'score' => $score,
            'signals' => $signals,
            'sources' => $sources,
        );
    }

    private static function is_cta_event(string $type): bool {
        $type = strtolower(trim($type));

        if ($type === '') {
            return false;
        }

        if (class_exists('AVDCTAI_Visitor_Intelligence')) {
            return AVDCTAI_Visitor_Intelligence::is_real_cta_event($type);
        }

        $excluded = array(
            'page_view',
            'view',
            'engaged_session',
            'scroll',
            'scroll_25',
            'scroll_50',
            'scroll_75',
            'popup_view',
            'popup_shown',
            'popup_open',
            'popup_close',
            'sticky_close',
            'toolbar_view',
            'heartbeat',
            'admin_tracking_test',
        );

        if (in_array($type, $excluded, true)) {
            return false;
        }

        return (
            strpos($type, 'cta') !== false ||
            strpos($type, 'click') !== false ||
            strpos($type, 'call') !== false ||
            strpos($type, 'bel') !== false ||
            strpos($type, 'whatsapp') !== false ||
            strpos($type, 'mail') !== false ||
            strpos($type, 'lead') !== false ||
            strpos($type, 'download') !== false ||
            strpos($type, 'donation') !== false ||
            strpos($type, 'claim') !== false ||
            strpos($type, 'aanvraag') !== false
        );
    }

    private static function is_money_event(string $type): bool {
        $type = strtolower(trim($type));

        if ($type === '') {
            return false;
        }

        $money_events = array(
            'download_click',
            'mail_click',
            'lead_click',
            'donation_click',
            'pricing_click',
            'services_click',
            'plugin_page_click',
            'checklist_download',
            'whatsapp_click',
            'mail_share_click',
            'whatsapp_share_click',
            'ai_assistent_click',
            'app_click',
            'tel_click',
        );

        if (in_array($type, $money_events, true)) {
            return true;
        }

        return (
            strpos($type, 'download') !== false ||
            strpos($type, 'mail') !== false ||
            strpos($type, 'lead') !== false ||
            strpos($type, 'donation') !== false ||
            strpos($type, 'pricing') !== false ||
            strpos($type, 'services') !== false ||
            strpos($type, 'plugin') !== false ||
            strpos($type, 'whatsapp') !== false
        );
    }

    private static function money_event_score(string $type): int {
        $scores = array(
            'lead_click' => 10,
            'mail_click' => 8,
            'donation_click' => 8,
            'pricing_click' => 7,
            'download_click' => 6,
            'plugin_page_click' => 5,
            'services_click' => 5,
            'checklist_download' => 4,
            'whatsapp_click' => 4,
            'ai_assistent_click' => 3,
            'app_click' => 2,
            'tel_click' => 2,
            'mail_share_click' => 2,
            'whatsapp_share_click' => 2,
        );

        return isset($scores[$type]) ? $scores[$type] : 1;
    }

    private static function detect_business_signal(array $event): string {
        $type = strtolower(self::event_field($event, array('type')));
        $source = strtolower(self::event_field($event, array('source')));
        $label = strtolower(self::event_field($event, array('label')));
        $page_url = strtolower(self::event_field($event, array('page_url', 'pageUrl', 'page')));
        $target_url = strtolower(self::event_field($event, array('target_url', 'targetUrl')));

        $combined = $type . ' ' . $source . ' ' . $label . ' ' . $page_url . ' ' . $target_url;

        /*
         * Pageviews mogen zakelijke intentie zijn, maar alleen op zakelijke pagina's.
         */
        if ($type === 'page_view') {
            if (self::contains_any($page_url, array(
                'avd-uber-cta-ai-conversion-platform-voor-wordpress',
                'diensten-van-alexandervandijl-nl',
                'prijzen',
                'bereikbaarheidscheck-aanvragen',
                'gratis-bedrijfsscan',
                'bedrijfspagina-claimen',
                'ai-assistent',
            ))) {
                return 'business_page_view';
            }

            return '';
        }

        /*
         * Scrolls, engagement, sticky bar close, popup events en first_click
         * mogen NIET als zakelijke intentie meetellen.
         */
        if (!self::is_business_intent_event_candidate($type)) {
            return '';
        }

        if (self::contains_any($combined, array(
            'lead_click',
            'bedrijfspagina',
            'bedrijfsscan',
            'bereikbaarheidscheck',
            'homepage_business_block',
            'homepage_business_check_block',
        ))) {
            return 'business_lead_intent';
        }

        if (self::contains_any($combined, array(
            'plugin_page_click',
            'download_click',
            'avd-uber-cta',
            'plugin',
            'wordpress plugin',
            'homepage_plugin_block',
        ))) {
            return 'plugin_business_intent';
        }

        if (self::contains_any($combined, array(
            'pricing_click',
            'prijzen',
            'price',
            'tarief',
        ))) {
            return 'pricing_intent';
        }

        if (self::contains_any($combined, array(
            'services_click',
            'diensten',
            'dienst',
            'service',
        ))) {
            return 'services_intent';
        }

        if (self::contains_any($combined, array(
            'mail_click',
            'mailto:',
            'hallo@alexandervandijl.nl',
            'conversiescan',
            'offerte',
            'aanvragen',
        ))) {
            return 'contact_intent';
        }

        if (self::contains_any($combined, array(
            'checklist_download',
            'bereikbaarheidscheck_alexandervandijl',
            'download gratis checklist',
        ))) {
            return 'checklist_intent';
        }

        return '';
    }

    private static function is_business_intent_event_candidate(string $type): bool {
        $type = strtolower(trim($type));

        if ($type === '') {
            return false;
        }

        $excluded = array(
            'page_view',
            'view',
            'engaged_session',
            'scroll',
            'scroll_25',
            'scroll_50',
            'scroll_75',
            'popup_view',
            'popup_shown',
            'popup_open',
            'popup_close',
            'sticky_close',
            'toolbar_view',
            'heartbeat',
            'first_click',
            'admin_tracking_test',
        );

        if (in_array($type, $excluded, true)) {
            return false;
        }

        return (
            strpos($type, 'click') !== false ||
            strpos($type, 'cta') !== false ||
            strpos($type, 'lead') !== false ||
            strpos($type, 'mail') !== false ||
            strpos($type, 'download') !== false ||
            strpos($type, 'plugin') !== false ||
            strpos($type, 'pricing') !== false ||
            strpos($type, 'services') !== false ||
            strpos($type, 'donation') !== false ||
            strpos($type, 'whatsapp') !== false ||
            strpos($type, 'checklist') !== false
        );
    }

    private static function business_signal_score(string $signal): int {
        $scores = array(
            'business_lead_intent' => 10,
            'contact_intent' => 9,
            'pricing_intent' => 8,
            'plugin_business_intent' => 7,
            'checklist_intent' => 6,
            'services_intent' => 6,
            'business_page_view' => 3,
        );

        return isset($scores[$signal]) ? $scores[$signal] : 1;
    }

    private static function page_intent_advice(string $page): string {
        $intent = self::detect_page_intent($page);

        switch ($intent) {
            case 'phone_intent':
                return 'Deze pagina heeft duidelijke belintentie. Toon bovenaan een korte CTA naar de gratis Doorverbinder-app, bijvoorbeeld: “Bellen lukt niet? Probeer gratis de Doorverbinder-app.”';

            case 'contact_problem_intent':
                return 'Deze pagina draait waarschijnlijk om contact opnemen of bereikbaarheid. Toon bovenaan een CTA naar de Doorverbinder-app of AI-assistent, met de nadruk op snel iemand bereiken.';

            case 'business_service_intent':
                return 'Deze pagina heeft zakelijke of dienstgerichte intentie. Toon bovenaan een CTA naar de bereikbaarheidscheck, bedrijfsscan of een vrijblijvend adviesgesprek.';

            case 'plugin_intent':
                return 'Deze pagina heeft plugin- of WordPress-intentie. Maak de downloadknop en installatiehulp prominenter en voeg een CTA toe voor een AI Conversiescan.';

            case 'pricing_intent':
                return 'Deze pagina heeft prijsinteresse. Toon direct een laagdrempelige CTA zoals “Laat gratis meekijken” of “Vraag een vrijblijvende inschatting aan”.';

            case 'category_or_tag_intent':
                return 'Dit is een tag- of categoriepagina. Gebruik deze pagina vooral als routepagina: plaats bovenaan links naar de Doorverbinder-app, AI-assistent en relevante populaire pagina’s.';

            case 'content_intent':
                return 'Deze pagina heeft informatieve intentie. Voeg halverwege en onderaan een contextuele CTA toe die logisch aansluit op het onderwerp.';

            case 'homepage_intent':
                return 'De homepage is een algemene instappagina. Test een duidelijkere keuze bovenaan: “Ik wil een nummer bereiken” versus “Ik wil meer leads uit mijn WordPress-site”.';

            default:
                return 'Controleer of de eerste zichtbare CTA past bij de zoekintentie van deze pagina. Maak de belangrijkste actie bovenaan direct duidelijk.';
        }
    }

    private static function detect_page_intent(string $page): string {
        $page = strtolower(trim($page));

        if ($page === '' || $page === 'homepage' || $page === '/' || $page === 'home') {
            return 'homepage_intent';
        }

        if (strpos($page, 'tag/') !== false || strpos($page, 'category/') !== false) {
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

    private static function event_timestamp(array $event): int {
        if (isset($event['timestamp'])) {
            return (int) $event['timestamp'];
        }

        if (isset($event['time'])) {
            $parsed = strtotime((string) $event['time']);

            if ($parsed) {
                return (int) $parsed;
            }
        }

        return 0;
    }

    private static function event_field(array $event, array $keys, string $default = ''): string {
        foreach ($keys as $key) {
            if (isset($event[$key]) && $event[$key] !== '') {
                return (string) $event[$key];
            }
        }

        return $default;
    }

    private static function contains_any(string $haystack, array $needles): bool {
        foreach ($needles as $needle) {
            $needle = strtolower((string) $needle);

            if ($needle !== '' && strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function format_count_list(array $items, string $empty_message): string {
        if (empty($items)) {
            return $empty_message . "\n";
        }

        $out = '';

        foreach (array_slice($items, 0, 10, true) as $key => $count) {
            $out .= "- " . self::pretty_label((string) $key) . ": " . (int) $count . "\n";
        }

        return $out;
    }

    private static function pretty_label(string $value): string {
        $value = trim($value);

        if ($value === '') {
            return 'Onbekend';
        }

        $known = array(
            'app_click' => 'Doorverbinder-app',
            'plugin_page_click' => 'Pluginpagina',
            'download_click' => 'Download',
            'mail_click' => 'E-mailklik',
            'lead_click' => 'Leadklik',
            'donation_click' => 'Donatieklik',
            'pricing_click' => 'Prijzen',
            'services_click' => 'Diensten',
            'tel_click' => 'Belklik',
            'whatsapp_click' => 'WhatsApp',
            'ai_assistent_click' => 'AI-assistent',
            'checklist_download' => 'Checklist download',
            'mail_share_click' => 'Delen via e-mail',
            'whatsapp_share_click' => 'Delen via WhatsApp',
            'admin_tracking_test' => 'Admin trackingtest',

            'business_page_view' => 'Zakelijke pagina bekeken',
            'business_lead_intent' => 'Zakelijke lead-intentie',
            'plugin_business_intent' => 'Plugin zakelijke intentie',
            'pricing_intent' => 'Prijsinteresse',
            'services_intent' => 'Diensteninteresse',
            'contact_intent' => 'Contactintentie',
            'checklist_intent' => 'Checklist-interesse',

            'homepage_plugin_block' => 'Homepage pluginblok',
            'homepage_hero' => 'Homepage bovenaan',
            'homepage_mobile_block' => 'Homepage mobiel blok',
            'homepage_business_block' => 'Homepage ondernemersblok',
            'homepage_business_check_block' => 'Homepage bereikbaarheidscheck',
            'homepage_support_block' => 'Homepage steunblok',
            'homepage_services_block' => 'Homepage dienstenblok',
            'homepage_access_numbers' => 'Homepage toegangsnummers',
            'homepage_stuck_block' => 'Homepage vastgelopen blok',
            'homepage_share_block' => 'Homepage deelblok',
            'admin_test' => 'Admin test',
            'page' => 'Pagina',
            'unknown' => 'Onbekend',
        );

        if (isset($known[$value])) {
            return $known[$value];
        }

        return ucwords(str_replace(array('_', '-'), ' ', $value));
    }
}
