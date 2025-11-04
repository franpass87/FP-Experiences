<?php

declare(strict_types=1);

namespace FP_Exp\Admin;

use FP_Exp\Utils\Helpers;

use function add_action;
use function esc_html__;
use function get_current_screen;
use function settings_errors;
use function wp_die;

final class ToolsPage
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
        if (! $screen || 'fp-exp-dashboard_page_fp_exp_tools' !== $screen->id) {
            error_log('[FP-EXP-TOOLS] enqueue_assets skipped - screen: ' . ($screen ? $screen->id : 'null'));
            return;
        }

        error_log('[FP-EXP-TOOLS] ✅ Calling enqueue_tools_assets()');
        $this->settings_page->enqueue_tools_assets();
        error_log('[FP-EXP-TOOLS] ✅ enqueue_tools_assets() completed');
    }

    public function render_page(): void
    {
        if (! Helpers::can_manage_fp()) {
            wp_die(esc_html__('Non hai i permessi per eseguire gli strumenti di FP Experiences.', 'fp-experiences'));
        }

        // Force config inline se non caricato
        ?>
        <script>
        window.fpExpAdmin = window.fpExpAdmin || {};
        window.fpExpAdmin.restUrl = <?php echo wp_json_encode(rest_url('fp-exp/v1/')); ?>;
        window.fpExpAdmin.restNonce = <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>;
        window.fpExpAdmin.ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
        window.fpExpAdmin.pluginUrl = <?php echo wp_json_encode(FP_EXP_PLUGIN_URL); ?>;
        window.fpExpAdmin.strings = window.fpExpAdmin.strings || {};
        
        window.fpExpTools = window.fpExpTools || {};
        window.fpExpTools.nonce = <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>;
        window.fpExpTools.actions = <?php echo wp_json_encode($this->settings_page->get_tool_actions_localised()); ?>;
        window.fpExpTools.i18n = {
            running: <?php echo wp_json_encode(esc_html__('Running action…', 'fp-experiences')); ?>,
            success: <?php echo wp_json_encode(esc_html__('Action completed successfully.', 'fp-experiences')); ?>,
            error: <?php echo wp_json_encode(esc_html__('Action failed. Check the logs for details.', 'fp-experiences')); ?>
        };
        console.log('FP-EXP: Config loaded inline', {fpExpAdmin, fpExpTools});
        
        // Inizializza i bottoni tools direttamente
        document.addEventListener('DOMContentLoaded', function() {
            const toolsContainer = document.querySelector('[data-fp-exp-tools]');
            if (!toolsContainer) {
                console.error('FP-EXP: Tools container not found!');
                return;
            }
            
            console.log('FP-EXP: Tools container found, attaching event listeners...');
            
            const buttons = toolsContainer.querySelectorAll('button[data-action]');
            console.log('FP-EXP: Found ' + buttons.length + ' tool buttons');
            
            buttons.forEach(button => {
                button.addEventListener('click', async function(e) {
                    e.preventDefault();
                    
                    const action = this.getAttribute('data-action');
                    if (!action) return;
                    
                    console.log('FP-EXP: Executing tool:', action);
                    
                    this.disabled = true;
                    const originalText = this.textContent;
                    this.textContent = 'Esecuzione...';
                    
                    try {
                        const nonce = window.fpExpTools?.nonce || window.fpExpAdmin?.restNonce || '';
                        const endpoint = window.fpExpTools?.actions?.find(a => a.slug === action)?.endpoint;
                        
                        if (!endpoint) {
                            throw new Error('Endpoint not found for action: ' + action);
                        }
                        
                        console.log('FP-EXP: Calling endpoint:', endpoint);
                        
                        const response = await fetch(endpoint, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': nonce
                            },
                            credentials: 'same-origin'
                        });
                        
                        const data = await response.json();
                        console.log('FP-EXP: Response:', data);
                        
                        // Mostra risultato
                        const output = toolsContainer.querySelector('.fp-exp-tools__output');
                        if (output) {
                            const isSuccess = data.success !== false;
                            const message = data.message || (isSuccess ? 'Operazione completata' : 'Operazione fallita');
                            
                            output.innerHTML = '<div class="notice notice-' + (isSuccess ? 'success' : 'error') + '" style="margin: 20px 0; padding: 12px;"><p><strong>' + message + '</strong></p></div>';
                            output.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                        
                    } catch (error) {
                        console.error('FP-EXP: Tool execution failed:', error);
                        alert('Errore: ' + error.message);
                    } finally {
                        this.disabled = false;
                        this.textContent = originalText;
                    }
                });
            });
            
            console.log('FP-EXP: Tool buttons initialized successfully');
        });
        </script>
        <?php

        echo '<div class="wrap fp-exp-tools-page">';
        echo '<div class="fp-exp-admin" data-fp-exp-admin>';
        echo '<div class="fp-exp-admin__body">';
        echo '<div class="fp-exp-admin__layout fp-exp-tools">';
        echo '<header class="fp-exp-admin__header">';
        echo '<nav class="fp-exp-admin__breadcrumb" aria-label="' . esc_attr__('Percorso di navigazione', 'fp-experiences') . '">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=fp_exp_dashboard')) . '">' . esc_html__('FP Experiences', 'fp-experiences') . '</a>';
        echo ' <span aria-hidden="true">›</span> ';
        echo '<span>' . esc_html__('Strumenti', 'fp-experiences') . '</span>';
        echo '</nav>';
        echo '<h1 class="fp-exp-admin__title">' . esc_html__('Strumenti operativi', 'fp-experiences') . '</h1>';
        echo '<p class="fp-exp-admin__intro">' . esc_html__('Esegui azioni di manutenzione: sincronizzazioni Brevo, ripubblicazione eventi, pulizia cache e diagnostica.', 'fp-experiences') . '</p>';
        echo '</header>';

        settings_errors('fp_exp_settings');
        $this->settings_page->render_tools_panel();
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}
