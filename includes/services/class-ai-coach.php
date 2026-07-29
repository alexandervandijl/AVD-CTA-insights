<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_AI_Coach {

    private array $payload;

    public function __construct(array $payload) {
        $this->payload = $payload;
    }

    public function recommendation(): array {
        $pages = $this->payload['needs_attention'] ?? array();
        $visitor = $this->payload['visitor_intelligence'] ?? array();

        if (!is_array($pages)) {
            $pages = array();
        }

        if (!is_array($visitor)) {
            $visitor = array();
        }

        $page_recommendation = $this->page_recommendation($pages);
        $visitor_actions = $this->visitor_actions($visitor);

        if (empty($page_recommendation) && empty($visitor_actions)) {
            return array(
                'title' => 'Nog geen harde aanbeveling',
                'message' => 'Er is nog te weinig data om één duidelijke verbeteractie te kiezen. Laat de plugin eerst meer bezoekers en CTA-events verzamelen.',
                'actions' => array(
                    'Open een paar belangrijke pagina’s op desktop en mobiel.',
                    'Klik zelf één of twee CTA’s aan om te controleren of tracking goed binnenkomt.',
                    'Controleer daarna opnieuw de AI Coach en Visitor Intelligence.',
                ),
                'score' => 0,
                'impact' => 'Laag',
                'stars' => '☆☆☆☆☆',
                'views' => 0,
                'cta' => 0,
                'conversion' => 0,
                'page' => '',
            );
        }

        if (!empty($page_recommendation)) {
            $page_recommendation['actions'] = array_values(
                array_unique(
                    array_merge(
                        $page_recommendation['actions'],
                        $visitor_actions
                    )
                )
            );

            return $page_recommendation;
        }

        return array(
            'title' => 'Optimaliseer op bezoekersgedrag',
            'message' => 'Visitor Intelligence ziet signalen waarmee je de website direct beter kunt laten aansluiten op echte bezoekers.',
            'actions' => array_values(array_unique($visitor_actions)),
            'score' => 40,
            'impact' => 'Gemiddeld',
            'stars' => '★★★☆☆',
            'views' => (int) ($visitor['summary']['views'] ?? 0),
            'cta' => (int) ($visitor['summary']['cta_clicks'] ?? 0),
            'conversion' => (float) ($visitor['summary']['cta_rate'] ?? 0),
            'page' => '',
        );
    }

    private function page_recommendation(array $pages): array {
        if (empty($pages)) {
            return array();
        }

        usort(
            $pages,
            static function ($a, $b): int {
                return AVDCTAI_Priority::score($b)
                    <=> AVDCTAI_Priority::score($a);
            }
        );

        $page = reset($pages);

        if (!is_array($page)) {
            return array();
        }

        $score = AVDCTAI_Priority::score($page);

        return array(
            'page' => $page['page'] ?? '',
            'score' => $score,
            'views' => (int) ($page['views'] ?? 0),
            'cta' => (int) ($page['cta'] ?? 0),
            'conversion' => (float) ($page['conversion'] ?? 0),
            'title' => 'Verbeter vandaag: ' . ($page['page'] ?? 'onbekende pagina'),
            'message' => $this->message($page, $score),
            'impact' => $this->impact_label($score),
            'stars' => $this->stars($score),
            'actions' => $this->page_actions($page),
        );
    }

    private function message(array $page, int $score): string {
        $views = (int) ($page['views'] ?? 0);
        $cta = (int) ($page['cta'] ?? 0);
        $conversion = (float) ($page['conversion'] ?? 0);

        if ($views >= 50 && $cta === 0) {
            return 'Deze pagina krijgt al bezoekers, maar levert nog geen CTA-kliks op. Dit is waarschijnlijk de snelste winst.';
        }

        if ($views >= 25 && $conversion < 2) {
            return 'Deze pagina heeft verkeer, maar de conversie blijft laag. Een duidelijkere CTA kan hier direct verschil maken.';
        }

        if ($score >= 70) {
            return 'Deze pagina heeft een hoge verbeterkans. Begin hier als je vandaag maar één pagina aanpakt.';
        }

        return 'Deze pagina heeft verbeterpotentie. Combineer de paginadata met Visitor Intelligence om de CTA beter te plaatsen.';
    }

    private function page_actions(array $page): array {
        $views = (int) ($page['views'] ?? 0);
        $cta = (int) ($page['cta'] ?? 0);
        $conversion = (float) ($page['conversion'] ?? 0);

        $actions = array();

        if ($cta === 0) {
            $actions[] = 'Plaats een duidelijke CTA boven de eerste alinea.';
            $actions[] = 'Voeg een WhatsApp-knop of belknop toe bovenaan de pagina.';
        }

        if ($conversion < 2) {
            $actions[] = 'Herhaal de belangrijkste CTA halverwege de pagina.';
            $actions[] = 'Maak de eerste alinea korter en stuur sneller naar actie.';
        }

        if ($views >= 25) {
            $actions[] = 'Controleer of de CTA direct zichtbaar is op mobiel.';
        }

        $actions[] = 'Sluit de pagina af met één duidelijke vervolgstap.';

        return array_values(array_unique($actions));
    }

    private function visitor_actions(array $visitor): array {
        $actions = array();

        $summary = isset($visitor['summary']) && is_array($visitor['summary'])
            ? $visitor['summary']
            : array();

        $devices = isset($visitor['devices']) && is_array($visitor['devices'])
            ? $visitor['devices']
            : array();

        $languages = isset($visitor['languages']) && is_array($visitor['languages'])
            ? $visitor['languages']
            : array();

        $referrers = isset($visitor['referrers']) && is_array($visitor['referrers'])
            ? $visitor['referrers']
            : array();

        $screens = isset($visitor['screens']) && is_array($visitor['screens'])
            ? $visitor['screens']
            : array();

        $views = (int) ($summary['views'] ?? 0);
        $cta_clicks = (int) ($summary['cta_clicks'] ?? 0);
        $cta_rate = (float) ($summary['cta_rate'] ?? 0);

        if ($views >= 20 && $cta_clicks === 0) {
            $actions[] = 'Er zijn bezoekers zonder CTA-kliks. Maak de bovenste CTA directer en visueel sterker.';
        }

        if ($views >= 20 && $cta_clicks > 0 && $cta_rate < 5) {
            $actions[] = 'De CTA-ratio is lager dan 5%. Test een actievere tekst zoals “Bel direct gratis door” of “Vraag gratis hulp aan”.';
        }

        if ($this->has_mobile_signal($devices, $screens)) {
            $actions[] = 'Er is mobiel verkeer zichtbaar. Zet de belangrijkste knop boven de vouw en maak deze groot genoeg voor duimgebruik.';
        }

        if ($this->has_foreign_language($languages)) {
            $actions[] = 'Er is meertalig bezoek zichtbaar. Test een korte Engelse CTA of automatische taalvariant.';
        }

        if (!empty($referrers)) {
            $actions[] = 'Er komt verkeer via externe bronnen binnen. Maak voor sterke referrers aparte CTA-teksten of landingspagina’s.';
        }

        return array_values(array_unique($actions));
    }

    private function has_mobile_signal(array $devices, array $screens): bool {
        foreach ($devices as $device) {
            $value = strtolower((string) ($device['value'] ?? ''));

            if (
                $value !== '' &&
                (
                    strpos($value, 'mobiel') !== false ||
                    strpos($value, 'mobile') !== false ||
                    strpos($value, 'phone') !== false
                )
            ) {
                return true;
            }
        }

        foreach ($screens as $screen) {
            $value = (string) ($screen['value'] ?? '');

            if (preg_match('/^(\d{2,4})x(\d{2,4})$/', $value, $matches)) {
                $width = (int) $matches[1];

                if ($width > 0 && $width < 768) {
                    return true;
                }
            }
        }

        return false;
    }

    private function has_foreign_language(array $languages): bool {
        foreach ($languages as $language) {
            $value = strtolower((string) ($language['value'] ?? ''));

            if ($value !== '' && strpos($value, 'nl') !== 0) {
                return true;
            }
        }

        return false;
    }

    private function impact_label(int $score): string {
        if ($score >= 90) {
            return 'Zeer hoog';
        }

        if ($score >= 70) {
            return 'Hoog';
        }

        if ($score >= 40) {
            return 'Gemiddeld';
        }

        return 'Laag';
    }

    private function stars(int $score): string {
        if ($score >= 90) {
            return '★★★★★';
        }

        if ($score >= 70) {
            return '★★★★☆';
        }

        if ($score >= 40) {
            return '★★★☆☆';
        }

        if ($score > 0) {
            return '★★☆☆☆';
        }

        return '☆☆☆☆☆';
    }
}
