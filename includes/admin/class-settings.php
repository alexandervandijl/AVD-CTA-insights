<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Settings {

    private const OPTION_NAME = 'avdctai_settings';
    private const SETTINGS_GROUP = 'avdctai_settings_group';

    public static function init(): void {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }

    public static function register_settings(): void {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_NAME,
            array(
                'type'              => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
                'default'           => self::defaults(),
            )
        );
    }

    public static function sanitize_settings($input): array {
        $defaults = self::defaults();
        $input = is_array($input) ? $input : array();

        $output = array(
            'tracking_enabled'         => self::checkbox($input, 'tracking_enabled'),
            'track_page_views'         => self::checkbox($input, 'track_page_views'),
            'content_cta_enabled'      => self::checkbox($input, 'content_cta_enabled'),
            'sticky_bar_enabled'       => self::checkbox($input, 'sticky_bar_enabled'),
            'popup_enabled'            => self::checkbox($input, 'popup_enabled'),
            'phone_display'            => isset($input['phone_display'])
                ? sanitize_text_field(wp_unslash((string) $input['phone_display']))
                : '',
            'phone_international'      => isset($input['phone_international'])
                ? sanitize_text_field(wp_unslash((string) $input['phone_international']))
                : '',
            'phone_tel'                => isset($input['phone_tel'])
                ? self::sanitize_phone_link(wp_unslash((string) $input['phone_tel']))
                : '',
            'whatsapp_number'          => isset($input['whatsapp_number'])
                ? preg_replace('/[^0-9]/', '', wp_unslash((string) $input['whatsapp_number']))
                : '',
            'cta_title'                => isset($input['cta_title'])
                ? sanitize_text_field(wp_unslash((string) $input['cta_title']))
                : '',
            'cta_text'                 => isset($input['cta_text'])
                ? sanitize_textarea_field(wp_unslash((string) $input['cta_text']))
                : '',
            'cta_button_label'         => isset($input['cta_button_label'])
                ? sanitize_text_field(wp_unslash((string) $input['cta_button_label']))
                : '',
            'cta_button_url'           => isset($input['cta_button_url'])
                ? esc_url_raw(wp_unslash((string) $input['cta_button_url']))
                : '',
            'whatsapp_default_message' => isset($input['whatsapp_default_message'])
                ? sanitize_textarea_field(wp_unslash((string) $input['whatsapp_default_message']))
                : '',
        );

        return wp_parse_args($output, $defaults);
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $requested_tab = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $tab = is_string($requested_tab) ? sanitize_key($requested_tab) : 'general';

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

        $settings = self::get_settings();
        ?>
        <div class="wrap">
            <h1>⚙️ AVD CTA Insights</h1>

            <p>
                <strong>CTA-analyse en conversie-inzichten</strong>
                — versie <?php echo esc_html(AVDCTAI_Plugin::VERSION); ?>
            </p>

            <?php settings_errors(self::OPTION_NAME); ?>

            <div class="avd-section">
                <h2>🚀 Snel starten</h2>
                <p>
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=avd-cta-insights')); ?>">Dashboard openen</a>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=avd-ai-analyse')); ?>">AI Analyse</a>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=avd-live-events')); ?>">Live Events</a>
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
                    <a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($tab_url); ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <div class="avd-section">
                <?php self::render_tab($tab, $settings); ?>
            </div>
        </div>
        <?php
    }

    private static function render_tab(string $tab, array $settings): void {
        switch ($tab) {
            case 'tracking':
                self::tab_tracking($settings);
                break;
            case 'ai':
                self::tab_ai();
                break;
            case 'cta':
                self::tab_cta($settings);
                break;
            case 'advanced':
                self::tab_advanced();
                break;
            case 'general':
            default:
                self::tab_general($settings);
                break;
        }
    }

    private static function tab_general(array $settings): void {
        ?>
        <h2>Algemeen</h2>
        <p>De plugin is standaard alleen als meet- en analyseplugin actief. Visuele CTA's worden pas zichtbaar nadat je ze zelf inschakelt.</p>

        <table class="widefat striped">
            <tbody>
                <tr><td>Pluginstatus</td><td>🟢 Actief</td></tr>
                <tr><td>Pluginversie</td><td><?php echo esc_html(AVDCTAI_Plugin::VERSION); ?></td></tr>
                <tr><td>Tracking</td><td><?php echo !empty($settings['tracking_enabled']) ? '✅ Ingeschakeld' : '⏸️ Uitgeschakeld'; ?></td></tr>
                <tr><td>CTA boven content</td><td><?php echo !empty($settings['content_cta_enabled']) ? '✅ Ingeschakeld' : '⏸️ Uitgeschakeld'; ?></td></tr>
                <tr><td>Sticky CTA</td><td><?php echo !empty($settings['sticky_bar_enabled']) ? '✅ Ingeschakeld' : '⏸️ Uitgeschakeld'; ?></td></tr>
                <tr><td>Popup CTA</td><td><?php echo !empty($settings['popup_enabled']) ? '✅ Ingeschakeld' : '⏸️ Uitgeschakeld'; ?></td></tr>
            </tbody>
        </table>
        <?php
    }

    private static function tab_tracking(array $settings): void {
        self::form_start('tracking');
        ?>
        <h2>Tracking</h2>
        <p>Meet bezoekersgedrag zonder dat er automatisch CTA's op de website worden geplaatst.</p>

        <table class="form-table" role="presentation">
            <tbody>
                <?php self::checkbox_row('tracking_enabled', 'Tracking inschakelen', 'Slaat frontend-events op voor dashboards, rapportages en analyses.', $settings); ?>
                <?php self::checkbox_row('track_page_views', 'Paginaweergaven meten', 'Registreert page_view-events. Dit werkt alleen wanneer tracking is ingeschakeld.', $settings); ?>
            </tbody>
        </table>
        <?php
        submit_button('Trackinginstellingen opslaan');
        self::form_end();
    }

    private static function tab_ai(): void {
        ?>
        <h2>AI</h2>
        <table class="widefat striped">
            <tbody>
                <tr><td>AI Coach</td><td>✅ Beschikbaar</td></tr>
                <tr><td>AI Prioriteiten</td><td>✅ Beschikbaar</td></tr>
                <tr><td>AI Analyse-export</td><td>✅ Beschikbaar</td></tr>
                <tr><td>AI Actiecentrum</td><td>✅ Beschikbaar</td></tr>
                <tr><td>CTA Preview</td><td>✅ Beschikbaar</td></tr>
                <tr><td>Automatisch toepassen</td><td>⏳ Gepland</td></tr>
            </tbody>
        </table>
        <?php
    }

    private static function tab_cta(array $settings): void {
        self::form_start('cta');
        ?>
        <h2>CTA-instellingen</h2>
        <p>Alle visuele CTA-onderdelen staan op nieuwe installaties uit. Vul eerst je eigen contactgegevens in en schakel daarna alleen de gewenste onderdelen in.</p>

        <h3>Weergave</h3>
        <table class="form-table" role="presentation">
            <tbody>
                <?php self::checkbox_row('content_cta_enabled', 'CTA boven de content', 'Voegt een CTA-blok toe boven de hoofdcontent van geschikte pagina’s.', $settings); ?>
                <?php self::checkbox_row('sticky_bar_enabled', 'Sticky CTA-balk', 'Toont onderaan het scherm een vaste CTA-balk.', $settings); ?>
                <?php self::checkbox_row('popup_enabled', 'Popup CTA', 'Staat toe dat de frontendtracking een beperkte popup toont.', $settings); ?>
            </tbody>
        </table>

        <h3>Contactgegevens</h3>
        <table class="form-table" role="presentation">
            <tbody>
                <?php self::text_row('phone_display', 'Telefoonnummer voor weergave', 'Bijvoorbeeld: 020 123 45 67', $settings); ?>
                <?php self::text_row('phone_international', 'Internationale weergave', 'Bijvoorbeeld: +31 20 123 45 67', $settings); ?>
                <?php self::text_row('phone_tel', 'Klikbare telefoonlink', 'Gebruik het formaat tel:+31201234567', $settings, 'url'); ?>
                <?php self::text_row('whatsapp_number', 'WhatsApp-nummer', 'Alleen cijfers, inclusief landcode. Bijvoorbeeld: 31612345678', $settings, 'text', 'numeric'); ?>
                <?php self::textarea_row('whatsapp_default_message', 'Standaard WhatsApp-bericht', 'Dit bericht wordt gebruikt wanneer een CTA geen eigen tekst heeft.', $settings); ?>
            </tbody>
        </table>

        <h3>Algemene CTA</h3>
        <p class="description">Deze velden zijn voorbereid voor generieke CTA-weergave. Lege velden veroorzaken geen knoppen of verwijzingen naar een andere website.</p>
        <table class="form-table" role="presentation">
            <tbody>
                <?php self::text_row('cta_title', 'Titel', 'Bijvoorbeeld: Kunnen we je helpen?', $settings); ?>
                <?php self::textarea_row('cta_text', 'Tekst', 'Een korte toelichting bij de CTA.', $settings); ?>
                <?php self::text_row('cta_button_label', 'Knoptekst', 'Bijvoorbeeld: Neem contact op', $settings); ?>
                <?php self::text_row('cta_button_url', 'Knoplink', 'Gebruik een volledige URL, mailto:-link of tel:-link.', $settings, 'url'); ?>
            </tbody>
        </table>

        <?php submit_button('CTA-instellingen opslaan'); ?>
        <?php self::form_end(); ?>

        <p>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=avd-cta-insights-templates')); ?>">CTA Templates beheren</a>
        </p>
        <?php
    }

    private static function tab_advanced(): void {
        ?>
        <h2>Geavanceerd</h2>
        <table class="widefat striped">
            <tbody>
                <tr><td>Visitor Intelligence</td><td>✅ Beschikbaar</td></tr>
                <tr><td>Verdachte-eventfiltering</td><td>✅ Beschikbaar</td></tr>
                <tr><td>Export statistieken</td><td>⏳ Gepland</td></tr>
                <tr><td>Statistieken resetten</td><td>⏳ Gepland</td></tr>
                <tr><td>Debuglogging</td><td>⏳ Gepland</td></tr>
                <tr><td>Aangepaste integraties</td><td>⏳ Gepland</td></tr>
            </tbody>
        </table>
        <?php
    }

    private static function form_start(string $tab): void {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields(self::SETTINGS_GROUP); ?>
            <input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr(admin_url('admin.php?page=avd-settings&tab=' . sanitize_key($tab))); ?>">
        <?php
    }

    private static function form_end(): void {
        ?></form><?php
    }

    private static function checkbox_row(string $key, string $label, string $description, array $settings): void {
        ?>
        <tr>
            <th scope="row"><?php echo esc_html($label); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($settings[$key])); ?>>
                    Ingeschakeld
                </label>
                <p class="description"><?php echo esc_html($description); ?></p>
            </td>
        </tr>
        <?php
    }

    private static function text_row(string $key, string $label, string $description, array $settings, string $type = 'text', string $inputmode = ''): void {
        $value = isset($settings[$key]) ? (string) $settings[$key] : '';
        ?>
        <tr>
            <th scope="row"><label for="avdctai-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <input
                    class="regular-text"
                    id="avdctai-<?php echo esc_attr($key); ?>"
                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>]"
                    type="<?php echo esc_attr($type); ?>"
                    value="<?php echo esc_attr($value); ?>"
                    <?php echo $inputmode ? 'inputmode="' . esc_attr($inputmode) . '"' : ''; ?>
                >
                <p class="description"><?php echo esc_html($description); ?></p>
            </td>
        </tr>
        <?php
    }

    private static function textarea_row(string $key, string $label, string $description, array $settings): void {
        $value = isset($settings[$key]) ? (string) $settings[$key] : '';
        ?>
        <tr>
            <th scope="row"><label for="avdctai-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <textarea class="large-text" rows="4" id="avdctai-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>]"><?php echo esc_textarea($value); ?></textarea>
                <p class="description"><?php echo esc_html($description); ?></p>
            </td>
        </tr>
        <?php
    }

    private static function get_settings(): array {
        $settings = get_option(self::OPTION_NAME, array());
        if (!is_array($settings)) {
            $settings = array();
        }

        return wp_parse_args($settings, self::defaults());
    }

    private static function defaults(): array {
        return array(
            'tracking_enabled'         => 1,
            'track_page_views'         => 1,
            'content_cta_enabled'      => 0,
            'sticky_bar_enabled'       => 0,
            'popup_enabled'            => 0,
            'phone_display'            => '',
            'phone_international'      => '',
            'phone_tel'                => '',
            'whatsapp_number'          => '',
            'cta_title'                => '',
            'cta_text'                 => '',
            'cta_button_label'         => '',
            'cta_button_url'           => '',
            'whatsapp_default_message' => '',
        );
    }

    private static function checkbox(array $input, string $key): int {
        return isset($input[$key]) && (string) $input[$key] === '1' ? 1 : 0;
    }

    private static function sanitize_phone_link(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (stripos($value, 'tel:') !== 0) {
            return '';
        }

        $number = preg_replace('/[^0-9+]/', '', substr($value, 4));
        if ($number === '' || $number === '+') {
            return '';
        }

        return 'tel:' . $number;
    }
}
