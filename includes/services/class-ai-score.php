<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_AI_Score {

    public static function calculate(array $page): int {

        $views = (int) ($page['views'] ?? 0);
        $cta = (int) ($page['cta'] ?? 0);
        $conversion = (float) ($page['conversion'] ?? 0);

        $score = 0;

        // Veel bezoekers = belangrijk.
        $score += min(40, $views);

        // CTA's leveren punten op.
        $score += min(30, $cta * 5);

        // Conversie.
        $score += min(30, (int) round($conversion * 5));

        return max(0, min(100, $score));
    }

    public static function stars(int $score): string {

        if ($score >= 90) {
            return '★★★★★';
        }

        if ($score >= 75) {
            return '★★★★☆';
        }

        if ($score >= 60) {
            return '★★★☆☆';
        }

        if ($score >= 40) {
            return '★★☆☆☆';
        }

        return '★☆☆☆☆';
    }

    public static function label(int $score): string {

        if ($score >= 90) {
            return 'Uitstekend';
        }

        if ($score >= 75) {
            return 'Goed';
        }

        if ($score >= 60) {
            return 'Redelijk';
        }

        if ($score >= 40) {
            return 'Verbeteren';
        }

        return 'Kritiek';
    }
}
