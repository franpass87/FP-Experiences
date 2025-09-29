(function () {
    'use strict';

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
        intro.textContent = container.getAttribute('data-intro') || 'Please review the highlighted fields.';
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

        const requiredTemplate = form.getAttribute('data-error-required') || 'Please complete the %s field.';
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
        });
    }

    onReady(init);
}());
