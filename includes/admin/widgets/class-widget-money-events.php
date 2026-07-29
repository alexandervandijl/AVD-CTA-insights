<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Widget_Money_Events extends AVDCTAI_Widget {

    public function render(array $payload): void {
        $events = $this->get_events();

        $today_start = strtotime(current_time('Y-m-d') . ' 00:00:00');
        $week_start = $today_start - (6 * DAY_IN_SECONDS);
        $now = time();

        $today = $this->summarize_money_events($events, $today_start, $now);
        $week = $this->summarize_money_events($events, $week_start, $now);
        $recent = $this->recent_money_events($events, $week_start, $now);

        ?>
        <div class="avd-section">
            <h2>💰 Money Events</h2>
            <p>Acties die kunnen leiden tot downloads, leads, donaties, offerte-aanvragen of omzet.</p>

            <div class="avd-dashboard-grid">
                <div class="avd-card">
                    <h2>Vandaag</h2>
                    <p class="avd-number"><?php echo esc_html($today['count']); ?></p>
                </div>

                <div class="avd-card">
                    <h2>Deze week</h2>
                    <p class="avd-number"><?php echo esc_html($week['count']); ?></p>
                </div>

                <div class="avd-card">
                    <h2>Score deze week</h2>
                    <p class="avd-number"><?php echo esc_html($week['score']); ?></p>
                </div>
            </div>

            <h3>Waardevolle acties deze week</h3>

            <?php if (empty($week['types'])) : ?>
                <p>Nog geen Money Events gevonden.</p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Actie</th>
                            <th>Aantal</th>
                            <th>Waarde</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($week['types'], 0, 8, true) as $type => $count) : ?>
                            <tr>
                                <td><?php echo esc_html($this->pretty_label((string) $type)); ?></td>
                                <td><?php echo esc_html((int) $count); ?></td>
                                <td><?php echo esc_html($this->event_value_label((string) $type)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h3 style="margin-top:20px;">Laatste waardevolle acties</h3>

            <?php if (empty($recent)) : ?>
                <p>Nog geen recente waardevolle acties.</p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Tijd</th>
                            <th>Actie</th>
                            <th>Bron</th>
                            <th>Pagina</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $event) : ?>
                            <tr>
                                <td><?php echo esc_html($event['time']); ?></td>
                                <td><?php echo esc_html($this->pretty_label($event['type'])); ?></td>
                                <td><?php echo esc_html($this->pretty_label($event['source'])); ?></td>
                                <td><?php echo esc_html($this->short_page($event['page_url'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private function get_events(): array {
        if (!class_exists('AVDCTAI_Plugin')) {
            return array();
        }

        $events = get_option(AVDCTAI_Plugin::OPTION_RECENT_EVENTS, array());

        return is_array($events) ? $events : array();
    }

    private function summarize_money_events(array $events, int $from, int $to): array {
        $types = array();
        $count = 0;
        $score = 0;

        foreach ($events as $event) {
            $timestamp = isset($event['timestamp']) ? (int) $event['timestamp'] : 0;

            if ($timestamp < $from || $timestamp > $to) {
                continue;
            }

            $type = isset($event['type']) ? strtolower((string) $event['type']) : '';

            if (!$this->is_money_event($type)) {
                continue;
            }

            $count++;
            $score += $this->event_score($type);
            $types[$type] = isset($types[$type]) ? $types[$type] + 1 : 1;
        }

        arsort($types);

        return array(
            'count' => $count,
            'score' => $score,
            'types' => $types,
        );
    }

    private function recent_money_events(array $events, int $from, int $to): array {
        $items = array();

        foreach ($events as $event) {
            $timestamp = isset($event['timestamp']) ? (int) $event['timestamp'] : 0;

            if ($timestamp < $from || $timestamp > $to) {
                continue;
            }

            $type = isset($event['type']) ? strtolower((string) $event['type']) : '';

            if (!$this->is_money_event($type)) {
                continue;
            }

            $items[] = array(
                'timestamp' => $timestamp,
                'time' => isset($event['time']) ? (string) $event['time'] : '',
                'type' => $type,
                'source' => isset($event['source']) ? (string) $event['source'] : 'unknown',
                'page_url' => isset($event['page_url']) ? (string) $event['page_url'] : '',
            );
        }

        usort($items, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        return array_slice($items, 0, 10);
    }

    private function is_money_event(string $type): bool {
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

    private function event_score(string $type): int {
        $type = strtolower($type);

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

    private function event_value_label(string $type): string {
        $score = $this->event_score($type);

        if ($score >= 8) {
            return 'Hoog';
        }

        if ($score >= 5) {
            return 'Middel';
        }

        return 'Laag';
    }

    private function short_page(string $url): string {
        if ($url === '') {
            return 'Onbekend';
        }

        $path = wp_parse_url($url, PHP_URL_PATH);
        $path = trim((string) $path, '/');

        return $path !== '' ? $path : 'homepage';
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
            'mail_share_click' => 'Delen via e-mail',
            'whatsapp_share_click' => 'Delen via WhatsApp',
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

        return ucwords(str_replace(array('_', '-'), ' ', $value));
    }
}
