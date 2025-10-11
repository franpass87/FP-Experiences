/**
 * Admin Tabs Module
 */
(function () {
    'use strict';

    function initTabs(root) {
        const tabs = root.querySelectorAll('[data-tab]');
        if (!tabs.length) return;

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
