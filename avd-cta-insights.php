<?php
/**
 * Plugin Name: AVD CTA Insights
 * Description: Meet CTA-kliks, analyseer bezoekersgedrag en ontvang concrete optimalisatievoorstellen voor WordPress.
 * Version: 4.0.0.15
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Alexander van Dijl
 * Text Domain: avd-cta-insights
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-loader.php';

if (!class_exists('AVDCTAI_Plugin')) {

    final class AVDCTAI_Plugin {
        const VERSION = '4.0.0.15';
        const AJAX_ACTION = 'avdctai_event';
        const OPTION_RECENT_EVENTS = 'avdctai_events_recent';
        const OPTION_API_KEY = 'avdctai_api_key';
        const OPTION_SETTINGS = 'avdctai_settings';
        const LEAD_POST_TYPE = 'avdctai_scan_lead';

        private static $instance = null;
        private static $content_cta_injected = false;

        private $base_phone_display = '';
        private $base_phone_international_display = '';
        private $base_phone_tel = '';
        private $whatsapp_number = '';

        public static function instance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Return the plugin settings merged with safe defaults.
         *
         * Visual CTA elements are disabled by default so installing this plugin
         * never places another website's phone number, WhatsApp link or marketing
         * content on the frontend.
         *
         * @return array
         */
        public function get_settings() {
            $defaults = array(
                'tracking_enabled'          => 1,
                'track_page_views'          => 1,
                'content_cta_enabled'       => 0,
                'sticky_bar_enabled'        => 0,
                'popup_enabled'             => 0,
                'phone_display'             => '',
                'phone_international'       => '',
                'phone_tel'                 => '',
                'whatsapp_number'           => '',
                'cta_title'                 => '',
                'cta_text'                  => '',
                'cta_button_label'          => '',
                'cta_button_url'            => '',
                'whatsapp_default_message'  => '',
            );

            $settings = get_option(self::OPTION_SETTINGS, array());

            if (!is_array($settings)) {
                $settings = array();
            }

            return wp_parse_args($settings, $defaults);
        }

        /**
         * Read one plugin setting.
         *
         * @param string $key     Setting key.
         * @param mixed  $default Fallback value.
         * @return mixed
         */
        private function setting($key, $default = '') {
            $settings = $this->get_settings();

            return array_key_exists($key, $settings) ? $settings[$key] : $default;
        }

        /**
         * Check whether a boolean plugin setting is enabled.
         *
         * @param string $key Setting key.
         * @return bool
         */
        private function setting_enabled($key) {
            return (bool) absint($this->setting($key, 0));
        }

        /**
         * Load runtime contact values from the saved plugin settings.
         *
         * @return void
         */
        private function load_runtime_settings() {
            $this->base_phone_display = sanitize_text_field((string) $this->setting('phone_display', ''));
            $this->base_phone_international_display = sanitize_text_field((string) $this->setting('phone_international', ''));
            $this->base_phone_tel = esc_url_raw((string) $this->setting('phone_tel', ''));
            $this->whatsapp_number = preg_replace('/[^0-9]/', '', (string) $this->setting('whatsapp_number', ''));
        }

        private function __construct() {
            $this->load_runtime_settings();

            add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            add_filter('the_content', array($this, 'inject_content_cta'), 8);
            add_action('wp_footer', array($this, 'render_sticky_bar'), 88);
            add_action('wp_footer', array($this, 'render_popup'), 98);
            add_action('admin_menu', array(new AVDCTAI_Admin($this), 'register_menu'));
            add_action('rest_api_init', array($this, 'register_rest_routes'));

            add_action('wp_ajax_' . self::AJAX_ACTION, array($this, 'track_event'));
            add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, array($this, 'track_event'));

            add_shortcode('avdctai_cta', array($this, 'shortcode_cta'));

            // Backwards-compatible aliases. These now render the generic CTA.
            add_shortcode('avdctai_paid_help', array($this, 'shortcode_cta'));
            add_shortcode('avdctai_business_scan_form', array($this, 'shortcode_cta'));
        }

        public function enqueue_assets() {
    if (is_admin()) {
        return;
    }

    $tracking_enabled = $this->setting_enabled('tracking_enabled');
    $visual_enabled = $this->setting_enabled('content_cta_enabled')
        || $this->setting_enabled('sticky_bar_enabled')
        || $this->setting_enabled('popup_enabled');

    if (!$tracking_enabled && !$visual_enabled) {
        return;
    }

    wp_register_style('avd-cta-insights-style', '', array(), self::VERSION);
    wp_enqueue_style('avd-cta-insights-style');
    wp_add_inline_style('avd-cta-insights-style', $this->get_css());

    wp_enqueue_script(
        'avd-cta-insights-frontend',
        plugin_dir_url(__FILE__) . 'assets/js/frontend-tracking.js',
        array(),
        self::VERSION,
        true
    );

    wp_localize_script(
        'avd-cta-insights-frontend',
        'AVDCTAIFrontend',
        array(
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'action'       => self::AJAX_ACTION,
            'nonce'        => wp_create_nonce('avdctai_event'),
            'pageUrl'      => esc_url_raw($this->current_url()),
            'pageType'     => $this->get_page_context(),
            'popupEnabled' => $this->popup_allowed() ? 1 : 0,
            'trackViews'   => ($this->setting_enabled('tracking_enabled') && $this->setting_enabled('track_page_views')) ? 1 : 0,
        )
    );
}

        public function enqueue_admin_assets($hook_suffix) {
            if (!is_string($hook_suffix)) {
                return;
            }

            $allowed_pages = array(
                'toplevel_page_avd-cta-insights',
                'avd-cta-insights_page_avd-ai-analyse',
            );

            if (!in_array($hook_suffix, $allowed_pages, true)) {
                return;
            }

            wp_register_script(
                'avd-cta-insights-admin',
                '',
                array(),
                self::VERSION,
                true
            );

            wp_enqueue_script('avd-cta-insights-admin');

            $script = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    var button = document.getElementById('avd-copy-ai-export');
    var textarea = document.getElementById('avd-ai-export');

    if (!button || !textarea) {
        return;
    }

    button.addEventListener('click', function () {
        textarea.select();
        textarea.setSelectionRange(0, textarea.value.length);

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(textarea.value).then(function () {
                button.textContent = 'Gekopieerd!';
            });
            return;
        }

        document.execCommand('copy');
        button.textContent = 'Gekopieerd!';
    });
});
JS;

            wp_add_inline_script(
                'avd-cta-insights-admin',
                $script,
                'after'
            );
        }
        public function inject_content_cta($content) {
            if (!$this->setting_enabled('content_cta_enabled')) {
                return $content;
            }

            if (is_admin() || is_feed() || wp_doing_ajax()) {
                return $content;
            }

            if (!is_singular() && !is_front_page() && !is_home()) {
                return $content;
            }

            if (!in_the_loop() || !is_main_query() || self::$content_cta_injected) {
                return $content;
            }

            if ($this->is_pixelverification()) {
                return $content;
            }

            $cta = $this->render_generic_cta('content');

            if ($cta === '') {
                return $content;
            }

            self::$content_cta_injected = true;

            return $cta . $content;
        }

        public function shortcode_cta($atts = array()) {
            $atts = shortcode_atts(
                array(
                    'source' => 'shortcode',
                    'title'  => '',
                    'text'   => '',
                    'label'  => '',
                    'url'    => '',
                ),
                $atts,
                'avdctai_cta'
            );

            return $this->render_generic_cta(
                sanitize_key($atts['source']),
                array(
                    'title' => sanitize_text_field($atts['title']),
                    'text'  => sanitize_textarea_field($atts['text']),
                    'label' => sanitize_text_field($atts['label']),
                    'url'   => esc_url_raw($atts['url']),
                )
            );
        }

        private function get_page_context() {
            if (is_front_page() || is_home()) {
                return 'home';
            }

            if (is_singular('post')) {
                return 'post';
            }

            if (is_page()) {
                return 'page';
            }

            if (is_search()) {
                return 'search';
            }

            if (is_404()) {
                return '404';
            }

            if (is_archive()) {
                return 'archive';
            }

            return 'default';
        }

        private function generic_cta_values($overrides = array()) {
            $values = array(
                'title' => sanitize_text_field((string) $this->setting('cta_title', '')),
                'text'  => sanitize_textarea_field((string) $this->setting('cta_text', '')),
                'label' => sanitize_text_field((string) $this->setting('cta_button_label', '')),
                'url'   => esc_url_raw((string) $this->setting('cta_button_url', '')),
            );

            foreach ($values as $key => $value) {
                if (isset($overrides[$key]) && $overrides[$key] !== '') {
                    $values[$key] = $overrides[$key];
                }
            }

            return $values;
        }

        private function render_generic_cta($source = 'generic', $overrides = array()) {
            $values = $this->generic_cta_values($overrides);

            if ($values['title'] === '' && $values['text'] === '' && ($values['label'] === '' || $values['url'] === '')) {
                return '';
            }

            $source = sanitize_key($source);
            $context = $this->get_page_context();

            ob_start();
            ?>
            <section class="avd-uber-content-cta avdctai-content-cta" data-avdctai-cta-context="<?php echo esc_attr($context); ?>">
                <div class="avd-uber-card avdctai-card">
                    <?php if ($values['title'] !== '') : ?>
                        <h2><?php echo esc_html($values['title']); ?></h2>
                    <?php endif; ?>

                    <?php if ($values['text'] !== '') : ?>
                        <p><?php echo esc_html($values['text']); ?></p>
                    <?php endif; ?>

                    <div class="avd-uber-button-row avdctai-button-row">
                        <?php
                        if ($values['label'] !== '' && $values['url'] !== '') {
                            echo wp_kses_post($this->button('cta_click', $values['label'], $values['url'], $source, 'primary'));
                        }

                        if ($this->base_phone_tel !== '') {
                            $phone_label = $this->base_phone_display !== ''
                                ? sprintf(__('Bel %s', 'avd-cta-insights'), $this->base_phone_display)
                                : __('Bellen', 'avd-cta-insights');
                            echo wp_kses_post($this->button('tel_click', $phone_label, $this->base_phone_tel, $source, 'secondary'));
                        }

                        if ($this->whatsapp_number !== '') {
                            echo wp_kses_post($this->button('whatsapp_click', __('WhatsApp', 'avd-cta-insights'), $this->whatsapp_url(), $source, 'tertiary'));
                        }
                        ?>
                    </div>
                </div>
            </section>
            <?php

            return trim(ob_get_clean());
        }

        public function render_sticky_bar() {
            if (!$this->setting_enabled('sticky_bar_enabled')) {
                return;
            }

            if (is_admin() || is_feed() || wp_doing_ajax() || $this->is_pixelverification()) {
                return;
            }

            $values = $this->generic_cta_values();

            if ($values['title'] === '' && ($values['label'] === '' || $values['url'] === '') && $this->base_phone_tel === '' && $this->whatsapp_number === '') {
                return;
            }
            ?>
            <div class="avd-uber-cta-wrapper avdctai-sticky" data-avdctai-cta-context="<?php echo esc_attr($this->get_page_context()); ?>" aria-label="<?php esc_attr_e('Oproep tot actie', 'avd-cta-insights'); ?>">
                <button class="avd-cta-close avdctai-cta-close" type="button" aria-label="<?php esc_attr_e('Sluiten', 'avd-cta-insights'); ?>">×</button>

                <?php if ($values['title'] !== '') : ?>
                    <div class="avd-cta-title"><?php echo esc_html($values['title']); ?></div>
                <?php endif; ?>

                <?php if ($values['text'] !== '') : ?>
                    <div class="avd-cta-desc"><?php echo esc_html($values['text']); ?></div>
                <?php endif; ?>

                <div class="avd-cta-buttons">
                    <?php
                    if ($values['label'] !== '' && $values['url'] !== '') {
                        echo wp_kses_post($this->legacy_button('cta_click', $values['label'], $values['url'], 'sticky_bar', 'primary'));
                    }

                    if ($this->base_phone_tel !== '') {
                        echo wp_kses_post($this->legacy_button('tel_click', __('Bellen', 'avd-cta-insights'), $this->base_phone_tel, 'sticky_bar', 'secondary'));
                    }

                    if ($this->whatsapp_number !== '') {
                        echo wp_kses_post($this->legacy_button('whatsapp_click', __('WhatsApp', 'avd-cta-insights'), $this->whatsapp_url(), 'sticky_bar', 'tertiary'));
                    }
                    ?>
                </div>
            </div>
            <?php
        }

        public function render_popup() {
            if (!$this->popup_allowed()) {
                return;
            }

            $values = $this->generic_cta_values();

            if ($values['title'] === '' && $values['text'] === '' && ($values['label'] === '' || $values['url'] === '')) {
                return;
            }
            ?>
            <div id="avdctaiPopup" class="avd-uber-popup avdctai-popup" aria-hidden="true" data-avdctai-popup="1" data-avdctai-cta-context="<?php echo esc_attr($this->get_page_context()); ?>">
                <div class="avd-uber-popup-backdrop" data-avdctai-popup-close="1"></div>
                <div class="avd-uber-popup-box" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Oproep tot actie', 'avd-cta-insights'); ?>">
                    <button type="button" class="avd-uber-popup-close" data-avdctai-popup-close="1" aria-label="<?php esc_attr_e('Sluiten', 'avd-cta-insights'); ?>">×</button>

                    <?php if ($values['title'] !== '') : ?>
                        <h2><?php echo esc_html($values['title']); ?></h2>
                    <?php endif; ?>

                    <?php if ($values['text'] !== '') : ?>
                        <p><?php echo esc_html($values['text']); ?></p>
                    <?php endif; ?>

                    <?php if ($values['label'] !== '' && $values['url'] !== '') : ?>
                        <div class="avd-uber-button-column">
                            <?php echo wp_kses_post($this->button('cta_click', $values['label'], $values['url'], 'popup', 'primary')); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }

        private function button($type, $label, $href, $source, $style = 'primary') {
            if (!$href || !$label) {
                return '';
            }

            return sprintf(
                '<a class="avd-uber-btn avd-uber-btn-%5$s avd-cta-button" href="%1$s" data-avdctai-cta="1" data-avdctai-cta-type="%2$s" data-avdctai-cta-source="%3$s" rel="nofollow">%4$s</a>',
                esc_url($href),
                esc_attr(sanitize_key($type)),
                esc_attr(sanitize_key($source)),
                esc_html($label),
                esc_attr(sanitize_key($style))
            );
        }

        private function legacy_button($type, $label, $href, $source, $legacy_class = 'primary') {
            if (!$href || !$label) {
                return '';
            }

            return sprintf(
                '<a href="%1$s" class="avd-cta-button %2$s" data-avdctai-cta="1" data-avdctai-cta-type="%3$s" data-avdctai-cta-source="%4$s" rel="nofollow">%5$s</a>',
                esc_url($href),
                esc_attr(sanitize_html_class($legacy_class)),
                esc_attr(sanitize_key($type)),
                esc_attr(sanitize_key($source)),
                esc_html($label)
            );
        }

        private function whatsapp_url($text = '') {
            if (!$this->whatsapp_number) {
                return '';
            }

            if ($text === '') {
                $text = sanitize_text_field((string) $this->setting('whatsapp_default_message', ''));
            }

            $url = 'https://wa.me/' . $this->whatsapp_number;

            if ($text !== '') {
                $url .= '?text=' . rawurlencode($text);
            }

            return $url;
        }

        public function track_event() {
    if (!$this->setting_enabled('tracking_enabled')) {
        wp_send_json_error(array('stored' => false, 'reason' => 'tracking_disabled'), 403);
    }

    check_ajax_referer('avdctai_event', 'nonce');

    $event = array(
        'time' => current_time('mysql'),
        'timestamp' => time(),
        'type' => isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : 'unknown',
        'source' => isset($_POST['source']) ? sanitize_key(wp_unslash($_POST['source'])) : 'unknown',
        'context' => isset($_POST['context']) ? sanitize_key(wp_unslash($_POST['context'])) : 'unknown',
        'device' => isset($_POST['device']) ? sanitize_key(wp_unslash($_POST['device'])) : 'unknown',
        'page_url' => isset($_POST['pageUrl']) ? esc_url_raw(wp_unslash($_POST['pageUrl'])) : '',
        'target_url' => isset($_POST['targetUrl']) ? esc_url_raw(wp_unslash($_POST['targetUrl'])) : '',
        'label' => isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '',
        'session_id' => isset($_POST['sessionId']) ? sanitize_text_field(wp_unslash($_POST['sessionId'])) : '',

        'referrer' => isset($_POST['referrer']) ? esc_url_raw(wp_unslash($_POST['referrer'])) : '',
        'language' => isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : '',
        'screen_width' => isset($_POST['screenWidth']) ? absint($_POST['screenWidth']) : 0,
        'screen_height' => isset($_POST['screenHeight']) ? absint($_POST['screenHeight']) : 0,
        'timezone' => isset($_POST['timezone']) ? sanitize_text_field(wp_unslash($_POST['timezone'])) : '',

        'ip_hash' => $this->ip_hash(),
        'user_agent_hash' => $this->ua_hash(),
    );

    $this->store_event($event);

    wp_send_json_success(array(
        'stored' => true,
        'event' => $event['type'],
    ));
}
        private function store_event($event) {
            $events = get_option(self::OPTION_RECENT_EVENTS, array());
            if (!is_array($events)) {
                $events = array();
            }

            $events[] = $event;

            if (count($events) > 500) {
                $events = array_slice($events, -500);
            }

            update_option(self::OPTION_RECENT_EVENTS, $events, false);

            $daily_key = 'avdctai_daily_' . gmdate('Ymd');
            $daily = get_option($daily_key, array());

            if (!is_array($daily)) {
                $daily = array();
            }

            foreach (array('total', 'types', 'contexts', 'devices') as $key) {
                if (!isset($daily[$key])) {
                    $daily[$key] = ($key === 'total') ? 0 : array();
                }
            }

            $type = $event['type'];
            $context = $event['context'];
            $device = $event['device'];

            $daily['total']++;
            $daily['types'][$type] = isset($daily['types'][$type]) ? $daily['types'][$type] + 1 : 1;
            $daily['contexts'][$context] = isset($daily['contexts'][$context]) ? $daily['contexts'][$context] + 1 : 1;
            $daily['devices'][$device] = isset($daily['devices'][$device]) ? $daily['devices'][$device] + 1 : 1;

            update_option($daily_key, $daily, false);
        }


        public function register_rest_routes() {
            register_rest_route('avdctai/v1', '/stats', array(
                'methods' => 'GET',
                'callback' => array($this, 'rest_stats'),
                'permission_callback' => '__return_true',
            ));
        }

        public function rest_stats($request) {
            $key = $request->get_param('key');
            if (!$key || !hash_equals($this->api_key(), (string) $key)) {
                return new WP_Error('avdctai_forbidden', 'Ongeldige of ontbrekende sleutel.', array('status' => 403));
            }

            return rest_ensure_response($this->build_stats_payload());
        }

        public function render_admin_page() {
            if (!current_user_can('manage_options')) {
                return;
            }

            $payload = $this->build_stats_payload();
            $export = $this->build_chatgpt_export($payload);
            $json_url = rest_url('avdctai/v1/stats?key=' . rawurlencode($this->api_key()));
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('AVD CTA Insights-analyse', 'avd-cta-insights'); ?></h1>
                <p><strong>Versie <?php echo esc_html(self::VERSION); ?>:</strong> Analyse met websitecijfers, CTA-prestaties en concrete aandachtspunten.</p>
                <p><strong>Publieke JSON-link:</strong><br><input type="text" readonly value="<?php echo esc_attr($json_url); ?>" style="width:100%;max-width:900px;"></p>
                <p><button class="button button-primary" id="avd-copy-ai-export">📋 Kopieer AI Briefing voor ChatGPT</button></p>
                <textarea id="avd-ai-export" readonly style="width:100%;min-height:460px;font-family:monospace;"><?php echo esc_textarea($export); ?></textarea>
            </div>
            <?php
        }

        private function api_key() {
            $key = get_option(self::OPTION_API_KEY, '');
            if (!$key) {
                $key = wp_generate_password(32, false, false);
                update_option(self::OPTION_API_KEY, $key, false);
            }
            return $key;
        }

        public function build_stats_payload() {
            $events = get_option(self::OPTION_RECENT_EVENTS, array());

            if (!is_array($events)) {
                $events = array();
            }

            $today_start = strtotime(current_time('Y-m-d') . ' 00:00:00');
            $yesterday_start = $today_start - DAY_IN_SECONDS;
            $week_start = $today_start - (6 * DAY_IN_SECONDS);
            $prev_week_start = $week_start - (7 * DAY_IN_SECONDS);

            $top_pages = $this->rank_pages(
                $events,
                $week_start,
                time(),
                'top'
            );

            $needs_attention = $this->rank_pages(
                $events,
                $week_start,
                time(),
                'attention'
            );

            $benchmarks = array();

            if (class_exists('AVDCTAI_Page_Benchmarks')) {
                $benchmarks = AVDCTAI_Page_Benchmarks::build($top_pages);
            }

            return array(
                'site' => home_url('/'),
                'generated' => current_time('c'),
                'today' => $this->summarize_events(
                    $events,
                    $today_start,
                    time()
                ),
                'yesterday' => $this->summarize_events(
                    $events,
                    $yesterday_start,
                    $today_start - 1
                ),
                'week' => $this->summarize_events(
                    $events,
                    $week_start,
                    time()
                ),
                'previous_week' => $this->summarize_events(
                    $events,
                    $prev_week_start,
                    $week_start - 1
                ),
                'visitor_intelligence' => class_exists('AVDCTAI_Visitor_Intelligence')
                    ? AVDCTAI_Visitor_Intelligence::get_data(7)
                    : array(),
                'top_pages' => $top_pages,
                'needs_attention' => $needs_attention,
                'benchmarks' => $benchmarks,
            );
        }

        private function summarize_events($events, $from, $to) {
            $sessions = array();
            $engaged = array();
            $views = 0; $cta = 0; $popup = 0; $applications = 0; $bot = 0; $bot_count = 0;
            foreach ($events as $event) {
                $ts = isset($event['timestamp']) ? (int) $event['timestamp'] : 0;
                if ($ts < $from || $ts > $to) { continue; }
                $type = isset($event['type']) ? $event['type'] : '';
                $sid = isset($event['session_id']) ? $event['session_id'] : '';
                if ($sid) { $sessions[$sid] = true; }
                if ($type === 'page_view') { $views++; }
                elseif ($type === 'engaged_session') { if ($sid) { $engaged[$sid] = true; } }
                elseif (strpos($type, 'popup') === 0) { $popup++; }

                if (in_array($type, array('application', 'form_submit', 'lead_submit'), true)) { $applications++; }
                if ($this->is_real_cta_event($type)) { $cta++; }

                if (!empty($event['user_agent_hash'])) { $bot_count++; }
            }
            return array(
                'views' => $views,
                'sessions' => count($sessions),
                'engaged_sessions' => count($engaged),
                'cta' => $cta,
                'conversion_views' => $views ? round(($cta / $views) * 100, 2) : 0,
                'conversion_sessions' => count($sessions) ? round(($cta / count($sessions)) * 100, 2) : 0,
                'popup_events' => $popup,
                'applications' => $applications,
                'bot_score' => $bot_count ? round($bot / max(1, $bot_count), 2) : 0,
            );
        }

        private function rank_pages($events, $from, $to, $mode) {
            $pages = array();
            foreach ($events as $event) {
                $ts = isset($event['timestamp']) ? (int) $event['timestamp'] : 0;
                if ($ts < $from || $ts > $to) { continue; }
                $url = isset($event['page_url']) ? $event['page_url'] : '';
                if (!$url) { continue; }
                $path = wp_parse_url($url, PHP_URL_PATH);
                $key = $path ? trim($path, '/') : 'homepage';
                if (!$key) { $key = 'homepage'; }
                if (!isset($pages[$key])) { $pages[$key] = array('page'=>$key, 'views'=>0, 'cta'=>0, 'engaged'=>0, 'score'=>0); }
                $type = isset($event['type']) ? $event['type'] : '';
                if ($type === 'page_view') { $pages[$key]['views']++; }
                elseif ($type === 'engaged_session') { $pages[$key]['engaged']++; }

                if ($this->is_real_cta_event($type)) { $pages[$key]['cta']++; }
            }
            foreach ($pages as &$p) {
                $conv = $p['views'] ? ($p['cta'] / $p['views']) * 100 : 0;
                $p['conversion'] = round($conv, 2);
                $p['score'] = (int) min(100, round(($p['views'] * 2) + ($p['engaged'] * 2) + (($p['cta'] == 0 && $p['views'] >= 10) ? 40 : 0) + (($conv > 0 && $conv < 5) ? 20 : 0)));
            }
            unset($p);
            uasort($pages, function($a, $b) use ($mode) {
                if ($mode === 'attention') { return $b['score'] <=> $a['score']; }
                return $b['views'] <=> $a['views'];
            });
            return array_slice(array_values($pages), 0, 10);
        }

        private function is_real_cta_event($type) {
            $type = strtolower(trim((string) $type));

            if ($type === '') {
                return false;
            }

            if (class_exists('AVDCTAI_Visitor_Intelligence')) {
                return AVDCTAI_Visitor_Intelligence::is_real_cta_event($type);
            }

            $excluded = array(
                'page_view',
                'view',
                'engaged_session',
                'scroll',
                'scroll_25',
                'scroll_50',
                'scroll_75',
                'popup_view',
                'popup_shown',
                'popup_open',
                'popup_close',
                'sticky_close',
                'toolbar_view',
                'heartbeat',
            );

            if (in_array($type, $excluded, true)) {
                return false;
            }

            return (
                strpos($type, 'cta') !== false ||
                strpos($type, 'click') !== false ||
                strpos($type, 'call') !== false ||
                strpos($type, 'whatsapp') !== false ||
                strpos($type, 'mail') !== false ||
                strpos($type, 'lead') !== false ||
                strpos($type, 'form_submit') !== false ||
                strpos($type, 'submit') !== false
            );
        }

        private function pct_change($new, $old) {
            if (!$old && !$new) { return '0%'; }
            if (!$old) { return '+100%'; }
            $pct = round((($new - $old) / $old) * 100, 1);
            return ($pct > 0 ? '+' : '') . $pct . '%';
        }

        private function build_chatgpt_export($payload) {
            $today = $payload['today'];
            $yesterday = $payload['yesterday'];
            $week = $payload['week'];
            $previous_week = $payload['previous_week'];

            $out = "=== AVD CTA INSIGHTS BRIEFING ===\n";
            $out .= 'Versie: ' . self::VERSION . "\n";
            $out .= 'Site: ' . home_url('/') . "\n";
            $out .= 'Gegenereerd: ' . current_time('c') . "\n\n";

            $out .= "=== VANDAAG ===\n";
            foreach (array('views', 'sessions', 'engaged_sessions', 'cta', 'conversion_views', 'conversion_sessions', 'popup_events', 'applications') as $key) {
                $old_value = isset($yesterday[$key]) ? $yesterday[$key] : 0;
                $out .= ucfirst(str_replace('_', ' ', $key)) . ': ' . $today[$key] . ' (' . $this->pct_change($today[$key], $old_value) . ")\n";
            }

            $out .= "\n=== DEZE WEEK ===\n";
            foreach (array('views', 'sessions', 'engaged_sessions', 'cta', 'conversion_views', 'conversion_sessions', 'popup_events', 'applications') as $key) {
                $old_value = isset($previous_week[$key]) ? $previous_week[$key] : 0;
                $out .= ucfirst(str_replace('_', ' ', $key)) . ': ' . $week[$key] . ' (' . $this->pct_change($week[$key], $old_value) . ")\n";
            }

            $out .= "\n=== TOPPAGINA'S ===\n";
            if (empty($payload['top_pages'])) {
                $out .= "Nog geen paginagegevens beschikbaar.\n";
            } else {
                foreach ($payload['top_pages'] as $page) {
                    $out .= sprintf(
                        "- %s: %d weergaven, %d CTA-acties, %s%% conversie\n",
                        $page['page'],
                        $page['views'],
                        $page['cta'],
                        $page['conversion']
                    );
                }
            }

            $out .= "\n=== PAGINA'S DIE AANDACHT VRAGEN ===\n";
            if (empty($payload['needs_attention'])) {
                $out .= "Nog geen gegevens beschikbaar.\n";
            } else {
                foreach ($payload['needs_attention'] as $page) {
                    $out .= sprintf(
                        "- %s: %d weergaven, %d CTA-acties, prioriteitsscore %d\n",
                        $page['page'],
                        $page['views'],
                        $page['cta'],
                        $page['score']
                    );
                }
            }

            $out .= "\n=== ANALYSEVRAAG ===\n";
            $out .= "Analyseer de prestaties, benoem opvallende veranderingen en geef drie concrete verbeteracties voor CTA's en pagina's.\n";
            $out .= "=== EINDE BRIEFING ===\n";

            return $out;
        }

        private function popup_allowed() {
            if (!$this->setting_enabled('popup_enabled')) {
                return false;
            }

            if (is_admin() || is_feed() || wp_doing_ajax() || $this->is_pixelverification()) {
                return false;
            }

            return true;
        }

