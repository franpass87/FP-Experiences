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

    /**
     * Badge personalizzati esperienza: clone template, indice __INDEX__, rimozione riga.
     *
     * @param {ParentNode} root
     */
    function initExperienceBadgeCustomEditors(root) {
        if (!root) {
            return;
        }

        const editors = root.querySelectorAll('[data-fp-exp-badge-custom-editor]');

        editors.forEach((editor) => {
            if (!(editor instanceof HTMLElement) || editor.dataset.fpExpBadgeCustomInit === '1') {
                return;
            }

            const template = editor.querySelector('template[data-fp-exp-badge-template]');
            const addButton = editor.querySelector('[data-fp-exp-badge-add]');
            const list = editor.querySelector('[data-fp-exp-badge-custom-list]');

            if (!template || !list || !(addButton instanceof HTMLElement)) {
                return;
            }

            editor.dataset.fpExpBadgeCustomInit = '1';

            function assignTemplateNames(scope) {
                scope.querySelectorAll('[data-name]').forEach((field) => {
                    if (field.closest('template')) {
                        return;
                    }

                    const templateName = field.getAttribute('data-name');
                    if (!templateName) {
                        return;
                    }

                    field.setAttribute('name', templateName);
                    field.removeAttribute('data-name');
                });
            }

            function bindRemove(target) {
                target.querySelectorAll('[data-fp-exp-badge-remove]').forEach((button) => {
                    button.addEventListener('click', (event) => {
                        event.preventDefault();
                        const item = button.closest('[data-fp-exp-badge-item]');
                        if (item) {
                            item.remove();
                        }
                    });
                });
            }

            function initBadgeIconPreviews(scope) {
                if (!scope) {
                    return;
                }

                scope.querySelectorAll('.fp-exp-badge-icon-select').forEach((selectEl) => {
                    if (!(selectEl instanceof HTMLSelectElement)) {
                        return;
                    }
                    if (selectEl.dataset.fpBadgeIconPreviewInit === '1') {
                        return;
                    }
                    selectEl.dataset.fpBadgeIconPreviewInit = '1';

                    const field = selectEl.closest('.fp-exp-badge-icon-field');
                    const preview = field ? field.querySelector('[data-fp-exp-badge-icon-preview]') : null;
                    if (!preview) {
                        return;
                    }

                    function sync() {
                        while (preview.firstChild) {
                            preview.removeChild(preview.firstChild);
                        }
                        const opt = selectEl.options[selectEl.selectedIndex];
                        const cls = opt ? opt.getAttribute('data-fp-fa-classes') : '';
                        if (!cls) {
                            return;
                        }
                        const i = document.createElement('i');
                        i.setAttribute('class', cls);
                        i.setAttribute('aria-hidden', 'true');
                        preview.appendChild(i);
                    }

                    selectEl.addEventListener('change', sync);
                    sync();
                });
            }

            function addRow(focus) {
                const nextIndex = parseInt(editor.dataset.fpExpBadgeNextIndex || '0', 10) || 0;
                const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                const row = wrapper.firstElementChild;

                if (!row) {
                    return;
                }

                assignTemplateNames(row);
                bindRemove(row);
                list.appendChild(row);
                initBadgeIconPreviews(row);
                editor.dataset.fpExpBadgeNextIndex = String(nextIndex + 1);

                if (focus) {
                    const focusable = row.querySelector('input, select, textarea');
                    if (focusable) {
                        focusable.focus();
                    }
                }
            }

            addButton.addEventListener('click', (event) => {
                event.preventDefault();
                addRow(true);
            });

            bindRemove(list);
            initBadgeIconPreviews(list);
        });
    }

    // Esporta funzioni
    window.fpExpAdmin = window.fpExpAdmin || {};
    window.fpExpAdmin.initTaxonomyEditors = initTaxonomyEditors;
    window.fpExpAdmin.initExperienceBadgeCustomEditors = initExperienceBadgeCustomEditors;

})();
