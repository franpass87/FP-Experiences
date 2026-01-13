<?php

declare(strict_types=1);

namespace FP_Exp\Admin\ExperienceMetaBoxes\Handlers;

use FP_Exp\Admin\ExperienceMetaBoxes\BaseMetaBoxHandler;

/**
 * Example implementation of BaseMetaBoxHandler.
 * 
 * This is a template showing how to create a new meta box handler.
 * Copy this structure for each new meta box tab.
 */
final class ExampleMetaBoxHandler extends BaseMetaBoxHandler
{
    protected function get_meta_key(): string
    {
        return '_fp_exp_example';
    }

    protected function render_tab_content(array $data, int $post_id): void
    {
        $example_field = $data['example_field'] ?? '';
        $example_toggle = $data['example_toggle'] ?? 'no';
        ?>

        <div class="fp-exp-meta-box__field">
            <label for="fp_exp_example_field">
                <?php esc_html_e('Example Field', 'fp-experiences'); ?>
            </label>
            <input 
                type="text" 
                id="fp_exp_example_field" 
                name="fp_exp_example[example_field]" 
                value="<?php echo esc_attr($example_field); ?>" 
                class="regular-text" 
            />
            <p class="description">
                <?php esc_html_e('This is an example field.', 'fp-experiences'); ?>
            </p>
        </div>

        <div class="fp-exp-meta-box__field">
            <label>
                <input 
                    type="checkbox" 
                    name="fp_exp_example[example_toggle]" 
                    value="yes" 
                    <?php checked($example_toggle, 'yes'); ?> 
                />
                <?php esc_html_e('Example Toggle', 'fp-experiences'); ?>
            </label>
        </div>

        <?php
    }

    protected function save_meta_data(int $post_id, array $raw): void
    {
        $example_field = $this->sanitize_text($raw['example_field'] ?? '');
        $example_toggle = isset($raw['example_toggle']) && $raw['example_toggle'] === 'yes' ? 'yes' : 'no';

        $this->update_or_delete_meta($post_id, 'example_field', $example_field);
        $this->update_or_delete_meta($post_id, 'example_toggle', $example_toggle);
    }

    protected function get_meta_data(int $post_id): array
    {
        return [
            'example_field' => $this->get_meta_value($post_id, 'example_field', ''),
            'example_toggle' => $this->get_meta_value($post_id, 'example_toggle', 'no'),
        ];
    }
}
















