<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Widget_Priority extends AVDCTAI_Widget {

    public function render(array $payload): void {
        $pages = $payload['needs_attention'] ?? array();

        if (empty($pages) || !is_array($pages)) {
            return;
        }

        usort(
            $pages,
            static function ($a, $b): int {
                return AVDCTAI_Priority::score($b)
                    <=> AVDCTAI_Priority::score($a);
            }
        );

        ?>
        <div class="avd-card">

            <h2>🤖 AI Prioriteiten</h2>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Pagina</th>
                        <th>Score</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach (array_slice($pages, 0, 5) as $page) : ?>
                        <?php
                        if (!is_array($page)) {
                            continue;
                        }

                        $score = AVDCTAI_Priority::score($page);
                        ?>

                        <tr>
                            <td>
                                <?php
                                echo esc_html(
                                    (string) ($page['page'] ?? '')
                                );
                                ?>
                            </td>

                            <td>
                                <strong>
                                    <?php echo esc_html((string) $score); ?>/100
                                </strong>
                            </td>

                            <td>
                                <?php
                                echo esc_html(
                                    AVDCTAI_Priority::label($score)
                                );
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        </div>
        <?php
    }
}
