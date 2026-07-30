<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Assets {

    /**
     * Alle beheerpagina's van de plugin waarop de algemene adminstylesheet
     * nodig is.
     *
     * @var string[]
     */
    private const ADMIN_PAGES = array(
        'avd-cta-insights',
        'avd-ai-analyse',
        'avd-cta-insights-action-center',
        'avd-cta-insights-templates',
        'avd-live-events',
        'avd-settings',
    );

    /**
     * Pagina's waarop dashboard.js daadwerkelijk wordt gebruikt.
     *
     * @var string[]
     */
    private const DASHBOARD_SCRIPT_PAGES = array(
        'avd-cta-insights',
        'avd-ai-analyse',
    );

    public static function init(): void {
        add_action(
            'admin_enqueue_scripts',
            array(__CLASS__, 'enqueue_admin_assets')
        );
    }

    public static function enqueue_admin_assets($hook_suffix): void {
        if (!is_string($hook_suffix)) {
            return;
        }

        $page = self::current_plugin_page();

        if ($page === '' || !in_array($page, self::ADMIN_PAGES, true)) {
            return;
        }

        wp_enqueue_style(
            'avd-cta-insights-admin',
            plugins_url('../../assets/css/admin.css', __FILE__),
            array(),
            AVDCTAI_Plugin::VERSION
        );

        if (!in_array($page, self::DASHBOARD_SCRIPT_PAGES, true)) {
            return;
        }

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
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('avdctai_dashboard_stats'),
                'page'    => $page,
                'strings' => array(
                    'loading' => __('Gegevens laden…', 'avd-cta-insights'),
                    'error'   => __('De gegevens konden niet worden geladen.', 'avd-cta-insights'),
                ),
            )
        );
    }

    private static function current_plugin_page(): string {
        $requested_page = filter_input(
            INPUT_GET,
            'page',
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );

        if (!is_string($requested_page)) {
            return '';
        }

        return sanitize_key(wp_unslash($requested_page));
    }
}
