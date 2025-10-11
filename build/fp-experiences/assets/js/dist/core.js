/* assets/js/admin/core.js */
/**
 * Core Admin JavaScript - Funzioni di base e utilità
 */
(function () {
    'use strict';

    // Funzioni di utilità globali
    const translateAdmin = (window.fpExpTranslate && window.fpExpTranslate.localize) ? window.fpExpTranslate.localize : (value) => value;
    
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function getString(key) {
        return translateAdmin(key);
    }

    // Esporta funzioni globali
    window.fpExpAdmin = {
        ready,
        getString,
        translate: translateAdmin
    };

})();


