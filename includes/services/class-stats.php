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
        return $this->plugin->build_stats_payload();
    }
}
