<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Template_Manager {

    private const OPTION_TEMPLATES = 'avdctai_templates';
    private const LEGACY_OPTION_TEMPLATES = 'avd_uber_cta_templates';

    public static function get_templates(): array {
        $saved = get_option(self::OPTION_TEMPLATES, null);

        if (!is_array($saved)) {
            $legacy = get_option(self::LEGACY_OPTION_TEMPLATES, array());

            if (is_array($legacy) && !empty($legacy)) {
                $saved = $legacy;
                update_option(self::OPTION_TEMPLATES, $saved, false);
            } else {
                $saved = array();
            }
        }

        return self::merge_templates($saved);
    }

    public static function get_template(string $intent): array {
        $intent = sanitize_key($intent);
        $templates = self::get_templates();

        if ($intent === '' || !isset($templates[$intent]) || !is_array($templates[$intent])) {
            return self::default_template();
        }

        return wp_parse_args($templates[$intent], self::default_template());
    }

    public static function save_templates(array $input): void {
        $clean = array();

        foreach (self::defaults() as $intent => $default) {
            $intent_input = isset($input[$intent]) && is_array($input[$intent])
                ? $input[$intent]
                : array();

            $clean[$intent] = array(
                'title' => isset($intent_input['title'])
                    ? sanitize_text_field(wp_unslash((string) $intent_input['title']))
                    : $default['title'],
                'text' => isset($intent_input['text'])
                    ? sanitize_textarea_field(wp_unslash((string) $intent_input['text']))
                    : $default['text'],
                'button' => isset($intent_input['button'])
                    ? sanitize_text_field(wp_unslash((string) $intent_input['button']))
                    : $default['button'],
                'url' => isset($intent_input['url'])
                    ? esc_url_raw(wp_unslash((string) $intent_input['url']))
                    : $default['url'],
                'enabled' => !empty($intent_input['enabled']) ? 1 : 0,
            );
        }

        update_option(self::OPTION_TEMPLATES, $clean, false);
    }

    public static function defaults(): array {
        return array(
            'homepage_intent' => self::template(
                'Wat wil je dat bezoekers hier doen?',
                'Kies één duidelijke vervolgstap die past bij het doel van deze pagina.',
                'Bekijk vervolgstap'
            ),
            'phone_intent' => self::template(
                'Maak contact opnemen eenvoudiger',
                'Bied bezoekers een duidelijke bel-, chat- of contactoptie.',
                'Neem contact op'
            ),
            'contact_problem_intent' => self::template(
                'Help bezoekers verder',
                'Bied een alternatieve route wanneer de huidige contactstap niet duidelijk genoeg is.',
                'Bekijk contactopties'
            ),
            'business_service_intent' => self::template(
                'Maak de volgende stap duidelijk',
                'Gebruik een concrete actie zoals een aanvraag, offerte, afspraak of kennismaking.',
                'Bekijk mogelijkheden'
            ),
            'plugin_intent' => self::template(
                'Maak interesse meetbaar',
                'Koppel deze pagina aan een concrete vervolgstap die je kunt meten.',
                'Ga verder'
            ),
            'pricing_intent' => self::template(
                'Verlaag twijfel rond de volgende stap',
                'Geef bezoekers een laagdrempelige manier om vragen te stellen voordat ze beslissen.',
                'Stel een vraag'
            ),
            'category_or_tag_intent' => self::template(
                'Wijs bezoekers de juiste route',
                'Gebruik deze pagina om bezoekers naar de meest relevante vervolgstap te sturen.',
                'Bekijk vervolgstap'
            ),
            'content_intent' => self::template(
                'Voeg een logische vervolgstap toe',
                'Laat de CTA aansluiten op het onderwerp en de intentie van de pagina.',
                'Bekijk mogelijkheden'
            ),
        );
    }

    public static function default_template(): array {
        return array(
            'title' => '',
            'text' => '',
            'button' => '',
            'url' => '',
            'enabled' => 0,
        );
    }

    public static function labels(): array {
        return array(
            'homepage_intent' => 'Homepage / algemene instap',
            'phone_intent' => 'Contactintentie',
            'contact_problem_intent' => 'Contactprobleem',
            'business_service_intent' => 'Dienst- of aanvraagintentie',
            'plugin_intent' => 'Product- of toolinteresse',
            'pricing_intent' => 'Prijsinteresse',
            'category_or_tag_intent' => 'Routepagina',
            'content_intent' => 'Informatieve intentie',
        );
    }

    private static function template(string $title, string $text, string $button): array {
        return array(
            'title' => $title,
            'text' => $text,
            'button' => $button,
            'url' => '',
            'enabled' => 0,
        );
    }

    private static function merge_templates(array $saved): array {
        $templates = array();

        foreach (self::defaults() as $intent => $default) {
            $value = isset($saved[$intent]) && is_array($saved[$intent])
                ? $saved[$intent]
                : array();

            $templates[$intent] = wp_parse_args($value, $default);
        }

        return $templates;
    }
}
