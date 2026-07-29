<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Action_Center {

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!class_exists('AVDCTAI_Action_Generator')) {
            echo '<div class="wrap">';
            echo '<h1>AI Actiecentrum</h1>';
            echo '<div class="notice notice-error"><p>De Action Generator kon niet worden geladen.</p></div>';
            echo '</div>';
            return;
        }

        self::enqueue_preview_script();

        $actions = AVDCTAI_Action_Generator::generate();

        ?>
        <div class="wrap">
            <h1>AI Actiecentrum</h1>

            <p>
                Hier zie je concrete optimalisatievoorstellen op basis van je websitegegevens,
                paginatype en ingestelde CTA Templates.
            </p>

            <div class="notice notice-info inline">
                <p>
                    <strong>Veilige previewmodus:</strong>
                    de plugin wijzigt nog geen pagina’s. Je kunt alleen bekijken welke CTA wordt voorgesteld.
                </p>
            </div>

            <table class="widefat striped" style="margin-top:18px;">
                <thead>
                    <tr>
                        <th style="width:110px;">Status</th>
                        <th>Pagina</th>
                        <th>Intentie</th>
                        <th>Voorgestelde actie</th>
                        <th style="width:90px;">Impact</th>
                        <th style="width:105px;">Zekerheid</th>
                        <th style="width:210px;">Acties</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($actions)) : ?>
                        <tr>
                            <td colspan="7">
                                Nog geen concrete acties gevonden. Verzamel eerst meer data of controleer de AI Analyse.
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($actions as $action) : ?>
                            <?php
                            if (!is_array($action)) {
                                continue;
                            }

                            $action_id = isset($action['id'])
                                ? sanitize_key((string) $action['id'])
                                : '';

                            $template = isset($action['template']) && is_array($action['template'])
                                ? $action['template']
                                : array();

                            $template_enabled = !empty($action['template_enabled']);
                            $preview_id = 'avd-preview-' . $action_id;
                            ?>

                            <tr>
                                <td>
                                    <?php
                                    echo esc_html(
                                        self::status_label(
                                            (string) ($action['status'] ?? 'pending')
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <strong>
                                        <?php echo esc_html((string) ($action['page'] ?? 'Onbekend')); ?>
                                    </strong>

                                    <br>

                                    <small>
                                        <?php echo esc_html((string) ((int) ($action['views'] ?? 0))); ?> views,
                                        <?php echo esc_html((string) ((int) ($action['cta'] ?? 0))); ?> CTA’s,
                                        <?php echo esc_html((string) ((float) ($action['conversion'] ?? 0))); ?>% conversie
                                    </small>
                                </td>

                                <td>
                                    <?php echo esc_html((string) ($action['intent_label'] ?? 'Onbekend')); ?>
                                </td>

                                <td>
                                    <?php
                                    echo esc_html(
                                        (string) ($action['action_label'] ?? 'Controleer deze pagina.')
                                    );
                                    ?>
                                </td>

                                <td>
                                    <strong>
                                        <?php echo esc_html((string) ($action['impact'] ?? 'Onbekend')); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?php echo esc_html((string) ((int) ($action['confidence'] ?? 0))); ?>%
                                </td>

                                <td>
                                    <?php if ($template_enabled) : ?>
                                        <button
                                            type="button"
                                            class="button avd-action-preview-toggle"
                                            data-preview-id="<?php echo esc_attr($preview_id); ?>"
                                        >
                                            Preview
                                        </button>
                                    <?php else : ?>
                                        <button type="button" class="button" disabled>
                                            Template uit
                                        </button>
                                    <?php endif; ?>

                                    <button
                                        type="button"
                                        class="button button-primary"
                                        disabled
                                        title="Wordt later beschikbaar"
                                    >
                                        Toepassen
                                    </button>

                                    <button
                                        type="button"
                                        class="button"
                                        disabled
                                        title="Wordt later beschikbaar"
                                    >
                                        Negeren
                                    </button>
                                </td>
                            </tr>

                            <tr
                                id="<?php echo esc_attr($preview_id); ?>"
                                class="avd-action-preview-row"
                                style="display:none;"
                            >
                                <td colspan="7">
                                    <?php
                                    self::render_preview(
                                        $template,
                                        $template_enabled,
                                        $action
                                    );
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="avd-section" style="margin-top:24px;">
                <h2>Volgende stap</h2>

                <p>
                    In een volgende versie kan een voorstel eerst worden goedgekeurd en daarna veilig
                    aan een pagina worden toegevoegd. Iedere wijziging krijgt dan een logboek en
                    mogelijkheid om deze ongedaan te maken.
                </p>
            </div>
        </div>
        <?php
    }

    private static function enqueue_preview_script(): void {
        $script = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    var buttons = document.querySelectorAll('.avd-action-preview-toggle');

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            var previewId = button.getAttribute('data-preview-id');
            var preview = document.getElementById(previewId);

            if (!preview) {
                return;
            }

            var isOpen = preview.style.display === 'table-row';

            preview.style.display = isOpen ? 'none' : 'table-row';
            button.textContent = isOpen ? 'Preview' : 'Preview sluiten';
        });
    });
});
JS;

        wp_add_inline_script(
            'avd-cta-insights-dashboard',
            $script,
            'after'
        );
    }

    private static function render_preview(
        array $template,
        bool $template_enabled,
        array $action
    ): void {
        if (!$template_enabled) {
            ?>
            <div class="notice notice-warning inline">
                <p>
                    Dit CTA Template staat uit. Activeer het template eerst via
                    <a href="<?php echo esc_url(admin_url('admin.php?page=avd-cta-insights-templates')); ?>">
                        CTA Templates
                    </a>.
                </p>
            </div>
            <?php
            return;
        }

        $title = isset($template['title'])
            ? sanitize_text_field((string) $template['title'])
            : '';

        $text = isset($template['text'])
            ? sanitize_textarea_field((string) $template['text'])
            : '';

        $button = isset($template['button'])
            ? sanitize_text_field((string) $template['button'])
            : '';

        $url = isset($template['url'])
            ? esc_url((string) $template['url'])
            : '';

        ?>
        <div
            style="
                max-width:760px;
                margin:14px 0;
                padding:24px;
                background:#ffffff;
                border:1px solid #dcdcde;
                border-left:5px solid #2271b1;
                border-radius:12px;
                box-shadow:0 4px 14px rgba(0,0,0,.06);
            "
        >
            <div style="margin-bottom:14px;">
                <small>
                    Preview voor:
                    <strong>
                        <?php echo esc_html((string) ($action['page'] ?? 'Onbekende pagina')); ?>
                    </strong>
                </small>
            </div>

            <?php if ($title !== '') : ?>
                <h2 style="margin:0 0 10px;">
                    <?php echo esc_html($title); ?>
                </h2>
            <?php endif; ?>

            <?php if ($text !== '') : ?>
                <p style="font-size:15px;line-height:1.6;margin:0 0 16px;">
                    <?php echo nl2br(esc_html($text)); ?>
                </p>
            <?php endif; ?>

            <?php if ($button !== '' && $url !== '') : ?>
                <a
                    href="<?php echo esc_url($url); ?>"
                    class="button button-primary"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <?php echo esc_html($button); ?>
                </a>
            <?php elseif ($button !== '') : ?>
                <button type="button" class="button button-primary" disabled>
                    <?php echo esc_html($button); ?>
                </button>

                <p style="margin-top:10px;color:#b32d2e;">
                    Er is nog geen geldige URL ingesteld voor dit template.
                </p>
            <?php endif; ?>

            <hr style="margin:20px 0 14px;">

            <small>
                Intentie:
                <?php echo esc_html((string) ($action['intent_label'] ?? 'Onbekend')); ?>
                · Impact:
                <?php echo esc_html((string) ($action['impact'] ?? 'Onbekend')); ?>
                · Zekerheid:
                <?php echo esc_html((string) ((int) ($action['confidence'] ?? 0))); ?>%
            </small>
        </div>
        <?php
    }

    private static function status_label(string $status): string {
        $labels = array(
            'pending' => '🟡 Voorstel',
            'approved' => '🔵 Goedgekeurd',
            'applied' => '🟢 Toegepast',
            'ignored' => '⚪ Genegeerd',
        );

        return $labels[$status] ?? '🟡 Voorstel';
    }
}
