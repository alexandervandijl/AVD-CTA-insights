<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Stats {

    private AVDCTAI_Plugin $plugin;

    public function __construct(AVDCTAI_Plugin $plugin) {
        $this->plugin = $plugin;
    }

    public function get_payload(): array {
        if (!class_exists('AVDCTAI_Event_Archive')) {
            return $this->plugin->build_stats_payload();
        }

        $option_name = AVDCTAI_Plugin::OPTION_RECENT_EVENTS;
        $original = get_option($option_name, array());
        $original = is_array($original) ? $original : array();
        $combined = AVDCTAI_Event_Archive::get_combined_events();

        /*
         * build_stats_payload() leest rechtstreeks uit de option-cache.
         * Door uitsluitend de cache tijdelijk te vervangen, krijgt de analyse
         * het volledige archief zonder de database-option te vergroten of een
         * gelijktijdig nieuw trackingevent te kunnen overschrijven.
         */
        wp_cache_set($option_name, $combined, 'options');

        try {
            return $this->plugin->build_stats_payload();
        } finally {
            wp_cache_set($option_name, $original, 'options');
        }
    }
}
