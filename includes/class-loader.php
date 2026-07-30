<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Loader {

    private static bool $loaded = false;

    public static function load(): void {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        $base_path = plugin_dir_path(dirname(__FILE__));

        /*
         * Installer
         */
        self::require_file($base_path . 'includes/class-installer.php');

        /*
         * Services
         */
        self::require_file($base_path . 'includes/services/class-stats.php');
        self::require_file($base_path . 'includes/services/class-priority.php');
        self::require_file($base_path . 'includes/services/class-ai-coach.php');
        self::require_file($base_path . 'includes/services/class-ai-score.php');
        self::require_file($base_path . 'includes/services/class-visitor-intelligence.php');
        self::require_file($base_path . 'includes/services/class-page-benchmarks.php');

        /*
         * Admin services
         */
        self::require_file($base_path . 'includes/admin/services/class-action-generator.php');
        self::require_file($base_path . 'includes/admin/services/class-template-manager.php');

        /*
         * Admin core
         *
         * Settings wordt vóór Admin geladen, omdat AVDCTAI_Admin de
         * instellingen tijdens de constructie initialiseert.
         */
        self::require_file($base_path . 'includes/admin/class-settings.php');
        self::require_file($base_path . 'includes/admin/class-admin.php');
        self::require_file($base_path . 'includes/admin/class-assets.php');
        self::require_file($base_path . 'includes/admin/class-leads.php');
        self::require_file($base_path . 'includes/admin/class-ai.php');
        self::require_file($base_path . 'includes/admin/class-analytics.php');
        self::require_file($base_path . 'includes/admin/class-live-events.php');
        self::require_file($base_path . 'includes/admin/class-action-center.php');
        self::require_file($base_path . 'includes/admin/class-templates.php');

        /*
         * Dashboard widgets
         */
        self::require_file($base_path . 'includes/admin/widgets/class-widget-base.php');
        self::require_file($base_path . 'includes/admin/widgets/class-widget-today.php');
        self::require_file($base_path . 'includes/admin/widgets/class-widget-health.php');
        self::require_file($base_path . 'includes/admin/widgets/class-widget-priority.php');
        self::require_file($base_path . 'includes/admin/widgets/class-widget-benchmarks.php');
        self::require_file($base_path . 'includes/admin/widgets/class-widget-ai-coach.php');
        self::require_file($base_path . 'includes/admin/widgets/class-widget-visitor-intelligence.php');
        self::require_file($base_path . 'includes/admin/widgets/class-widget-cta-breakdown.php');
        self::require_file($base_path . 'includes/admin/widgets/class-widget-money-events.php');
        self::require_file($base_path . 'includes/admin/widgets/class-widget-business-intent.php');
        self::require_file($base_path . 'includes/admin/widgets/class-widget-top-pages.php');
        self::require_file($base_path . 'includes/admin/widgets/class-widget-trends.php');
        self::require_file($base_path . 'includes/admin/widgets/class-widget-manager.php');

        /*
         * Dashboard
         */
        self::require_file($base_path . 'includes/admin/class-dashboard.php');

        /*
         * Initialisatie
         *
         * AVDCTAI_Settings::init() wordt bewust niet opnieuw aangeroepen:
         * dat gebeurt al vanuit AVDCTAI_Admin::__construct().
         */
        self::init_class('AVDCTAI_Installer');
        self::init_class('AVDCTAI_Admin');
        self::init_class('AVDCTAI_Leads');
        self::init_class('AVDCTAI_AI');
        self::init_class('AVDCTAI_Analytics');
        self::init_class('AVDCTAI_Live_Events');
        self::init_class('AVDCTAI_Assets');
        self::init_class('AVDCTAI_Dashboard');
    }

    private static function require_file(string $file): void {
        if (!file_exists($file)) {
            self::fail(
                sprintf(
                    /* translators: %s is a plugin file path. */
                    __('Een vereist bestand van AVD CTA Insights ontbreekt: %s', 'avd-cta-insights'),
                    $file
                )
            );
        }

        require_once $file;
    }

    private static function init_class(string $class_name): void {
        if (!class_exists($class_name)) {
            self::fail(
                sprintf(
                    /* translators: %s is a PHP class name. */
                    __('Een vereiste pluginclass kon niet worden geladen: %s', 'avd-cta-insights'),
                    $class_name
                )
            );
        }

        if (is_callable(array($class_name, 'init'))) {
            call_user_func(array($class_name, 'init'));
        }
    }

    private static function fail(string $message): void {
        if (function_exists('wp_die')) {
            wp_die(
                esc_html($message),
                esc_html__('AVD CTA Insights kon niet worden gestart', 'avd-cta-insights'),
                array('response' => 500)
            );
        }

        throw new RuntimeException($message);
    }
}
