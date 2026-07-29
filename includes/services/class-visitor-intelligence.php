<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Visitor_Intelligence {

    const MAX_EVENTS_PER_SESSION = 100;
    const MAX_PAGE_VIEWS_PER_SESSION = 30;
    const MAX_IDENTICAL_FINGERPRINT_EVENTS = 120;

    public static function get_data(int $days = 7): array {
        $events = get_option(
            AVDCTAI_Plugin::OPTION_RECENT_EVENTS,
            array()
        );

        if (!is_array($events)) {
            $events = array();
        }

        $days = max(1, $days);
        $since = time() - ($days * DAY_IN_SECONDS);

        $recent_events = array_values(
            array_filter(
                $events,
                function ($event) use ($since) {
                    if (!is_array($event)) {
                        return false;
                    }

                    $timestamp = isset($event['timestamp'])
                        ? (int) $event['timestamp']
                        : 0;

                    if ($timestamp <= 0) {
                        return true;
                    }

                    return $timestamp >= $since;
                }
            )
        );

        $session_stats = self::build_session_stats($recent_events);
        $fingerprint_stats = self::build_fingerprint_stats($recent_events);

        $filtered_events = array();
        $filtered_count = 0;
        $suspicious_sessions = array();

        foreach ($recent_events as $event) {
            $session_id = self::value(
                $event,
                array(
                    'session_id',
                    'sessionId',
                    'visitor_id',
                    'client_id',
                )
            );

            $fingerprint = self::fingerprint($event);

            $is_suspicious = self::is_suspicious_event(
                $event,
                $session_id,
                $session_stats,
                $fingerprint,
                $fingerprint_stats
            );

            if ($is_suspicious) {
                $filtered_count++;

                if ($session_id !== '') {
                    $suspicious_sessions[$session_id] = true;
                }

                continue;
            }

            $filtered_events[] = $event;
        }

        $views = 0;
        $cta_clicks = 0;
        $sessions = array();

        $devices = array();
        $languages = array();
        $timezones = array();
        $referrers = array();
        $screens = array();

        foreach ($filtered_events as $event) {
            $type = self::value(
                $event,
                array(
                    'type',
                    'event_type',
                    'event',
                    'action',
                )
            );

            if ($type === 'page_view') {
                $views++;
            }

            if (self::is_real_cta_event($type)) {
                $cta_clicks++;
            }

            $session_id = self::value(
                $event,
                array(
                    'session_id',
                    'sessionId',
                    'visitor_id',
                    'client_id',
                )
            );

            if ($session_id !== '') {
                $sessions[$session_id] = true;
            }

            $device = self::value(
                $event,
                array(
                    'device',
                    'device_type',
                )
            );

            $screen = self::screen_value($event);

            if ($device === '') {
                $device = self::detect_device_from_screen($screen);
            }

            self::increment($devices, $device);

            self::increment(
                $languages,
                self::value(
                    $event,
                    array(
                        'language',
                        'browser_language',
                        'visitor_language',
                    )
                )
            );

            self::increment(
                $timezones,
                self::value(
                    $event,
                    array(
                        'timezone',
                        'visitor_timezone',
                    )
                )
            );

            self::increment(
                $referrers,
                self::normalize_referrer(
                    self::value(
                        $event,
                        array(
                            'referrer',
                            'referer',
                            'source_url',
                        )
                    )
                )
            );

            self::increment($screens, $screen);
        }

        $cta_rate = $views > 0
            ? round(($cta_clicks / $views) * 100, 2)
            : 0;

        return array(
            'summary' => array(
                'events' => count($filtered_events),
                'raw_events' => count($recent_events),
                'filtered_events' => $filtered_count,
                'suspicious_sessions' => count($suspicious_sessions),
                'views' => $views,
                'unique_sessions' => count($sessions),
                'cta_clicks' => $cta_clicks,
                'cta_rate' => $cta_rate,
            ),
            'devices' => self::top_values($devices),
            'languages' => self::top_values($languages),
            'timezones' => self::top_values($timezones),
            'referrers' => self::top_values($referrers),
            'screens' => self::top_values($screens),
        );
    }

    public static function dashboard_payload(int $days = 7): array {
        return self::get_data($days);
    }

    public static function get_payload(int $days = 7): array {
        return self::get_data($days);
    }

    public static function is_real_cta_event(string $type): bool {
        $type = strtolower(trim($type));

        if ($type === '') {
            return false;
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

    private static function build_session_stats(array $events): array {
        $stats = array();

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $session_id = self::value(
                $event,
                array(
                    'session_id',
                    'sessionId',
                    'visitor_id',
                    'client_id',
                )
            );

            if ($session_id === '') {
                continue;
            }

            if (!isset($stats[$session_id])) {
                $stats[$session_id] = array(
                    'events' => 0,
                    'views' => 0,
                );
            }

            $stats[$session_id]['events']++;

            $type = self::value(
                $event,
                array(
                    'type',
                    'event_type',
                    'event',
                    'action',
                )
            );

            if ($type === 'page_view') {
                $stats[$session_id]['views']++;
            }
        }

        return $stats;
    }

    private static function build_fingerprint_stats(array $events): array {
        $stats = array();

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $fingerprint = self::fingerprint($event);

            if ($fingerprint === '') {
                continue;
            }

            if (!isset($stats[$fingerprint])) {
                $stats[$fingerprint] = 0;
            }

            $stats[$fingerprint]++;
        }

        return $stats;
    }

    private static function is_suspicious_event(
        array $event,
        string $session_id,
        array $session_stats,
        string $fingerprint,
        array $fingerprint_stats
    ): bool {
        if (self::is_known_bot_user_agent($event)) {
            return true;
        }

        if (
            $session_id !== '' &&
            isset($session_stats[$session_id])
        ) {
            $session = $session_stats[$session_id];

            if (
                isset($session['events']) &&
                (int) $session['events'] > self::MAX_EVENTS_PER_SESSION
            ) {
                return true;
            }

            if (
                isset($session['views']) &&
                (int) $session['views'] > self::MAX_PAGE_VIEWS_PER_SESSION
            ) {
                return true;
            }
        }

        if (
            $fingerprint !== '' &&
            isset($fingerprint_stats[$fingerprint]) &&
            (int) $fingerprint_stats[$fingerprint] >
                self::MAX_IDENTICAL_FINGERPRINT_EVENTS
        ) {
            return true;
        }

        return false;
    }

    private static function is_known_bot_user_agent(array $event): bool {
        $user_agent = self::value(
            $event,
            array(
                'user_agent',
                'userAgent',
                'ua',
            )
        );

        if ($user_agent === '') {
            return false;
        }

        $user_agent = strtolower($user_agent);

        $bot_terms = array(
            'bot',
            'crawler',
            'spider',
            'slurp',
            'bingpreview',
            'facebookexternalhit',
            'headless',
            'phantomjs',
            'selenium',
            'python-requests',
            'curl/',
            'wget/',
        );

        foreach ($bot_terms as $term) {
            if (strpos($user_agent, $term) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function fingerprint(array $event): string {
        $language = self::value(
            $event,
            array(
                'language',
                'browser_language',
                'visitor_language',
            )
        );

        $timezone = self::value(
            $event,
            array(
                'timezone',
                'visitor_timezone',
            )
        );

        $screen = self::screen_value($event);

        if (
            $language === '' &&
            $timezone === '' &&
            $screen === ''
        ) {
            return '';
        }

        return strtolower(
            trim($language) . '|' .
            trim($timezone) . '|' .
            trim($screen)
        );
    }

    private static function value(array $event, array $keys): string {
        foreach ($keys as $key) {
            if (
                isset($event[$key]) &&
                $event[$key] !== ''
            ) {
                return sanitize_text_field(
                    (string) $event[$key]
                );
            }
        }

        return '';
    }

    private static function screen_value(array $event): string {
        $direct = self::value(
            $event,
            array(
                'screen_resolution',
                'screen',
                'resolution',
            )
        );

        if ($direct !== '') {
            return $direct;
        }

        $width = isset($event['screen_width'])
            ? absint($event['screen_width'])
            : 0;

        $height = isset($event['screen_height'])
            ? absint($event['screen_height'])
            : 0;

        if ($width > 0 && $height > 0) {
            return $width . 'x' . $height;
        }

        return '';
    }

    private static function detect_device_from_screen(
        string $screen
    ): string {
        if (
            preg_match(
                '/^(\d{2,4})x(\d{2,4})$/',
                $screen,
                $matches
            )
        ) {
            $width = (int) $matches[1];

            if ($width < 768) {
                return 'mobiel';
            }

            if ($width < 1100) {
                return 'tablet';
            }

            return 'desktop';
        }

        return '';
    }

    private static function normalize_referrer(
        string $referrer
    ): string {
        if ($referrer === '') {
            return '';
        }

        $parsed = wp_parse_url($referrer);

        if (
            is_array($parsed) &&
            !empty($parsed['host'])
        ) {
            return strtolower($parsed['host']);
        }

        return strtolower($referrer);
    }

    private static function increment(
        array &$bucket,
        string $value
    ): void {
        $value = trim($value);

        if ($value === '') {
            return;
        }

        if (!isset($bucket[$value])) {
            $bucket[$value] = 0;
        }

        $bucket[$value]++;
    }

    private static function top_values(
        array $values,
        int $limit = 5
    ): array {
        arsort($values);

        $values = array_slice(
            $values,
            0,
            max(1, $limit),
            true
        );

        $result = array();

        foreach ($values as $value => $total) {
            $result[] = array(
                'value' => (string) $value,
                'total' => (int) $total,
            );
        }

        return $result;
    }
}
