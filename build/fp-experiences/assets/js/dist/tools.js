/* assets/js/admin/tools.js */
/**
 * Admin Tools Module
 */
(function () {
    'use strict';

    function initTools() {
        initEmailSubjectPreview();
        initCognitiveBiasLimiter();
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


