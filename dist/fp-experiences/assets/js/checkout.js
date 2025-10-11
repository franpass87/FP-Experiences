(function () {
    'use strict';

    const translate = (window.fpExpTranslate && window.fpExpTranslate.localize) ? window.fpExpTranslate.localize : (value) => value;

    function showErrorSummary(container, errors) {
        if (!container) {
            return;
        }

        container.innerHTML = '';

        if (!errors.length) {
            container.hidden = true;
            return;
        }

        const intro = document.createElement('p');
        intro.className = 'fp-exp-error-summary__intro';
        intro.textContent = container.getAttribute('data-intro') || translate('Controlla i campi evidenziati.');
        container.appendChild(intro);

        const list = document.createElement('ul');
        list.className = 'fp-exp-error-summary__list';

        errors.forEach((error) => {
            const item = document.createElement('li');
            if (error.id) {
                const link = document.createElement('a');
                link.href = `#${error.id}`;
                link.textContent = error.message;
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    const target = container.ownerDocument.getElementById(error.id);
                    if (target && typeof target.focus === 'function') {
                        target.focus();
                    }
                });
                item.appendChild(link);
            } else {
                item.textContent = error.message;
            }
            list.appendChild(item);
        });

        container.appendChild(list);
        container.hidden = false;
        container.focus();
    }

    function hideErrorSummary(container) {
        if (!container) {
            return;
        }

        container.hidden = true;
        container.innerHTML = '';
    }

    function markFieldInvalid(field) {
        if (!field) {
            return;
        }

        field.setAttribute('aria-invalid', 'true');
        field.classList.add('is-invalid');
    }

    function clearFieldInvalidState(field) {
        if (!field) {
            return;
        }

        field.removeAttribute('aria-invalid');
        field.classList.remove('is-invalid');
    }

    function getFieldLabel(form, field) {
        if (!field || !form) {
            return 'field';
        }

        if (field.id) {
            const label = form.querySelector(`label[for="${field.id}"]`);
            if (label && label.textContent) {
                return label.textContent.replace(/[*:]/g, '').trim() || field.name || 'field';
            }
        }

        return field.name || 'field';
    }

    function validateEmail(value) {
        if (!value) {
            return false;
        }

        const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return pattern.test(value);
    }

    function validateCheckoutForm(form) {
        const errors = [];
        if (!form) {
            return errors;
        }

        const requiredTemplate = form.getAttribute('data-error-required') || translate('Completa il campo %s.');
        const emailMessage = form.getAttribute('data-error-email') || 'Enter a valid email address.';

        const requiredFields = Array.from(form.querySelectorAll('[required]'));
        requiredFields.forEach((field) => {
            const isCheckbox = field instanceof HTMLInputElement && field.type === 'checkbox';
            const value = isCheckbox ? (field.checked ? '1' : '') : (field.value || '').trim();
            if (!value) {
                markFieldInvalid(field);
                const label = getFieldLabel(form, field);
                errors.push({ id: field.id, message: requiredTemplate.replace('%s', label) });
            }
        });

        const emailField = form.querySelector('input[type="email"]');
        if (emailField) {
            const value = (emailField.value || '').trim();
            if (value && !validateEmail(value)) {
                markFieldInvalid(emailField);
                errors.push({ id: emailField.id, message: emailMessage });
            }
        }

        return errors;
    }

    function onReady(callback) {
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            callback();
        } else {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        }
    }

    function init() {
        document.querySelectorAll('[data-fp-shortcode="checkout"] form.fp-exp-checkout__form').forEach((form) => {
            const errorSummary = form.querySelector('[data-fp-error-summary]');
            const container = form.closest('[data-fp-shortcode="checkout"]');

            form.querySelectorAll('input, select, textarea').forEach((field) => {
                const eventName = field.type === 'checkbox' ? 'change' : 'input';
                field.addEventListener(eventName, () => {
                    clearFieldInvalidState(field);
                });
            });

            form.addEventListener('submit', (event) => {
                event.preventDefault();

                hideErrorSummary(errorSummary);

                const validationErrors = validateCheckoutForm(form);
                if (validationErrors.length) {
                    showErrorSummary(errorSummary, validationErrors);
                    return;
                }

                const formData = new FormData(form);
                const payload = Object.fromEntries(formData.entries());

                form.dispatchEvent(new CustomEvent('fpExpCheckoutSubmit', {
                    bubbles: true,
                    detail: {
                        payload,
                        nonce: form.getAttribute('data-nonce'),
                    },
                }));
            });

            // Handle the custom checkout submit event: create order and redirect to payment
            form.addEventListener('fpExpCheckoutSubmit', async (event) => {
                const detail = event && event.detail ? event.detail : {};
                const submitButton = form.querySelector('.fp-exp-checkout__submit');

                const setSubmitting = (submitting) => {
                    if (submitButton) {
                        submitButton.disabled = Boolean(submitting);
                        if (submitting) {
                            submitButton.setAttribute('aria-busy', 'true');
                        } else {
                            submitButton.removeAttribute('aria-busy');
                        }
                    }
                };

                const showGenericError = (message) => {
                    const errors = [{ message: message || (translate ? translate('Impossibile generare l’ordine di pagamento. Riprova.') : 'Impossibile generare l’ordine di pagamento. Riprova.') }];
                    showErrorSummary(errorSummary, errors);
                };

                try {
                    hideErrorSummary(errorSummary);
                    setSubmitting(true);

                    const config = (typeof window !== 'undefined' && window.fpExpConfig) ? window.fpExpConfig : {};
                    const restUrl = (config.restUrl || '').replace(/\/?$/, '/');
                    const ajaxUrl = config.ajaxUrl || '';

                    const payload = detail.payload || {};
                    const body = {
                        nonce: detail.nonce || '',
                        contact: payload.contact || {},
                        billing: payload.billing || {},
                        consent: payload.consent || {},
                    };

                    let paymentUrl = '';

                    // Try REST first
                    if (restUrl) {
                        try {
                            const headers = { 'Content-Type': 'application/json' };
                            // Usa il nonce wp_rest per l'autenticazione WordPress nell'header
                            // Il nonce fp-exp-checkout viene inviato nel body
                            if (config.restNonce) {
                                headers['X-WP-Nonce'] = config.restNonce;
                            }

                            const res = await fetch(restUrl + 'checkout', {
                                method: 'POST',
                                headers,
                                credentials: 'same-origin',
                                body: JSON.stringify(body),
                            });

                            const data = await res.json().catch(() => ({}));
                            if (res.ok && data && data.payment_url) {
                                paymentUrl = String(data.payment_url);
                            } else if ((res.status === 401 || res.status === 403)) {
                                // Permesso negato: ricadi immediatamente su AJAX
                            } else if (!res.ok && data && data.message) {
                                showGenericError(String(data.message));
                            }
                        } catch (e) {
                            // ignore and fallback to AJAX
                        }
                    }

                    // Fallback to AJAX if needed
                    if (!paymentUrl && ajaxUrl) {
                        try {
                            const fd = new FormData();
                            fd.set('action', 'fp_exp_checkout');
                            fd.set('nonce', body.nonce);
                            fd.set('contact', JSON.stringify(body.contact));
                            fd.set('billing', JSON.stringify(body.billing));
                            fd.set('consent', JSON.stringify(body.consent));

                            const res = await fetch(ajaxUrl, {
                                method: 'POST',
                                credentials: 'same-origin',
                                body: fd,
                            });

                            const data = await res.json().catch(() => ({}));
                            if (data && data.success && data.data && data.data.payment_url) {
                                paymentUrl = String(data.data.payment_url);
                            } else if (data && data.data && data.data.message) {
                                showGenericError(String(data.data.message));
                            }
                        } catch (e) {
                            // network error
                        }
                    }

                    if (paymentUrl) {
                        window.location.assign(paymentUrl);
                        return;
                    }

                    // If we got here, no redirect happened
                    showGenericError();
                } finally {
                    setSubmitting(false);
                }
            }, { once: false });

            // Pulsante sblocco carrello
            const unlockBtn = container && container.querySelector('.fp-exp-checkout__unlock');
            if (unlockBtn) {
                unlockBtn.addEventListener('click', async () => {
                    const config = (typeof window !== 'undefined' && window.fpExpConfig) ? window.fpExpConfig : {};
                    const restUrl = (config.restUrl || '').replace(/\/?$/, '/');
                    try {
                        if (restUrl) {
                            const headers = { 'Content-Type': 'application/json' };
                            if (config.restNonce) headers['X-WP-Nonce'] = config.restNonce;
                            const res = await fetch(restUrl + 'cart/unlock', { method: 'POST', headers, credentials: 'same-origin' });
                            if (!res.ok) throw new Error('rest_unlock_failed');
                        }
                    } catch (e) {
                        // Fallback AJAX
                        try {
                            const fd = new FormData();
                            fd.set('action', 'fp_exp_unlock_cart');
                            fd.set('nonce', config.checkoutNonce || '');
                            await fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd });
                        } catch (e2) {
                            // Ultimo fallback: reset cookie sessione
                            document.cookie = 'fp_exp_sid=; Max-Age=0; path=/';
                        }
                    }
                    location.reload();
                });
            }
        });
    }

    onReady(init);
}());
