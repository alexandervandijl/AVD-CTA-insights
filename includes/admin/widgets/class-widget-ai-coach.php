<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Widget_AI_Coach extends AVDCTAI_Widget {

    public function render(array $payload): void {
        $coach = new AVDCTAI_AI_Coach($payload);
        $advies = $coach->recommendation();

        $actions = isset($advies['actions']) && is_array($advies['actions'])
            ? $advies['actions']
            : array();

        $ai_score = AVDCTAI_AI_Score::calculate(
            array(
                'views' => $advies['views'] ?? 0,
                'cta' => $advies['cta'] ?? 0,
                'conversion' => $advies['conversion'] ?? 0,
            )
        );

        ?>
        <div class="avd-card avd-ai-coach">

            <h2>🤖 AI Coach</h2>

            <p style="font-size:16px;">
                <strong>
                    <?php echo esc_html($advies['title'] ?? 'AI Coach'); ?>
                </strong>
            </p>

            <?php if (!empty($advies['message'])) : ?>
                <p><?php echo esc_html($advies['message']); ?></p>
            <?php endif; ?>

            <div style="display:flex;gap:20px;flex-wrap:wrap;margin:20px 0;">

                <div>
                    <strong>AI Score</strong><br>
                    <span style="font-size:22px;">
                        <?php echo esc_html((string) $ai_score); ?>/100
                    </span><br>

                    <?php
                    echo esc_html(
                        AVDCTAI_AI_Score::label($ai_score)
                    );
                    ?>
                    <br>

                    <span style="font-size:22px;">
                        <?php
                        echo esc_html(
                            AVDCTAI_AI_Score::stars($ai_score)
                        );
                        ?>
                    </span>
                </div>

                <div>
                    <strong>Impact</strong><br>

                    <span style="font-size:22px;">
                        <?php
                        echo esc_html(
                            (string) ($advies['stars'] ?? '☆☆☆☆☆')
                        );
                        ?>
                    </span>
                    <br>

                    <?php
                    echo esc_html(
                        (string) ($advies['impact'] ?? 'Laag')
                    );
                    ?>
                </div>

                <div>
                    <strong>Bezoekers</strong><br>
                    👁
                    <?php
                    echo esc_html(
                        (string) ((int) ($advies['views'] ?? 0))
                    );
                    ?>
                </div>

                <div>
                    <strong>CTA's</strong><br>
                    📞
                    <?php
                    echo esc_html(
                        (string) ((int) ($advies['cta'] ?? 0))
                    );
                    ?>
                </div>

                <div>
                    <strong>Conversie</strong><br>
                    📈
                    <?php
                    echo esc_html(
                        (string) ((float) ($advies['conversion'] ?? 0))
                    );
                    ?>%
                </div>

            </div>

            <hr>

            <h3>🎯 Acties voor vandaag</h3>

            <?php if (!empty($actions)) : ?>
                <ol>
                    <?php foreach ($actions as $actie) : ?>
                        <li>
                            <?php echo esc_html((string) $actie); ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else : ?>
                <p>
                    Er is nog te weinig data voor concrete acties.
                    Laat de plugin eerst meer events verzamelen.
                </p>
            <?php endif; ?>

            <p style="margin-top:20px;">
                <span
                    class="button button-primary"
                    style="cursor:default;"
                >
                    Geschatte tijd: 15 minuten
                </span>
            </p>

        </div>
        <?php
    }
}
