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
        add_action(
            'wp_ajax_avdctai_dashboard_stats',
            array(__CLASS__, 'ajax_stats')
        );
    }

    public function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $stats = new AVDCTAI_Stats($this->plugin);
        $payload = $stats->get_payload();

        ?>
        <div class="wrap">
            <h1>AVD CTA Insights Dashboard</h1>

            <p>
                Overzicht van je websiteprestaties, CTA-kliks
                en snelle verbeterkansen.
            </p>

            <?php
            $widget_manager = new AVDCTAI_Widget_Manager();
            $widget_manager->render($payload);
            ?>

            <div class="avd-section">
                <h2>🔥 Vandaag verbeteren</h2>

                <p>
                    Begin met pagina's die veel bezoekers hebben,
                    maar weinig CTA-kliks.
                </p>

                <?php if (!empty($payload['needs_attention'])) : ?>
                    <ol>
                        <?php
                        foreach (
                            array_slice(
                                $payload['needs_attention'],
                                0,
                                5
                            ) as $page
                        ) :
                            ?>
                            <li>
                                <strong>
                                    <?php
                                    echo esc_html(
                                        $page['page'] ?? ''
                                    );
                                    ?>
                                </strong>

                                —
                                <?php
                                echo esc_html(
                                    (string) ($page['views'] ?? 0)
                                );
                                ?>
                                views,

                                <?php
                                echo esc_html(
                                    (string) ($page['cta'] ?? 0)
                                );
                                ?>
                                CTA's,

                                <?php
                                echo esc_html(
                                    (string) ($page['conversion'] ?? 0)
                                );
                                ?>
                                % conversie
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else : ?>
                    <p>Nog geen verbeterpunten gevonden.</p>
                <?php endif; ?>
            </div>

            <div class="avd-section avd-actions">
                <h2>⚡ Snelle acties</h2>

                <p>
                    <a
                        class="button button-primary"
                        href="<?php echo esc_url(
                            admin_url(
                                'admin.php?page=avd-ai-analyse'
                            )
                        ); ?>"
                    >
                        Open AI Analyse
                    </a>

                    <a
                        class="button"
                        href="<?php echo esc_url(
                            admin_url(
                                'edit.php?post_type=avdctai_scan_lead'
                            )
                        ); ?>"
                    >
                        Bekijk leads
                    </a>

                    <a
                        class="button"
                        href="<?php echo esc_url(
                            admin_url(
                                'admin.php?page=avd-settings'
                            )
                        ); ?>"
                    >
                        Instellingen
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
                    'message' => 'Geen toegang.',
                )
            );
        }

        check_ajax_referer(
            'avdctai_dashboard_stats',
            'nonce'
        );

        $stats = new AVDCTAI_Stats(
            AVDCTAI_Plugin::instance()
        );

        $payload = $stats->get_payload();

        wp_send_json_success($payload);
    }
}
