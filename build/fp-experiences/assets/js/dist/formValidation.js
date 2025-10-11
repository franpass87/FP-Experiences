/* assets/js/admin/form-validation.js */
/**
 * Admin Form Validation Module
 */
(function () {
    'use strict';

    function initFormValidation(root) {
        const forms = root.querySelectorAll('form[data-fp-validate]');
        if (!forms.length) return;

        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!validateForm(form)) {
                    e.preventDefault();
                }
            });

            // Validazione in tempo reale
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    validateField(this);
                });
            });
        });
    }

    function validateForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            if (!validateField(input)) {
                isValid = false;
            }
        });
        
        return isValid;
    }

    function validateField(field) {
        const value = field.value.trim();
        const type = field.type;
        const required = field.hasAttribute('required');
        
        let isValid = true;
        let message = '';
        
        // Validazione required
        if (required && !value) {
            isValid = false;
            message = window.fpExpAdmin.getString('This field is required');
        }
        
        // Validazione email
        if (type === 'email' && value && !isValidEmail(value)) {
            isValid = false;
            message = window.fpExpAdmin.getString('Please enter a valid email address');
        }
        
        // Validazione numerica
        if (type === 'number' && value && isNaN(value)) {
            isValid = false;
            message = window.fpExpAdmin.getString('Please enter a valid number');
        }
        
        // Aggiorna stato del campo
        updateFieldValidation(field, isValid, message);
        
        return isValid;
    }

    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function updateFieldValidation(field, isValid, message) {
        const errorElement = field.parentNode.querySelector('.fp-exp-field-error');
        
        if (isValid) {
            field.classList.remove('is-invalid');
            field.setAttribute('aria-invalid', 'false');
            if (errorElement) {
                errorElement.remove();
            }
        } else {
            field.classList.add('is-invalid');
            field.setAttribute('aria-invalid', 'true');
            
            if (errorElement) {
                errorElement.textContent = message;
            } else {
                const error = document.createElement('div');
                error.className = 'fp-exp-field-error';
                error.textContent = message;
                field.parentNode.appendChild(error);
            }
        }
    }

    // Esporta funzioni
    window.fpExpAdmin = window.fpExpAdmin || {};
    window.fpExpAdmin.initFormValidation = initFormValidation;

})();


