<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Event_Archive {

    public const OPTION_ARCHIVE = 'avdctai_events_archive';

    private static bool $updating = false;

    public static function init(): void {
        add_action(
            'updated_option',
            array(__CLASS__, 'capture_recent_event_update'),
            10,
            3
        );
    }

    public static function capture_recent_event_update($option, $old_value, $value): void {
        if (self::$updating || $option !== AVDCTAI_Plugin::OPTION_RECENT_EVENTS) {
            return;
        }

        if (!is_array($value)) {
            return;
        }

        $old_value = is_array($old_value) ? $old_value : array();
        $known = array();

        foreach ($old_value as $event) {
            if (is_array($event)) {
                $known[self::fingerprint($event)] = true;
            }
        }

        $new_events = array();

        foreach ($value as $event) {
            if (!is_array($event)) {
                continue;
            }

            $fingerprint = self::fingerprint($event);

            if (!isset($known[$fingerprint])) {
                $new_events[] = $event;
            }
        }

        if (empty($new_events)) {
            return;
        }

        $archive = get_option(self::OPTION_ARCHIVE, array());
        if (!is_array($archive)) {
            $archive = array();
        }

        $archive = self::merge($archive, $new_events);
        $archive = self::prune($archive);

        self::$updating = true;
        update_option(self::OPTION_ARCHIVE, $archive, false);
        self::$updating = false;
    }

    public static function get_combined_events(): array {
        $recent = get_option(AVDCTAI_Plugin::OPTION_RECENT_EVENTS, array());
        $archive = get_option(self::OPTION_ARCHIVE, array());

        if (!is_array($recent)) {
            $recent = array();
        }

        if (!is_array($archive)) {
            $archive = array();
        }

        return self::prune(self::merge($archive, $recent));
    }

    private static function merge(array $base, array $incoming): array {
        $seen = array();
        $clean = array();

        foreach ($base as $event) {
            if (!is_array($event)) {
                continue;
            }

            $fingerprint = self::fingerprint($event);
            if (isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $clean[] = $event;
        }

        foreach ($incoming as $event) {
            if (!is_array($event)) {
                continue;
            }

            $fingerprint = self::fingerprint($event);
            if (isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $clean[] = $event;
        }

        return $clean;
    }

    private static function prune(array $events): array {
        $cutoff = time() - (90 * DAY_IN_SECONDS);

        $events = array_values(array_filter($events, static function ($event) use ($cutoff) {
            if (!is_array($event)) {
                return false;
            }

            $timestamp = (int) ($event['timestamp'] ?? 0);
            return $timestamp === 0 || $timestamp >= $cutoff;
        }));

        usort($events, static function ($a, $b) {
            return ((int) ($a['timestamp'] ?? 0)) <=> ((int) ($b['timestamp'] ?? 0));
        });

        if (count($events) > 20000) {
            $events = array_slice($events, -20000);
        }

        return $events;
    }

    private static function fingerprint(array $event): string {
        return md5(wp_json_encode(array(
            'timestamp'  => (int) ($event['timestamp'] ?? 0),
            'type'       => (string) ($event['type'] ?? ''),
            'source'     => (string) ($event['source'] ?? ''),
            'context'    => (string) ($event['context'] ?? ''),
            'page_url'   => (string) ($event['page_url'] ?? ($event['pageUrl'] ?? '')),
            'target_url' => (string) ($event['target_url'] ?? ($event['targetUrl'] ?? '')),
            'session_id' => (string) ($event['session_id'] ?? ($event['sessionId'] ?? '')),
            'label'      => (string) ($event['label'] ?? ''),
        )));
    }
}
