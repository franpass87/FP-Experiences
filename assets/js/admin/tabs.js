/**
 * Admin Tabs Module
 */
(function () {
    'use strict';

    function initTabs(root) {
        const tabs = root.querySelectorAll('[data-tab]');
        if (!tabs.length) return;

        const tablist = root.querySelector('[role="tablist"]') || root.querySelector('.fp-exp-tabs');
        
        // Fix: Assicura che gli eventi della tastiera non vengano intercettati dalle tab
        // quando il focus è su un input, textarea o elemento contenteditable
        if (tablist) {
            tablist.addEventListener('keydown', function(e) {
                const activeElement = document.activeElement;
                
                // Se il focus è su un input, textarea o elemento contenteditable,
                // NON intercettare gli eventi della tastiera (lasciali passare)
                if (activeElement && (
                    activeElement.tagName === 'INPUT' || 
                    activeElement.tagName === 'TEXTAREA' || 
                    activeElement.isContentEditable === true ||
                    activeElement.contentEditable === 'true'
                )) {
                    // Lascia che l'evento venga gestito normalmente dall'elemento editabile
                    // NON fare preventDefault o stopPropagation
                    return;
                }
                
                // Se il focus è su una tab, gestisci la navigazione da tastiera (solo frecce)
                // Questo è il comportamento standard ARIA per le tab
                if (activeElement && activeElement.getAttribute('role') === 'tab') {
                    // Gestisci solo frecce, Home, End - lascia passare altri tasti
                    if (e.key === 'ArrowLeft' || e.key === 'ArrowRight' || 
                        e.key === 'Home' || e.key === 'End' || 
                        e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                        // Gestione navigazione tab (opzionale - può essere implementata in futuro)
                        // Per ora, lascia che WordPress core gestisca la navigazione
                        return;
                    }
                }
            }, { capture: false });
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                
                const tabId = this.getAttribute('data-tab');
                const panel = root.querySelector(`[data-tab-panel="${tabId}"]`);
                
                if (!panel) return;

                // Aggiorna tab attivo
                root.querySelectorAll('[data-tab]').forEach(t => {
                    t.setAttribute('aria-selected', 'false');
                    t.classList.remove('is-active');
                });
                
                this.setAttribute('aria-selected', 'true');
                this.classList.add('is-active');

                // Mostra panel corrispondente
                root.querySelectorAll('[data-tab-panel]').forEach(p => {
                    p.setAttribute('hidden', '');
                    p.classList.remove('is-active');
                });
                
                panel.removeAttribute('hidden');
                panel.classList.add('is-active');
            });
        });
    }

    // Esporta funzione
    window.fpExpAdmin = window.fpExpAdmin || {};
    window.fpExpAdmin.initTabs = initTabs;

})();
