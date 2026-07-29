<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Widget_Health extends AVDCTAI_Widget {

    public function render(array $payload): void {

        $events = get_option(
            AVDCTAI_Plugin::OPTION_RECENT_EVENTS,
            array()
        );

        if (!is_array($events)) {
            $events = array();
        }

        $last_event = !empty($events)
            ? end($events)
            : array();

        $last_timestamp = isset($last_event['timestamp'])
            ? (int) $last_event['timestamp']
            : 0;

        $seconds_ago = $last_timestamp
            ? max(0, time() - $last_timestamp)
            : null;

        $tracking = $seconds_ago !== null;

        $database_status =
            get_option(
                AVDCTAI_Plugin::OPTION_RECENT_EVENTS,
                null
            ) !== null;

        $wordpress_version = get_bloginfo('version');
        $php_version = PHP_VERSION;

        ?>

        <div class="avd-card">

            <h2>
                <?php echo $tracking ? '🟢 Gezondheid' : '🟠 Gezondheid'; ?>
            </h2>

            <table class="widefat striped">
                <tbody>

                    <tr>
                        <td>Tracking</td>
                        <td>
                            <?php echo $tracking ? '✅ Actief' : '❌ Geen data'; ?>
                        </td>
                    </tr>

                    <tr>
                        <td>Laatste event</td>
                        <td>

                            <?php
                            if ($seconds_ago === null) {
                                echo 'Nog geen event';
                            } elseif ($seconds_ago < 60) {
                                echo esc_html($seconds_ago . ' seconden geleden');
                            } elseif ($seconds_ago < 3600) {
                                echo esc_html(
                                    floor($seconds_ago / 60)
                                    . ' minuten geleden'
                                );
                            } elseif ($seconds_ago < DAY_IN_SECONDS) {
                                echo esc_html(
                                    floor($seconds_ago / 3600)
                                    . ' uur geleden'
                                );
                            } else {
                                echo esc_html(
                                    floor($seconds_ago / DAY_IN_SECONDS)
                                    . ' dagen geleden'
                                );
                            }
                            ?>

                        </td>
                    </tr>

                    <tr>
                        <td>Laatste eventtype</td>
                        <td>
                            <?php echo esc_html($last_event['type'] ?? '-'); ?>
                        </td>
                    </tr>

                    <tr>
                        <td>Pluginversie</td>
                        <td>
                            <?php
                            echo esc_html(
                                AVDCTAI_Plugin::VERSION
                            );
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <td>WordPress-versie</td>
                        <td><?php echo esc_html($wordpress_version); ?></td>
                    </tr>

                    <tr>
                        <td>PHP-versie</td>
                        <td><?php echo esc_html($php_version); ?></td>
                    </tr>

                    <tr>
                        <td>Eventopslag</td>
                        <td>
                            <?php
                            echo $database_status
                                ? '✅ Beschikbaar'
                                : '❌ Niet beschikbaar';
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <td>Status</td>
                        <td>

                            <?php
                            if (!$tracking) {
                                echo '🟠 Controleer tracking';
                            } elseif (!$database_status) {
                                echo '🟠 Controleer eventopslag';
                            } else {
                                echo '🟢 Gezond';
                            }
                            ?>

                        </td>
                    </tr>

                </tbody>
            </table>

        </div>

        <?php
    }
}
