<?php

declare(strict_types=1);

namespace FP_Exp;

use FP_Exp\Booking\Reservations;
use FP_Exp\Booking\Resources;
use FP_Exp\Booking\Slots;
use FP_Exp\PostTypes\ExperienceCPT;

use function __;
use function add_role;
use function flush_rewrite_rules;
use function get_role;

final class Activation
{
    public static function activate(): void
    {
        $cpt = new ExperienceCPT();
        $cpt->register_immediately();

        Slots::create_table();
        Reservations::create_table();
        Resources::create_table();

        self::register_roles();

        flush_rewrite_rules();

        do_action('fp_exp_plugin_activated');
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();

        do_action('fp_exp_plugin_deactivated');
    }

    private static function register_roles(): void
    {
        $experience_caps = [
            'edit_fp_experience' => true,
            'read_fp_experience' => true,
            'delete_fp_experience' => true,
            'edit_fp_experiences' => true,
            'edit_others_fp_experiences' => true,
            'publish_fp_experiences' => true,
            'read_private_fp_experiences' => true,
            'delete_fp_experiences' => true,
            'delete_others_fp_experiences' => true,
            'delete_private_fp_experiences' => true,
            'delete_published_fp_experiences' => true,
            'edit_private_fp_experiences' => true,
            'edit_published_fp_experiences' => true,
        ];

        $manager_caps = array_merge(
            [
                'read' => true,
                'edit_posts' => true,
                'upload_files' => true,
                'fp_exp_manage' => true,
                'fp_exp_operate' => true,
                'fp_exp_guide' => true,
            ],
            $experience_caps
        );

        add_role(
            'fp_exp_manager',
            __('FP Experiences Manager', 'fp-experiences'),
            $manager_caps
        );

        $operator_caps = array_merge(
            [
                'read' => true,
                'edit_posts' => true,
                'fp_exp_operate' => true,
                'fp_exp_guide' => true,
            ],
            $experience_caps
        );

        add_role(
            'fp_exp_operator',
            __('FP Experiences Operator', 'fp-experiences'),
            $operator_caps
        );

        $guide_caps = [
            'read' => true,
            'fp_exp_guide' => true,
        ];

        add_role(
            'fp_exp_guide',
            __('FP Experiences Guide', 'fp-experiences'),
            $guide_caps
        );

        $administrator = get_role('administrator');
        if ($administrator) {
            foreach (array_keys($manager_caps) as $capability) {
                if (! $administrator->has_cap($capability)) {
                    $administrator->add_cap($capability);
                }
            }
        }
    }
}
