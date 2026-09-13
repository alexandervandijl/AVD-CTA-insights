<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configurable bridge from an existing site to another project.
 *
 * The feature is deliberately opt-in: it never publishes a project card until
 * an administrator enables it and it never changes content on the destination.
 */
final class AVDCTAI_Project_Bridge {

    public const OPTION_NAME = 'avdctai_project_bridge_settings';
    public const MENU_SLUG = 'avdctai-project-bridge';

    private static bool $rendered_on_homepage = false;

    public static function init(): void {
        add_action('admin_menu', array(__CLASS__, 'register_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
        add_filter('the_content', array(__CLASS__, 'inject_homepage_bridge'), 18);
        add_shortcode('avdctai_project_bridge', array(__CLASS__, 'shortcode'));
    }

    public static function defaults(): array {
        return array(
            'enabled'             => 0,
            'show_on_homepage'    => 0,
            'eyebrow'             => __('Uitgelicht project', 'avd-cta-insights'),
            'project_name'        => '',
            'headline'            => '',
            'description'         => '',
            'primary_url'         => '',
            'primary_label'       => __('Bekijk project', 'avd-cta-insights'),
            'audience_a_url'      => '',
            'audience_a_label'    => '',
            'audience_b_url'      => '',
            'audience_b_label'    => '',
            'overview_url'        => '',
            'overview_label'      => '',
            'placement'           => 'project_bridge',
        );
    }

    public static function settings(): array {
        $saved = get_option(self::OPTION_NAME, array());
        if (!is_array($saved)) {
            $saved = array();
        }

        return wp_parse_args($saved, self::defaults());
    }

    public static function register_menu(): void {
        add_submenu_page(
            'avd-cta-insights',
            __('Project Bridge', 'avd-cta-insights'),
            __('Project Bridge', 'avd-cta-insights'),
            'manage_options',
            self::MENU_SLUG,
            array(__CLASS__, 'render_admin_page')
        );
    }

    public static function register_settings(): void {
        register_setting(
            'avdctai_project_bridge',
            self::OPTION_NAME,
            array(
                'type'              => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
                'default'           => self::defaults(),
            )
        );
    }

    public static function sanitize_settings($input): array {
        $input = is_array($input) ? $input : array();
        $defaults = self::defaults();

        $clean = array(
            'enabled'          => !empty($input['enabled']) ? 1 : 0,
            'show_on_homepage' => !empty($input['show_on_homepage']) ? 1 : 0,
            'eyebrow'          => sanitize_text_field((string) ($input['eyebrow'] ?? $defaults['eyebrow'])),
            'project_name'     => sanitize_text_field((string) ($input['project_name'] ?? '')),
            'headline'         => sanitize_text_field((string) ($input['headline'] ?? '')),
            'description'      => sanitize_textarea_field((string) ($input['description'] ?? '')),
            'primary_url'      => esc_url_raw((string) ($input['primary_url'] ?? '')),
            'primary_label'    => sanitize_text_field((string) ($input['primary_label'] ?? $defaults['primary_label'])),
            'audience_a_url'   => esc_url_raw((string) ($input['audience_a_url'] ?? '')),
            'audience_a_label' => sanitize_text_field((string) ($input['audience_a_label'] ?? '')),
            'audience_b_url'   => esc_url_raw((string) ($input['audience_b_url'] ?? '')),
            'audience_b_label' => sanitize_text_field((string) ($input['audience_b_label'] ?? '')),
            'overview_url'     => esc_url_raw((string) ($input['overview_url'] ?? '')),
            'overview_label'   => sanitize_text_field((string) ($input['overview_label'] ?? '')),
            'placement'        => sanitize_key((string) ($input['placement'] ?? $defaults['placement'])),
        );

        if ($clean['placement'] === '') {
            $clean['placement'] = 'project_bridge';
        }

        return $clean;
    }

    public static function enqueue_frontend_assets(): void {
        $settings = self::settings();
        if (empty($settings['enabled'])) {
            return;
        }

        wp_enqueue_script(
            'avd-cta-insights-project-bridge',
            plugins_url('assets/js/project-bridge.js', dirname(dirname(dirname(__FILE__))) . '/avd-cta-insights.php'),
            array(),
            AVDCTAI_Plugin::VERSION,
            true
        );

        self::enqueue_shared_styles();
    }

    public static function enqueue_admin_assets(string $hook_suffix): void {
        if (strpos($hook_suffix, self::MENU_SLUG) === false) {
            return;
        }

        self::enqueue_shared_styles();
    }

    private static function enqueue_shared_styles(): void {
        $handle = 'avd-cta-insights-project-bridge-style';
        wp_register_style($handle, false, array(), AVDCTAI_Plugin::VERSION);
        wp_enqueue_style($handle);
        wp_add_inline_style($handle, self::css());
    }

    private static function css(): string {
        return '.avdctai-project-bridge{border:1px solid #dcdcde;border-radius:14px;padding:24px;margin:24px 0;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.06)}'
            . '.avdctai-project-bridge__eyebrow{margin:0 0 8px;font-size:13px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#646970}'
            . '.avdctai-project-bridge h2{margin:0 0 10px;font-size:clamp(24px,3vw,34px);line-height:1.15}'
            . '.avdctai-project-bridge__description{margin:0 0 18px;font-size:17px;line-height:1.6}'
            . '.avdctai-project-bridge__buttons{display:flex;flex-wrap:wrap;gap:10px}'
            . '.avdctai-project-bridge__button{display:inline-block;padding:11px 16px;border-radius:8px;text-decoration:none!important;font-weight:700;background:#1d2327;color:#fff!important;border:1px solid #1d2327}'
            . '.avdctai-project-bridge__button--secondary{background:#fff;color:#1d2327!important}'
            . '.avdctai-project-bridge__meta{margin:12px 0 0;color:#646970;font-size:13px}'
            . '.avdctai-project-bridge-admin{max-width:1000px}'
            . '.avdctai-project-bridge-admin .form-table th{width:220px}'
            . '.avdctai-project-bridge-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:18px 0}'
            . '.avdctai-project-bridge-stat{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:16px}'
            . '.avdctai-project-bridge-stat strong{display:block;font-size:26px;line-height:1.2}'
            . '.avdctai-project-bridge-preview{max-width:760px}';
    }

    public static function inject_homepage_bridge(string $content): string {
        if (self::$rendered_on_homepage || is_admin() || !is_front_page() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $settings = self::settings();
        if (empty($settings['enabled']) || empty($settings['show_on_homepage'])) {
            return $content;
        }

        self::$rendered_on_homepage = true;
        $bridge = self::render_bridge($settings);

        return $bridge !== '' ? $content . $bridge : $content;
    }

    public static function shortcode($atts = array()): string {
        $settings = self::settings();
        if (empty($settings['enabled'])) {
            return '';
        }

        $atts = shortcode_atts(
            array(
                'placement' => $settings['placement'],
            ),
            is_array($atts) ? $atts : array(),
            'avdctai_project_bridge'
        );

        $settings['placement'] = sanitize_key((string) $atts['placement']) ?: 'project_bridge';
        return self::render_bridge($settings);
    }

    private static function render_bridge(array $settings): string {
        $has_destination = self::valid_button($settings['primary_url'], $settings['primary_label'])
            || self::valid_button($settings['audience_a_url'], $settings['audience_a_label'])
            || self::valid_button($settings['audience_b_url'], $settings['audience_b_label'])
            || self::valid_button($settings['overview_url'], $settings['overview_label']);

        if (!$has_destination || (string) $settings['headline'] === '') {
            return '';
        }

        $placement = sanitize_key((string) $settings['placement']) ?: 'project_bridge';
        $project_name = (string) $settings['project_name'];

        ob_start();
        ?>
        <section
            class="avdctai-project-bridge"
            data-avdctai-project-bridge="1"
            data-avdctai-project-name="<?php echo esc_attr($project_name); ?>"
            data-avdctai-placement="<?php echo esc_attr($placement); ?>"
        >
            <?php if ((string) $settings['eyebrow'] !== '') : ?>
                <p class="avdctai-project-bridge__eyebrow"><?php echo esc_html((string) $settings['eyebrow']); ?></p>
            <?php endif; ?>

            <h2><?php echo esc_html((string) $settings['headline']); ?></h2>

            <?php if ((string) $settings['description'] !== '') : ?>
                <p class="avdctai-project-bridge__description"><?php echo esc_html((string) $settings['description']); ?></p>
            <?php endif; ?>

            <div class="avdctai-project-bridge__buttons">
                <?php
                echo wp_kses_post(self::button(
                    (string) $settings['primary_url'],
                    (string) $settings['primary_label'],
                    'cta_project_bridge_primary',
                    $placement,
                    false
                ));
                echo wp_kses_post(self::button(
                    (string) $settings['audience_a_url'],
                    (string) $settings['audience_a_label'],
                    'cta_project_bridge_audience_a',
                    $placement,
                    true
                ));
                echo wp_kses_post(self::button(
                    (string) $settings['audience_b_url'],
                    (string) $settings['audience_b_label'],
                    'cta_project_bridge_audience_b',
                    $placement,
                    true
                ));
                echo wp_kses_post(self::button(
                    (string) $settings['overview_url'],
                    (string) $settings['overview_label'],
                    'cta_project_bridge_overview',
                    $placement,
                    true
                ));
                ?>
            </div>

            <?php if ($project_name !== '') : ?>
                <p class="avdctai-project-bridge__meta">
                    <?php
                    printf(
                        /* translators: %s is the project name configured by the site administrator. */
                        esc_html__('Project: %s', 'avd-cta-insights'),
                        esc_html($project_name)
                    );
                    ?>
                </p>
            <?php endif; ?>
        </section>
        <?php

        return trim((string) ob_get_clean());
    }

    private static function valid_button(string $url, string $label): bool {
        return trim($url) !== '' && trim($label) !== '';
    }

    private static function button(string $url, string $label, string $type, string $source, bool $secondary): string {
        if (!self::valid_button($url, $label)) {
            return '';
        }

        $class = 'avdctai-project-bridge__button';
        if ($secondary) {
            $class .= ' avdctai-project-bridge__button--secondary';
        }

        return sprintf(
            '<a class="%1$s" href="%2$s" data-avd-cta="1" data-avd-cta-type="%3$s" data-avd-cta-source="%4$s" data-avd-cta-label="%5$s">%6$s</a>',
            esc_attr($class),
            esc_url($url),
            esc_attr($type),
            esc_attr($source),
            esc_attr($label),
            esc_html($label)
        );
    }

    public static function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = self::settings();
        $stats_7 = self::stats(7);
        $stats_30 = self::stats(30);
        ?>
        <div class="wrap avdctai-project-bridge-admin">
            <h1><?php esc_html_e('Project Bridge', 'avd-cta-insights'); ?></h1>
            <p><?php esc_html_e('Stuur bestaand verkeer meetbaar door naar een ander project. Publicatie is altijd opt-in en de bestemmingssite blijft zelf verantwoordelijk voor de uiteindelijke conversie.', 'avd-cta-insights'); ?></p>

            <div class="avdctai-project-bridge-stats">
                <?php self::stat_card(__('Gezien · 7d', 'avd-cta-insights'), $stats_7['seen_sessions']); ?>
                <?php self::stat_card(__('Kliksessies · 7d', 'avd-cta-insights'), $stats_7['click_sessions']); ?>
                <?php self::stat_card(__('CTR · 7d', 'avd-cta-insights'), $stats_7['ctr'] . '%'); ?>
                <?php self::stat_card(__('Kliksessies · 30d', 'avd-cta-insights'), $stats_30['click_sessions']); ?>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('avdctai_project_bridge'); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Publicatie', 'avd-cta-insights'); ?></th>
                            <td>
                                <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[enabled]" value="1" <?php checked(!empty($settings['enabled'])); ?>> <?php esc_html_e('Project Bridge inschakelen', 'avd-cta-insights'); ?></label><br>
                                <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[show_on_homepage]" value="1" <?php checked(!empty($settings['show_on_homepage'])); ?>> <?php esc_html_e('Kaart automatisch onderaan de homepage tonen', 'avd-cta-insights'); ?></label>
                                <p class="description"><?php esc_html_e('Zonder de tweede optie kun je de kaart alleen via de shortcode plaatsen.', 'avd-cta-insights'); ?></p>
                            </td>
                        </tr>
                        <?php
                        self::text_row('eyebrow', __('Bovenregel', 'avd-cta-insights'), $settings['eyebrow']);
                        self::text_row('project_name', __('Projectnaam', 'avd-cta-insights'), $settings['project_name']);
                        self::text_row('headline', __('Kop', 'avd-cta-insights'), $settings['headline']);
                        self::textarea_row('description', __('Beschrijving', 'avd-cta-insights'), $settings['description']);
                        self::url_label_rows('primary', __('Primaire CTA', 'avd-cta-insights'), $settings);
                        self::url_label_rows('audience_a', __('Doelgroep A', 'avd-cta-insights'), $settings);
                        self::url_label_rows('audience_b', __('Doelgroep B', 'avd-cta-insights'), $settings);
                        self::url_label_rows('overview', __('Overzicht', 'avd-cta-insights'), $settings);
                        self::text_row('placement', __('Meetbron', 'avd-cta-insights'), $settings['placement'], 'project_bridge');
                        ?>
                    </tbody>
                </table>
                <?php submit_button(__('Project Bridge opslaan', 'avd-cta-insights')); ?>
            </form>

            <h2><?php esc_html_e('Plaatsing en meting', 'avd-cta-insights'); ?></h2>
            <p><code>[avdctai_project_bridge]</code></p>
            <p><?php esc_html_e('Gebruik UTM-parameters of een via-parameter in de bestemmingslinks als de andere site de bron moet blijven herkennen. Project Bridge meet lokaal alleen vertoningen en uitgaande kliks; daadwerkelijke inschrijvingen, aanvragen of andere uitkomsten horen op de bestemmingssite gemeten te worden.', 'avd-cta-insights'); ?></p>

            <?php if (!empty($settings['enabled'])) : ?>
                <h2><?php esc_html_e('Voorbeeld', 'avd-cta-insights'); ?></h2>
                <div class="avdctai-project-bridge-preview">
                    <?php echo wp_kses_post(self::render_bridge($settings)); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function text_row(string $key, string $label, $value, string $placeholder = ''): void {
        ?>
        <tr>
            <th scope="row"><label for="avdctai-project-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td><input class="regular-text" id="avdctai-project-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>]" type="text" value="<?php echo esc_attr((string) $value); ?>" placeholder="<?php echo esc_attr($placeholder); ?>"></td>
        </tr>
        <?php
    }

    private static function textarea_row(string $key, string $label, $value): void {
        ?>
        <tr>
            <th scope="row"><label for="avdctai-project-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td><textarea class="large-text" rows="4" id="avdctai-project-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>]"><?php echo esc_textarea((string) $value); ?></textarea></td>
        </tr>
        <?php
    }

    private static function url_label_rows(string $prefix, string $label, array $settings): void {
        $url_key = $prefix . '_url';
        $label_key = $prefix . '_label';
        ?>
        <tr>
            <th scope="row"><?php echo esc_html($label); ?></th>
            <td>
                <p><input class="large-text" name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($url_key); ?>]" type="url" value="<?php echo esc_attr((string) ($settings[$url_key] ?? '')); ?>" placeholder="https://"></p>
                <p><input class="regular-text" name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($label_key); ?>]" type="text" value="<?php echo esc_attr((string) ($settings[$label_key] ?? '')); ?>" placeholder="<?php esc_attr_e('Knoptekst', 'avd-cta-insights'); ?>"></p>
            </td>
        </tr>
        <?php
    }

    private static function stat_card(string $label, $value): void {
        ?>
        <div class="avdctai-project-bridge-stat">
            <strong><?php echo esc_html((string) $value); ?></strong>
            <span><?php echo esc_html($label); ?></span>
        </div>
        <?php
    }

    private static function stats(int $days): array {
        $events = class_exists('AVDCTAI_Event_Archive')
            ? AVDCTAI_Event_Archive::get_combined_events()
            : get_option(AVDCTAI_Plugin::OPTION_RECENT_EVENTS, array());

        if (!is_array($events)) {
            $events = array();
        }

        $cutoff = time() - (max(1, $days) * DAY_IN_SECONDS);
        $seen = array();
        $clicked = array();
        $clicks = array(
            'primary'    => 0,
            'audience_a' => 0,
            'audience_b' => 0,
            'overview'   => 0,
        );

        foreach ($events as $event) {
            if (!is_array($event) || (int) ($event['timestamp'] ?? 0) < $cutoff) {
                continue;
            }

            $type = sanitize_key((string) ($event['type'] ?? ''));
            $session = sanitize_text_field((string) ($event['session_id'] ?? ($event['sessionId'] ?? '')));
            if ($session === '') {
                $session = 'event:' . md5(wp_json_encode($event));
            }

            if ($type === 'project_bridge_seen') {
                $seen[$session] = true;
                continue;
            }

            if (strpos($type, 'cta_project_bridge_') === 0) {
                $clicked[$session] = true;
                $suffix = substr($type, strlen('cta_project_bridge_'));
                if (isset($clicks[$suffix])) {
                    $clicks[$suffix]++;
                }
            }
        }

        $seen_count = count($seen);
        $click_count = count($clicked);

        return array(
            'seen_sessions'  => $seen_count,
            'click_sessions' => $click_count,
            'ctr'            => $seen_count > 0 ? round(($click_count / $seen_count) * 100, 1) : 0,
            'clicks'         => $clicks,
        );
    }
}
