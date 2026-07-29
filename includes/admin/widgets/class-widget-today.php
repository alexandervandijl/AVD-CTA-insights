<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Widget_Today extends AVDCTAI_Widget {

    public function render(array $payload): void {
        $today = isset($payload['today'])
            ? $payload['today']
            : array();

        $views = isset($today['views'])
            ? (int) $today['views']
            : 0;

        $cta = isset($today['cta'])
            ? (int) $today['cta']
            : 0;

        $conversion = isset($today['conversion_views'])
            ? $today['conversion_views']
            : 0;
        ?>

        <div class="avd-section">
            <h2>Vandaag</h2>

            <div class="avd-dashboard-grid">

                <div class="avd-card">
                    <h2>Views</h2>
                    <p class="avd-number">
                        <?php echo esc_html($views); ?>
                    </p>
                </div>

                <div class="avd-card">
                    <h2>CTA's</h2>
                    <p class="avd-number">
                        <?php echo esc_html($cta); ?>
                    </p>
                </div>

                <div class="avd-card">
                    <h2>Conversie</h2>
                    <p class="avd-number">
                        <?php echo esc_html($conversion); ?>%
                    </p>
                </div>

            </div>
        </div>

        <?php
    }
}
