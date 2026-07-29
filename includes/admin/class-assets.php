<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Assets {

    public static function init(): void {
        add_action(
            'admin_enqueue_scripts',
            array(__CLASS__, 'enqueue_admin_assets')
        );
    }

    public static function enqueue_admin_assets($hook): void {
        if (!is_string($hook)) {
            return;
        }

        if (
            strpos($hook, 'avd-cta-insights') === false &&
            strpos($hook, 'avd-ai-analyse') === false &&
            strpos($hook, 'avd-live-events') === false &&
            strpos($hook, 'avd-settings') === false
        ) {
            return;
        }

        wp_enqueue_style(
            'avd-cta-insights-admin',
            plugins_url('../../assets/css/admin.css', __FILE__),
            array(),
            AVDCTAI_Plugin::VERSION
        );

        if (
            strpos($hook, 'avd-cta-insights') !== false ||
            strpos($hook, 'avd-ai-analyse') !== false
        ) {
            wp_enqueue_script(
                'avd-cta-insights-dashboard',
                plugins_url('../../assets/js/dashboard.js', __FILE__),
                array(),
                AVDCTAI_Plugin::VERSION,
                true
            );

            wp_localize_script(
                'avd-cta-insights-dashboard',
                'AVDCTAIDashboard',
                array(
                    'nonce' => wp_create_nonce(
                        'avdctai_dashboard_stats'
                    ),
                )
            );
        }
    }
}
