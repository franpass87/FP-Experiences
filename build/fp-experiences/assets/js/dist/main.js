/* assets/js/admin/main.js */
/**
 * Admin Main JavaScript - Carica tutti i moduli
 */
(function () {
    'use strict';

    // Carica moduli in sequenza
    function loadModules() {
        // Core deve essere caricato per primo
        if (window.fpExpAdmin && window.fpExpAdmin.ready) {
            window.fpExpAdmin.ready(function() {
                // Inizializza tutti i moduli
                initializeModules();
            });
        }
    }

    function initializeModules() {
        const root = document.body;
        
        // Inizializza moduli disponibili
        if (window.fpExpAdmin.initTabs) {
            window.fpExpAdmin.initTabs(root);
        }
        
        if (window.fpExpAdmin.initMediaControls) {
            window.fpExpAdmin.initMediaControls(root);
        }
        
        if (window.fpExpAdmin.initGalleryControls) {
            window.fpExpAdmin.initGalleryControls(root);
        }
        
        if (window.fpExpAdmin.initTaxonomyEditors) {
            window.fpExpAdmin.initTaxonomyEditors(root);
        }
        
        if (window.fpExpAdmin.initRepeaters) {
            window.fpExpAdmin.initRepeaters(root);
        }
        
        if (window.fpExpAdmin.initFormValidation) {
            window.fpExpAdmin.initFormValidation(root);
        }
        
        if (window.fpExpAdmin.initRecurrence) {
            window.fpExpAdmin.initRecurrence(root);
        }
        
        if (window.fpExpAdmin.initCalendarApp) {
            window.fpExpAdmin.initCalendarApp();
        }
        
        if (window.fpExpAdmin.initTools) {
            window.fpExpAdmin.initTools();
        }
    }

    // Inizializza quando il DOM è pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadModules);
    } else {
        loadModules();
    }

})();


