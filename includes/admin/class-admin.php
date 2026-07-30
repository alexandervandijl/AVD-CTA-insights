<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Admin {

    private AVDCTAI_Plugin $plugin;

    public function __construct(AVDCTAI_Plugin $plugin) {
        $this->plugin = $plugin;

        /*
         * Initialiseer de instellingen vroeg genoeg zodat de Settings API
         * haar admin_init-hooks kan registreren voordat de pagina wordt getoond.
         */
        if (class_exists('AVDCTAI_Settings')) {
            AVDCTAI_Settings::init();
        }
    }

    public static function init(): void {
        /*
         * De hoofdplugin maakt een instantie van deze class en registreert
         * register_menu() op admin_menu. Aanvullende globale admin-hooks
         * kunnen hier later veilig worden toegevoegd.
         */
    }

    public function register_menu(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        /*
         * Gebruik exact hetzelfde dashboardobject voor het hoofdmenu
         * en het Dashboard-submenu. Zo wordt dezelfde rendercallback
         * niet dubbel opgebouwd.
         */
        $dashboard = class_exists('AVDCTAI_Dashboard')
            ? new AVDCTAI_Dashboard($this->plugin)
            : null;

        $dashboard_callback = $dashboard
            ? array($dashboard, 'render')
            : array($this, 'render_missing_dashboard');

        add_menu_page(
            __('AVD CTA Insights', 'avd-cta-insights'),
            __('AVD CTA Insights', 'avd-cta-insights'),
            'manage_options',
            'avd-cta-insights',
            $dashboard_callback,
            'dashicons-chart-area',
            58
        );

        add_submenu_page(
            'avd-cta-insights',
            __('Dashboard', 'avd-cta-insights'),
            __('Dashboard', 'avd-cta-insights'),
            'manage_options',
            'avd-cta-insights',
            $dashboard_callback
        );

        add_submenu_page(
            'avd-cta-insights',
            __('AI Analyse', 'avd-cta-insights'),
            __('AI Analyse', 'avd-cta-insights'),
            'manage_options',
            'avd-ai-analyse',
            array($this, 'render_ai_analysis')
        );

        add_submenu_page(
            'avd-cta-insights',
            __('AI Actiecentrum', 'avd-cta-insights'),
            __('AI Actiecentrum', 'avd-cta-insights'),
            'manage_options',
            'avd-cta-insights-action-center',
            array($this, 'render_action_center')
        );

        add_submenu_page(
            'avd-cta-insights',
            __('CTA Templates', 'avd-cta-insights'),
            __('CTA Templates', 'avd-cta-insights'),
            'manage_options',
            'avd-cta-insights-templates',
            array($this, 'render_templates')
        );

        add_submenu_page(
            'avd-cta-insights',
            __('Live Events', 'avd-cta-insights'),
            __('Live Events', 'avd-cta-insights'),
            'manage_options',
            'avd-live-events',
            array($this, 'render_live_events')
        );

        add_submenu_page(
            'avd-cta-insights',
            __('Instellingen', 'avd-cta-insights'),
            __('Instellingen', 'avd-cta-insights'),
            'manage_options',
            'avd-settings',
            array($this, 'render_settings')
        );
    }

    public function render_ai_analysis(): void {
        $this->render_static_module('AVDCTAI_AI', 'render', __('AI Analyse', 'avd-cta-insights'));
    }

    public function render_action_center(): void {
        $this->render_static_module(
            'AVDCTAI_Action_Center',
            'render',
            __('AI Actiecentrum', 'avd-cta-insights')
        );
    }

    public function render_templates(): void {
        $this->render_static_module(
            'AVDCTAI_Templates',
            'render',
            __('CTA Templates', 'avd-cta-insights')
        );
    }

    public function render_live_events(): void {
        $this->render_static_module(
            'AVDCTAI_Live_Events',
            'render',
            __('Live Events', 'avd-cta-insights')
        );
    }

    public function render_settings(): void {
        $this->render_static_module(
            'AVDCTAI_Settings',
            'render',
            __('Instellingen', 'avd-cta-insights')
        );
    }

    public function render_missing_dashboard(): void {
        $this->render_missing_module(__('Dashboard', 'avd-cta-insights'));
    }

    private function render_static_module(string $class_name, string $method, string $label): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (class_exists($class_name) && is_callable(array($class_name, $method))) {
            call_user_func(array($class_name, $method));
            return;
        }

        $this->render_missing_module($label);
    }

    private function render_missing_module(string $label): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html($label); ?></h1>

            <div class="notice notice-error">
                <p>
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %s is the name of a plugin module. */
                            __('De module “%s” kon niet worden geladen. Controleer of alle pluginbestanden aanwezig zijn.', 'avd-cta-insights'),
                            $label
                        )
                    );
                    ?>
                </p>
            </div>
        </div>
        <?php
    }
}
