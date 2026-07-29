<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Widget_CTA_Breakdown extends AVDCTAI_Widget {

    public function render(array $payload): void {
        $events = $this->get_events();

        $today_start = strtotime(current_time('Y-m-d') . ' 00:00:00');
        $week_start = $today_start - (6 * DAY_IN_SECONDS);
        $now = time();

        $today = $this->summarize_ctas(
            $events,
            $today_start,
            $now
        );

        $week = $this->summarize_ctas(
            $events,
            $week_start,
            $now
        );

        ?>
        <div class="avd-section">
            <h2>🎯 CTA Breakdown</h2>

            <p>
                Welke knoppen, links en acties zorgen echt voor beweging?
            </p>

            <div class="avd-dashboard-grid">

                <div class="avd-card">
                    <h2>CTA's vandaag</h2>

                    <p class="avd-number">
                        <?php echo esc_html((string) $today['total']); ?>
                    </p>
                </div>

                <div class="avd-card">
                    <h2>CTA's deze week</h2>

                    <p class="avd-number">
                        <?php echo esc_html((string) $week['total']); ?>
                    </p>
                </div>

                <div class="avd-card">
                    <h2>Beste type</h2>

                    <p style="font-size:1.2em;font-weight:bold;">
                        <?php
                        echo esc_html(
                            $this->best_key($week['types'])
                        );
                        ?>
                    </p>
                </div>

                <div class="avd-card">
                    <h2>Beste bron</h2>

                    <p style="font-size:1.2em;font-weight:bold;">
                        <?php
                        echo esc_html(
                            $this->best_key($week['sources'])
                        );
                        ?>
                    </p>
                </div>

            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;margin-top:18px;">

                <div>
                    <h3>CTA-types deze week</h3>

                    <?php
                    $this->render_table(
                        $week['types'],
                        'Type'
                    );
                    ?>
                </div>

                <div>
                    <h3>CTA-bronnen deze week</h3>

                    <?php
                    $this->render_table(
                        $week['sources'],
                        'Bron'
                    );
                    ?>
                </div>

            </div>
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

    private function summarize_ctas(
        array $events,
        int $from,
        int $to
    ): array {
        $types = array();
        $sources = array();
        $labels = array();
        $total = 0;

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

            $type = isset($event['type'])
                ? strtolower((string) $event['type'])
                : '';

            if (!$this->is_cta_event($type)) {
                continue;
            }

            $source = isset($event['source'])
                ? strtolower((string) $event['source'])
                : 'unknown';

            $label = isset($event['label'])
                ? trim((string) $event['label'])
                : '';

            $total++;

            $types[$type] = isset($types[$type])
                ? $types[$type] + 1
                : 1;

            $sources[$source] = isset($sources[$source])
                ? $sources[$source] + 1
                : 1;

            if ($label !== '') {
                $labels[$label] = isset($labels[$label])
                    ? $labels[$label] + 1
                    : 1;
            }
        }

        arsort($types);
        arsort($sources);
        arsort($labels);

        return array(
            'total' => $total,
            'types' => $types,
            'sources' => $sources,
            'labels' => $labels,
        );
    }

    private function is_cta_event(string $type): bool {
        $type = strtolower(trim($type));

        if ($type === '') {
            return false;
        }

        if (class_exists('AVDCTAI_Visitor_Intelligence')) {
            return AVDCTAI_Visitor_Intelligence::is_real_cta_event(
                $type
            );
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

    private function best_key(array $items): string {
        if (empty($items)) {
            return 'Nog geen data';
        }

        $key = array_key_first($items);

        return $this->pretty_label((string) $key)
            . ' ('
            . (int) $items[$key]
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
            echo '<p>Nog geen CTA-data beschikbaar.</p>';

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

    private function pretty_label(string $value): string {
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
            'homepage_plugin_block' => 'Homepage pluginblok',
            'homepage_hero' => 'Homepage bovenaan',
            'homepage_mobile_block' => 'Homepage mobiel blok',
            'homepage_business_block' => 'Homepage ondernemersblok',
            'homepage_business_check_block' => 'Homepage bereikbaarheidscheck',
            'homepage_support_block' => 'Homepage steunblok',
            'homepage_services_block' => 'Homepage dienstenblok',
            'homepage_access_numbers' => 'Homepage toegangsnummers',
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
