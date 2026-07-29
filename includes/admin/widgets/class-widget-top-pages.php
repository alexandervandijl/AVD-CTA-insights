<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Widget_Top_Pages extends AVDCTAI_Widget {

    public function render(array $payload): void {
        $pages = isset($payload['top_pages']) && is_array($payload['top_pages'])
            ? array_slice($payload['top_pages'], 0, 5)
            : array();

        ?>
        <div class="avd-section">
            <h2>📄 Top pagina's</h2>

            <?php if (empty($pages)) : ?>

                <p>Nog geen gegevens beschikbaar.</p>

            <?php else : ?>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Pagina</th>
                            <th>Views</th>
                            <th>CTA's</th>
                            <th>Conversie</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($pages as $page) : ?>
                            <tr>
                                <td>
                                    <?php
                                    echo esc_html(
                                        (string) ($page['page'] ?? '')
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo esc_html(
                                        (string) ((int) ($page['views'] ?? 0))
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo esc_html(
                                        (string) ((int) ($page['cta'] ?? 0))
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo esc_html(
                                        (string) ($page['conversion'] ?? 0)
                                    );
                                    ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php endif; ?>
        </div>
        <?php
    }
}
