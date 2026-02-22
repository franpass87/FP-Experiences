<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Core\Hook\HookableInterface;
use FP_Exp\Utils\Helpers;

use function add_action;
use function admin_url;
use function add_query_arg;
use function esc_attr__;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function get_current_screen;
use function settings_errors;
use function settings_fields;
use function do_settings_sections;
use function submit_button;
use function wp_die;
use function wp_enqueue_script;
use function wp_enqueue_style;

final class EmailsPage implements HookableInterface
{
    private SettingsPage $settings_page;

    public function __construct(SettingsPage $settings_page)
    {
        $this->settings_page = $settings_page;
    }

    public function register_hooks(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(): void
    {
        $screen = get_current_screen();
        // Verifica anche il hook e il page parameter per maggiore sicurezza
        $is_emails_page = $screen && (
            'fp-exp-dashboard_page_fp_exp_emails' === $screen->id ||
            (isset($_GET['page']) && $_GET['page'] === 'fp_exp_emails')
        );
        
        if (! $is_emails_page) {
            return;
        }

        $admin_css = Helpers::resolve_asset_rel([
            'assets/css/dist/fp-experiences-admin.min.css',
            'assets/css/admin.css',
        ]);
        wp_enqueue_style(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . $admin_css,
            [],
            Helpers::asset_version($admin_css)
        );

        $admin_js = Helpers::resolve_asset_rel([
            'assets/js/dist/fp-experiences-admin.min.js',
            'assets/js/admin.js',
        ]);
        wp_enqueue_script(
            'fp-exp-admin',
            FP_EXP_PLUGIN_URL . $admin_js,
            ['jquery'],
            Helpers::asset_version($admin_js),
            true
        );

        // Config base per fpExpAdmin
        wp_localize_script('fp-exp-admin', 'fpExpAdmin', [
            'restUrl' => rest_url('fp-exp/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'pluginUrl' => FP_EXP_PLUGIN_URL,
            'strings' => [],
        ]);
    }

    public function render_page(): void
    {
        if (! Helpers::can_manage_fp()) {
            wp_die(esc_html__('Non hai i permessi per gestire le impostazioni email.', 'fp-experiences'));
        }

		$tabs = $this->get_tabs();
		$active_tab = $this->get_active_tab($tabs);

        echo '<div class="wrap fp-exp-emails-page">';
        echo '<div class="fp-exp-admin" data-fp-exp-admin>';
        echo '<div class="fp-exp-admin__body">';
        echo '<div class="fp-exp-admin__layout fp-exp-emails">';
        echo '<header class="fp-exp-admin__header">';
        echo '<nav class="fp-exp-admin__breadcrumb" aria-label="' . esc_attr__('Percorso di navigazione', 'fp-experiences') . '">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp_exp_dashboard')) . '">' . esc_html__('FP Experiences', 'fp-experiences') . '</a>';
        echo ' <span aria-hidden="true">›</span> ';
        echo '<span>' . esc_html__('Email', 'fp-experiences') . '</span>';
        echo '</nav>';
        echo '<h1 class="fp-exp-admin__title">' . esc_html__('Gestione email', 'fp-experiences') . '</h1>';
        echo '<p class="fp-exp-admin__intro">' . esc_html__('Configura mittenti, destinatari, branding e integrazioni per le comunicazioni automatiche.', 'fp-experiences') . '</p>';
        echo '</header>';

        settings_errors('fp_exp_settings');

		// Tabs navigation
		echo '<div class="fp-exp-tabs nav-tab-wrapper">';
		foreach ($tabs as $slug => $label) {
			$url = add_query_arg([
				'page' => 'fp_exp_emails',
				'tab' => $slug,
			], admin_url('admin.php'));
			$classes = 'nav-tab' . ($active_tab === $slug ? ' nav-tab-active' : '');
			echo '<a class="' . esc_attr($classes) . '" href="' . esc_attr($url) . '">' . esc_html($label) . '</a>';
		}
		echo '</div>';

		echo '<div class="fp-exp-settings__panel">';
		$this->render_tab_panel($active_tab);
		echo '</div>';

        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

	private function render_tab_panel(string $tab): void
	{
		$tab_titles = [
			'senders'  => esc_html__('Mittenti e destinatari', 'fp-experiences'),
			'branding' => esc_html__('Branding email', 'fp-experiences'),
			'config'   => esc_html__('Configurazione invii', 'fp-experiences'),
			'previews' => esc_html__('Anteprime email', 'fp-experiences'),
			'brevo'    => esc_html__('Integrazione Brevo', 'fp-experiences'),
		];

		$page_slugs = [
			'senders'  => 'fp_exp_emails_senders',
			'branding' => 'fp_exp_emails_look',
			'config'   => 'fp_exp_emails_config',
			'previews' => 'fp_exp_emails_previews',
			'brevo'    => 'fp_exp_settings_brevo',
		];

		$title = $tab_titles[$tab] ?? '';
		if ($title) {
			echo '<h2>' . $title . '</h2>';
		}

		if ('previews' === $tab) {
			do_settings_sections($page_slugs[$tab]);
			return;
		}

		$option_group = ('brevo' === $tab) ? 'fp_exp_settings_brevo' : 'fp_exp_settings_emails';

		echo '<form action="options.php" method="post" class="fp-exp-settings__form">';
		settings_fields($option_group);
		do_settings_sections($page_slugs[$tab]);
		submit_button();
		echo '</form>';
	}

	/**
	 * @return array<string, string>
	 */
	private function get_tabs(): array
	{
		return [
			'senders'  => esc_html__('Mittenti', 'fp-experiences'),
			'branding' => esc_html__('Branding', 'fp-experiences'),
			'config'   => esc_html__('Configurazione', 'fp-experiences'),
			'previews' => esc_html__('Anteprime', 'fp-experiences'),
			'brevo'    => esc_html__('Brevo', 'fp-experiences'),
		];
	}

	/**
	 * @param array<string, string> $tabs
	 */
	private function get_active_tab(array $tabs): string
	{
		$default = 'senders';
		$requested = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : $default; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return array_key_exists($requested, $tabs) ? $requested : $default;
	}
}
