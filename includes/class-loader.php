<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Loader {

    public static function load(): void {
        $base_path = plugin_dir_path(dirname(__FILE__));

        /*
         * Installer
         */
        require_once $base_path . 'includes/class-installer.php';

        /*
         * Services
         */
        require_once $base_path . 'includes/services/class-stats.php';
        require_once $base_path . 'includes/services/class-priority.php';
        require_once $base_path . 'includes/services/class-ai-coach.php';
        require_once $base_path . 'includes/services/class-ai-score.php';
        require_once $base_path . 'includes/services/class-visitor-intelligence.php';
        require_once $base_path . 'includes/services/class-page-benchmarks.php';

        /*
         * Admin services
         */
        require_once $base_path . 'includes/admin/services/class-action-generator.php';
        require_once $base_path . 'includes/admin/services/class-template-manager.php';

        /*
         * Admin core
         */
        require_once $base_path . 'includes/admin/class-admin.php';
        require_once $base_path . 'includes/admin/class-assets.php';
        require_once $base_path . 'includes/admin/class-leads.php';
        require_once $base_path . 'includes/admin/class-ai.php';
        require_once $base_path . 'includes/admin/class-analytics.php';
        require_once $base_path . 'includes/admin/class-settings.php';
        require_once $base_path . 'includes/admin/class-live-events.php';
        require_once $base_path . 'includes/admin/class-action-center.php';
        require_once $base_path . 'includes/admin/class-templates.php';

        /*
         * Dashboard widgets
         */
        require_once $base_path . 'includes/admin/widgets/class-widget-base.php';
        require_once $base_path . 'includes/admin/widgets/class-widget-today.php';
        require_once $base_path . 'includes/admin/widgets/class-widget-health.php';
        require_once $base_path . 'includes/admin/widgets/class-widget-priority.php';
        require_once $base_path . 'includes/admin/widgets/class-widget-benchmarks.php';
        require_once $base_path . 'includes/admin/widgets/class-widget-ai-coach.php';
        require_once $base_path . 'includes/admin/widgets/class-widget-visitor-intelligence.php';
        require_once $base_path . 'includes/admin/widgets/class-widget-cta-breakdown.php';
        require_once $base_path . 'includes/admin/widgets/class-widget-money-events.php';
        require_once $base_path . 'includes/admin/widgets/class-widget-business-intent.php';
        require_once $base_path . 'includes/admin/widgets/class-widget-top-pages.php';
        require_once $base_path . 'includes/admin/widgets/class-widget-trends.php';
        require_once $base_path . 'includes/admin/widgets/class-widget-manager.php';

        /*
         * Dashboard
         */
        require_once $base_path . 'includes/admin/class-dashboard.php';

        /*
         * Init
         */
        AVDCTAI_Installer::init();
        AVDCTAI_Admin::init();
        AVDCTAI_Leads::init();
        AVDCTAI_AI::init();
        AVDCTAI_Analytics::init();
        AVDCTAI_Settings::init();
        AVDCTAI_Live_Events::init();
        AVDCTAI_Assets::init();
        AVDCTAI_Dashboard::init();
    }
}
