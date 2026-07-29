<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Live_Events {

    public static function init(): void {
        add_action(
            'wp_ajax_avdctai_live_events',
            array(__CLASS__, 'ajax_live_events')
        );
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        self::enqueue_live_events_script();

        ?>
        <div class="wrap">
            <h1>📡 Live Events</h1>

            <p>
                Bekijk de laatste tracking-events.
                De lijst ververst automatisch iedere 2 seconden.
            </p>

            <div class="avd-section">
                <p>
                    <button
                        type="button"
                        class="button button-primary"
                        id="avd-refresh-live-events"
                    >
                        Nu verversen
                    </button>

                    <span id="avd-live-events-status">
                        Nog niet geladen.
                    </span>
                </p>

                <div id="avd-live-events-table">
                    Events worden geladen...
                </div>
            </div>
        </div>
        <?php
    }

    private static function enqueue_live_events_script(): void {
        $settings = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action'  => 'avdctai_live_events',
            'nonce'   => wp_create_nonce('avdctai_live_events'),
        );

        wp_localize_script(
            'avd-cta-insights-dashboard',
            'AVDCTAILiveEvents',
            $settings
        );

        $script = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    var table = document.getElementById('avd-live-events-table');
    var status = document.getElementById('avd-live-events-status');
    var button = document.getElementById('avd-refresh-live-events');

    if (!table || !status || !window.AVDCTAILiveEvents) {
        return;
    }

    function loadEvents() {
        status.textContent = ' Laden...';

        var body = new URLSearchParams({
            action: AVDCTAILiveEvents.action,
            nonce: AVDCTAILiveEvents.nonce
        });

        fetch(AVDCTAILiveEvents.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: body.toString()
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP-fout: ' + response.status);
            }

            return response.text();
        })
        .then(function (html) {
            table.innerHTML = html;
            status.textContent =
                ' Laatst bijgewerkt: ' +
                new Date().toLocaleTimeString();
        })
        .catch(function () {
            status.textContent = ' Fout bij laden.';
        });
    }

    if (button) {
        button.addEventListener('click', loadEvents);
    }

    loadEvents();
    window.setInterval(loadEvents, 2000);
});
JS;

        wp_add_inline_script(
            'avd-cta-insights-dashboard',
            $script,
            'after'
        );
    }

    public static function ajax_live_events(): void {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('Geen toegang.', 'avd-cta-insights')
            );
        }

        check_ajax_referer(
            'avdctai_live_events',
            'nonce'
        );

        $events = get_option(
            AVDCTAI_Plugin::OPTION_RECENT_EVENTS,
            array()
        );

        if (!is_array($events)) {
            $events = array();
        }

        $events = array_reverse(
            array_slice($events, -50)
        );

        if (empty($events)) {
            echo '<p>';
            echo esc_html__(
                'Nog geen events gevonden.',
                'avd-cta-insights'
            );
            echo '</p>';

            wp_die();
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Tijd', 'avd-cta-insights') . '</th>';
        echo '<th>' . esc_html__('Type', 'avd-cta-insights') . '</th>';
        echo '<th>' . esc_html__('Bron', 'avd-cta-insights') . '</th>';
        echo '<th>' . esc_html__('Context', 'avd-cta-insights') . '</th>';
        echo '<th>' . esc_html__('Device', 'avd-cta-insights') . '</th>';
        echo '<th>' . esc_html__('Label', 'avd-cta-insights') . '</th>';
        echo '<th>' . esc_html__('Pagina', 'avd-cta-insights') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $time = isset($event['time'])
                ? sanitize_text_field((string) $event['time'])
                : '';

            $type = isset($event['type'])
                ? sanitize_text_field((string) $event['type'])
                : '';

            $source = isset($event['source'])
                ? sanitize_text_field((string) $event['source'])
                : '';

            $context = isset($event['context'])
                ? sanitize_text_field((string) $event['context'])
                : '';

            $device = isset($event['device'])
                ? sanitize_text_field((string) $event['device'])
                : '';

            $label = isset($event['label'])
                ? sanitize_text_field((string) $event['label'])
                : '';

            $page_url = isset($event['page_url'])
                ? esc_url_raw((string) $event['page_url'])
                : '';

            echo '<tr>';
            echo '<td>' . esc_html($time) . '</td>';
            echo '<td><strong>' . esc_html($type) . '</strong></td>';
            echo '<td>' . esc_html($source) . '</td>';
            echo '<td>' . esc_html($context) . '</td>';
            echo '<td>' . esc_html($device) . '</td>';
            echo '<td>' . esc_html($label) . '</td>';
            echo '<td>';

            if ($page_url !== '') {
                echo '<a href="' . esc_url($page_url) . '"';
                echo ' target="_blank"';
                echo ' rel="noopener noreferrer">';
                echo esc_html(self::short_url($page_url));
                echo '</a>';
            } else {
                echo '&mdash;';
            }

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        wp_die();
    }

    private static function short_url(string $url): string {
        if ($url === '') {
            return '';
        }

        $path = wp_parse_url($url, PHP_URL_PATH);

        if (!$path || $path === '/') {
            return 'homepage';
        }

        return trim((string) $path, '/');
    }
}
