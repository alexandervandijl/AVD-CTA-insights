<?php
/**
 * Plugin Name: AVD CTA Insights
 * Plugin URI: https://alexandervandijl.nl/avd-cta-insights/
 * Description: Meet CTA-kliks, analyseer bezoekersgedrag en ontvang concrete optimalisatievoorstellen voor WordPress.
 * Version: 4.0.0.13
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Alexander van Dijl
 * Author URI: https://alexandervandijl.nl
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
        const VERSION = '4.0.0.13';
        const AJAX_ACTION = 'avdctai_event';
        const OPTION_RECENT_EVENTS = 'avdctai_events_recent';
        const OPTION_API_KEY = 'avdctai_api_key';
        const LEAD_POST_TYPE = 'avdctai_scan_lead';

        private static $instance = null;
        private static $content_cta_injected = false;

        private $base_phone_display = '020 262 1789';
        private $base_phone_international_display = '+31 20 262 1789';
        private $base_phone_tel = 'tel:+31202621789';
        private $whatsapp_number = '31645430985';

        public static function instance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
        }

       private function __construct() {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            add_filter('the_content', array($this, 'inject_content_cta'), 8);
            add_action('wp_footer', array($this, 'render_sticky_bar'), 88);
            add_action('wp_footer', array($this, 'render_popup'), 98);
            add_action('admin_menu', array(new AVDCTAI_Admin($this), 'register_menu'));
            add_action('rest_api_init', array($this, 'register_rest_routes'));
            add_action('init', array($this, 'register_lead_post_type'));
            add_action('admin_post_avdctai_business_scan_submit', array($this, 'handle_bedrijfsscan_submit'));
            add_action('admin_post_nopriv_avdctai_business_scan_submit', array($this, 'handle_bedrijfsscan_submit'));
            add_filter('manage_' . self::LEAD_POST_TYPE . '_posts_columns', array($this, 'lead_columns'));
            add_action('manage_' . self::LEAD_POST_TYPE . '_posts_custom_column', array($this, 'lead_column_content'), 10, 2);

            add_action('wp_ajax_' . self::AJAX_ACTION, array($this, 'track_event'));
            add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, array($this, 'track_event'));

            add_shortcode('avdctai_cta', array($this, 'shortcode_cta'));
            add_shortcode('avdctai_paid_help', array($this, 'shortcode_paid_help'));
            add_shortcode('avdctai_business_scan_form', array($this, 'shortcode_bedrijfsscan_form'));
        }

        public function enqueue_assets() {
    if (is_admin()) {
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
            'trackViews'   => 1,
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
            if (is_admin() || is_feed() || wp_doing_ajax()) {
                return $content;
            }

            if (!is_singular() && !is_front_page() && !is_home()) {
                return $content;
            }

            if (!in_the_loop() || !is_main_query()) {
                return $content;
            }

            if (self::$content_cta_injected) {
                return $content;
            }

            if ($this->is_pixelverification()) {
                return $content;
            }

            if (strpos($content, 'avd-uber-content-cta') !== false) {
                return $content;
            }

            $context = $this->get_page_context();

            if ($context === 'ignore') {
                return $content;
            }

            $cta = $this->render_content_cta($context, 'content_top');

            if (!$cta) {
                return $content;
            }

            self::$content_cta_injected = true;

            return $cta . $content;
        }

        public function shortcode_cta($atts = array()) {
            $atts = shortcode_atts(array(
                'type' => '',
                'source' => 'shortcode',
            ), $atts, 'avdctai_cta');

            $context = sanitize_key($atts['type']);
            if (!$context) {
                $context = $this->get_page_context();
            }

            return $this->render_content_cta($context, sanitize_key($atts['source']));
        }

        public function shortcode_paid_help($atts = array()) {
            return $this->paid_help_block('shortcode_betaalde_hulp');
        }

        private function get_page_context() {
            if (is_admin() || $this->is_pixelverification()) {
                return 'ignore';
            }

            $path = $this->path();
            $slug = $this->slug();
            $title = strtolower(wp_strip_all_tags(get_the_title()));
            $combined = trim($path . ' ' . $slug . ' ' . $title);

            if ($this->contains_any($combined, array('gratis-bedrijfsscan', 'bedrijfsscan'))) {
                return 'landing';
            }

            if ($this->contains_any($combined, array(
                'ai-assistent',
                'ai assistent',
                'ai-chatbot',
                'ai chatbot',
                'chatbot',
                'zakelijk',
                'business',
                'automatisering',
                'google-sheets',
                'google sheets',
                'voice-ai',
                'voice ai',
                'whatsapp-business',
                'whatsapp business',
                'computerhulp',
                'bedrijfsscan-aanvragen'
            ))) {
                return 'business';
            }

            if (is_front_page() || is_home() || $path === '') {
                return 'home';
            }

            if ($this->contains_any($combined, array('doneer', 'doneren', 'donatie'))) {
                return 'donate';
            }

            if ($this->contains_any($combined, array('contact', 'contactpagina'))) {
                return 'contact';
            }

            if ($this->contains_any($combined, array('0906', '0909'))) {
                return 'paid_number';
            }

            if ($this->contains_any($combined, array('woningnet', 'woonnet', 'woonnet-rijnmond'))) {
                return 'woningnet';
            }

            if ($this->contains_any($combined, array(
                'buitenland',
                'vanuit-het-buitenland',
                'vanuit buitenland',
                '0800-nummer',
                '0800 nummer',
                '085-en-088',
                '085 en 088',
                'vast-nummer-bellen',
                'vast nummer bellen',
                'internationale-telefoonnummers',
                'internationale telefoonnummers',
                'noodnummers-buitenland',
                'noodnummers buitenland',
                'simpel-bellen-buitenland',
                'simpel bellen buitenland',
                'ik-kan-niet-bellen',
                'ik kan niet bellen'
            ))) {
                return 'international';
            }

            if ($this->contains_any($combined, array(
                'telefoonnummer-blokkeren',
                'telefoonnummer blokkeren',
                'telefoonkosten-werk-vergoeden',
                'telefoonkosten werk vergoeden',
                'vaste-telefoon',
                'vaste telefoon'
            ))) {
                return 'phone_help';
            }

            if (is_singular('bedrijf') || has_category('bedrijven') || has_tag('bedrijven')) {
                return 'company';
            }

            if ($this->contains_any($combined, array(
                'shein',
                'gls',
                'decathlon',
                'anwb',
                'snappcar',
                'rentumo',
                'uwv',
                'klantenservice',
                'telefoon-bellen',
                'telefoon bellen',
                'wegenwacht',
                'bellen?'
            ))) {
                return 'company';
            }

            return 'default';
        }

        private function render_content_cta($context, $source = 'content_top') {
            switch ($context) {
                case 'landing':
                    return '';
                case 'business':
                    return $this->cta_business_scan($source);
                case 'home':
                    return $this->cta_home($source);
                case 'contact':
                    return $this->cta_contact($source);
                case 'donate':
                    return $this->cta_donate($source);
                case 'woningnet':
                    return $this->cta_woningnet($source);
                case 'international':
                    return $this->cta_international($source);
                case 'phone_help':
                    return $this->cta_phone_help($source);
                case 'paid_number':
                    return $this->cta_paid_number($source);
                case 'company':
                    return $this->cta_company($source);
                case 'default':
                    return $this->cta_default($source);
                default:
                    return '';
            }
        }

        private function cta_business_scan($source) {
            ob_start();
            ?>
            <section class="avd-uber-content-cta avd-uber-content-business" data-avd-cta-context="business">
                <div class="avd-uber-card avd-uber-card-business">
                    <p class="avd-uber-eyebrow">Voor ondernemers</p>
                    <h2>Wil je meer klanten, minder werk of slimmer klantcontact?</h2>
                    <p>Vraag gratis de bedrijfsscan aan. Ik kijk waar jouw bedrijf snel kan winnen met AI, telefonie, automatisering, bereikbaarheid en lead capture.</p>
                    <div class="avd-uber-button-row">
                        <?php echo wp_kses_post($this->button('bedrijfsscan', 'Vraag gratis bedrijfsscan aan', home_url('/gratis-bedrijfsscan-2/'), $source, 'primary')); ?>
                        <?php echo wp_kses_post($this->button('ai_assistent', 'Bekijk AI-assistent', home_url('/ai-assistent/'), $source, 'secondary')); ?>
                    </div>
                    <p class="avd-uber-small">Gratis en vrijblijvend. Meestal binnen één werkdag reactie.</p>
                </div>
            </section>
            <?php
            return trim(ob_get_clean());
        }

        private function cta_home($source) {
            $wa_text = 'Hoi Alexander, ik heb hulp nodig met bellen of doorverbinden via AlexandervanDijl.nl.';
            $paid_text = 'Hoi Alexander, ik wil graag dat jij namens mij belt of iets uitzoekt.';

            ob_start();
            ?>
            <section class="avd-uber-content-cta avd-uber-content-home" data-avd-cta-context="home">
                <div class="avd-uber-card avd-uber-card-hero">
                    <p class="avd-uber-eyebrow">Gratis doorverbindservice</p>
                    <h2>Bel makkelijker via <?php echo esc_html($this->base_phone_display); ?></h2>
                    <p>Bel het basisnummer en toets daarna het volledige telefoonnummer in dat je wilt bereiken. Handig bij 0800-, 0900-, 14+ nummers, vaste nummers en bellen vanuit het buitenland.</p>
                    <div class="avd-uber-button-row">
                        <?php echo wp_kses_post($this->button('bel', 'Bel via ' . $this->base_phone_display, $this->base_phone_tel, $source, 'primary')); ?>
                        <?php echo wp_kses_post($this->button('bedrijfsscan', 'Gratis bedrijfsscan voor bedrijven', home_url('/gratis-bedrijfsscan-2/'), $source, 'secondary')); ?>
                        <?php echo wp_kses_post($this->button('whatsapp', 'Vraag hulp via WhatsApp', $this->whatsapp_url($wa_text), $source, 'tertiary')); ?>
                    </div>
                    <p class="avd-uber-small">Je betaalt alleen je normale belkosten naar het basisnummer. Persoonlijk uitzoekwerk kan tegen een vaste prijs.</p>
                </div>
            </section>
            <?php
            return trim(ob_get_clean());
        }

        private function cta_contact($source) {
            $wa_text = 'Hoi Alexander, ik kom via de contactpagina en heb een vraag.';
            $paid_text = 'Hoi Alexander, ik wil graag betaalde hulp aanvragen.';

            ob_start();
            ?>
            <section class="avd-uber-content-cta avd-uber-content-contact" data-avd-cta-context="contact">
                <div class="avd-uber-card">
                    <p class="avd-uber-eyebrow">Snelste route</p>
                    <h2>Waarmee kan ik je helpen?</h2>
                    <p>Kies direct de optie die past. Zo hoeft niemand te zoeken naar een knop die ergens onderaan verstopt zit als een sok in de wasmachine.</p>
                    <div class="avd-uber-choice-grid">
                        <?php echo wp_kses_post($this->choice('bedrijfsscan', 'Gratis bedrijfsscan', 'Voor ondernemers en bedrijven', home_url('/gratis-bedrijfsscan-2/'), $source)); ?>
                        <?php echo wp_kses_post($this->choice('bel', 'Gratis doorverbinden', 'Bel via ' . $this->base_phone_display, $this->base_phone_tel, $source)); ?>
                        <?php echo wp_kses_post($this->choice('whatsapp', 'WhatsApp hulp', 'Stuur direct je vraag', $this->whatsapp_url($wa_text), $source)); ?>
                        <?php echo wp_kses_post($this->choice('betaalde_hulp', 'Laat mij bellen', 'Voor €60 namens jou uitzoeken', $this->paid_help_url($paid_text), $source)); ?>
                    </div>
                </div>
            </section>
            <?php
            return trim(ob_get_clean());
        }

        private function cta_donate($source) {
            ob_start();
            ?>
            <section class="avd-uber-content-cta avd-uber-content-donate" data-avd-cta-context="donate">
                <div class="avd-uber-card">
                    <p class="avd-uber-eyebrow">Steun gratis hulp</p>
                    <h2>Help AlexandervanDijl.nl online houden</h2>
                    <p>Heeft de gratis doorverbindservice of informatie op deze site je geholpen? Een kleine donatie helpt om de dienst beschikbaar te houden.</p>
                    <div class="avd-uber-button-row">
                        <?php echo wp_kses_post($this->button('donatie', 'Doneer via Tikkie', 'https://link.vraagalex.com/tikkie', $source, 'primary')); ?>
                        <?php echo wp_kses_post($this->button('donatie_paypal', 'Doneer via PayPal', 'https://www.paypal.com/ncp/payment/CCHK5S3ZBQLFQ', $source, 'secondary')); ?>
                        <?php echo wp_kses_post($this->button('bel', 'Bel via ' . $this->base_phone_display, $this->base_phone_tel, $source, 'tertiary')); ?>
                    </div>
                </div>
            </section>
            <?php
            return trim(ob_get_clean());
        }

        private function cta_woningnet($source) {
            $name = $this->page_subject('Woningnet');
            $wa_text = 'Hoi Alexander, ik heb hulp nodig met ' . $name . '.';
            $paid_text = 'Hoi Alexander, ik wil graag dat jij meekijkt of namens mij contact probeert te leggen met ' . $name . '.';

            ob_start();
            ?>
            <section class="avd-uber-content-cta avd-uber-content-woningnet" data-avd-cta-context="woningnet">
                <div class="avd-uber-card">
                    <p class="avd-uber-eyebrow">Woningnet / Woonnet hulp</p>
                    <h2><?php echo esc_html($name); ?> bereiken of hulp nodig?</h2>
                    <p>Bel via <?php echo esc_html($this->base_phone_display); ?> en toets daarna het nummer in dat je wilt bereiken. Loop je vast met inschrijven, reageren of bereikbaarheid? App mij dan je vraag.</p>
                    <div class="avd-uber-button-row">
                        <?php echo wp_kses_post($this->button('bel', 'Bel via ' . $this->base_phone_display, $this->base_phone_tel, $source, 'primary')); ?>
                        <?php echo wp_kses_post($this->button('whatsapp', 'App voor hulp', $this->whatsapp_url($wa_text), $source, 'secondary')); ?>
                        <?php echo wp_kses_post($this->button('betaalde_hulp', 'Laat Alexander meekijken', $this->paid_help_url($paid_text), $source, 'tertiary')); ?>
                    </div>
                </div>
            </section>
            <?php
            return trim(ob_get_clean());
        }

        private function cta_international($source) {
            $wa_text = 'Hoi Alexander, ik probeer vanuit het buitenland te bellen en kom er niet uit.';
            $paid_text = 'Hoi Alexander, ik wil graag dat jij uitzoekt hoe ik dit nummer vanuit het buitenland kan bereiken.';

            ob_start();
            ?>
            <section class="avd-uber-content-cta avd-uber-content-international" data-avd-cta-context="international">
                <div class="avd-uber-card">
                    <p class="avd-uber-eyebrow">Bellen vanuit het buitenland</p>
                    <h2>Wil je direct een Nederlands nummer bellen?</h2>
                    <p>Bel vanuit het buitenland naar <strong><?php echo esc_html($this->base_phone_international_display); ?></strong>. Toets daarna het volledige Nederlandse nummer in dat je wilt bereiken en wacht tot je wordt doorverbonden.</p>
                    <div class="avd-uber-steps">
                        <span>1. Bel <?php echo esc_html($this->base_phone_international_display); ?></span>
                        <span>2. Toets het Nederlandse nummer in</span>
                        <span>3. Wacht op de verbinding</span>
                    </div>
                    <div class="avd-uber-button-row">
                        <?php echo wp_kses_post($this->button('bel', 'Bel nu via ' . $this->base_phone_display, $this->base_phone_tel, $source, 'primary')); ?>
                        <?php echo wp_kses_post($this->button('whatsapp', 'Werkt het niet? App mij', $this->whatsapp_url($wa_text), $source, 'secondary')); ?>
                        <?php echo wp_kses_post($this->button('betaalde_hulp', 'Laat het nummer uitzoeken', $this->paid_help_url($paid_text), $source, 'tertiary')); ?>
                    </div>
                    <p class="avd-uber-small">Binnen Nederland bel je gewoon <?php echo esc_html($this->base_phone_display); ?>. Vanuit het buitenland gebruik je <?php echo esc_html($this->base_phone_international_display); ?>.</p>
                </div>
            </section>
            <?php
            return trim(ob_get_clean());
        }

        private function cta_phone_help($source) {
            $wa_text = 'Hoi Alexander, ik heb hulp nodig met een telefoonprobleem.';
            $paid_text = 'Hoi Alexander, ik wil graag hulp met mijn telefooninstellingen of bereikbaarheid.';

            ob_start();
            ?>
            <section class="avd-uber-content-cta avd-uber-content-phone-help" data-avd-cta-context="phone_help">
                <div class="avd-uber-card">
                    <p class="avd-uber-eyebrow">Telefoniehulp</p>
                    <h2>Kom je er niet uit met bellen, blokkeren of bereikbaarheid?</h2>
                    <p>Ik help met praktische telefoonvragen, vaste telefonie, bereikbaarheid en doorverbinden. Begin gratis met de doorverbindservice of stuur je vraag via WhatsApp.</p>
                    <div class="avd-uber-button-row">
                        <?php echo wp_kses_post($this->button('bel', 'Bel via ' . $this->base_phone_display, $this->base_phone_tel, $source, 'primary')); ?>
                        <?php echo wp_kses_post($this->button('whatsapp', 'Stel je vraag via WhatsApp', $this->whatsapp_url($wa_text), $source, 'secondary')); ?>
                        <?php echo wp_kses_post($this->button('betaalde_hulp', 'Vraag persoonlijke hulp aan', $this->paid_help_url($paid_text), $source, 'tertiary')); ?>
                    </div>
                </div>
            </section>
            <?php
            return trim(ob_get_clean());
        }

        private function cta_paid_number($source) {
            $wa_text = 'Hoi Alexander, ik heb een vraag over een betaald telefoonnummer.';
            ob_start();
            ?>
            <section class="avd-uber-content-cta avd-uber-content-paid-number" data-avd-cta-context="paid_number">
                <div class="avd-uber-card">
                    <p class="avd-uber-eyebrow">Let op met betaalde nummers</p>
                    <h2>0906- en 0909-nummers kunnen niet gratis worden doorverbonden</h2>
                    <p>Voor betaalde servicenummers gelden aparte regels. Heb je twijfel over kosten of bereikbaarheid? Stel je vraag eerst voordat je onnodig geld uitgeeft.</p>
                    <div class="avd-uber-button-row">
                        <?php echo wp_kses_post($this->button('whatsapp', 'Vraag advies via WhatsApp', $this->whatsapp_url($wa_text), $source, 'primary')); ?>
                        <?php echo wp_kses_post($this->button('donatie', 'Steun de gratis dienst', home_url('/doneer/'), $source, 'secondary')); ?>
                    </div>
                </div>
            </section>
            <?php
            return trim(ob_get_clean());
        }

        private function cta_company($source) {
            $name = $this->page_subject('dit bedrijf');
            $wa_text = 'Hoi Alexander, ik probeer ' . $name . ' te bereiken en kom er niet uit.';
            $paid_text = 'Hoi Alexander, ik wil graag dat jij namens mij contact probeert te leggen met ' . $name . '.';

            ob_start();
            ?>
            <section class="avd-uber-content-cta avd-uber-content-company" data-avd-cta-context="company">
                <div class="avd-uber-card">
                    <p class="avd-uber-eyebrow">Snel contact zoeken</p>
                    <h2><?php echo esc_html($name); ?> bellen?</h2>
                    <p>Gebruik de gratis doorverbindservice via <?php echo esc_html($this->base_phone_display); ?>. Toets daarna het volledige telefoonnummer in van <?php echo esc_html($name); ?>.</p>
                    <div class="avd-uber-button-row">
                        <?php echo wp_kses_post($this->button('bel', 'Bel via ' . $this->base_phone_display, $this->base_phone_tel, $source, 'primary')); ?>
                        <?php echo wp_kses_post($this->button('whatsapp', 'Geen gehoor? App mij', $this->whatsapp_url($wa_text), $source, 'secondary')); ?>
                        <?php echo wp_kses_post($this->button('betaalde_hulp', 'Laat Alexander bellen', $this->paid_help_url($paid_text), $source, 'tertiary')); ?>
                    </div>
                    <div class="avd-uber-business-strip">
                        <strong>Heb jij zelf een bedrijf?</strong>
                        <span>Ontdek hoe AI, telefonie en automatisering jouw bereikbaarheid en leads kunnen verbeteren.</span>
                        <?php echo wp_kses_post($this->button('bedrijfsscan', 'Gratis bedrijfsscan', home_url('/gratis-bedrijfsscan-2/'), $source, 'secondary')); ?>
                    </div>
                    <p class="avd-uber-small">Lukt het niet via de klantenservice? Dan kan ik tegen een vast bedrag meekijken of namens jou contact proberen te leggen.</p>
                </div>
            </section>
            <?php
            return trim(ob_get_clean());
        }

        private function cta_default($source) {
            $wa_text = 'Hoi Alexander, ik heb een vraag via AlexandervanDijl.nl.';
            $paid_text = 'Hoi Alexander, ik wil graag persoonlijke hulp aanvragen.';

            ob_start();
            ?>
            <section class="avd-uber-content-cta avd-uber-content-default" data-avd-cta-context="default">
                <div class="avd-uber-card">
                    <p class="avd-uber-eyebrow">Hulp nodig?</p>
                    <h2>Kies direct je volgende stap</h2>
                    <p>Je kunt gratis doorverbinden, je vraag stellen via WhatsApp of persoonlijke hulp aanvragen.</p>
                    <div class="avd-uber-button-row">
                        <?php echo wp_kses_post($this->button('bel', 'Bel via ' . $this->base_phone_display, $this->base_phone_tel, $source, 'primary')); ?>
                        <?php echo wp_kses_post($this->button('whatsapp', 'Stel je vraag via WhatsApp', $this->whatsapp_url($wa_text), $source, 'secondary')); ?>
                        <?php echo wp_kses_post($this->button('betaalde_hulp', 'Vraag persoonlijke hulp aan', $this->paid_help_url($paid_text), $source, 'tertiary')); ?>
                    </div>
                </div>
            </section>
            <?php
            return trim(ob_get_clean());
        }

        private function paid_help_block($source = 'paid_help_block') {
            $source = sanitize_key($source);
            $wa_text = 'Hoi Alexander, ik wil graag gebruikmaken van betaalde hulp.';

            ob_start();
            ?>
            <section class="avd-uber-paid-help" data-avd-cta-context="betaalde_hulp_blok">
                <div class="avd-uber-card">
                    <p class="avd-uber-eyebrow">Persoonlijke hulp</p>
                    <h2>Geen zin om zelf in de wacht te staan?</h2>
                    <p>Voor een vast bedrag van €60 kan ik namens jou proberen contact te leggen, het juiste kanaal zoeken of je helpen met één duidelijke klantenservicevraag.</p>
                    <ul class="avd-uber-list">
                        <li>Geschikt bij moeilijk bereikbare klantenservices.</li>
                        <li>Handig als je niet weet welk nummer of formulier je nodig hebt.</li>
                        <li>Maximaal 3 vragen of één duidelijke casus.</li>
                    </ul>
                    <div class="avd-uber-button-row">
                        <?php echo wp_kses_post($this->button('betaalde_hulp', 'Vraag betaalde hulp aan', $this->paid_help_url($wa_text), $source, 'primary')); ?>
                        <?php echo wp_kses_post($this->button('whatsapp', 'Eerst overleggen via WhatsApp', $this->whatsapp_url($wa_text), $source, 'secondary')); ?>
                    </div>
                </div>
            </section>
            <?php
            return trim(ob_get_clean());
        }

        public function render_sticky_bar() {
            if (is_admin() || is_feed() || wp_doing_ajax() || $this->is_pixelverification()) {
                return;
            }

            $context = $this->get_page_context();
            if ($context === 'ignore' || $context === 'landing') {
                return;
            }

            $subject = $this->page_subject('hulp');
            $wa_text = 'Hoi Alexander, ik kom via AlexandervanDijl.nl en heb hulp nodig.';
            $paid_text = 'Hoi Alexander, ik wil graag dat jij iets voor mij uitzoekt of namens mij belt.';

            if ($context === 'company') {
                $wa_text = 'Hoi Alexander, ik probeer ' . $subject . ' te bereiken en kom er niet uit.';
                $paid_text = 'Hoi Alexander, ik wil graag dat jij namens mij contact probeert te leggen met ' . $subject . '.';
            }

            if ($context === 'international') {
                $wa_text = 'Hoi Alexander, ik probeer vanuit het buitenland te bellen en kom er niet uit.';
                $subject = 'bellen vanuit het buitenland';
            }

            ?>
            <div class="avd-uber-cta-wrapper" data-avd-cta-context="<?php echo esc_attr($context); ?>" aria-label="Snelle hulp">
                <button class="avd-cta-close" type="button" aria-label="Sluit">×</button>
                <div class="avd-cta-title"><?php echo esc_html($this->sticky_title($context, $subject)); ?></div>
                <div class="avd-cta-desc"><?php echo esc_html($this->sticky_desc($context)); ?></div>
                <div class="avd-cta-buttons">
                    <?php if (in_array($context, array('business','contact','home'), true)) : ?>
                        <?php echo wp_kses_post($this->legacy_button('bedrijfsscan', 'Gratis bedrijfsscan', home_url('/gratis-bedrijfsscan-2/'), 'sticky_bar', 'bedrijfsscan')); ?>
                    <?php endif; ?>
                    <?php echo wp_kses_post($this->legacy_button('bel', 'Bel ' . $this->base_phone_display, $this->base_phone_tel, 'sticky_bar', 'bel')); ?>
                    <?php echo wp_kses_post($this->legacy_button('whatsapp', 'WhatsApp', $this->whatsapp_url($wa_text), 'sticky_bar', 'vraag')); ?>
                    <?php if (!in_array($context, array('business','home'), true)) : ?>
                        <?php echo wp_kses_post($this->legacy_button('betaalde_hulp', 'Hulp €60', $this->paid_help_url($paid_text), 'sticky_bar', 'hulp')); ?>
                        <?php echo wp_kses_post($this->legacy_button('donatie', 'Steun €2', home_url('/doneer/'), 'sticky_bar', 'donatie')); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }

        private function sticky_title($context, $subject) {
            if ($context === 'international') {
                return 'Bellen vanuit het buitenland?';
            }

            if ($context === 'company') {
                return $subject . ' bereiken?';
            }

            if ($context === 'woningnet') {
                return 'Woningnet hulp nodig?';
            }

            if ($context === 'contact') {
                return 'Kies je snelste route';
            }

            if ($context === 'business') {
                return 'Gratis bedrijfsscan?';
            }

            return 'Hulp nodig?';
        }

        private function sticky_desc($context) {
            if ($context === 'international') {
                return 'Bel +31 20 262 1789 en toets daarna het Nederlandse nummer in.';
            }

            if ($context === 'company') {
                return 'Bel gratis door of vraag hulp als je vastloopt.';
            }

            if ($context === 'woningnet') {
                return 'Bel door, app je vraag of laat meekijken.';
            }

            return 'Bel gratis, app je vraag of vraag persoonlijke hulp aan.';
        }

        public function render_popup() {
            if (!$this->popup_allowed()) {
                return;
            }

            $context = $this->get_page_context();
            if ($context === 'ignore' || $context === 'landing' || $context === 'donate' || $context === 'paid_number') {
                return;
            }

            $wa_text = 'Hoi Alexander, ik kom via AlexandervanDijl.nl en loop vast. Kun je helpen?';
            $paid_text = 'Hoi Alexander, ik wil graag dat jij iets voor mij uitzoekt of namens mij belt.';
            ?>
            <div id="avdUberPopup" class="avd-uber-popup" aria-hidden="true" data-avd-cta-context="<?php echo esc_attr($context); ?>">
                <div class="avd-uber-popup-backdrop" data-avd-popup-close="1"></div>
                <div class="avd-uber-popup-box" role="dialog" aria-modal="true" aria-label="Hulp nodig?">
                    <button type="button" class="avd-uber-popup-close" data-avd-popup-close="1" aria-label="Sluiten">×</button>
                    <p class="avd-uber-eyebrow">Hulp nodig?</p>
                    <h2>Kom je er niet uit?</h2>
                    <p>Probeer gratis door te verbinden of stuur mij je vraag. Als het ingewikkelder is, kan ik het persoonlijk voor je uitzoeken.</p>
                    <div class="avd-uber-button-column">
                        <?php echo wp_kses_post($this->button('popup_bel', 'Bel gratis via ' . $this->base_phone_display, $this->base_phone_tel, 'popup', 'primary')); ?>
                        <?php echo wp_kses_post($this->button('popup_whatsapp', 'Stuur WhatsApp', $this->whatsapp_url($wa_text), 'popup', 'secondary')); ?>
                        <?php echo wp_kses_post($this->button('popup_betaalde_hulp', 'Laat Alexander het uitzoeken', $this->paid_help_url($paid_text), 'popup', 'tertiary')); ?>
                    </div>
                    <p class="avd-uber-small">Deze melding verschijnt beperkt: pas na tijd, scroll of exit-intent op desktop.</p>
                </div>
            </div>
            <?php
        }

        private function button($type, $label, $href, $source, $style = 'primary') {
            $type = sanitize_key($type);
            $source = sanitize_key($source);
            $style = sanitize_key($style);

            return sprintf(
                '<a class="avd-uber-btn avd-uber-btn-%5$s avd-cta-button" href="%1$s" data-avd-cta="1" data-avd-cta-type="%2$s" data-avd-cta-source="%3$s" rel="nofollow">%4$s</a>',
                esc_url($href),
                esc_attr($type),
                esc_attr($source),
                esc_html($label),
                esc_attr($style)
            );
        }

        private function legacy_button($type, $label, $href, $source, $legacy_class = 'vraag') {
            $type = sanitize_key($type);
            $source = sanitize_key($source);
            $legacy_class = sanitize_html_class($legacy_class);

            return sprintf(
                '<a href="%1$s" class="avd-cta-button %2$s" data-avd-cta="1" data-avd-cta-type="%3$s" data-avd-cta-source="%4$s" rel="nofollow">%5$s</a>',
                esc_url($href),
                esc_attr($legacy_class),
                esc_attr($type),
                esc_attr($source),
                esc_html($label)
            );
        }

        private function choice($type, $title, $text, $href, $source) {
            $type = sanitize_key($type);
            $source = sanitize_key($source);

            return sprintf(
                '<a class="avd-uber-choice avd-cta-button" href="%1$s" data-avd-cta="1" data-avd-cta-type="%2$s" data-avd-cta-source="%3$s" rel="nofollow"><strong>%4$s</strong><span>%5$s</span></a>',
                esc_url($href),
                esc_attr($type),
                esc_attr($source),
                esc_html($title),
                esc_html($text)
            );
        }

        private function whatsapp_url($text = '') {
            $text = $text ? $text : 'Hoi Alexander, ik heb een vraag via AlexandervanDijl.nl.';
            return 'https://wa.me/' . $this->whatsapp_number . '?text=' . rawurlencode($text);
        }

        private function paid_help_url($text = '') {
            $text = $text ? $text : 'Hoi Alexander, ik wil graag betaalde hulp aanvragen.';
            return $this->whatsapp_url($text);
        }

        private function page_subject($fallback = 'dit bedrijf') {
            $title = wp_strip_all_tags(get_the_title());
            if (!$title) {
                return $fallback;
            }

            $title = html_entity_decode($title, ENT_QUOTES, get_bloginfo('charset'));
            $parts = preg_split('/\s[-|–—]\s/u', $title);
            if (!empty($parts[0])) {
                $title = trim($parts[0]);
            }

            $replace = array(
                'Telefoon bellen?' => '',
                'Telefoon bellen' => '',
                'telefoon bellen?' => '',
                'telefoon bellen' => '',
                'Klantenservice bellen?' => '',
                'klantenservice bellen?' => '',
                'Klantenservice en contactinformatie' => '',
                'Telefoonnummers en contactinformatie' => '',
                'Pechhulp en contactinformatie' => '',
                'bellen?' => '',
                'Bellen?' => '',
                'bellen' => '',
                'Bellen' => '',
            );

            $title = str_replace(array_keys($replace), array_values($replace), $title);
            $title = trim(preg_replace('/\s+/', ' ', $title));

            return $title ? $title : $fallback;
        }

        public function track_event() {
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


        public function register_lead_post_type() {
            register_post_type(self::LEAD_POST_TYPE, array(
                'labels' => array(
                    'name' => 'Bedrijfsscan leads',
                    'singular_name' => 'Bedrijfsscan lead',
                    'menu_name' => 'Bedrijfsscan leads',
                    'add_new_item' => 'Nieuwe lead toevoegen',
                    'edit_item' => 'Lead bekijken',
                    'view_item' => 'Lead bekijken',
                    'search_items' => 'Leads zoeken',
                ),
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => 'avd-cta-insights',
                'capability_type' => 'post',
                'supports' => array('title', 'editor'),
                'menu_icon' => 'dashicons-businessperson',
            ));
        }

        public function shortcode_bedrijfsscan_form($atts = array()) {
            $result = filter_input(
    INPUT_GET,
    'avdctai_business_scan',
    FILTER_SANITIZE_FULL_SPECIAL_CHARS
);

$result = is_string($result) ? sanitize_key($result) : '';

$success = $result === 'bedankt';
$error = $result === 'fout';
            $action = esc_url(admin_url('admin-post.php'));
            $current = esc_url_raw($this->current_url());
            ob_start();
            ?>
            <div class="avd-scan-form-wrap">
                <?php if ($success) : ?>
                    <div class="avd-scan-message avd-scan-success"><strong>Bedankt!</strong> Je aanvraag is ontvangen. Ik neem zo snel mogelijk contact met je op.</div>
                <?php elseif ($error) : ?>
                    <div class="avd-scan-message avd-scan-error"><strong>Niet gelukt.</strong> Controleer de verplichte velden en probeer het opnieuw.</div>
                <?php endif; ?>
                <form class="avd-scan-form" method="post" action="<?php echo esc_url($action); ?>">
                    <input type="hidden" name="action" value="avdctai_business_scan_submit">
                    <input type="hidden" name="avdctai_return_url" value="<?php echo esc_attr($current); ?>">
                    <?php wp_nonce_field('avdctai_business_scan_submit', 'avdctai_business_scan_nonce'); ?>
                    <div class="avd-scan-hp" aria-hidden="true"><label>Website<input type="text" name="avdctai_website_check" tabindex="-1" autocomplete="off"></label></div>

                    <div class="avd-scan-grid">
                        <label>Naam *<input type="text" name="naam" required autocomplete="name"></label>
                        <label>Bedrijfsnaam *<input type="text" name="bedrijf" required autocomplete="organization"></label>
                        <label>E-mailadres *<input type="email" name="email" required autocomplete="email"></label>
                        <label>Telefoonnummer *<input type="tel" name="telefoon" required autocomplete="tel"></label>
                        <label class="avd-scan-full">Website<input type="url" name="website" placeholder="https://" autocomplete="url"></label>
                    </div>

                    <fieldset class="avd-scan-fieldset">
                        <legend>Wat wil je verbeteren? *</legend>
                        <label><input type="checkbox" name="verbeteren[]" value="Betere bereikbaarheid"> Betere bereikbaarheid</label>
                        <label><input type="checkbox" name="verbeteren[]" value="Meer leads"> Meer leads</label>
                        <label><input type="checkbox" name="verbeteren[]" value="AI inzetten"> AI inzetten</label>
                        <label><input type="checkbox" name="verbeteren[]" value="Minder administratie"> Minder administratie</label>
                        <label><input type="checkbox" name="verbeteren[]" value="Minder telefoondruk"> Minder telefoondruk</label>
                        <label><input type="checkbox" name="verbeteren[]" value="Kosten besparen"> Kosten besparen</label>
                    </fieldset>

                    <label class="avd-scan-full">Korte toelichting<textarea name="toelichting" rows="5" placeholder="Waar loop je nu tegenaan?"></textarea></label>

                    <button class="avd-scan-submit avd-cta-button" type="submit" data-avd-cta="1" data-avd-cta-type="bedrijfsscan" data-avd-cta-source="bedrijfsscan_form">Vraag gratis bedrijfsscan aan</button>
                    <p class="avd-scan-privacy">Geen spam. Geen verplichtingen. Je aanvraag wordt opgeslagen in WordPress onder Bedrijfsscan leads.</p>
                </form>
            </div>
            <?php
            return trim(ob_get_clean());
        }

        public function handle_bedrijfsscan_submit() {
            $return = isset($_POST['avdctai_return_url']) ? esc_url_raw(wp_unslash($_POST['avdctai_return_url'])) : home_url('/gratis-bedrijfsscan/');
            $return = remove_query_arg('avdctai_business_scan', $return);

            if (!isset($_POST['avdctai_business_scan_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['avdctai_business_scan_nonce'])), 'avdctai_business_scan_submit')) {
                wp_safe_redirect(add_query_arg('avdctai_business_scan', 'fout', $return) . '#aanvragen'); exit;
            }
            if (!empty($_POST['avdctai_website_check'])) {
                wp_safe_redirect(add_query_arg('avdctai_business_scan', 'bedankt', $return) . '#aanvragen'); exit;
            }

            $naam = isset($_POST['naam']) ? sanitize_text_field(wp_unslash($_POST['naam'])) : '';
            $bedrijf = isset($_POST['bedrijf']) ? sanitize_text_field(wp_unslash($_POST['bedrijf'])) : '';
            $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
            $telefoon = isset($_POST['telefoon']) ? sanitize_text_field(wp_unslash($_POST['telefoon'])) : '';
            $website = isset($_POST['website']) ? esc_url_raw(wp_unslash($_POST['website'])) : '';
            $toelichting = isset($_POST['toelichting']) ? sanitize_textarea_field(wp_unslash($_POST['toelichting'])) : '';
            $verbeteren_input = filter_input(
    INPUT_POST,
    'verbeteren',
    FILTER_DEFAULT,
    FILTER_REQUIRE_ARRAY
);

$verbeteren = array();

if (is_array($verbeteren_input)) {
    foreach ($verbeteren_input as $item) {
        if (!is_scalar($item)) {
            continue;
        }

        $clean_item = sanitize_text_field((string) $item);

        if ($clean_item !== '') {
            $verbeteren[] = $clean_item;
        }
    }
}
            if (!$naam || !$bedrijf || !$email || !$telefoon || empty($verbeteren)) {
                wp_safe_redirect(add_query_arg('avdctai_business_scan', 'fout', $return) . '#aanvragen'); exit;
            }

            $content = "Naam: {$naam}\nBedrijf: {$bedrijf}\nE-mail: {$email}\nTelefoon: {$telefoon}\nWebsite: {$website}\nVerbeteren: " . implode(', ', $verbeteren) . "\n\nToelichting:\n{$toelichting}";
            $lead_id = wp_insert_post(array(
                'post_type' => self::LEAD_POST_TYPE,
                'post_status' => 'publish',
                'post_title' => $bedrijf . ' - ' . $naam,
                'post_content' => $content,
                'meta_input' => array(
                    '_avdctai_lead_status' => 'Nieuw',
                    '_avdctai_naam' => $naam,
                    '_avdctai_bedrijf' => $bedrijf,
                    '_avdctai_email' => $email,
                    '_avdctai_telefoon' => $telefoon,
                    '_avdctai_website' => $website,
                    '_avdctai_verbeteren' => implode(', ', $verbeteren),
                    '_avdctai_toelichting' => $toelichting,
                    '_avdctai_page_url' => $return,
                    '_avdctai_ip_hash' => $this->ip_hash(),
                    '_avdctai_user_agent_hash' => $this->ua_hash(),
                ),
            ));

            if ($lead_id && !is_wp_error($lead_id)) {
                $this->store_event(array(
                    'time' => current_time('mysql'),
                    'timestamp' => time(),
                    'type' => 'bedrijfsscan',
                    'source' => 'bedrijfsscan_form_submit',
                    'context' => 'landing',
                    'device' => 'unknown',
                    'page_url' => $return,
                    'target_url' => '',
                    'label' => $bedrijf,
                    'session_id' => '',
                    'ip_hash' => $this->ip_hash(),
                    'user_agent_hash' => $this->ua_hash(),
                ));
                $to = get_option('admin_email');
                $subject = 'Nieuwe gratis bedrijfsscan aanvraag: ' . $bedrijf;
                $message = $content . "\n\nBekijk in WordPress: " . admin_url('post.php?post=' . $lead_id . '&action=edit');
                wp_mail($to, $subject, $message, array('Reply-To: ' . $naam . ' <' . $email . '>'));
                wp_safe_redirect(add_query_arg('avdctai_business_scan', 'bedankt', $return) . '#aanvragen'); exit;
            }

            wp_safe_redirect(add_query_arg('avdctai_business_scan', 'fout', $return) . '#aanvragen'); exit;
        }

        public function lead_columns($columns) {
            return array(
                'cb' => isset($columns['cb']) ? $columns['cb'] : '<input type="checkbox" />',
                'title' => 'Lead',
                'status' => 'Status',
                'email' => 'E-mail',
                'telefoon' => 'Telefoon',
                'verbeteren' => 'Interesse',
                'date' => 'Datum',
            );
        }

        public function lead_column_content($column, $post_id) {
            if ($column === 'status') { echo esc_html(get_post_meta($post_id, '_avdctai_lead_status', true) ?: 'Nieuw'); }
            if ($column === 'email') {
                $email = sanitize_email(get_post_meta($post_id, '_avdctai_email', true));

                if ($email) {
                    echo wp_kses_post(
                        sprintf(
                            '<a href="%1$s">%2$s</a>',
                            esc_url('mailto:' . $email),
                            esc_html($email)
                        )
                    );
                }
            }
            if ($column === 'telefoon') { echo esc_html(get_post_meta($post_id, '_avdctai_telefoon', true)); }
            if ($column === 'verbeteren') { echo esc_html(get_post_meta($post_id, '_avdctai_verbeteren', true)); }
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
                <h1>AVD AI Analyse</h1>
                <p><strong>Versie <?php echo esc_html(self::VERSION); ?>:</strong> AI Briefing met websitecijfers, nieuwe leads, open leads en acties voor vandaag.</p>
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

                if (in_array($type, array('aanvraag','application','claim','form_submit','bedrijfsscan'), true)) { $applications++; }
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
                strpos($type, 'bel') !== false ||
                strpos($type, 'whatsapp') !== false ||
                strpos($type, 'mail') !== false ||
                strpos($type, 'lead') !== false ||
                strpos($type, 'form_submit') !== false ||
                strpos($type, 'bedrijfsscan') !== false ||
                strpos($type, 'claim') !== false ||
                strpos($type, 'aanvraag') !== false
            );
        }

        private function pct_change($new, $old) {
            if (!$old && !$new) { return '0%'; }
            if (!$old) { return '+100%'; }
            $pct = round((($new - $old) / $old) * 100, 1);
            return ($pct > 0 ? '+' : '') . $pct . '%';
        }

        private function get_lead_briefing_data() {
            $all = get_posts(array(
                'post_type' => self::LEAD_POST_TYPE,
                'post_status' => array('publish', 'draft', 'pending', 'private'),
                'numberposts' => 50,
                'orderby' => 'date',
                'order' => 'DESC',
            ));

            $now = current_time('timestamp');
            $new_leads = array();
            $open_leads = array();
            $status_counts = array();
            $expected_total = 0;
            $won_total = 0;

            foreach ($all as $post) {
                $status = get_post_meta($post->ID, '_avdctai_lead_status', true);
                if (!$status) { $status = 'Nieuw'; }
                $status_counts[$status] = isset($status_counts[$status]) ? $status_counts[$status] + 1 : 1;

                $expected = (float) str_replace(',', '.', preg_replace('/[^0-9,\.]/', '', (string) get_post_meta($post->ID, '_avdctai_expected_revenue', true)));
                $won = (float) str_replace(',', '.', preg_replace('/[^0-9,\.]/', '', (string) get_post_meta($post->ID, '_avdctai_actual_revenue', true)));
                $expected_total += $expected;
                $won_total += $won;

                $item = array(
                    'id' => $post->ID,
                    'date' => get_date_from_gmt(get_gmt_from_date($post->post_date), 'Y-m-d H:i'),
                    'title' => get_the_title($post),
                    'status' => $status,
                    'naam' => get_post_meta($post->ID, '_avdctai_naam', true),
                    'bedrijf' => get_post_meta($post->ID, '_avdctai_bedrijf', true),
                    'email' => get_post_meta($post->ID, '_avdctai_email', true),
                    'telefoon' => get_post_meta($post->ID, '_avdctai_telefoon', true),
                    'website' => get_post_meta($post->ID, '_avdctai_website', true),
                    'interesse' => get_post_meta($post->ID, '_avdctai_verbeteren', true),
                    'pagina' => get_post_meta($post->ID, '_avdctai_page_url', true),
                    'expected' => $expected,
                    'won' => $won,
                    'edit_url' => admin_url('post.php?post=' . $post->ID . '&action=edit'),
                );

                $created = strtotime($post->post_date);
                if ($created && ($now - $created) <= DAY_IN_SECONDS) {
                    $new_leads[] = $item;
                }

                if (!in_array(strtolower($status), array('gewonnen', 'betaald', 'verloren', 'afgewezen'), true)) {
                    $open_leads[] = $item;
                }
            }

            return array(
                'total' => count($all),
                'new' => $new_leads,
                'open' => $open_leads,
                'status_counts' => $status_counts,
                'expected_total' => $expected_total,
                'won_total' => $won_total,
            );
        }

        private function build_chatgpt_export($p) {
            $t = $p['today']; $y = $p['yesterday']; $w = $p['week']; $pw = $p['previous_week'];
            $leads = $this->get_lead_briefing_data();

            $out = "=== AVD AI BRIEFING ===\n";
            $out .= "Versie: " . self::VERSION . "\n";
            $out .= "Site: AlexandervanDijl.nl\n";
            $out .= "Doel: break-even voor 31 augustus. Prioriteer acties met snelste kans op omzet of leads.\n\n";

            $out .= "=== LEADS EN OMZET ===\n";
            $out .= "Nieuwe leads laatste 24 uur: " . count($leads['new']) . "\n";
            $out .= "Open leads: " . count($leads['open']) . "\n";
            $out .= "Totaal leads zichtbaar: " . $leads['total'] . "\n";
            $out .= "Verwachte omzet: €" . number_format($leads['expected_total'], 2, ',', '.') . "\n";
            $out .= "Werkelijke/gewonnen omzet: €" . number_format($leads['won_total'], 2, ',', '.') . "\n";
            if (!empty($leads['status_counts'])) {
                $out .= "Leadstatussen:\n";
                foreach ($leads['status_counts'] as $status => $count) {
                    $out .= "- {$status}: {$count}\n";
                }
            }

            $out .= "\n=== NIEUWE LEADS ===\n";
            if (empty($leads['new'])) {
                $out .= "Geen nieuwe leads in de laatste 24 uur.\n";
            } else {
                foreach (array_slice($leads['new'], 0, 10) as $lead) {
                    $out .= "- {$lead['bedrijf']} / {$lead['naam']} | Status: {$lead['status']} | Interesse: {$lead['interesse']} | Tel: {$lead['telefoon']} | E-mail: {$lead['email']} | Website: {$lead['website']} | Pagina: {$lead['pagina']} | Bewerken: {$lead['edit_url']}\n";
                }
            }

            $out .= "\n=== OPEN LEADS ===\n";
            if (empty($leads['open'])) {
                $out .= "Geen open leads.\n";
            } else {
                foreach (array_slice($leads['open'], 0, 10) as $lead) {
                    $out .= "- {$lead['bedrijf']} / {$lead['naam']} | Status: {$lead['status']} | Interesse: {$lead['interesse']} | Verwacht: €" . number_format($lead['expected'], 2, ',', '.') . " | Actie: opvolgen | Bewerken: {$lead['edit_url']}\n";
                }
            }

            $out .= "\n=== WEBSITE VANDAAG ===\n";
            foreach (array('views','sessions','engaged_sessions','cta','conversion_views','conversion_sessions','popup_events','applications','bot_score') as $k) {
                $out .= ucfirst(str_replace('_',' ', $k)) . ': ' . $t[$k] . ' (' . $this->pct_change($t[$k], isset($y[$k]) ? $y[$k] : 0) . ")\n";
            }

            $out .= "\n=== WEBSITE DEZE WEEK ===\n";
            foreach (array('views','sessions','engaged_sessions','cta','conversion_views','conversion_sessions','popup_events','applications') as $k) {
                $out .= ucfirst(str_replace('_',' ', $k)) . ': ' . $w[$k] . ' (' . $this->pct_change($w[$k], isset($pw[$k]) ? $pw[$k] : 0) . ")\n";
            }

            $out .= "\n=== TOP PAGINA'S ===\n";
            foreach ($p['top_pages'] as $page) {
                $out .= "- {$page['page']}: {$page['views']} views, {$page['cta']} CTA, {$page['conversion']}%, score {$page['score']}\n";
            }

            $out .= "\n=== PAGINA'S MET HOOGSTE PRIORITEIT ===\n";
            foreach ($p['needs_attention'] as $page) {
                $out .= "- {$page['page']}: {$page['views']} views, {$page['cta']} CTA, {$page['conversion']}%, AI Priority Score {$page['score']}\n";
            }

            $out .= "\n=== GEVRAAGD AAN CHATGPT ===\n";
            $out .= "1. Welke leads moet ik als eerste opvolgen?\n";
            $out .= "2. Welke pagina moet vandaag als eerste worden verbeterd?\n";
            $out .= "3. Welke CTA of funnel heeft de snelste kans op omzet?\n";
            $out .= "4. Geef een concreet actieplan voor vandaag.\n";
            $out .= "=== EINDE AVD AI BRIEFING ===\n";

            return $out;
        }

        private function popup_allowed() {
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
