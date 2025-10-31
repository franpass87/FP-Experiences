/**
 * Admin Tools Module
 */
(function () {
    'use strict';

    function initTools() {
        initEmailSubjectPreview();
        initCognitiveBiasLimiter();
        initToolButtons();
    }

    function initToolButtons() {
        const toolsContainer = document.querySelector('[data-fp-exp-tools]');
        if (!toolsContainer) return;

        const buttons = toolsContainer.querySelectorAll('button[data-action]');
        if (!buttons.length) return;

        buttons.forEach(button => {
            button.addEventListener('click', async function(e) {
                e.preventDefault();
                
                const action = this.getAttribute('data-action');
                if (!action) return;

                // Disable button
                this.disabled = true;
                const originalText = this.textContent;
                this.textContent = 'Esecuzione...';

                try {
                    const nonce = window.fpExpTools?.nonce || window.fpExpAdmin?.restNonce || '';
                    const response = await fetch(`/wp-json/fp-exp/v1/tools/${action}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': nonce
                        },
                        credentials: 'same-origin'
                    });

                    const data = await response.json();

                    // Show result
                    showToolResult(data, toolsContainer);

                } catch (error) {
                    showToolResult({
                        success: false,
                        message: error.message || 'Errore durante l\'esecuzione del tool'
                    }, toolsContainer);
                } finally {
                    // Re-enable button
                    this.disabled = false;
                    this.textContent = originalText;
                }
            });
        });
    }

    function showToolResult(data, container) {
        const output = container.querySelector('.fp-exp-tools__output');
        if (!output) return;

        const isSuccess = data.success !== false;
        const message = data.message || (isSuccess ? 'Operazione completata' : 'Operazione fallita');

        output.innerHTML = `
            <div class="notice notice-${isSuccess ? 'success' : 'error'} is-dismissible" style="margin: 20px 0;">
                <p><strong>${message}</strong></p>
                ${data.details ? `<pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">${JSON.stringify(data.details, null, 2)}</pre>` : ''}
                ${data.rebuilt !== undefined ? `<p>Esperienze aggiornate: ${data.rebuilt}</p>` : ''}
                ${data.cleaned !== undefined ? `<p>Elementi puliti: ${data.cleaned}</p>` : ''}
            </div>
        `;

        // Scroll to result
        output.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        // Auto-dismiss after 10 seconds
        setTimeout(() => {
            const dismissBtn = output.querySelector('.notice-dismiss');
            if (dismissBtn) {
                dismissBtn.click();
            }
        }, 10000);
    }

    function initEmailSubjectPreview() {
        const previewElements = document.querySelectorAll('[data-fp-email-subject-preview]');
        if (!previewElements.length) return;

        previewElements.forEach(element => {
            const input = element.querySelector('input[type="text"]');
            const preview = element.querySelector('[data-fp-email-preview]');
            
            if (!input || !preview) return;
            
            input.addEventListener('input', function() {
                updateEmailPreview(this.value, preview);
            });
            
            // Inizializza preview
            updateEmailPreview(input.value, preview);
        });
    }

    function updateEmailPreview(subject, preview) {
        if (!subject.trim()) {
            preview.textContent = window.fpExpAdmin.getString('No subject');
            return;
        }
        
        preview.textContent = subject;
    }

    function initCognitiveBiasLimiter() {
        const limiters = document.querySelectorAll('[data-fp-cognitive-bias-limiter]');
        if (!limiters.length) return;

        limiters.forEach(limiter => {
            const input = limiter.querySelector('input, textarea');
            const counter = limiter.querySelector('[data-fp-cognitive-bias-counter]');
            const maxLength = parseInt(limiter.getAttribute('data-fp-cognitive-bias-max')) || 100;
            
            if (!input || !counter) return;
            
            input.addEventListener('input', function() {
                const length = this.value.length;
                const remaining = maxLength - length;
                
                counter.textContent = `${length}/${maxLength}`;
                
                if (remaining < 0) {
                    counter.classList.add('is-over-limit');
                    input.classList.add('is-over-limit');
                } else {
                    counter.classList.remove('is-over-limit');
                    input.classList.remove('is-over-limit');
                }
            });
            
            // Inizializza counter
            const length = input.value.length;
            counter.textContent = `${length}/${maxLength}`;
        });
    }

    // Esporta funzioni
    window.fpExpAdmin = window.fpExpAdmin || {};
    window.fpExpAdmin.initTools = initTools;

})();
