<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Utils\Helpers;

use function absint;
use function add_action;
use function add_submenu_page;
use function admin_url;
use function check_admin_referer;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function esc_url_raw;
use function get_current_screen;
use function get_current_user_id;
use function get_option;
use function is_array;
use function remove_submenu_page;
use function sprintf;
use function strpos;
use function time;
use function update_option;
use function wp_date;
use function wp_die;
use function wp_nonce_field;
use function wp_safe_redirect;
use function wp_unslash;

/**
 * Handles the onboarding wizard for FP Experiences administrators.
 */
final class Onboarding
{
    private const OPTION_KEY = 'fp_exp_onboarding_status';

    /**
     * Bootstraps onboarding hooks for admin users.
     */
    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_page']);
        add_action('admin_head', [$this, 'hide_menu_entry']);
        add_action('admin_notices', [$this, 'maybe_show_notice']);
        add_action('admin_post_fp_exp_onboarding_complete', [$this, 'handle_submission']);
    }

    public function register_page(): void
    {
        add_submenu_page(
            'fp_exp_dashboard',
            esc_html__('Benvenuto in FP Experiences', 'fp-experiences'),
            esc_html__('Onboarding', 'fp-experiences'),
            'fp_exp_manage',
            'fp_exp_onboarding',
            [$this, 'render']
        );
    }

    public function hide_menu_entry(): void
    {
        remove_submenu_page('fp_exp_dashboard', 'fp_exp_onboarding');
    }

    /**
     * Renders the onboarding wizard screen in the admin area.
     */
    public function render(): void
    {
        if (! Helpers::can_manage_fp()) {
            wp_die(esc_html__('You do not have permission to manage FP Experiences.', 'fp-experiences'));
        }

        $status = $this->get_status();
        $completed = $status['completed'] ?? false;
        $action_url = admin_url('admin-post.php');

        echo '<div class="wrap fp-exp-onboarding">';
        echo '<h1>' . esc_html__('FP Experiences — Onboarding', 'fp-experiences') . '</h1>';

        if ($completed) {
            $completed_at = isset($status['completed_at']) ? absint($status['completed_at']) : 0;
            echo '<div class="notice notice-success"><p>';
            echo esc_html__('✅ Onboarding completato: puoi sempre riaprire questa guida per rivedere i passaggi chiave.', 'fp-experiences');
            if ($completed_at > 0) {
                echo ' ' . esc_html(sprintf(
                    /* translators: %s: formatted date string. */
                    __('Ultimo aggiornamento: %s', 'fp-experiences'),
                    wp_date(get_option('date_format', 'F j, Y') . ' ' . get_option('time_format', 'H:i'), $completed_at)
                ));
            }
            echo '</p></div>';
        }

        echo '<p class="fp-exp-onboarding__intro">' . esc_html__('Configura rapidamente il plugin seguendo i tre step consigliati. Ogni step include shortcut verso le aree principali del plugin.', 'fp-experiences') . '</p>';

        echo '<ol class="fp-exp-onboarding__steps">';
        echo '<li class="fp-exp-onboarding__step">';
        echo '<h2>' . esc_html__('1. Crea o collega le esperienze', 'fp-experiences') . '</h2>';
        echo '<p>' . esc_html__('Importa le esperienze esistenti o creane di nuove, completando descrizioni, media e prezzi.', 'fp-experiences') . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('post-new.php?post_type=fp_experience')) . '">' . esc_html__('Nuova esperienza', 'fp-experiences') . '</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('edit.php?post_type=fp_experience')) . '">' . esc_html__('Gestisci esperienze', 'fp-experiences') . '</a></p>';
        echo '</li>';

        echo '<li class="fp-exp-onboarding__step">';
        echo '<h2>' . esc_html__('2. Imposta disponibilità e prenotazioni', 'fp-experiences') . '</h2>';
        echo '<p>' . esc_html__('Configura calendario, regole di pricing e opzioni di checkout o richiesta di prenotazione.', 'fp-experiences') . '</p>';
        echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=fp_exp_calendar')) . '">' . esc_html__('Apri calendario', 'fp-experiences') . '</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=fp_exp_settings')) . '">' . esc_html__('Vai alle impostazioni', 'fp-experiences') . '</a></p>';
        echo '</li>';

        echo '<li class="fp-exp-onboarding__step">';
        echo '<h2>' . esc_html__('3. Pubblica la vetrina e monitora', 'fp-experiences') . '</h2>';
        echo '<p>' . esc_html__('Inserisci shortcode o widget Elementor per la vetrina e la pagina esperienza, poi verifica tracking e branding.', 'fp-experiences') . '</p>';
        echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=fp_exp_help')) . '">' . esc_html__('Apri guida & shortcode', 'fp-experiences') . '</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=fp_exp_tools')) . '">' . esc_html__('Strumenti utili', 'fp-experiences') . '</a></p>';
        echo '</li>';
        echo '</ol>';

        echo '<form method="post" action="' . esc_url($action_url) . '" class="fp-exp-onboarding__actions">';
        wp_nonce_field('fp_exp_onboarding_complete');
        echo '<input type="hidden" name="action" value="fp_exp_onboarding_complete" />';
        echo '<input type="hidden" name="redirect_to" value="' . esc_attr(admin_url('admin.php?page=fp_exp_dashboard')) . '" />';
        echo '<button type="submit" class="button button-primary">' . esc_html__('Segna onboarding come completato', 'fp-experiences') . '</button>';
        echo '</form>';

        echo '</div>';
    }

    /**
     * Processes onboarding form submissions and persists settings.
     */
    public function handle_submission(): void
    {
        if (! Helpers::can_manage_fp()) {
            wp_die(esc_html__('You do not have permission to manage FP Experiences.', 'fp-experiences'));
        }

        check_admin_referer('fp_exp_onboarding_complete');

        $redirect = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash((string) $_POST['redirect_to'])) : admin_url('admin.php?page=fp_exp_dashboard');

        update_option(self::OPTION_KEY, [
            'completed' => true,
            'completed_at' => time(),
            'completed_by' => get_current_user_id(),
        ], false);

        wp_safe_redirect($redirect ?: admin_url('admin.php?page=fp_exp_dashboard'));
        exit;
    }

    public function maybe_show_notice(): void
    {
        if (! Helpers::can_manage_fp() || $this->is_completed()) {
            return;
        }

        $screen = get_current_screen();
        if (! $screen) {
            return;
        }

        $screen_id = $screen->id ?? '';
        $post_type = $screen->post_type ?? '';

        if ('toplevel_page_fp_exp_dashboard' !== $screen_id && 0 !== strpos($screen_id, 'fp-exp-dashboard_page_fp_exp_') && 'fp_experience' !== $post_type) {
            return;
        }

        $link = esc_url(admin_url('admin.php?page=fp_exp_onboarding'));

        echo '<div class="notice notice-info fp-exp-onboarding-notice">';
        echo '<p>' . esc_html__('Benvenuto! Completa l’onboarding guidato per verificare prezzi, disponibilità e tracking prima di andare live.', 'fp-experiences') . '</p>';
        echo '<p><a class="button button-primary" href="' . $link . '">' . esc_html__('Apri onboarding', 'fp-experiences') . '</a></p>';
        echo '</div>';
    }

    /**
     * @return array<string, mixed>
     */
    private function get_status(): array
    {
        $status = get_option(self::OPTION_KEY, []);

        return is_array($status) ? $status : [];
    }

    private function is_completed(): bool
    {
        $status = $this->get_status();

        return ! empty($status['completed']);
    }
}
