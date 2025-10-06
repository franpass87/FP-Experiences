/**
 * Frontend JavaScript - Entry point per il frontend
 * Questo file serve come entry point per il frontend
 */

// Carica i moduli frontend necessari
(function() {
    'use strict';
    
    // Verifica che jQuery sia disponibile
    if (typeof jQuery === 'undefined') {
        console.warn('FP Experiences: jQuery non trovato');
        return;
    }
    
    // Inizializza quando il documento è pronto
    jQuery(document).ready(function($) {
        console.log('FP Experiences Frontend: Inizializzato');
        
        // Qui possono essere aggiunte inizializzazioni specifiche per il frontend
        // se necessario in futuro
    });
    
})();