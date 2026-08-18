<?php
/**
 * Installer en migraties voor AVD CTA Insights.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Installer {

    private const MIGRATION_OPTION = 'avdctai_stats_recovery_4018';
    private const ARCHIVE_OPTION = 'avdctai_events_archive';

    public static function init(): void {
        $installed = get_option('avdctai_version');

        /*
         * Eenmalige migratie vanaf de oude version-option.
         */
        if ($installed === false) {
            $legacy_installed = get_option('avd_uber_cta_version');

            if (is_string($legacy_installed) && $legacy_installed !== '') {
                $installed = $legacy_installed;
                update_option('avdctai_version', $legacy_installed, false);
            }
        }

        /*
         * Herstel historische trackingdata uit bekende oude optionnamen.
         * Oude options worden bewust NIET verwijderd. Daardoor blijft herstel
         * altijd omkeerbaar en gaat een upgrade nooit destructief met data om.
         */
        self::recover_legacy_stats();

        if ($installed !== AVDCTAI_Plugin::VERSION) {
            self::upgrade($installed);
            update_option(
                'avdctai_version',
                AVDCTAI_Plugin::VERSION,
                false
            );
        }
    }

    private static function recover_legacy_stats(): void {
        if (get_option(self::MIGRATION_OPTION, false)) {
            return;
        }

        $archive = get_option(self::ARCHIVE_OPTION, array());
        if (!is_array($archive)) {
            $archive = array();
        }

        $candidates = array(
            'avd_uber_cta_events_recent',
            'avd_uber_events_recent',
            'avd_uber_cta_events',
            'avd_uber_events',
            'avd_cta_events_recent',
            'avd_cta_events',
        );

        foreach ($candidates as $option_name) {
            $legacy = get_option($option_name, null);

            if (!is_array($legacy)) {
                continue;
            }

            $archive = self::merge_event_arrays($archive, $legacy);
        }

        /*
         * Sommige oudere builds gebruikten nog avd_uber_* optionnamen die niet
         * in bovenstaande lijst voorkwamen. Zoek uitsluitend binnen die legacy
         * namespace en neem alleen arrays over die aantoonbaar op eventrecords
         * lijken. Templates, instellingen en andere arrays worden genegeerd.
         */
        global $wpdb;

        if (isset($wpdb->options)) {
            $like_one = $wpdb->esc_like('avd_uber_') . '%';
            $like_two = $wpdb->esc_like('avd_cta_') . '%';

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    $like_one,
                    $like_two
                ),
                ARRAY_A
            );

            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $name = isset($row['option_name']) ? (string) $row['option_name'] : '';

                    if ($name === '' || in_array($name, $candidates, true)) {
                        continue;
                    }

                    $value = maybe_unserialize($row['option_value'] ?? null);

                    if (!self::looks_like_event_list($value)) {
                        continue;
                    }

                    $archive = self::merge_event_arrays($archive, $value);
                }
            }
        }

        if (!empty($archive)) {
            $archive = self::sort_and_prune($archive);
            update_option(self::ARCHIVE_OPTION, $archive, false);
        }

        /*
         * Migreer tevens de oude API-key als de nieuwe nog niet bestaat.
         */
        if (!get_option('avdctai_api_key', '')) {
            foreach (array('avd_uber_cta_api_key', 'avd_uber_api_key') as $legacy_key_option) {
                $legacy_key = get_option($legacy_key_option, '');
                if (is_string($legacy_key) && $legacy_key !== '') {
                    update_option('avdctai_api_key', $legacy_key, false);
                    break;
                }
            }
        }

        update_option(
            self::MIGRATION_OPTION,
            array(
                'completed' => current_time('mysql'),
                'events'    => count($archive),
            ),
            false
        );
    }

    private static function looks_like_event_list($value): bool {
        if (!is_array($value) || empty($value)) {
            return false;
        }

        $checked = 0;
        $matches = 0;

        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }

            $checked++;

            $has_type = isset($row['type']) || isset($row['event_type']);
            $has_time = isset($row['timestamp']) || isset($row['time']) || isset($row['created_at']);
            $has_event_context = isset($row['page_url']) || isset($row['pageUrl']) || isset($row['session_id']) || isset($row['sessionId']) || isset($row['source']);

            if ($has_type && ($has_time || $has_event_context)) {
                $matches++;
            }

            if ($checked >= 10) {
                break;
            }
        }

        return $checked > 0 && $matches >= max(1, (int) ceil($checked * 0.6));
    }

    private static function merge_event_arrays(array $base, array $incoming): array {
        $seen = array();

        foreach ($base as $event) {
            if (!is_array($event)) {
                continue;
            }
            $seen[self::event_fingerprint($event)] = true;
        }

        foreach ($incoming as $event) {
            if (!is_array($event)) {
                continue;
            }

            $event = self::normalize_event($event);
            $fingerprint = self::event_fingerprint($event);

            if (isset($seen[$fingerprint])) {
                continue;
            }

            $base[] = $event;
            $seen[$fingerprint] = true;
        }

        return $base;
    }

    private static function normalize_event(array $event): array {
        if (!isset($event['type']) && isset($event['event_type'])) {
            $event['type'] = sanitize_key((string) $event['event_type']);
        }

        if (!isset($event['page_url']) && isset($event['pageUrl'])) {
            $event['page_url'] = esc_url_raw((string) $event['pageUrl']);
        }

        if (!isset($event['target_url']) && isset($event['targetUrl'])) {
            $event['target_url'] = esc_url_raw((string) $event['targetUrl']);
        }

        if (!isset($event['session_id']) && isset($event['sessionId'])) {
            $event['session_id'] = sanitize_text_field((string) $event['sessionId']);
        }

        if (empty($event['timestamp'])) {
            $candidate = $event['time'] ?? ($event['created_at'] ?? '');
            $parsed = is_numeric($candidate) ? (int) $candidate : strtotime((string) $candidate);
            $event['timestamp'] = $parsed ?: 0;
        }

        return $event;
    }

    private static function event_fingerprint(array $event): string {
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

    private static function sort_and_prune(array $events): array {
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

    private static function upgrade($old_version): void {
        /*
         * Toekomstige database-upgrades komen hier.
         */
    }
}
