<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AVDCTAI_Templates {

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $nonce = filter_input(
            INPUT_POST,
            'avd_uber_cta_templates_nonce',
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );

        if (
            is_string($nonce) &&
            wp_verify_nonce(
                sanitize_text_field($nonce),
                'avd_uber_cta_save_templates'
            )
        ) {
            $templates = filter_input(
                INPUT_POST,
                'templates',
                FILTER_DEFAULT,
                FILTER_REQUIRE_ARRAY
            );

            if (!is_array($templates)) {
                $templates = array();
            }

            AVDCTAI_Template_Manager::save_templates($templates);

            echo '<div class="notice notice-success is-dismissible"><p>CTA Templates opgeslagen.</p></div>';
        }

        $templates = AVDCTAI_Template_Manager::get_templates();
        $labels = AVDCTAI_Template_Manager::labels();

        ?>
        <div class="wrap">
            <h1>CTA Templates</h1>

            <p>
                Stel per paginatype in welke CTA de AI mag voorstellen.
                Deze templates worden later gebruikt voor preview, toepassen en Auto Optimize.
            </p>

            <form method="post">
                <?php wp_nonce_field('avd_uber_cta_save_templates', 'avd_uber_cta_templates_nonce'); ?>

                <?php foreach ($templates as $intent => $template) : ?>
                    <div class="avd-section" style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px;margin:18px 0;">
                        <h2>
                            <?php echo esc_html($labels[$intent] ?? $intent); ?>
                        </h2>

                        <p>
                            <label>
                                <input
                                    type="checkbox"
                                    name="templates[<?php echo esc_attr($intent); ?>][enabled]"
                                    value="1"
                                    <?php checked(!empty($template['enabled'])); ?>
                                >
                                Template actief
                            </label>
                        </p>

                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="template-<?php echo esc_attr($intent); ?>-title">
                                            Titel
                                        </label>
                                    </th>
                                    <td>
                                        <input
                                            type="text"
                                            id="template-<?php echo esc_attr($intent); ?>-title"
                                            name="templates[<?php echo esc_attr($intent); ?>][title]"
                                            value="<?php echo esc_attr($template['title'] ?? ''); ?>"
                                            class="regular-text"
                                        >
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="template-<?php echo esc_attr($intent); ?>-text">
                                            Tekst
                                        </label>
                                    </th>
                                    <td>
                                        <textarea
                                            id="template-<?php echo esc_attr($intent); ?>-text"
                                            name="templates[<?php echo esc_attr($intent); ?>][text]"
                                            rows="3"
                                            class="large-text"
                                        ><?php echo esc_textarea($template['text'] ?? ''); ?></textarea>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="template-<?php echo esc_attr($intent); ?>-button">
                                            Knoptekst
                                        </label>
                                    </th>
                                    <td>
                                        <input
                                            type="text"
                                            id="template-<?php echo esc_attr($intent); ?>-button"
                                            name="templates[<?php echo esc_attr($intent); ?>][button]"
                                            value="<?php echo esc_attr($template['button'] ?? ''); ?>"
                                            class="regular-text"
                                        >
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="template-<?php echo esc_attr($intent); ?>-url">
                                            URL
                                        </label>
                                    </th>
                                    <td>
                                        <input
                                            type="url"
                                            id="template-<?php echo esc_attr($intent); ?>-url"
                                            name="templates[<?php echo esc_attr($intent); ?>][url]"
                                            value="<?php echo esc_attr($template['url'] ?? ''); ?>"
                                            class="regular-text"
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div style="background:#f6f7f7;border-left:4px solid #2271b1;padding:12px;margin-top:12px;">
                            <strong>Preview:</strong><br>

                            <strong>
                                <?php echo esc_html($template['title'] ?? ''); ?>
                            </strong><br>

                            <?php echo esc_html($template['text'] ?? ''); ?><br>

                            <em>
                                <?php echo esc_html($template['button'] ?? ''); ?>
                            </em>
                        </div>
                    </div>
                <?php endforeach; ?>

                <p>
                    <button type="submit" class="button button-primary">
                        CTA Templates opslaan
                    </button>
                </p>
            </form>
        </div>
        <?php
    }
}
