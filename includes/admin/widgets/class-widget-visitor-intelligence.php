<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Widget_Visitor_Intelligence extends AVDCTAI_Widget {

    public function render(array $payload): void {
        $visitor = isset($payload['visitor_intelligence']) &&
            is_array($payload['visitor_intelligence'])
                ? $payload['visitor_intelligence']
                : array();

        if (
            empty($visitor) &&
            class_exists('AVDCTAI_Visitor_Intelligence')
        ) {
            $visitor = AVDCTAI_Visitor_Intelligence::get_data(7);
        }

        $summary = isset($visitor['summary']) &&
            is_array($visitor['summary'])
                ? $visitor['summary']
                : array();

        $events = (int) ($summary['events'] ?? 0);
        $sessions = (int) ($summary['unique_sessions'] ?? 0);
        $cta_clicks = (int) ($summary['cta_clicks'] ?? 0);
        $cta_rate = (float) ($summary['cta_rate'] ?? 0);

        $devices = isset($visitor['devices']) &&
            is_array($visitor['devices'])
                ? $visitor['devices']
                : array();

        $languages = isset($visitor['languages']) &&
            is_array($visitor['languages'])
                ? $visitor['languages']
                : array();

        $timezones = isset($visitor['timezones']) &&
            is_array($visitor['timezones'])
                ? $visitor['timezones']
                : array();

        $referrers = isset($visitor['referrers']) &&
            is_array($visitor['referrers'])
                ? $visitor['referrers']
                : array();

        $screens = isset($visitor['screens']) &&
            is_array($visitor['screens'])
                ? $visitor['screens']
                : array();

        ?>
        <div class="avd-section avd-visitor-intelligence-widget">

            <h2>🧠 Visitor Intelligence</h2>

            <p>
                Bezoekerscontext uit tracking-events:
                taal, device, scherm, timezone en herkomst.
            </p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin:18px 0;">
                <?php $this->render_metric('Events 7 dagen', $events); ?>
                <?php $this->render_metric('Unieke sessies', $sessions); ?>
                <?php $this->render_metric('Echte CTA-kliks', $cta_clicks); ?>
                <?php $this->render_metric('CTA-ratio', $cta_rate . '%'); ?>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;margin-top:20px;">
                <?php
                $this->render_list(
                    'Devices',
                    $devices,
                    'Nog geen devicedata.'
                );

                $this->render_list(
                    'Talen',
                    $languages,
                    'Nog geen taaldata.'
                );

                $this->render_list(
                    'Timezones',
                    $timezones,
                    'Nog geen timezonedata.'
                );

                $this->render_list(
                    'Referrers',
                    $referrers,
                    'Nog geen referrerdata.'
                );

                $this->render_list(
                    'Schermen',
                    $screens,
                    'Nog geen schermdata.'
                );
                ?>
            </div>

            <?php $this->render_advice($visitor); ?>

        </div>
        <?php
    }

    private function render_metric(string $label, $value): void {
        echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:14px;box-shadow:0 1px 2px rgba(0,0,0,.04);">';
        echo '<div style="font-size:13px;color:#64748b;font-weight:700;">';
        echo esc_html($label);
        echo '</div>';
        echo '<div style="font-size:26px;font-weight:900;margin-top:6px;">';
        echo esc_html((string) $value);
        echo '</div>';
        echo '</div>';
    }

    private function render_list(
        string $title,
        array $items,
        string $empty_message
    ): void {
        echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:14px;box-shadow:0 1px 2px rgba(0,0,0,.04);">';
        echo '<h3 style="margin-top:0;">';
        echo esc_html($title);
        echo '</h3>';

        if (empty($items)) {
            echo '<p style="color:#64748b;">';
            echo esc_html($empty_message);
            echo '</p>';
            echo '</div>';

            return;
        }

        echo '<ul style="margin:0;padding:0;list-style:none;">';

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $value = isset($item['value'])
                ? (string) $item['value']
                : '';

            $total = isset($item['total'])
                ? (int) $item['total']
                : 0;

            if ($value === '') {
                continue;
            }

            echo '<li style="display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #f1f5f9;padding:6px 0;">';
            echo '<span>';
            echo esc_html($value);
            echo '</span>';
            echo '<strong>';
            echo esc_html((string) $total);
            echo '</strong>';
            echo '</li>';
        }

        echo '</ul>';
        echo '</div>';
    }

    private function render_advice(array $visitor): void {
        $advice = $this->build_advice($visitor);

        echo '<div style="border-top:1px solid #e5e7eb;margin-top:20px;padding-top:16px;">';
        echo '<h3>AI Coach interpretatie</h3>';

        if (empty($advice)) {
            echo '<p>';
            echo 'Er is nog te weinig bezoekersdata voor een duidelijke interpretatie.';
            echo '</p>';
            echo '</div>';

            return;
        }

        echo '<ul>';

        foreach ($advice as $line) {
            echo '<li>';
            echo esc_html($line);
            echo '</li>';
        }

        echo '</ul>';
        echo '</div>';
    }

    private function build_advice(array $visitor): array {
        $summary = isset($visitor['summary']) &&
            is_array($visitor['summary'])
                ? $visitor['summary']
                : array();

        $devices = isset($visitor['devices']) &&
            is_array($visitor['devices'])
                ? $visitor['devices']
                : array();

        $languages = isset($visitor['languages']) &&
            is_array($visitor['languages'])
                ? $visitor['languages']
                : array();

        $referrers = isset($visitor['referrers']) &&
            is_array($visitor['referrers'])
                ? $visitor['referrers']
                : array();

        $screens = isset($visitor['screens']) &&
            is_array($visitor['screens'])
                ? $visitor['screens']
                : array();

        $views = (int) ($summary['views'] ?? 0);
        $cta_clicks = (int) ($summary['cta_clicks'] ?? 0);
        $cta_rate = (float) ($summary['cta_rate'] ?? 0);

        $advice = array();

        if ($views >= 20 && $cta_clicks === 0) {
            $advice[] = 'Er zijn bezoekers zonder CTA-kliks. Maak de belangrijkste CTA hoger, groter en directer.';
        }

        if ($views >= 20 && $cta_clicks > 0 && $cta_rate < 5) {
            $advice[] = 'CTA-ratio kan omhoog. Test een duidelijkere belofte, zoals “Bel direct gratis door” of “Vraag gratis hulp aan”.';
        }

        if ($this->has_mobile_signal($devices, $screens)) {
            $advice[] = 'Mobiel verkeer verdient prioriteit. Houd de eerste CTA kort, groot en direct bereikbaar zonder scrollen.';
        }

        if ($this->has_foreign_language($languages)) {
            $advice[] = 'Meertalig bezoek zichtbaar. Overweeg een korte Engelstalige CTA of automatische taalvariant.';
        }

        if (!empty($referrers)) {
            $advice[] = 'Referrer-verkeer benutten. Maak voor sterke bronnen aparte landingspagina’s of CTA-teksten.';
        }

        return array_values(array_unique($advice));
    }

    private function has_mobile_signal(
        array $devices,
        array $screens
    ): bool {
        foreach ($devices as $device) {
            if (!is_array($device)) {
                continue;
            }

            $value = strtolower(
                (string) ($device['value'] ?? '')
            );

            if (
                strpos($value, 'mobiel') !== false ||
                strpos($value, 'mobile') !== false ||
                strpos($value, 'phone') !== false
            ) {
                return true;
            }
        }

        foreach ($screens as $screen) {
            if (!is_array($screen)) {
                continue;
            }

            $value = (string) ($screen['value'] ?? '');

            if (
                preg_match(
                    '/^(\d{2,4})x(\d{2,4})$/',
                    $value,
                    $matches
                )
            ) {
                $width = (int) $matches[1];

                if ($width > 0 && $width < 768) {
                    return true;
                }
            }
        }

        return false;
    }

    private function has_foreign_language(array $languages): bool {
        foreach ($languages as $language) {
            if (!is_array($language)) {
                continue;
            }

            $value = strtolower(
                (string) ($language['value'] ?? '')
            );

            if ($value !== '' && strpos($value, 'nl') !== 0) {
                return true;
            }
        }

        return false;
    }
}
