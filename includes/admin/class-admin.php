<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Admin {

    private AVDCTAI_Plugin $plugin;

    public function __construct(AVDCTAI_Plugin $plugin) {
        $this->plugin = $plugin;
    }

    public static function init(): void {
        // Admin hooks worden via de hoofdplugin geregistreerd.
    }

    public function register_menu(): void {
        /*
         * Gebruik exact hetzelfde dashboardobject voor het hoofdmenu
         * en het Dashboard-submenu.
         *
         * Zo voorkomt WordPress dat dezelfde rendercallback twee keer
         * wordt uitgevoerd op de pagina avd-cta-insights.
         */
        $dashboard = new AVDCTAI_Dashboard($this->plugin);
        $dashboard_callback = array($dashboard, 'render');

        add_menu_page(
            'AVD CTA Insights',
            'AVD CTA Insights',
            'manage_options',
            'avd-cta-insights',
            $dashboard_callback,
            'dashicons-chart-area',
            58
        );

        add_submenu_page(
            'avd-cta-insights',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'avd-cta-insights',
            $dashboard_callback
        );

        add_submenu_page(
            'avd-cta-insights',
            'AI Analyse',
            'AI Analyse',
            'manage_options',
            'avd-ai-analyse',
            array('AVDCTAI_AI', 'render')
        );

        add_submenu_page(
            'avd-cta-insights',
            'AI Actiecentrum',
            'AI Actiecentrum',
            'manage_options',
            'avd-cta-insights-action-center',
            array('AVDCTAI_Action_Center', 'render')
        );

        add_submenu_page(
            'avd-cta-insights',
            'CTA Templates',
            'CTA Templates',
            'manage_options',
            'avd-cta-insights-templates',
            array('AVDCTAI_Templates', 'render')
        );

        add_submenu_page(
            'avd-cta-insights',
            'Live Events',
            'Live Events',
            'manage_options',
            'avd-live-events',
            array('AVDCTAI_Live_Events', 'render')
        );

        add_submenu_page(
            'avd-cta-insights',
            'Instellingen',
            'Instellingen',
            'manage_options',
            'avd-settings',
            array('AVDCTAI_Settings', 'render')
        );
    }
}
