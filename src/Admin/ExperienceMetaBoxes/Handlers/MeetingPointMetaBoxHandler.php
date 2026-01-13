<?php

declare(strict_types=1);

namespace FP_Exp\Admin\ExperienceMetaBoxes\Handlers;

use FP_Exp\Admin\ExperienceMetaBoxes\BaseMetaBoxHandler;
use FP_Exp\Admin\ExperienceMetaBoxes\Traits\MetaBoxHelpers;
use FP_Exp\MeetingPoints\Repository;

use function esc_attr;
use function esc_html;
use function selected;

/**
 * Handler for Meeting Point tab in Experience Meta Box.
 * 
 * Handles primary and alternative meeting points for experiences.
 */
final class MeetingPointMetaBoxHandler extends BaseMetaBoxHandler
{
    use MetaBoxHelpers;

    protected function get_meta_key(): string
    {
        return '_fp'; // Base prefix for meta keys
    }

    protected function render_tab_content(array $data, int $post_id): void
    {
        $panel_id = 'fp-exp-tab-meeting-point-panel';
        $primary_id = $data['primary_id'] ?? 0;
        $alternatives = $data['alternatives'] ?? [];
        $choices = $data['choices'] ?? [];
        ?>
        <section
            id="<?php echo esc_attr($panel_id); ?>"
            class="fp-exp-tab-panel"
            role="tabpanel"
            tabindex="0"
            aria-labelledby="fp-exp-tab-meeting-point"
            data-tab-panel="meeting-point"
            hidden
        >
            <fieldset class="fp-exp-fieldset">
                <legend><?php esc_html_e('Meeting Point', 'fp-experiences'); ?></legend>
                
                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-meeting-primary">
                        <?php esc_html_e('Meeting Point Principale', 'fp-experiences'); ?>
                        <?php $this->render_tooltip('fp-exp-meeting-primary-help', esc_html__('Seleziona il meeting point principale per questa esperienza.', 'fp-experiences')); ?>
                    </label>
                    <select
                        id="fp-exp-meeting-primary"
                        name="fp_exp_meeting_point[primary]"
                        aria-describedby="fp-exp-meeting-primary-help"
                    >
                        <option value="0"><?php esc_html_e('-- Nessuno --', 'fp-experiences'); ?></option>
                        <?php foreach ($choices as $choice) : ?>
                            <option 
                                value="<?php echo esc_attr((string) $choice['id']); ?>" 
                                <?php selected($primary_id, $choice['id'], true); ?>
                            >
                                <?php echo esc_html($choice['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="fp-exp-field__description" id="fp-exp-meeting-primary-help">
                        <?php esc_html_e('Il meeting point principale verrà mostrato come punto di ritrovo predefinito.', 'fp-experiences'); ?>
                    </p>
                </div>

                <div class="fp-exp-field">
                    <label class="fp-exp-field__label" for="fp-exp-meeting-alternatives">
                        <?php esc_html_e('Meeting Point Alternativi', 'fp-experiences'); ?>
                        <?php $this->render_tooltip('fp-exp-meeting-alt-help', esc_html__('Seleziona meeting point alternativi che possono essere scelti dai clienti.', 'fp-experiences')); ?>
                    </label>
                    <select
                        id="fp-exp-meeting-alternatives"
                        name="fp_exp_meeting_point[alternatives][]"
                        multiple
                        size="5"
                        aria-describedby="fp-exp-meeting-alt-help"
                    >
                        <?php foreach ($choices as $choice) : ?>
                            <option 
                                value="<?php echo esc_attr((string) $choice['id']); ?>" 
                                <?php selected(in_array($choice['id'], $alternatives, true), true, true); ?>
                            >
                                <?php echo esc_html($choice['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="fp-exp-field__description" id="fp-exp-meeting-alt-help">
                        <?php esc_html_e('Usa CTRL/CMD + clic per selezionare più voci.', 'fp-experiences'); ?>
                    </p>
                </div>
            </fieldset>
        </section>
        <?php
    }

    protected function save_meta_data(int $post_id, array $raw): void
    {
        $primary_id = isset($raw['primary']) ? absint((string) $raw['primary']) : 0;
        $alternatives_raw = $raw['alternatives'] ?? [];
        
        // Sanitize alternatives array
        $alternatives = [];
        if (is_array($alternatives_raw)) {
            foreach ($alternatives_raw as $alt_id) {
                $alt_id = absint((string) $alt_id);
                if ($alt_id > 0 && $alt_id !== $primary_id) { // Don't include primary in alternatives
                    $alternatives[] = $alt_id;
                }
            }
        }
        $alternatives = array_unique($alternatives);

        // Save primary meeting point
        if ($primary_id > 0) {
            $this->update_or_delete_meta($post_id, 'meeting_point_id', $primary_id);
        } else {
            delete_post_meta($post_id, '_fp_meeting_point_id');
        }

        // Save alternatives
        if (!empty($alternatives)) {
            $this->update_or_delete_meta($post_id, 'meeting_point_alt', $alternatives);
        } else {
            delete_post_meta($post_id, '_fp_meeting_point_alt');
        }

        // Update summary from Repository
        $summary = Repository::get_primary_summary_for_experience($post_id, $primary_id);
        if ($summary) {
            $this->update_or_delete_meta($post_id, 'meeting_point', $summary);
        } else {
            delete_post_meta($post_id, '_fp_meeting_point');
        }
    }

    protected function get_meta_data(int $post_id): array
    {
        // Try to use repository if available
        $repo = $this->getExperienceRepository();
        $primary = 0;
        $alternatives_meta = [];
        if ($repo !== null) {
            $primary = absint((string) $repo->getMeta($post_id, '_fp_meeting_point_id', 0));
            $alternatives_meta = $repo->getMeta($post_id, '_fp_meeting_point_alt', []);
            if (!is_array($alternatives_meta)) {
                $alternatives_meta = [];
            }
        } else {
            // Fallback to direct get_post_meta for backward compatibility
            $primary = absint((string) get_post_meta($post_id, '_fp_meeting_point_id', true));
            $alternatives_meta = get_post_meta($post_id, '_fp_meeting_point_alt', true);
            if (!is_array($alternatives_meta)) {
                $alternatives_meta = [];
            }
        }
        
        $alternatives = [];
        if (is_array($alternatives_meta)) {
            $alternatives = array_map('absint', $alternatives_meta);
            $alternatives = array_values(array_unique(array_filter($alternatives)));
        }

        // Get meeting point choices
        $choices = $this->get_meeting_point_choices();

        return [
            'primary' => $primary,
            'alternatives' => $alternatives,
            'choices' => $choices,
        ];
    }

    /**
     * Get meeting point choices for select dropdown.
     * 
     * @return array<int, array{id: int, title: string}>
     */
    private function get_meeting_point_choices(): array
    {
        $posts = get_posts([
            'post_type' => \FP_Exp\MeetingPoints\MeetingPointCPT::POST_TYPE,
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish'],
            'fields' => 'ids',
        ]);

        $choices = [];
        foreach ($posts as $post_id) {
            $point = Repository::get_meeting_point((int) $post_id);
            if (!$point) {
                continue;
            }

            $choices[] = [
                'id' => $point['id'],
                'title' => $point['title'],
            ];
        }

        return $choices;
    }
}