private function is_pixelverification() {
    $pixelverification = filter_input(
        INPUT_GET,
        'pixelverification',
        FILTER_SANITIZE_FULL_SPECIAL_CHARS
    );

    return is_string($pixelverification) && $pixelverification !== '';
}
        
private function current_url() {
    $scheme = is_ssl() ? 'https://' : 'http://';

    $host = filter_input(INPUT_SERVER, 'HTTP_HOST', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $uri  = filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    return $scheme . (string) $host . (string) $uri;
}

        private function path() {
            $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
            $path = wp_parse_url($uri, PHP_URL_PATH);
            $path = trim((string) $path, '/');

            return strtolower($path);
        }

        private function slug() {
            if (!is_singular()) {
                return '';
            }

            $id = get_queried_object_id();
            if (!$id) {
                return '';
            }

            return strtolower((string) get_post_field('post_name', $id));
        }

        private function contains_any($haystack, $needles) {
            $haystack = strtolower((string) $haystack);
            foreach ($needles as $needle) {
                $needle = strtolower((string) $needle);
                if ($needle !== '' && strpos($haystack, $needle) !== false) {
                    return true;
                }
            }

            return false;
        }

        private function ip_hash() {
            $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
            return $ip ? hash('sha256', $ip . wp_salt('auth')) : '';
        }

        private function ua_hash() {
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
            return $ua ? hash('sha256', $ua . wp_salt('auth')) : '';
        }

        private function get_css() {
            return '
                .avd-scan-form-wrap { width: 100%; }
                .avd-scan-message { border-radius: 14px; padding: 15px 18px; margin: 0 0 18px; }
                .avd-scan-success { background: #ecfdf5; border: 1px solid #16a34a; color: #065f46; }
                .avd-scan-error { background: #fff1f2; border: 1px solid #fb7185; color: #9f1239; }
                .avd-scan-form label { display: block; font-weight: 800; color: #0f172a; }
                .avd-scan-form input[type="text"], .avd-scan-form input[type="email"], .avd-scan-form input[type="tel"], .avd-scan-form input[type="url"], .avd-scan-form textarea { width: 100%; margin-top: 7px; border: 1px solid #cbd5e1; border-radius: 14px; padding: 13px 14px; font-size: 16px; box-sizing: border-box; background: #fff; }
                .avd-scan-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 18px; }
                .avd-scan-full { grid-column: 1 / -1; margin-bottom: 18px; }
                .avd-scan-fieldset { border: 1px solid #e2e8f0; border-radius: 18px; padding: 18px; margin: 0 0 18px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 16px; }
                .avd-scan-fieldset legend { font-size: 18px; font-weight: 900; padding: 0 8px; color: #0f172a; }
                .avd-scan-fieldset label { font-weight: 700; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 12px; padding: 10px 12px; }
                .avd-scan-submit { width: 100%; border: 0; border-radius: 999px; background: #16a34a; color: #fff; font-size: 19px; font-weight: 900; padding: 16px 22px; cursor: pointer; }
                .avd-scan-privacy { font-size: 14px; color: #64748b; margin: 13px 0 0; }
                .avd-scan-hp { position: absolute; left: -9999px; height: 1px; overflow: hidden; }
                @media (max-width: 700px) { .avd-scan-grid, .avd-scan-fieldset { grid-template-columns: 1fr; } }
                .avd-uber-content-cta,
                .avd-uber-paid-help {
                    clear: both;
                    margin: 0 0 28px 0;
                }

                .avd-uber-card {
                    border: 1px solid rgba(0,0,0,.10);
                    border-radius: 18px;
                    padding: 22px;
                    background: #fff;
                    box-shadow: 0 8px 28px rgba(0,0,0,.08);
                    margin: 18px 0 26px 0;
                }

                .avd-uber-card h2 {
                    margin: 0 0 10px 0;
                    font-size: clamp(22px, 3vw, 34px);
                    line-height: 1.15;
                }

                .avd-uber-card p {
                    margin: 0 0 16px 0;
                    font-size: 17px;
                    line-height: 1.55;
                }

                .avd-uber-eyebrow {
                    display: inline-block;
                    font-size: 13px !important;
                    font-weight: 800;
                    text-transform: uppercase;
                    letter-spacing: .04em;
                    margin-bottom: 8px !important;
                    opacity: .75;
                }

                .avd-uber-small {
                    font-size: 13px !important;
                    opacity: .75;
                    margin-top: 12px !important;
                    margin-bottom: 0 !important;
                }

                .avd-uber-content-business .avd-uber-card,
                .avd-uber-card-business {
                    border-color: #16a34a;
                    background: linear-gradient(135deg, #ecfdf5, #ffffff);
                }

                .avd-uber-business-strip {
                    display: grid;
                    gap: 8px;
                    background: #ecfdf5;
                    border: 1px solid #bbf7d0;
                    border-radius: 16px;
                    padding: 15px;
                    margin-top: 16px;
                }

                .avd-uber-button-row,
                .avd-cta-buttons {
                    display: flex;
                    gap: 10px;
                    flex-wrap: wrap;
                    align-items: center;
                    margin-top: 14px;
                }

                .avd-uber-button-column {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                    margin-top: 14px;
                }

                .avd-uber-btn,
                .avd-cta-button {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 44px;
                    padding: 11px 15px;
                    border-radius: 999px;
                    text-decoration: none !important;
                    font-weight: 800;
                    line-height: 1.2;
                    border: 2px solid #111;
                    cursor: pointer;
                    transition: transform .15s ease, box-shadow .15s ease, opacity .15s ease;
                }

                .avd-uber-btn:hover,
                .avd-cta-button:hover,
                .avd-uber-choice:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 8px 20px rgba(0,0,0,.16);
                    opacity: .95;
                }

                .avd-uber-btn-primary,
                .avd-cta-button.bel,
                .avd-cta-button.hulp {
                    background: #111;
                    color: #fff !important;
                }

                .avd-uber-btn-secondary,
                .avd-cta-button.vraag {
                    background: #fff;
                    color: #111 !important;
                }

                .avd-uber-btn-tertiary,
                .avd-cta-button.donatie {
                    background: #f5f5f5;
                    color: #111 !important;
                    border-color: rgba(0,0,0,.25);
                }

                .avd-uber-choice-grid {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 12px;
                    margin-top: 16px;
                }

                .avd-uber-choice {
                    display: block;
                    padding: 16px;
                    border-radius: 16px;
                    border: 1px solid rgba(0,0,0,.12);
                    text-decoration: none !important;
                    color: inherit !important;
                    background: #fff;
                }

                .avd-uber-choice strong {
                    display: block;
                    font-size: 17px;
                    margin-bottom: 4px;
                }

                .avd-uber-choice span {
                    display: block;
                    font-size: 14px;
                    opacity: .78;
                }

                .avd-uber-steps {
                    display: grid;
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                    gap: 10px;
                    margin: 16px 0;
                }

                .avd-uber-steps span {
                    display: block;
                    padding: 12px;
                    border-radius: 14px;
                    background: #f4f4f4;
                    font-weight: 800;
                    font-size: 14px;
                }

                .avd-uber-list {
                    margin: 0 0 16px 20px;
                }

                .avd-uber-list li {
                    margin-bottom: 6px;
                }

                .avd-uber-cta-wrapper {
                    position: fixed;
                    left: 18px;
                    right: auto;
                    bottom: 18px;
                    z-index: 99998;
                    width: min(420px, calc(100vw - 36px));
                    background: #fff;
                    border: 1px solid rgba(0,0,0,.12);
                    border-radius: 16px;
                    box-shadow: 0 12px 36px rgba(0,0,0,.22);
                    padding: 16px;
                    font-family: inherit;
                    line-height: 1.4;
                }

                .avd-uber-cta-wrapper .avd-cta-title {
                    font-size: 17px;
                    font-weight: 900;
                    margin: 0 34px 4px 0;
                }

                .avd-uber-cta-wrapper .avd-cta-desc {
                    font-size: 14px;
                    margin: 0 0 10px 0;
                    opacity: .82;
                }

                .avd-uber-cta-wrapper .avd-cta-buttons {
                    margin-top: 8px;
                    gap: 8px;
                }

                .avd-uber-cta-wrapper .avd-cta-button {
                    min-height: 38px;
                    padding: 8px 11px;
                    font-size: 13px;
                }

                .avd-cta-close {
                    position: absolute;
                    top: 8px;
                    right: 10px;
                    width: 28px;
                    height: 28px;
                    border: 0;
                    border-radius: 999px;
                    background: #eee;
                    color: #111;
                    font-size: 20px;
                    line-height: 1;
                    cursor: pointer;
                }

                .avd-uber-popup {
                    position: fixed;
                    inset: 0;
                    z-index: 999999;
                    display: none;
                    align-items: center;
                    justify-content: center;
                    padding: 18px;
                }

                .avd-uber-popup.avd-uber-popup-visible {
                    display: flex;
                }

                .avd-uber-popup-backdrop {
                    position: absolute;
                    inset: 0;
                    background: rgba(0,0,0,.48);
                }

                .avd-uber-popup-box {
                    position: relative;
                    width: min(460px, 100%);
                    background: #fff;
                    border-radius: 22px;
                    padding: 24px;
                    box-shadow: 0 20px 60px rgba(0,0,0,.30);
                    z-index: 1;
                }

                .avd-uber-popup-box h2 {
                    margin: 0 0 10px 0;
                    font-size: 28px;
                    line-height: 1.15;
                }

                .avd-uber-popup-box p {
                    margin: 0 0 14px 0;
                    font-size: 16px;
                    line-height: 1.5;
                }

                .avd-uber-popup-close {
                    position: absolute;
                    top: 10px;
                    right: 12px;
                    border: 0;
                    background: transparent;
                    font-size: 30px;
                    line-height: 1;
                    cursor: pointer;
                    padding: 6px;
                }

                @media (max-width: 720px) {
                    .avd-uber-card {
                        padding: 18px;
                        border-radius: 16px;
                    }

                    .avd-uber-button-row,
                    .avd-cta-buttons {
                        flex-direction: column;
                        align-items: stretch;
                    }

                    .avd-uber-btn,
                    .avd-cta-button {
                        width: 100%;
                    }

                    .avd-uber-choice-grid,
                    .avd-uber-steps {
                        grid-template-columns: 1fr;
                    }

                    .avd-uber-cta-wrapper {
                        left: 10px;
                        right: 10px;
                        bottom: 10px;
                        width: auto;
                        padding: 13px;
                    }

                    .avd-uber-cta-wrapper .avd-cta-buttons {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                    }

                    .avd-uber-cta-wrapper .avd-cta-button:nth-child(n+3) {
                        display: none;
                    }

                    .avd-uber-popup {
                        align-items: flex-end;
                        padding: 10px;
                    }

                    .avd-uber-popup-box {
                        border-radius: 20px 20px 14px 14px;
                    }
                }
            ';
        }


}

}

function avdctai_bootstrap() {
    AVDCTAI_Loader::load();
    AVDCTAI_Plugin::instance();
}
add_action('plugins_loaded', 'avdctai_bootstrap');
