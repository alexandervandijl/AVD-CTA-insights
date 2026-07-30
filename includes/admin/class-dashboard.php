<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Dashboard {

    private AVDCTAI_Plugin $plugin;

    public function __construct(AVDCTAI_Plugin $plugin) {
        $this->plugin = $plugin;
    }

    public static function init(): void {
        /*
         * Nieuwe, generieke AJAX-actienaam.
         */
        add_action(
            'wp_ajax_avdctai_dashboard_stats',
            array(__CLASS__, 'ajax_stats')
        );

        /*
         * Tijdelijke compatibiliteit met oudere dashboard.js-versies.
         * Deze alias kan in een latere release worden verwijderd zodra
         * alle installaties de nieuwe JavaScript-actienaam gebruiken.
         */
        add_action(
            'wp_ajax_avd_uber_cta_dashboard_stats',
            array(__CLASS__, 'ajax_stats')
        );
    }

    public function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $stats = new AVDCTAI_Stats($this->plugin);
        $payload = $stats->get_payload();

        if (!is_array($payload)) {
            $payload = array();
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('AVD CTA Insights Dashboard', 'avd-cta-insights'); ?></h1>

            <p>
                <?php
                echo esc_html__(
                    'Overzicht van je websiteprestaties, CTA-kliks en snelle verbeterkansen.',
                    'avd-cta-insights'
                );
                ?>
            </p>

            <?php
            if (class_exists('AVDCTAI_Widget_Manager')) {
                $widget_manager = new AVDCTAI_Widget_Manager();
                $widget_manager->render($payload);
            } else {
                self::render_module_notice(
                    __('De dashboardwidgets konden niet worden geladen.', 'avd-cta-insights')
                );
            }
            ?>

            <div class="avd-section">
                <h2><?php echo esc_html__('Vandaag verbeteren', 'avd-cta-insights'); ?></h2>

                <p>
                    <?php
                    echo esc_html__(
                        'Begin met pagina’s die veel bezoekers hebben, maar weinig CTA-kliks.',
                        'avd-cta-insights'
                    );
                    ?>
                </p>

                <?php
                $needs_attention = isset($payload['needs_attention']) && is_array($payload['needs_attention'])
                    ? $payload['needs_attention']
                    : array();
                ?>

                <?php if (!empty($needs_attention)) : ?>
                    <ol>
                        <?php foreach (array_slice($needs_attention, 0, 5) as $page) : ?>
                            <?php
                            if (!is_array($page)) {
                                continue;
                            }

                            $page_name  = isset($page['page']) ? (string) $page['page'] : '';
                            $views      = isset($page['views']) ? (int) $page['views'] : 0;
                            $cta        = isset($page['cta']) ? (int) $page['cta'] : 0;
                            $conversion = isset($page['conversion'])
                                ? (float) $page['conversion']
                                : 0.0;
                            ?>
                            <li>
                                <strong><?php echo esc_html($page_name); ?></strong>
                                —
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: 1: page views, 2: CTA clicks, 3: conversion percentage. */
                                        __('%1$d weergaven, %2$d CTA-kliks, %3$s%% conversie', 'avd-cta-insights'),
                                        $views,
                                        $cta,
                                        number_format_i18n($conversion, 2)
                                    )
                                );
                                ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else : ?>
                    <p>
                        <?php
                        echo esc_html__(
                            'Nog geen verbeterpunten gevonden.',
                            'avd-cta-insights'
                        );
                        ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="avd-section avd-actions">
                <h2><?php echo esc_html__('Snelle acties', 'avd-cta-insights'); ?></h2>

                <p>
                    <a
                        class="button button-primary"
                        href="<?php echo esc_url(admin_url('admin.php?page=avd-ai-analyse')); ?>"
                    >
                        <?php echo esc_html__('Open AI Analyse', 'avd-cta-insights'); ?>
                    </a>

                    <a
                        class="button"
                        href="<?php echo esc_url(admin_url('edit.php?post_type=avdctai_scan_lead')); ?>"
                    >
                        <?php echo esc_html__('Bekijk leads', 'avd-cta-insights'); ?>
                    </a>

                    <a
                        class="button"
                        href="<?php echo esc_url(admin_url('admin.php?page=avd-settings')); ?>"
                    >
                        <?php echo esc_html__('Instellingen', 'avd-cta-insights'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }

    public static function ajax_stats(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                array(
                    'message' => __('Geen toegang.', 'avd-cta-insights'),
                ),
                403
            );
        }

        check_ajax_referer(
            'avdctai_dashboard_stats',
            'nonce'
        );

        if (
            !class_exists('AVDCTAI_Plugin') ||
            !is_callable(array('AVDCTAI_Plugin', 'instance')) ||
            !class_exists('AVDCTAI_Stats')
        ) {
            wp_send_json_error(
                array(
                    'message' => __(
                        'De dashboardstatistieken konden niet worden geladen.',
                        'avd-cta-insights'
                    ),
                ),
                500
            );
        }

        $plugin = AVDCTAI_Plugin::instance();
        $stats  = new AVDCTAI_Stats($plugin);
        $payload = $stats->get_payload();

        if (!is_array($payload)) {
            $payload = array();
        }

        wp_send_json_success($payload);
    }

    private static function render_module_notice(string $message): void {
        ?>
        <div class="notice notice-error inline">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }
}
