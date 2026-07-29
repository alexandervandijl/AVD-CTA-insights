 <?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Priority {

    public static function score(array $page): int {
        $views = (int) ($page['views'] ?? 0);
        $cta   = (int) ($page['cta'] ?? 0);

        if ($views === 0) {
            return 0;
        }

        $conversion = ($cta / $views) * 100;

        $score = 0;

        $score += min(60, $views / 2);
        $score += max(0, 40 - ($conversion * 4));

        return max(0, min(100, (int) round($score)));
    }

    public static function label(int $score): string {
        if ($score >= 90) {
            return '🔴 Zeer hoge prioriteit';
        }

        if ($score >= 70) {
            return '🟠 Hoge prioriteit';
        }

        if ($score >= 40) {
            return '🟡 Gemiddeld';
        }

        return '🟢 Laag';
    }
}
