<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Page_Benchmarks {

    public static function build(array $pages): array {
        $groups = array();

        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }

            $page_name = isset($page['page'])
                ? sanitize_text_field((string) $page['page'])
                : '';

            if ($page_name === '') {
                continue;
            }

            $group = self::detect_group($page_name);

            if (!isset($groups[$group])) {
                $groups[$group] = array(
                    'label' => self::group_label($group),
                    'pages' => array(),
                    'views' => 0,
                    'cta' => 0,
                );
            }

            $views = isset($page['views'])
                ? max(0, (int) $page['views'])
                : 0;

            $cta = isset($page['cta'])
                ? max(0, (int) $page['cta'])
                : 0;

            $conversion = $views > 0
                ? round(($cta / $views) * 100, 2)
                : 0;

            $groups[$group]['pages'][] = array(
                'page' => $page_name,
                'views' => $views,
                'cta' => $cta,
                'conversion' => $conversion,
            );

            $groups[$group]['views'] += $views;
            $groups[$group]['cta'] += $cta;
        }

        foreach ($groups as &$group) {
            $group['conversion'] = $group['views'] > 0
                ? round(($group['cta'] / $group['views']) * 100, 2)
                : 0;

            $group['page_count'] = count($group['pages']);

            foreach ($group['pages'] as &$page) {
                $page['difference'] = round(
                    $page['conversion'] - $group['conversion'],
                    2
                );

                $page['performance'] = self::performance_label(
                    $page['conversion'],
                    $group['conversion'],
                    $page['views']
                );
            }

            unset($page);

            usort(
                $group['pages'],
                static function (array $a, array $b): int {
                    if ($a['difference'] === $b['difference']) {
                        return $b['views'] <=> $a['views'];
                    }

                    return $a['difference'] <=> $b['difference'];
                }
            );
        }

        unset($group);

        uasort(
            $groups,
            static function (array $a, array $b): int {
                return $b['views'] <=> $a['views'];
            }
        );

        return $groups;
    }

    public static function detect_group(string $page): string {
        $page = strtolower(trim($page));

        if (
            $page === '' ||
            $page === 'homepage' ||
            $page === '/' ||
            $page === 'home'
        ) {
            return 'homepage';
        }

        if (
            strpos($page, 'tag/') !== false ||
            strpos($page, 'category/') !== false
        ) {
            return 'archive';
        }

        if (
            strpos($page, '-bellen') !== false ||
            strpos($page, 'bellen') !== false ||
            strpos($page, 'telefoon') !== false ||
            strpos($page, 'klantenservice') !== false
        ) {
            return 'phone';
        }

        if (
            strpos($page, 'avd-cta-insights') !== false ||
            strpos($page, 'wordpress') !== false ||
            strpos($page, 'plugin') !== false ||
            strpos($page, 'ai-conversion') !== false
        ) {
            return 'plugin';
        }

        if (
            strpos($page, 'bedrijfsscan') !== false ||
            strpos($page, 'zakelijk') !== false ||
            strpos($page, 'diensten') !== false ||
            strpos($page, 'telecom') !== false ||
            strpos($page, 'voip') !== false ||
            strpos($page, 'internet') !== false ||
            strpos($page, 'ai-assistent') !== false
        ) {
            return 'business';
        }

        if (
            strpos($page, 'contact') !== false ||
            strpos($page, 'bereikbaar') !== false ||
            strpos($page, 'werkt-niet') !== false ||
            strpos($page, 'woningnet') !== false ||
            strpos($page, 'gemeente') !== false
        ) {
            return 'contact_problem';
        }

        return 'content';
    }

    public static function group_label(string $group): string {
        $labels = array(
            'homepage' => 'Homepage',
            'archive' => 'Categorie- en tagpagina’s',
            'phone' => 'Bel- en contactpagina’s',
            'plugin' => 'Plugin- en WordPresspagina’s',
            'business' => 'Zakelijke pagina’s',
            'contact_problem' => 'Bereikbaarheids- en probleemoplossingspagina’s',
            'content' => 'Informatieve pagina’s',
        );

        return isset($labels[$group])
            ? $labels[$group]
            : 'Overige pagina’s';
    }

    private static function performance_label(
        float $page_conversion,
        float $group_conversion,
        int $views
    ): string {
        if ($views < 3) {
            return 'Te weinig data';
        }

        if ($group_conversion <= 0) {
            return $page_conversion > 0
                ? 'Boven gemiddeld'
                : 'Nog geen conversiedata';
        }

        $ratio = $page_conversion / $group_conversion;

        if ($ratio >= 1.25) {
            return 'Boven gemiddeld';
        }

        if ($ratio <= 0.75) {
            return 'Onder gemiddeld';
        }

        return 'Rond gemiddeld';
    }
}
