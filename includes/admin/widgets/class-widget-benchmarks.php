<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Widget_Benchmarks extends AVDCTAI_Widget {

    public function render(array $payload): void {
        $benchmarks = isset($payload['benchmarks']) && is_array($payload['benchmarks'])
            ? $payload['benchmarks']
            : array();

        ?>
        <div class="avd-card">

            <h2>🏆 AI Learn</h2>

            <p>
                Vergelijk pagina’s met vergelijkbare pagina’s op dezelfde website.
                Zo zie je waar de grootste conversiekansen liggen.
            </p>

            <?php if (empty($benchmarks)) : ?>

                <p>Nog onvoldoende benchmarkdata beschikbaar.</p>

            <?php else : ?>

                <?php foreach ($benchmarks as $group) : ?>

                    <?php
                    $label = isset($group['label'])
                        ? (string) $group['label']
                        : 'Paginagroep';

                    $page_count = isset($group['page_count'])
                        ? (int) $group['page_count']
                        : 0;

                    $views = isset($group['views'])
                        ? (int) $group['views']
                        : 0;

                    $cta = isset($group['cta'])
                        ? (int) $group['cta']
                        : 0;

                    $conversion = isset($group['conversion'])
                        ? (float) $group['conversion']
                        : 0;

                    $pages = isset($group['pages']) && is_array($group['pages'])
                        ? $group['pages']
                        : array();
                    ?>

                    <div style="margin:18px 0 24px;">

                        <h3><?php echo esc_html($label); ?></h3>

                        <p>
                            <?php echo esc_html((string) $page_count); ?> pagina’s,
                            <?php echo esc_html((string) $views); ?> views,
                            <?php echo esc_html((string) $cta); ?> CTA-kliks,
                            gemiddeld <?php echo esc_html((string) $conversion); ?>%
                            conversie.
                        </p>

                        <?php if (!empty($pages)) : ?>

                            <table class="widefat striped">

                                <thead>
                                    <tr>
                                        <th>Pagina</th>
                                        <th>Views</th>
                                        <th>CTA's</th>
                                        <th>Conversie</th>
                                        <th>Verschil</th>
                                        <th>Prestatie</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach (array_slice($pages, 0, 5) as $page) : ?>

                                        <?php
                                        if (!is_array($page)) {
                                            continue;
                                        }

                                        $difference = isset($page['difference'])
                                            ? (float) $page['difference']
                                            : 0;

                                        $difference_label = $difference > 0
                                            ? '+' . $difference . '%'
                                            : $difference . '%';
                                        ?>

                                        <tr>

                                            <td>
                                                <?php
                                                echo esc_html(
                                                    (string) ($page['page'] ?? 'Onbekende pagina')
                                                );
                                                ?>
                                            </td>

                                            <td>
                                                <?php
                                                echo esc_html(
                                                    (string) ($page['views'] ?? 0)
                                                );
                                                ?>
                                            </td>

                                            <td>
                                                <?php
                                                echo esc_html(
                                                    (string) ($page['cta'] ?? 0)
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

                                            <td>
                                                <?php
                                                echo esc_html($difference_label);
                                                ?>
                                            </td>

                                            <td>
                                                <?php
                                                echo esc_html(
                                                    (string) ($page['performance'] ?? 'Onbekend')
                                                );
                                                ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

        <?php
    }
}
