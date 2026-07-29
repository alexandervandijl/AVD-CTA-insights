<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Widget_AI_Advice extends AVDCTAI_Widget {

    public function render(array $payload): void {
        $advice = $this->build_advice($payload);
        ?>
        <div class="avd-section">
            <h2>🤖 AI Advies</h2>
            <p>Automatische verbeterpunten op basis van je huidige statistieken.</p>

            <?php if (!empty($advice)) : ?>
                <ol>
                    <?php foreach ($advice as $item) : ?>
                        <li>
                            <strong><?php echo esc_html($item['title']); ?></strong><br>
                            <?php echo esc_html($item['text']); ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else : ?>
                <p>Er is nog te weinig data voor concrete adviezen. Laat de site eerst wat bezoekers verzamelen.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function build_advice(array $payload): array {
        $advice = array();

        $today = isset($payload['today']) && is_array($payload['today']) ? $payload['today'] : array();
        $week = isset($payload['week']) && is_array($payload['week']) ? $payload['week'] : array();
        $pages = isset($payload['needs_attention']) && is_array($payload['needs_attention']) ? $payload['needs_attention'] : array();

        $today_views = isset($today['views']) ? (int) $today['views'] : 0;
        $today_cta = isset($today['cta']) ? (int) $today['cta'] : 0;
        $today_conversion = isset($today['conversion_views']) ? (float) $today['conversion_views'] : 0;

        $week_views = isset($week['views']) ? (int) $week['views'] : 0;
        $week_cta = isset($week['cta']) ? (int) $week['cta'] : 0;

        if ($today_views >= 20 && $today_cta === 0) {
            $advice[] = array(
                'title' => 'Vandaag wel bezoekers, maar geen CTA-kliks',
                'text' => 'Controleer of de belangrijkste knoppen direct zichtbaar zijn op mobiele schermen. Begin met homepage en de best bezochte bedrijfspagina.',
            );
        }

        if ($today_views >= 20 && $today_conversion > 0 && $today_conversion < 5) {
            $advice[] = array(
                'title' => 'Conversie vandaag is laag',
                'text' => 'Test een duidelijkere CTA-tekst bovenaan de pagina, bijvoorbeeld “Bel direct gratis door” of “Stel je vraag via WhatsApp”.',
            );
        }

        if ($week_views >= 100 && $week_cta < 5) {
            $advice[] = array(
                'title' => 'Deze week veel verkeer, weinig actie',
                'text' => 'Maak de bovenste CTA prominenter en voeg een tweede CTA toe na de eerste alinea.',
            );
        }

        if (!empty($pages)) {
            $page = $pages[0];
            $page_name = isset($page['page']) ? $page['page'] : 'een belangrijke pagina';
            $views = isset($page['views']) ? (int) $page['views'] : 0;
            $cta = isset($page['cta']) ? (int) $page['cta'] : 0;
            $conversion = isset($page['conversion']) ? (float) $page['conversion'] : 0;

            $advice[] = array(
                'title' => 'Verbeter eerst: ' . $page_name,
                'text' => $page_name . ' heeft ' . $views . ' views, ' . $cta . ' CTA-kliks en ' . $conversion . '% conversie. Dit is waarschijnlijk de snelste verbeterkans.',
            );
        }

        if (empty($advice) && $today_cta > 0) {
            $advice[] = array(
                'title' => 'Er is vandaag al interactie',
                'text' => 'Bekijk welke pagina’s CTA-kliks krijgen en hergebruik die opbouw op pagina’s met minder conversie.',
            );
        }

        return array_slice($advice, 0, 3);
    }
}
