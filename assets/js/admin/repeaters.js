/**
 * Admin Repeaters Module
 */
(function () {
    'use strict';

    function initRepeaters(root) {
        const repeaters = root.querySelectorAll('[data-fp-repeater]');
        if (!repeaters.length) return;

        repeaters.forEach(repeater => {
            const addButton = repeater.querySelector('[data-fp-repeater-add]');
            const list = repeater.querySelector('[data-fp-repeater-list]');
            const template = repeater.querySelector('[data-fp-repeater-template]');

            if (!addButton || !list || !template) return;

            addButton.addEventListener('click', function(e) {
                e.preventDefault();
                addRepeaterItem(list, template);
            });

            // Gestione rimozione elementi
            list.addEventListener('click', function(e) {
                if (e.target.matches('[data-fp-repeater-remove]')) {
                    e.preventDefault();
                    const item = e.target.closest('[data-fp-repeater-item]');
                    if (item) {
                        removeRepeaterItem(item);
                    }
                }
            });

            // Gestione drag and drop
            initDragAndDrop(list);
        });
    }

    function addRepeaterItem(list, template) {
        const clone = template.content.cloneNode(true);
        const item = clone.querySelector('[data-fp-repeater-item]');
        
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

    function removeRepeaterItem(item) {
        item.remove();
    }

    function initDragAndDrop(list) {
        let draggedElement = null;

        list.addEventListener('dragstart', function(e) {
            if (e.target.matches('[data-fp-repeater-item]')) {
                draggedElement = e.target;
                e.target.classList.add('is-dragging');
            }
        });

        list.addEventListener('dragover', function(e) {
            e.preventDefault();
            const afterElement = getDragAfterElement(list, e.clientY);
            if (afterElement == null) {
                list.appendChild(draggedElement);
            } else {
                list.insertBefore(draggedElement, afterElement);
            }
        });

        list.addEventListener('dragend', function(e) {
            if (e.target.matches('[data-fp-repeater-item]')) {
                e.target.classList.remove('is-dragging');
                draggedElement = null;
            }
        });
    }

    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('[data-fp-repeater-item]:not(.is-dragging)')];
        
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    // Esporta funzioni
    window.fpExpAdmin = window.fpExpAdmin || {};
    window.fpExpAdmin.initRepeaters = initRepeaters;

})();
