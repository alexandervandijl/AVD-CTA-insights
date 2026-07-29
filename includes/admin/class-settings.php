<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Settings {

    public static function init(): void {
        // Settings hooks kunnen hier later worden geregistreerd.
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $requested_tab = filter_input(
            INPUT_GET,
            'tab',
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );

        $tab = is_string($requested_tab)
            ? sanitize_key($requested_tab)
            : 'general';

        $tabs = array(
            'general'  => 'Algemeen',
            'tracking' => 'Tracking',
            'ai'       => 'AI',
            'cta'      => 'CTA',
            'advanced' => 'Geavanceerd',
        );

        if (!isset($tabs[$tab])) {
            $tab = 'general';
        }

        ?>
        <div class="wrap">
            <h1>⚙️ AVD CTA Insights</h1>

            <p>
                <strong>AI Conversion Platform</strong>
                — versie <?php echo esc_html(AVDCTAI_Plugin::VERSION); ?>
            </p>

            <div class="avd-section">
                <h2>🚀 Snel starten</h2>

                <p>
                    <a
                        class="button button-primary"
                        href="<?php echo esc_url(admin_url('admin.php?page=avd-cta-insights')); ?>"
                    >
                        Dashboard openen
                    </a>

                    <a
                        class="button"
                        href="<?php echo esc_url(admin_url('admin.php?page=avd-ai-analyse')); ?>"
                    >
                        AI Analyse
                    </a>

                    <a
                        class="button"
                        href="<?php echo esc_url(admin_url('admin.php?page=avd-live-events')); ?>"
                    >
                        Live Events
                    </a>

                    <a
                        class="button"
                        href="<?php echo esc_url('https://alexandervandijl.nl/avd-cta-insights/'); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Pluginwebsite
                    </a>
                </p>
            </div>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($tabs as $key => $label) : ?>
                    <?php
                    $tab_url = add_query_arg(
                        array(
                            'page' => 'avd-settings',
                            'tab'  => $key,
                        ),
                        admin_url('admin.php')
                    );
                    ?>

                    <a
                        class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>"
                        href="<?php echo esc_url($tab_url); ?>"
                    >
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <div class="avd-section">
                <?php self::render_tab($tab); ?>
            </div>
        </div>
        <?php
    }

    private static function render_tab(string $tab): void {
        switch ($tab) {
            case 'tracking':
                self::tab_tracking();
                break;

            case 'ai':
                self::tab_ai();
                break;

            case 'cta':
                self::tab_cta();
                break;

            case 'advanced':
                self::tab_advanced();
                break;

            case 'general':
            default:
                self::tab_general();
                break;
        }
    }

    private static function tab_general(): void {
        ?>
        <h2>Algemeen</h2>

        <table class="widefat striped">
            <tbody>
                <tr>
                    <td>Pluginstatus</td>
                    <td>🟢 Actief</td>
                </tr>

                <tr>
                    <td>Pluginversie</td>
                    <td><?php echo esc_html(AVDCTAI_Plugin::VERSION); ?></td>
                </tr>

                <tr>
                    <td>Dashboard</td>
                    <td>✅ Beschikbaar</td>
                </tr>

                <tr>
                    <td>AI Analyse</td>
                    <td>✅ Beschikbaar</td>
                </tr>

                <tr>
                    <td>AI Actiecentrum</td>
                    <td>✅ Beschikbaar</td>
                </tr>

                <tr>
                    <td>CTA Templates</td>
                    <td>✅ Beschikbaar</td>
                </tr>

                <tr>
                    <td>Live Events</td>
                    <td>✅ Beschikbaar</td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    private static function tab_tracking(): void {
        ?>
        <h2>Tracking</h2>

        <table class="widefat striped">
            <tbody>
                <tr>
                    <td>Paginaweergaven</td>
                    <td>✅ Actief</td>
                </tr>

                <tr>
                    <td>CTA-kliks</td>
                    <td>✅ Actief</td>
                </tr>

                <tr>
                    <td>Scrolltracking</td>
                    <td>✅ Actief</td>
                </tr>

                <tr>
                    <td>Betrokken sessies</td>
                    <td>✅ Actief</td>
                </tr>

                <tr>
                    <td>Popup-events</td>
                    <td>✅ Actief</td>
                </tr>

                <tr>
                    <td>Sticky-bar-events</td>
                    <td>✅ Actief</td>
                </tr>

                <tr>
                    <td>Bot- en eventfiltering</td>
                    <td>✅ Actief</td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    private static function tab_ai(): void {
        ?>
        <h2>AI</h2>

        <table class="widefat striped">
            <tbody>
                <tr>
                    <td>AI Coach</td>
                    <td>✅ Actief</td>
                </tr>

                <tr>
                    <td>AI Prioriteiten</td>
                    <td>✅ Actief</td>
                </tr>

                <tr>
                    <td>AI Analyse-export</td>
                    <td>✅ Actief</td>
                </tr>

                <tr>
                    <td>AI Actiecentrum</td>
                    <td>✅ Actief</td>
                </tr>

                <tr>
                    <td>CTA Preview</td>
                    <td>✅ Actief</td>
                </tr>

                <tr>
                    <td>Automatisch toepassen</td>
                    <td>⏳ Gepland</td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    private static function tab_cta(): void {
        ?>
        <h2>CTA</h2>

        <table class="widefat striped">
            <tbody>
                <tr>
                    <td>CTA Templates</td>
                    <td>✅ Beschikbaar</td>
                </tr>

                <tr>
                    <td>CTA boven content</td>
                    <td>✅ Beschikbaar</td>
                </tr>

                <tr>
                    <td>Sticky CTA</td>
                    <td>✅ Beschikbaar</td>
                </tr>

                <tr>
                    <td>WhatsApp-knoppen</td>
                    <td>✅ Beschikbaar</td>
                </tr>

                <tr>
                    <td>Belknoppen</td>
                    <td>✅ Beschikbaar</td>
                </tr>

                <tr>
                    <td>Popup CTA</td>
                    <td>✅ Beschikbaar</td>
                </tr>
            </tbody>
        </table>

        <p style="margin-top:15px;">
            <a
                class="button button-primary"
                href="<?php echo esc_url(admin_url('admin.php?page=avd-cta-insights-templates')); ?>"
            >
                CTA Templates beheren
            </a>
        </p>
        <?php
    }

    private static function tab_advanced(): void {
        ?>
        <h2>Geavanceerd</h2>

        <table class="widefat striped">
            <tbody>
                <tr>
                    <td>Visitor Intelligence</td>
                    <td>✅ Actief</td>
                </tr>

                <tr>
                    <td>Verdachte-eventfiltering</td>
                    <td>✅ Actief</td>
                </tr>

                <tr>
                    <td>Export statistieken</td>
                    <td>⏳ Gepland</td>
                </tr>

                <tr>
                    <td>Statistieken resetten</td>
                    <td>⏳ Gepland</td>
                </tr>

                <tr>
                    <td>Debuglogging</td>
                    <td>⏳ Gepland</td>
                </tr>

                <tr>
                    <td>Aangepaste integraties</td>
                    <td>⏳ Gepland</td>
                </tr>
            </tbody>
        </table>
        <?php
    }
}
