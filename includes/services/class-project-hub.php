<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Opt-in multi-project discovery hub.
 *
 * This remains generic for WordPress.org: no site-specific destinations are
 * shipped. Administrators explicitly configure and publish project cards.
 */
final class AVDCTAI_Project_Hub {

    public const OPTION_NAME = 'avdctai_project_hub_settings';
    public const MENU_SLUG = 'avdctai-project-hub';
    private const MAX_PROJECTS = 4;

    private static bool $rendered_on_homepage = false;

    public static function init(): void {
        add_action('admin_menu', array(__CLASS__, 'register_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
        add_filter('the_content', array(__CLASS__, 'inject_homepage_hub'), 24);
        add_shortcode('avdctai_project_hub', array(__CLASS__, 'shortcode'));
    }

    public static function defaults(): array {
        $projects = array();
        for ($i = 1; $i <= self::MAX_PROJECTS; $i++) {
            $projects[$i] = array(
                'enabled' => 0,
                'name' => '',
                'description' => '',
                'url' => '',
                'label' => __('Bekijk project', 'avd-cta-insights'),
            );
        }

        return array(
            'enabled' => 0,
            'show_on_homepage' => 0,
            'eyebrow' => __('Meer projecten', 'avd-cta-insights'),
            'headline' => __('Ontdek wat nog meer bij je past', 'avd-cta-insights'),
            'description' => '',
            'projects' => $projects,
        );
    }

    public static function settings(): array {
        $saved = get_option(self::OPTION_NAME, array());
        if (!is_array($saved)) {
            $saved = array();
        }

        $settings = wp_parse_args($saved, self::defaults());
        $settings['projects'] = is_array($settings['projects'] ?? null) ? $settings['projects'] : array();
        $defaults = self::defaults();

        for ($i = 1; $i <= self::MAX_PROJECTS; $i++) {
            $settings['projects'][$i] = wp_parse_args(
                is_array($settings['projects'][$i] ?? null) ? $settings['projects'][$i] : array(),
                $defaults['projects'][$i]
            );
        }

        return $settings;
    }

    public static function register_menu(): void {
        add_submenu_page(
            'avd-cta-insights',
            __('Project Hub', 'avd-cta-insights'),
            __('Project Hub', 'avd-cta-insights'),
            'manage_options',
            self::MENU_SLUG,
            array(__CLASS__, 'render_admin_page')
        );
    }

    public static function register_settings(): void {
        register_setting(
            'avdctai_project_hub',
            self::OPTION_NAME,
            array(
                'type' => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
                'default' => self::defaults(),
            )
        );
    }

    public static function sanitize_settings($input): array {
        $input = is_array($input) ? $input : array();
        $defaults = self::defaults();

        $clean = array(
            'enabled' => !empty($input['enabled']) ? 1 : 0,
            'show_on_homepage' => !empty($input['show_on_homepage']) ? 1 : 0,
            'eyebrow' => sanitize_text_field((string) ($input['eyebrow'] ?? $defaults['eyebrow'])),
            'headline' => sanitize_text_field((string) ($input['headline'] ?? $defaults['headline'])),
            'description' => sanitize_textarea_field((string) ($input['description'] ?? '')),
            'projects' => array(),
        );

        $incoming_projects = is_array($input['projects'] ?? null) ? $input['projects'] : array();
        for ($i = 1; $i <= self::MAX_PROJECTS; $i++) {
            $project = is_array($incoming_projects[$i] ?? null) ? $incoming_projects[$i] : array();
            $clean['projects'][$i] = array(
                'enabled' => !empty($project['enabled']) ? 1 : 0,
                'name' => sanitize_text_field((string) ($project['name'] ?? '')),
                'description' => sanitize_textarea_field((string) ($project['description'] ?? '')),
                'url' => esc_url_raw((string) ($project['url'] ?? '')),
                'label' => sanitize_text_field((string) ($project['label'] ?? $defaults['projects'][$i]['label'])),
            );
        }

        return $clean;
    }

    public static function enqueue_frontend_assets(): void {
        $settings = self::settings();
        if (empty($settings['enabled'])) {
            return;
        }

        wp_enqueue_script(
            'avd-cta-insights-project-hub',
            plugins_url('assets/js/project-hub.js', dirname(dirname(dirname(__FILE__))) . '/avd-cta-insights.php'),
            array(),
            AVDCTAI_Plugin::VERSION,
            true
        );

        self::enqueue_styles();
    }

    public static function enqueue_admin_assets(string $hook_suffix): void {
        if (strpos($hook_suffix, self::MENU_SLUG) === false) {
            return;
        }
        self::enqueue_styles();
    }

    private static function enqueue_styles(): void {
        $handle = 'avd-cta-insights-project-hub-style';
        wp_register_style($handle, false, array(), AVDCTAI_Plugin::VERSION);
        wp_enqueue_style($handle);
        wp_add_inline_style($handle, self::css());
    }

    public static function inject_homepage_hub(string $content): string {
        if (self::$rendered_on_homepage || is_admin() || !is_front_page() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $settings = self::settings();
        if (empty($settings['enabled']) || empty($settings['show_on_homepage'])) {
            return $content;
        }

        self::$rendered_on_homepage = true;
        $hub = self::render_hub($settings, 'homepage_project_hub');
        return $hub !== '' ? $content . $hub : $content;
    }

    public static function shortcode($atts = array()): string {
        $settings = self::settings();
        if (empty($settings['enabled'])) {
            return '';
        }

        $atts = shortcode_atts(
            array('placement' => 'shortcode_project_hub'),
            is_array($atts) ? $atts : array(),
            'avdctai_project_hub'
        );

        return self::render_hub($settings, sanitize_key((string) $atts['placement']) ?: 'shortcode_project_hub');
    }

    private static function active_projects(array $settings): array {
        $active = array();
        foreach ($settings['projects'] as $index => $project) {
            if (empty($project['enabled'])) {
                continue;
            }
            if (trim((string) ($project['name'] ?? '')) === '' || trim((string) ($project['url'] ?? '')) === '') {
                continue;
            }
            $active[(int) $index] = $project;
        }
        return $active;
    }

    private static function render_hub(array $settings, string $placement): string {
        $projects = self::active_projects($settings);
        if (empty($projects) || trim((string) $settings['headline']) === '') {
            return '';
        }

        ob_start();
        ?>
        <section class="avdctai-project-hub" data-avdctai-project-hub="1" data-avdctai-placement="<?php echo esc_attr($placement); ?>">
            <div class="avdctai-project-hub__header">
                <?php if ((string) $settings['eyebrow'] !== '') : ?>
                    <p class="avdctai-project-hub__eyebrow"><?php echo esc_html((string) $settings['eyebrow']); ?></p>
                <?php endif; ?>
                <h2><?php echo esc_html((string) $settings['headline']); ?></h2>
                <?php if ((string) $settings['description'] !== '') : ?>
                    <p class="avdctai-project-hub__description"><?php echo esc_html((string) $settings['description']); ?></p>
                <?php endif; ?>
            </div>
            <div class="avdctai-project-hub__grid">
                <?php foreach ($projects as $index => $project) : ?>
                    <article class="avdctai-project-hub__card">
                        <h3><?php echo esc_html((string) $project['name']); ?></h3>
                        <?php if ((string) $project['description'] !== '') : ?>
                            <p><?php echo esc_html((string) $project['description']); ?></p>
                        <?php endif; ?>
                        <a
                            class="avdctai-project-hub__button"
                            href="<?php echo esc_url(self::tracked_url((string) $project['url'], $placement, (int) $index)); ?>"
                            data-avd-cta="1"
                            data-avd-cta-type="<?php echo esc_attr('cta_project_hub_' . (int) $index); ?>"
                            data-avd-cta-source="<?php echo esc_attr($placement); ?>"
                        ><?php echo esc_html((string) $project['label']); ?></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return trim((string) ob_get_clean());
    }

    private static function tracked_url(string $url, string $placement, int $index): string {
        return add_query_arg(
            array(
                'utm_source' => wp_parse_url(home_url('/'), PHP_URL_HOST),
                'utm_medium' => 'project_hub',
                'utm_campaign' => 'project_network',
                'utm_content' => sanitize_key($placement . '_project_' . $index),
            ),
            $url
        );
    }

    public static function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = self::settings();
        $stats = self::stats(7);
        ?>
        <div class="wrap avdctai-project-hub-admin">
            <h1><?php esc_html_e('Project Hub', 'avd-cta-insights'); ?></h1>
            <p><?php esc_html_e('Stuur bestaand verkeer naar meerdere relevante projecten en meet per kaart welke route interesse oplevert. Publicatie is altijd opt-in.', 'avd-cta-insights'); ?></p>

            <div class="avdctai-project-hub-stats">
                <?php self::stat_card(__('Gezien · 7d', 'avd-cta-insights'), $stats['seen_sessions']); ?>
                <?php self::stat_card(__('Kliksessies · 7d', 'avd-cta-insights'), $stats['click_sessions']); ?>
                <?php self::stat_card(__('CTR · 7d', 'avd-cta-insights'), $stats['ctr'] . '%'); ?>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('avdctai_project_hub'); ?>
                <table class="form-table" role="presentation"><tbody>
                    <tr><th><?php esc_html_e('Publicatie', 'avd-cta-insights'); ?></th><td>
                        <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[enabled]" value="1" <?php checked(!empty($settings['enabled'])); ?>> <?php esc_html_e('Project Hub inschakelen', 'avd-cta-insights'); ?></label><br>
                        <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[show_on_homepage]" value="1" <?php checked(!empty($settings['show_on_homepage'])); ?>> <?php esc_html_e('Automatisch onderaan de homepage tonen', 'avd-cta-insights'); ?></label>
                    </td></tr>
                    <?php self::text_row('eyebrow', __('Bovenregel', 'avd-cta-insights'), $settings['eyebrow']); ?>
                    <?php self::text_row('headline', __('Kop', 'avd-cta-insights'), $settings['headline']); ?>
                    <?php self::textarea_row('description', __('Intro', 'avd-cta-insights'), $settings['description']); ?>
                </tbody></table>

                <h2><?php esc_html_e('Projectkaarten', 'avd-cta-insights'); ?></h2>
                <?php for ($i = 1; $i <= self::MAX_PROJECTS; $i++) : $project = $settings['projects'][$i]; ?>
                    <fieldset class="avdctai-project-hub-project">
                        <legend><?php echo esc_html(sprintf(__('Project %d', 'avd-cta-insights'), $i)); ?></legend>
                        <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[projects][<?php echo esc_attr((string) $i); ?>][enabled]" value="1" <?php checked(!empty($project['enabled'])); ?>> <?php esc_html_e('Deze kaart tonen', 'avd-cta-insights'); ?></label>
                        <?php self::project_input($i, 'name', __('Naam', 'avd-cta-insights'), $project['name']); ?>
                        <?php self::project_textarea($i, 'description', __('Beschrijving', 'avd-cta-insights'), $project['description']); ?>
                        <?php self::project_input($i, 'url', __('URL', 'avd-cta-insights'), $project['url'], 'url'); ?>
                        <?php self::project_input($i, 'label', __('Knoptekst', 'avd-cta-insights'), $project['label']); ?>
                    </fieldset>
                <?php endfor; ?>

                <?php submit_button(); ?>
            </form>

            <p><code>[avdctai_project_hub]</code></p>
        </div>
        <?php
    }

    public static function stats(int $days): array {
        $events = class_exists('AVDCTAI_Event_Archive')
            ? AVDCTAI_Event_Archive::get_combined_events()
            : get_option(AVDCTAI_Plugin::OPTION_RECENT_EVENTS, array());
        $events = is_array($events) ? $events : array();
        $since = time() - (max(1, $days) * DAY_IN_SECONDS);
        $seen = array();
        $clicked = array();
        $by_project = array();

        foreach ($events as $event) {
            if (!is_array($event) || (int) ($event['timestamp'] ?? 0) < $since) {
                continue;
            }
            $type = sanitize_key((string) ($event['type'] ?? ''));
            $session = sanitize_text_field((string) ($event['session_id'] ?? ($event['sessionId'] ?? '')));
            if ($session === '') {
                continue;
            }
            if ($type === 'project_hub_seen') {
                $seen[$session] = true;
                continue;
            }
            if (strpos($type, 'cta_project_hub_') === 0) {
                $clicked[$session] = true;
                $slot = absint(str_replace('cta_project_hub_', '', $type));
                if ($slot > 0) {
                    if (!isset($by_project[$slot])) {
                        $by_project[$slot] = array();
                    }
                    $by_project[$slot][$session] = true;
                }
            }
        }

        $seen_count = count($seen);
        $click_count = count($clicked);
        $project_counts = array();
        foreach ($by_project as $slot => $sessions) {
            $project_counts[$slot] = count($sessions);
        }

        return array(
            'seen_sessions' => $seen_count,
            'click_sessions' => $click_count,
            'ctr' => $seen_count > 0 ? round(($click_count / $seen_count) * 100, 1) : 0.0,
            'project_click_sessions' => $project_counts,
        );
    }

    private static function text_row(string $key, string $label, string $value): void {
        echo '<tr><th><label for="avdctai-project-hub-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input class="regular-text" id="avdctai-project-hub-' . esc_attr($key) . '" name="' . esc_attr(self::OPTION_NAME) . '[' . esc_attr($key) . ']" value="' . esc_attr($value) . '"></td></tr>';
    }

    private static function textarea_row(string $key, string $label, string $value): void {
        echo '<tr><th><label for="avdctai-project-hub-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><textarea class="large-text" rows="3" id="avdctai-project-hub-' . esc_attr($key) . '" name="' . esc_attr(self::OPTION_NAME) . '[' . esc_attr($key) . ']">' . esc_textarea($value) . '</textarea></td></tr>';
    }

    private static function project_input(int $index, string $key, string $label, string $value, string $type = 'text'): void {
        echo '<p><label><strong>' . esc_html($label) . '</strong><br><input class="regular-text" type="' . esc_attr($type) . '" name="' . esc_attr(self::OPTION_NAME) . '[projects][' . esc_attr((string) $index) . '][' . esc_attr($key) . ']" value="' . esc_attr($value) . '"></label></p>';
    }

    private static function project_textarea(int $index, string $key, string $label, string $value): void {
        echo '<p><label><strong>' . esc_html($label) . '</strong><br><textarea class="large-text" rows="2" name="' . esc_attr(self::OPTION_NAME) . '[projects][' . esc_attr((string) $index) . '][' . esc_attr($key) . ']">' . esc_textarea($value) . '</textarea></label></p>';
    }

    /** @param int|string $value */
    private static function stat_card(string $label, $value): void {
        echo '<div class="avdctai-project-hub-stat"><strong>' . esc_html((string) $value) . '</strong><span>' . esc_html($label) . '</span></div>';
    }

    private static function css(): string {
        return '.avdctai-project-hub{border:1px solid #dcdcde;border-radius:16px;padding:24px;margin:28px 0;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.05)}'
            . '.avdctai-project-hub__eyebrow{margin:0 0 6px;color:#646970;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}'
            . '.avdctai-project-hub h2{margin:0 0 8px;font-size:clamp(24px,3vw,34px)}'
            . '.avdctai-project-hub__description{margin:0 0 18px;color:#50575e;font-size:16px;line-height:1.55}'
            . '.avdctai-project-hub__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px}'
            . '.avdctai-project-hub__card{display:flex;flex-direction:column;padding:18px;border:1px solid #e2e4e7;border-radius:12px;background:#f9f9f9}'
            . '.avdctai-project-hub__card h3{margin:0 0 8px;font-size:20px}'
            . '.avdctai-project-hub__card p{margin:0 0 16px;line-height:1.5}'
            . '.avdctai-project-hub__button{display:inline-block;margin-top:auto;padding:10px 14px;border-radius:8px;background:#1d2327;color:#fff!important;text-decoration:none!important;font-weight:700}'
            . '.avdctai-project-hub-admin{max-width:1050px}'
            . '.avdctai-project-hub-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:18px 0}'
            . '.avdctai-project-hub-stat{padding:16px;border:1px solid #dcdcde;border-radius:10px;background:#fff}'
            . '.avdctai-project-hub-stat strong{display:block;font-size:26px}'
            . '.avdctai-project-hub-project{margin:0 0 16px;padding:16px;border:1px solid #dcdcde;border-radius:10px;background:#fff}'
            . '.avdctai-project-hub-project legend{padding:0 6px;font-weight:700}';
    }
}
