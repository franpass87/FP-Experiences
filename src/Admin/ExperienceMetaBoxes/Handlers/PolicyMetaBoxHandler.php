<?php

declare(strict_types=1);

namespace FP_Exp\Admin\ExperienceMetaBoxes\Handlers;

use FP_Exp\Admin\ExperienceMetaBoxes\BaseMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Traits\MetaBoxHelpers;

use function esc_attr;
use function esc_html;
use function esc_textarea;
use function wp_kses_post;

/**
 * Handler for Policy/FAQ tab in Experience Meta Box.
 * 
 * This is a simpler handler demonstrating the BaseMetaBoxHandler pattern.
 */
final class PolicyMetaBoxHandler extends BaseMetaBoxHandler
{
    use MetaBoxHelpers;

    protected function get_meta_key(): string
    {
        return '_fp'; // Base prefix for meta keys
    }

    protected function render_tab_content(array $data, int $post_id): void
    {
        $panel_id = 'fp-exp-tab-policy-panel';
        $cancel = $data['cancel'] ?? '';
        $faqs = $data['faq'] ?? [];
        
        // Ensure at least one FAQ item for repeater
        if (empty($faqs)) {
            $faqs = [['question' => '', 'answer' => '']];
        }
        ?>
        <section
            id="<?php echo esc_attr($panel_id); ?>"
            class="fp-exp-tab-panel"
            role="tabpanel"
            tabindex="0"
            aria-labelledby="fp-exp-tab-policy"
            data-tab-panel="policy"
        >
            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Policy di cancellazione', 'fp-experiences'); ?></legend>
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-cancellation">
                        <?php esc_html_e('Regole di cancellazione', 'fp-experiences'); ?>
                    </label>
                    <textarea
                        id="fp-exp-policy-text"
                        name="fp_exp_policy[cancel]"
                        rows="5"
                        class="large-text"
                        placeholder="<?php echo esc_attr__('Es. Cancellazione gratuita fino a 48 ore dalla partenza.', 'fp-experiences'); ?>"
                        aria-describedby="fp-exp-policy-help"
                    ><?php echo esc_textarea($cancel); ?></textarea>
                    <p class="fp-exp-field__description" id="fp-exp-policy-help">
                        <?php esc_html_e('Esempio: Cancellazione gratuita fino a 48 ore dalla partenza.', 'fp-experiences'); ?>
                    </p>
                    <p class="fp-exp-field__description">
                        <?php esc_html_e('Descrivi le regole di cancellazione e rimborso per questa esperienza.', 'fp-experiences'); ?>
                    </p>
                </div>
            </fieldset>

            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('FAQ', 'fp-experiences'); ?></legend>
                <div class="fp-exp-repeater" data-repeater="faq" data-repeater-next-index="<?php echo esc_attr((string) count($faqs)); ?>">
                    <div class="fp-exp-repeater__items">
                        <?php foreach ($faqs as $index => $item) : ?>
                            <?php $this->render_faq_row((string) $index, $item); ?>
                        <?php endforeach; ?>
                    </div>
                    <template data-repeater-template>
                        <?php $this->render_faq_row('__INDEX__', ['question' => '', 'answer' => ''], true); ?>
                    </template>
                    <p class="fp-exp-repeater__actions">
                        <button type="button" class="button button-secondary" data-repeater-add><?php esc_html_e('Aggiungi FAQ', 'fp-experiences'); ?></button>
                    </p>
                </div>
            </fieldset>
        </section>
        <?php
    }

    protected function save_meta_data(int $post_id, array $raw): void
    {
        // Cancellation policy - uses wp_kses_post for HTML support
        $cancel = isset($raw['cancel']) ? wp_kses_post((string) $raw['cancel']) : '';
        $this->update_or_delete_meta($post_id, 'policy_cancel', $cancel);

        // FAQs - uses wp_kses_post for answer HTML support
        $faqs_raw = $raw['faq'] ?? [];
        $faqs = [];

        if (is_array($faqs_raw)) {
            foreach ($faqs_raw as $faq) {
                if (!is_array($faq)) {
                    continue;
                }

                $question = $this->sanitize_text($faq['question'] ?? '');
                $answer = isset($faq['answer']) ? wp_kses_post((string) $faq['answer']) : '';

                // Original code requires both question and answer
                if ($question === '' || $answer === '') {
                    continue;
                }

                $faqs[] = [
                    'question' => $question,
                    'answer' => $answer,
                ];
            }
        }

        $this->update_or_delete_meta($post_id, 'faq', !empty($faqs) ? $faqs : null);
    }

    protected function get_meta_data(int $post_id): array
    {
        // Get cancellation policy - uses wp_kses_post for output
        $cancel_meta = get_post_meta($post_id, '_fp_policy_cancel', true);
        $cancel = wp_kses_post((string) $cancel_meta);

        // Get FAQs - sanitize each item
        $faqs_meta = get_post_meta($post_id, '_fp_faq', true);
        $faqs = [];

        if (is_array($faqs_meta)) {
            foreach ($faqs_meta as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $faqs[] = [
                    'question' => sanitize_text_field((string) ($item['question'] ?? '')),
                    'answer' => wp_kses_post((string) ($item['answer'] ?? '')),
                ];
            }
        }

        return [
            'cancel' => $cancel,
            'faq' => $faqs,
        ];
    }

    /**
     * Render a single FAQ row (used in repeater).
     */
    private function render_faq_row(string $index, array $faq, bool $is_template = false): void
    {
        $question = $faq['question'] ?? '';
        $answer = $faq['answer'] ?? '';
        $field_name = $this->field_name_attribute('faqs', $is_template);
        ?>
        <div class="fp-exp-repeater__item" <?php echo $is_template ? 'data-template="faq"' : ''; ?>>
            <div class="fp-exp-repeater__item-header">
                <span class="fp-exp-repeater__item-number"><?php echo esc_html($is_template ? '#' : ((int) $index + 1)); ?></span>
                <button type="button" class="fp-exp-repeater__item-remove" aria-label="<?php esc_attr_e('Rimuovi FAQ', 'fp-experiences'); ?>">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
            <div class="fp-exp-repeater__item-body">
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label">
                        <?php esc_html_e('Domanda', 'fp-experiences'); ?>
                    </label>
                    <input
                        type="text"
                        name="<?php echo esc_attr($field_name . '[' . $index . '][question]'); ?>"
                        value="<?php echo esc_attr($question); ?>"
                        class="regular-text"
                        placeholder="<?php echo esc_attr__('Es. Qual è la durata dell\'esperienza?', 'fp-experiences'); ?>"
                        <?php echo $is_template ? 'data-repeater-field="question"' : ''; ?>
                    />
                </div>
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label">
                        <?php esc_html_e('Risposta', 'fp-experiences'); ?>
                    </label>
                    <textarea
                        name="<?php echo esc_attr($field_name . '[' . $index . '][answer]'); ?>"
                        rows="3"
                        class="large-text"
                        placeholder="<?php echo esc_attr__('Risposta alla domanda...', 'fp-experiences'); ?>"
                        <?php echo $is_template ? 'data-repeater-field="answer"' : ''; ?>
                    ><?php echo esc_textarea($answer); ?></textarea>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get field name attribute for repeater items.
     */
    private function field_name_attribute(string $name, bool $is_template): string
    {
        $base = $this->get_meta_key();
        return $is_template ? $base . '_' . $name . '_template' : $base . '[' . $name . ']';
    }
}

