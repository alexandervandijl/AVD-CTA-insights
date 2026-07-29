<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Widget_Trends extends AVDCTAI_Widget {

    public function render(array $payload): void {
        $trends = $this->calculate_trends();

        ?>
        <div class="avd-section avd-trends-widget">
            <h2>📈 Trends</h2>

            <p>
                Vergelijk vandaag met gisteren en deze week met de vorige periode.
            </p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin:18px 0;">
                <?php
                $this->render_metric_card(
                    'Views vandaag',
                    $trends['today']['views'],
                    $this->change_label(
                        $trends['today']['views'],
                        $trends['yesterday']['views']
                    )
                );

                $this->render_metric_card(
                    "CTA's vandaag",
                    $trends['today']['cta'],
                    $this->change_label(
                        $trends['today']['cta'],
                        $trends['yesterday']['cta']
                    )
                );

                $this->render_metric_card(
                    'Conversie vandaag',
                    $trends['today']['conversion'] . '%',
                    $this->change_label(
                        $trends['today']['conversion'],
                        $trends['yesterday']['conversion'],
                        true
                    )
                );

                $this->render_metric_card(
                    'Views 7 dagen',
                    $trends['week']['views'],
                    $this->change_label(
                        $trends['week']['views'],
                        $trends['previous_week']['views']
                    )
                );

                $this->render_metric_card(
                    "CTA's 7 dagen",
                    $trends['week']['cta'],
                    $this->change_label(
                        $trends['week']['cta'],
                        $trends['previous_week']['cta']
                    )
                );

                $this->render_metric_card(
                    'Conversie 7 dagen',
                    $trends['week']['conversion'] . '%',
                    $this->change_label(
                        $trends['week']['conversion'],
                        $trends['previous_week']['conversion'],
                        true
                    )
                );
                ?>
            </div>

            <div style="border-top:1px solid #e5e7eb;margin-top:18px;padding-top:16px;">
                <h3>🧭 Trendinterpretatie</h3>

                <?php if (!empty($trends['advice'])) : ?>

                    <ul>
                        <?php foreach ($trends['advice'] as $line) : ?>
                            <li>
                                <?php echo esc_html((string) $line); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                <?php else : ?>

                    <p>
                        Er is nog te weinig vergelijkingsdata om een duidelijke
                        trend te bepalen.
                    </p>

                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function calculate_trends(): array {
        $events = $this->get_recent_events();
        $periods = $this->periods();

        $today = $this->count_period(
            $events,
            $periods['today_start'],
            $periods['tomorrow_start']
        );

        $yesterday = $this->count_period(
            $events,
            $periods['yesterday_start'],
            $periods['today_start']
        );

        $week = $this->count_period(
            $events,
            $periods['week_start'],
            $periods['tomorrow_start']
        );

        $previous_week = $this->count_period(
            $events,
            $periods['previous_week_start'],
            $periods['week_start']
        );

        return array(
            'today' => $today,
            'yesterday' => $yesterday,
            'week' => $week,
            'previous_week' => $previous_week,
            'advice' => $this->build_advice(
                $today,
                $yesterday,
                $week,
                $previous_week
            ),
        );
    }

    private function get_recent_events(): array {
        $option_name = 'avd_uber_cta_events_recent';

        if (
            class_exists('AVDCTAI_Plugin') &&
            defined('AVDCTAI_Plugin::OPTION_RECENT_EVENTS')
        ) {
            $option_name = constant(
                'AVDCTAI_Plugin::OPTION_RECENT_EVENTS'
            );
        }

        $events = get_option(
            $option_name,
            array()
        );

        return is_array($events)
            ? $events
            : array();
    }

    private function periods(): array {
        $timezone = wp_timezone();

        $today = new DateTimeImmutable(
            'today',
            $timezone
        );

        $tomorrow = $today->modify('+1 day');
        $yesterday = $today->modify('-1 day');
        $week_start = $today->modify('-6 days');
        $previous_week_start = $week_start->modify('-7 days');

        return array(
            'tomorrow_start' => $tomorrow->getTimestamp(),
            'today_start' => $today->getTimestamp(),
            'yesterday_start' => $yesterday->getTimestamp(),
            'week_start' => $week_start->getTimestamp(),
            'previous_week_start' => $previous_week_start->getTimestamp(),
        );
    }

    private function count_period(
        array $events,
        int $start,
        int $end
    ): array {
        $views = 0;
        $cta = 0;

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $timestamp = $this->event_timestamp($event);

            if (
                $timestamp <= 0 ||
                $timestamp < $start ||
                $timestamp >= $end
            ) {
                continue;
            }

            $type = $this->event_type($event);

            if ($type === 'page_view') {
                $views++;
            }

            if ($this->is_real_cta_event($type)) {
                $cta++;
            }
        }

        $conversion = $views > 0
            ? round(($cta / $views) * 100, 2)
            : 0;

        return array(
            'views' => $views,
            'cta' => $cta,
            'conversion' => $conversion,
        );
    }

    private function event_timestamp(array $event): int {
        $keys = array(
            'timestamp',
            'time',
            'created_at',
            'event_time',
            'date',
        );

        foreach ($keys as $key) {
            if (
                !isset($event[$key]) ||
                $event[$key] === ''
            ) {
                continue;
            }

            $value = $event[$key];

            if (is_numeric($value)) {
                $timestamp = (int) $value;

                if ($timestamp > 9999999999) {
                    $timestamp = (int) floor(
                        $timestamp / 1000
                    );
                }

                return $timestamp;
            }

            $parsed = strtotime((string) $value);

            if ($parsed !== false) {
                return (int) $parsed;
            }
        }

        return 0;
    }

    private function event_type(array $event): string {
        $keys = array(
            'type',
            'event_type',
            'event',
            'action',
        );

        foreach ($keys as $key) {
            if (
                isset($event[$key]) &&
                $event[$key] !== ''
            ) {
                return strtolower(
                    sanitize_text_field(
                        (string) $event[$key]
                    )
                );
            }
        }

        return '';
    }

    private function is_real_cta_event(string $type): bool {
        if (class_exists('AVDCTAI_Visitor_Intelligence')) {
            return AVDCTAI_Visitor_Intelligence::is_real_cta_event(
                $type
            );
        }

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
            strpos($type, 'form_submit') !== false ||
            strpos($type, 'bedrijfsscan') !== false ||
            strpos($type, 'claim') !== false ||
            strpos($type, 'aanvraag') !== false
        );
    }

    private function render_metric_card(
        string $label,
        $value,
        string $change
    ): void {
        echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:14px;box-shadow:0 1px 2px rgba(0,0,0,.04);">';
        echo '<div style="font-size:13px;color:#64748b;font-weight:700;">';
        echo esc_html($label);
        echo '</div>';

        echo '<div style="font-size:26px;font-weight:900;margin:6px 0;">';
        echo esc_html((string) $value);
        echo '</div>';

        echo '<div style="font-size:13px;font-weight:800;color:#475569;">';
        echo esc_html($change);
        echo '</div>';
        echo '</div>';
    }

    private function change_label(
        $current,
        $previous,
        bool $percentage_points = false
    ): string {
        $current = (float) $current;
        $previous = (float) $previous;

        if ($previous === 0.0 && $current === 0.0) {
            return 'geen verandering';
        }

        if ($previous === 0.0 && $current > 0) {
            return 'nieuw verkeer';
        }

        if ($percentage_points) {
            $diff = round(
                $current - $previous,
                2
            );

            if ($diff > 0) {
                return '+' . $diff . ' procentpunt';
            }

            if ($diff < 0) {
                return $diff . ' procentpunt';
            }

            return 'geen verandering';
        }

        $change = round(
            (($current - $previous) / max(1, $previous)) * 100,
            1
        );

        if ($change > 0) {
            return '+' . $change . '%';
        }

        if ($change < 0) {
            return $change . '%';
        }

        return 'geen verandering';
    }

    private function build_advice(
        array $today,
        array $yesterday,
        array $week,
        array $previous_week
    ): array {
        $lines = array();

        if (
            $today['views'] > $yesterday['views'] &&
            $today['cta'] <= $yesterday['cta']
        ) {
            $lines[] = 'Vandaag is er meer verkeer, maar niet meer actie. Zet de belangrijkste CTA hoger op de pagina.';
        }

        if (
            $today['views'] >= 20 &&
            $today['cta'] === 0
        ) {
            $lines[] = 'Vandaag zijn er bezoekers zonder CTA-kliks. Dit is een directe verbeterkans.';
        }

        if (
            $today['conversion'] > $yesterday['conversion'] &&
            $today['cta'] > 0
        ) {
            $lines[] = 'De conversie beweegt vandaag positief. Kijk welke pagina of CTA dit veroorzaakt en herhaal dat patroon.';
        }

        if (
            $today['conversion'] < $yesterday['conversion'] &&
            $today['views'] >= 10
        ) {
            $lines[] = 'De conversie ligt vandaag lager dan gisteren. Controleer of de CTA goed zichtbaar is op mobiel.';
        }

        if (
            $week['views'] > $previous_week['views'] &&
            $week['cta'] <= $previous_week['cta']
        ) {
            $lines[] = 'Deze week groeit het verkeer, maar de CTA-kliks groeien niet mee. De funnel verdient aandacht.';
        }

        if (
            $week['cta'] > $previous_week['cta'] &&
            $week['conversion'] >= $previous_week['conversion']
        ) {
            $lines[] = 'De weektrend is positief: meer CTA-kliks zonder conversieverlies.';
        }

        if (
            $week['views'] >= 100 &&
            $week['conversion'] < 2
        ) {
            $lines[] = 'Er is genoeg verkeer om te optimaliseren. Test een directere CTA-tekst en herhaal de CTA na de eerste alinea.';
        }

        if (
            empty($lines) &&
            (
                $today['views'] > 0 ||
                $week['views'] > 0
            )
        ) {
            $lines[] = 'Er is activiteit zichtbaar. Verzamel nog iets meer data om een hardere trend te bepalen.';
        }

        return array_values(
            array_unique($lines)
        );
    }
}
