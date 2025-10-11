/**
 * Admin Taxonomy Editors Module
 */
(function () {
    'use strict';

    function initTaxonomyEditors(root) {
        const editors = root.querySelectorAll('[data-fp-taxonomy-editor]');
        if (!editors.length) return;

        editors.forEach(editor => {
            const addButton = editor.querySelector('[data-fp-taxonomy-add]');
            const list = editor.querySelector('[data-fp-taxonomy-list]');
            const template = editor.querySelector('[data-fp-taxonomy-template]');

            if (!addButton || !list || !template) return;

            addButton.addEventListener('click', function(e) {
                e.preventDefault();
                addTaxonomyItem(list, template);
            });

            // Gestione rimozione elementi
            list.addEventListener('click', function(e) {
                if (e.target.matches('[data-fp-taxonomy-remove]')) {
                    e.preventDefault();
                    const item = e.target.closest('[data-fp-taxonomy-item]');
                    if (item) {
                        removeTaxonomyItem(item);
                    }
                }
            });
        });
    }

    function addTaxonomyItem(list, template) {
        const clone = template.content.cloneNode(true);
        const item = clone.querySelector('[data-fp-taxonomy-item]');
        
        if (item) {
            // Aggiorna indici dei campi
            const fields = item.querySelectorAll('input, select, textarea');
            fields.forEach(field => {
                const name = field.getAttribute('name');
                if (name) {
                    field.setAttribute('name', name.replace(/\[\d+\]/, `[${Date.now()}]`));
                }
            });
            
            list.appendChild(item);
        }
    }

    function removeTaxonomyItem(item) {
        item.remove();
    }

    // Esporta funzioni
    window.fpExpAdmin = window.fpExpAdmin || {};
    window.fpExpAdmin.initTaxonomyEditors = initTaxonomyEditors;

})();
