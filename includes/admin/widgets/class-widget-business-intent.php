<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Widget_Business_Intent extends AVDCTAI_Widget {

    public function render(array $payload): void {
        $events = $this->get_events();

        $today_start = strtotime(current_time('Y-m-d') . ' 00:00:00');
        $week_start = $today_start - (6 * DAY_IN_SECONDS);
        $now = time();

        $today = $this->summarize_business_intent(
            $events,
            $today_start,
            $now
        );

        $week = $this->summarize_business_intent(
            $events,
            $week_start,
            $now
        );

        $recent = $this->recent_business_intent(
            $events,
            $week_start,
            $now
        );

        ?>
        <div class="avd-section">
            <h2>🏢 Zakelijke intentie</h2>

            <p>
                Bezoekers die waarschijnlijk interessant zijn voor diensten,
                plugin-downloads, bedrijfsscan, bereikbaarheid of omzet.
            </p>

            <div class="avd-dashboard-grid">

                <div class="avd-card">
                    <h2>Vandaag</h2>

                    <p class="avd-number">
                        <?php echo esc_html((string) $today['count']); ?>
                    </p>
                </div>

                <div class="avd-card">
                    <h2>Deze week</h2>

                    <p class="avd-number">
                        <?php echo esc_html((string) $week['count']); ?>
                    </p>
                </div>

                <div class="avd-card">
                    <h2>Intentiescore</h2>

                    <p class="avd-number">
                        <?php echo esc_html((string) $week['score']); ?>
                    </p>
                </div>

                <div class="avd-card">
                    <h2>Sterkste signaal</h2>

                    <p style="font-size:1.15em;font-weight:bold;">
                        <?php
                        echo esc_html(
                            $this->best_signal($week['signals'])
                        );
                        ?>
                    </p>
                </div>

            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;margin-top:18px;">

                <div>
                    <h3>Zakelijke signalen deze week</h3>

                    <?php
                    $this->render_table(
                        $week['signals'],
                        'Signaal'
                    );
                    ?>
                </div>

                <div>
                    <h3>Zakelijke bronnen deze week</h3>

                    <?php
                    $this->render_table(
                        $week['sources'],
                        'Bron'
                    );
                    ?>
                </div>

            </div>

            <h3 style="margin-top:20px;">
                Laatste zakelijke signalen
            </h3>

            <?php if (empty($recent)) : ?>

                <p>Nog geen zakelijke intentie gevonden.</p>

            <?php else : ?>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Tijd</th>
                            <th>Signaal</th>
                            <th>Bron</th>
                            <th>Pagina</th>
                            <th>Score</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($recent as $event) : ?>
                            <tr>
                                <td>
                                    <?php
                                    echo esc_html(
                                        (string) ($event['time'] ?? '')
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo esc_html(
                                        $this->pretty_label(
                                            (string) ($event['signal'] ?? '')
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo esc_html(
                                        $this->pretty_label(
                                            (string) ($event['source'] ?? '')
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo esc_html(
                                        $this->short_page(
                                            (string) ($event['page_url'] ?? '')
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo esc_html(
                                        (string) ((int) ($event['score'] ?? 0))
                                    );
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php endif; ?>

            <p style="margin-top:14px;">
                <small>
                    Let op: zakelijke intentie is geen lead. Een echte lead
                    blijft een mailklik, formulieractie, aanvraag of andere
                    duidelijke actie.
                </small>
            </p>
        </div>
        <?php
    }

    private function get_events(): array {
        if (!class_exists('AVDCTAI_Plugin')) {
            return array();
        }

        $events = get_option(
            AVDCTAI_Plugin::OPTION_RECENT_EVENTS,
            array()
        );

        return is_array($events)
            ? $events
            : array();
    }

    private function summarize_business_intent(
        array $events,
        int $from,
        int $to
    ): array {
        $count = 0;
        $score = 0;
        $signals = array();
        $sources = array();

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $timestamp = isset($event['timestamp'])
                ? (int) $event['timestamp']
                : 0;

            if ($timestamp < $from || $timestamp > $to) {
                continue;
            }

            $signal = $this->detect_business_signal($event);

            if ($signal === null) {
                continue;
            }

            $source = isset($event['source'])
                ? strtolower((string) $event['source'])
                : 'unknown';

            $event_score = $this->signal_score($signal);

            $count++;
            $score += $event_score;

            $signals[$signal] = isset($signals[$signal])
                ? $signals[$signal] + 1
                : 1;

            $sources[$source] = isset($sources[$source])
                ? $sources[$source] + 1
                : 1;
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

    private function recent_business_intent(
        array $events,
        int $from,
        int $to
    ): array {
        $items = array();

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $timestamp = isset($event['timestamp'])
                ? (int) $event['timestamp']
                : 0;

            if ($timestamp < $from || $timestamp > $to) {
                continue;
            }

            $signal = $this->detect_business_signal($event);

            if ($signal === null) {
                continue;
            }

            $items[] = array(
                'timestamp' => $timestamp,
                'time' => isset($event['time'])
                    ? (string) $event['time']
                    : '',
                'signal' => $signal,
                'source' => isset($event['source'])
                    ? (string) $event['source']
                    : 'unknown',
                'page_url' => isset($event['page_url'])
                    ? (string) $event['page_url']
                    : '',
                'score' => $this->signal_score($signal),
            );
        }

        usort(
            $items,
            static function (array $a, array $b): int {
                return $b['timestamp'] <=> $a['timestamp'];
            }
        );

        return array_slice($items, 0, 12);
    }

    private function detect_business_signal(array $event): ?string {
        $type = isset($event['type'])
            ? strtolower((string) $event['type'])
            : '';

        $source = isset($event['source'])
            ? strtolower((string) $event['source'])
            : '';

        $label = isset($event['label'])
            ? strtolower((string) $event['label'])
            : '';

        $page_url = isset($event['page_url'])
            ? strtolower((string) $event['page_url'])
            : '';

        $target_url = isset($event['target_url'])
            ? strtolower((string) $event['target_url'])
            : '';

        $combined = $type
            . ' '
            . $source
            . ' '
            . $label
            . ' '
            . $page_url
            . ' '
            . $target_url;

        /*
         * Pageviews mogen zakelijke intentie zijn,
         * maar alleen op zakelijke pagina's.
         */
        if ($type === 'page_view') {
            if (
                $this->contains_any(
                    $page_url,
                    array(
                        'avd-uber-cta-ai-conversion-platform-voor-wordpress',
                        'avd-cta-insights',
                        'diensten-van-alexandervandijl-nl',
                        'prijzen',
                        'bereikbaarheidscheck-aanvragen',
                        'gratis-bedrijfsscan',
                        'bedrijfspagina-claimen',
                        'ai-assistent',
                    )
                )
            ) {
                return 'business_page_view';
            }

            return null;
        }

        /*
         * Scrolls, engagement-events en sluitacties
         * zijn geen zakelijke intentie.
         */
        if (!$this->is_business_intent_event_candidate($type)) {
            return null;
        }

        if (
            $this->contains_any(
                $combined,
                array(
                    'lead_click',
                    'bedrijfspagina',
                    'bedrijfsscan',
                    'bereikbaarheidscheck',
                    'homepage_business_block',
                    'homepage_business_check_block',
                )
            )
        ) {
            return 'business_lead_intent';
        }

        if (
            $this->contains_any(
                $combined,
                array(
                    'plugin_page_click',
                    'download_click',
                    'avd-uber-cta',
                    'avd-cta-insights',
                    'plugin',
                    'wordpress plugin',
                    'homepage_plugin_block',
                )
            )
        ) {
            return 'plugin_business_intent';
        }

        if (
            $this->contains_any(
                $combined,
                array(
                    'pricing_click',
                    'prijzen',
                    'price',
                    'tarief',
                )
            )
        ) {
            return 'pricing_intent';
        }

        if (
            $this->contains_any(
                $combined,
                array(
                    'services_click',
                    'diensten',
                    'dienst',
                    'service',
                )
            )
        ) {
            return 'services_intent';
        }

        if (
            $this->contains_any(
                $combined,
                array(
                    'mail_click',
                    'mailto:',
                    'hallo@alexandervandijl.nl',
                    'conversiescan',
                    'offerte',
                    'aanvragen',
                )
            )
        ) {
            return 'contact_intent';
        }

        if (
            $this->contains_any(
                $combined,
                array(
                    'checklist_download',
                    'bereikbaarheidscheck_alexandervandijl',
                    'download gratis checklist',
                )
            )
        ) {
            return 'checklist_intent';
        }

        return null;
    }

    private function is_business_intent_event_candidate(
        string $type
    ): bool {
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

    private function contains_any(
        string $haystack,
        array $needles
    ): bool {
        foreach ($needles as $needle) {
            $needle = strtolower((string) $needle);

            if (
                $needle !== '' &&
                strpos($haystack, $needle) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    private function signal_score(string $signal): int {
        $scores = array(
            'business_lead_intent' => 10,
            'contact_intent' => 9,
            'pricing_intent' => 8,
            'plugin_business_intent' => 7,
            'checklist_intent' => 6,
            'services_intent' => 6,
            'business_page_view' => 3,
        );

        return isset($scores[$signal])
            ? $scores[$signal]
            : 1;
    }

    private function best_signal(array $signals): string {
        if (empty($signals)) {
            return 'Nog geen data';
        }

        $key = array_key_first($signals);

        return $this->pretty_label((string) $key)
            . ' ('
            . (int) $signals[$key]
            . ')';
    }

    private function render_table(
        array $items,
        string $label
    ): void {
        $items = array_slice(
            $items,
            0,
            8,
            true
        );

        if (empty($items)) {
            echo '<p>Nog geen data beschikbaar.</p>';

            return;
        }

        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php echo esc_html($label); ?></th>
                    <th>Aantal</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($items as $key => $count) : ?>
                    <tr>
                        <td>
                            <?php
                            echo esc_html(
                                $this->pretty_label(
                                    (string) $key
                                )
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo esc_html(
                                (string) ((int) $count)
                            );
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function short_page(string $url): string {
        if ($url === '') {
            return 'Onbekend';
        }

        $path = wp_parse_url($url, PHP_URL_PATH);
        $path = trim((string) $path, '/');

        return $path !== ''
            ? $path
            : 'homepage';
    }

    private function pretty_label(string $value): string {
        $value = trim($value);

        if ($value === '') {
            return 'Onbekend';
        }

        $known = array(
            'business_page_view' => 'Zakelijke pagina bekeken',
            'business_lead_intent' => 'Zakelijke lead-intentie',
            'plugin_business_intent' => 'Plugin zakelijke intentie',
            'pricing_intent' => 'Prijsinteresse',
            'services_intent' => 'Diensteninteresse',
            'contact_intent' => 'Contactintentie',
            'checklist_intent' => 'Checklist-interesse',
            'homepage_business_block' => 'Homepage ondernemersblok',
            'homepage_business_check_block' => 'Homepage bereikbaarheidscheck',
            'homepage_plugin_block' => 'Homepage pluginblok',
            'homepage_services_block' => 'Homepage dienstenblok',
            'lead_click' => 'Leadklik',
            'mail_click' => 'E-mailklik',
            'download_click' => 'Download',
            'plugin_page_click' => 'Pluginpagina',
            'pricing_click' => 'Prijzen',
            'services_click' => 'Diensten',
            'checklist_download' => 'Checklist download',
            'page' => 'Pagina',
            'unknown' => 'Onbekend',
        );

        if (isset($known[$value])) {
            return $known[$value];
        }

        return ucwords(
            str_replace(
                array('_', '-'),
                ' ',
                $value
            )
        );
    }
}
