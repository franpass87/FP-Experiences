/**
 * Admin Repeaters — allineato al markup PHP (data-repeater, fp-exp-repeater__items, template[data-repeater-template]).
 */
(function () {
    'use strict';

    function getString(key) {
        const strings = window.fpExpAdmin && window.fpExpAdmin.strings ? window.fpExpAdmin.strings : {};
        const value = strings[key];
        if (typeof value === 'string' && value !== '') {
            return value;
        }
        if (window.fpExpAdmin && typeof window.fpExpAdmin.getString === 'function') {
            return window.fpExpAdmin.getString(key) || '';
        }
        return '';
    }

    function getDragAfterElement(container, y) {
        const elements = [...container.querySelectorAll('[data-repeater-item]')];
        return elements.reduce(
            (closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset, element: child };
                }
                return closest;
            },
            { offset: Number.NEGATIVE_INFINITY, element: null }
        ).element;
    }

    /**
     * Chip orari legacy (data-time-set) annidati nei repeater.
     *
     * @param {ParentNode} scope
     */
    function initRecurrenceTimeSets(scope) {
        const containers = Array.from(scope.querySelectorAll('[data-time-set]'));
        containers.forEach((container) => {
            if (container.dataset.recurrenceInit === '1') {
                return;
            }
            const chipsContainer = container.querySelector('[data-time-set-chips]');
            if (!chipsContainer) {
                return;
            }

            container.dataset.recurrenceInit = '1';
            const addButton = container.querySelector('[data-time-set-add]');
            const baseName = container.dataset.timeSetBase || '';
            const existingChips = Array.from(chipsContainer.querySelectorAll('[data-time-set-chip] input[name]'));

            let nextIndex = parseInt(container.dataset.timeSetNextIndex || String(existingChips.length), 10) || 0;

            existingChips.forEach((input) => {
                const match = (input.name || '').match(/\[(\d+)\]$/);
                if (match) {
                    nextIndex = Math.max(nextIndex, parseInt(match[1], 10) + 1);
                }
            });

            container.dataset.timeSetNextIndex = String(nextIndex);

            function addChip(value, focus) {
                const chip = document.createElement('span');
                chip.className = 'fp-exp-chip';
                chip.setAttribute('data-time-set-chip', '');

                const label = document.createElement('label');
                const sr = document.createElement('span');
                sr.className = 'screen-reader-text';
                sr.textContent = getString('recurrenceTimeLabel') || 'Orario';

                const inputEl = document.createElement('input');
                inputEl.type = 'time';
                if (baseName) {
                    inputEl.name = `${baseName}[${nextIndex}]`;
                }
                inputEl.value = value || '';

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'fp-exp-chip__remove';
                remove.setAttribute('data-time-set-remove', '');
                remove.setAttribute('aria-label', getString('recurrenceRemoveTime') || 'Rimuovi orario');
                remove.textContent = '×';

                label.appendChild(sr);
                label.appendChild(inputEl);
                chip.appendChild(label);
                chip.appendChild(remove);
                chipsContainer.appendChild(chip);

                nextIndex += 1;
                container.dataset.timeSetNextIndex = String(nextIndex);

                if (focus && inputEl) {
                    inputEl.focus();
                }
            }

            if (addButton) {
                addButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    addChip('', true);
                });
            }

            chipsContainer.addEventListener('click', (event) => {
                const removeButton = event.target.closest('[data-time-set-remove]');
                if (!removeButton) {
                    return;
                }
                event.preventDefault();
                const chip = removeButton.closest('[data-time-set-chip]');
                if (chip) {
                    chip.remove();
                }
                if (!chipsContainer.querySelector('[data-time-set-chip]')) {
                    addChip('', false);
                }
            });

            if (!chipsContainer.querySelector('[data-time-set-chip]')) {
                addChip('', false);
            }
        });
    }

    function initEnhancementsOnNewRow(node) {
        if (window.fpExpAdmin && typeof window.fpExpAdmin.initMediaControls === 'function') {
            window.fpExpAdmin.initMediaControls(node);
        }
        if (window.fpExpAdmin && typeof window.fpExpAdmin.initGalleryControls === 'function') {
            window.fpExpAdmin.initGalleryControls(node);
        }
        if (typeof window.fpExpInitMediaControls === 'function') {
            window.fpExpInitMediaControls(node);
        }
        initRecurrenceTimeSets(node);
    }

    function initRepeaters(root) {
        if (!root || !root.querySelectorAll) {
            return;
        }

        const repeaterNodes = Array.from(root.querySelectorAll('[data-repeater]'));
        repeaterNodes.forEach((repeater) => {
            if (repeater.getAttribute('data-fp-repeater-skip') === '1') {
                return;
            }
            /* Fallback PHP inline dedicato: evita doppio click → doppia riga */
            if (
                repeater.getAttribute('data-fp-exp-addon-repeater') === '1' ||
                repeater.getAttribute('data-fp-exp-faq-repeater') === '1'
            ) {
                return;
            }

            const itemsContainer = repeater.querySelector('.fp-exp-repeater__items');
            const template = repeater.querySelector('template[data-repeater-template]');
            const hint = repeater.querySelector('[data-repeater-hint]');
            if (!itemsContainer || !template) {
                return;
            }

            function assignTemplateNames(scope) {
                Array.from(scope.querySelectorAll('[data-name]')).forEach((field) => {
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

            function reindexItems() {
                const items = Array.from(itemsContainer.querySelectorAll('[data-repeater-item], .fp-exp-repeater__item'));
                items.forEach((item, newIndex) => {
                    item.querySelectorAll('[name]').forEach((field) => {
                        const name = field.getAttribute('name');
                        if (!name) {
                            return;
                        }
                        const newName = name.replace(/\[(\d+)\](\[question\]|\[answer\])/g, `[${newIndex}]$2`);
                        if (newName !== name) {
                            field.setAttribute('name', newName);
                        }
                    });
                    const numberSpan = item.querySelector('.fp-exp-repeater__item-number');
                    if (numberSpan) {
                        numberSpan.textContent = String(newIndex + 1);
                    }
                });
                repeater.dataset.repeaterNextIndex = String(items.length);
            }

            function updateHint() {
                if (!hint || repeater.dataset.repeater !== 'tickets') {
                    return;
                }
                const rows = Array.from(itemsContainer.querySelectorAll('[data-repeater-item], .fp-exp-repeater__item'));
                const hasValid = rows.some((row) => {
                    const label = row.querySelector('input[name*="[label]"]');
                    const price = row.querySelector('input[name*="[price]"]');
                    if (!label || !price) {
                        return false;
                    }
                    const priceValue = parseFloat(price.value || '0');
                    return label.value.trim() !== '' && priceValue >= 0;
                });
                hint.textContent = hasValid ? '' : getString('ticketWarning');
            }

            function bindRemoveButtons(scope) {
                Array.from(scope.querySelectorAll('[data-repeater-remove]')).forEach((button) => {
                    button.addEventListener('click', () => {
                        const row = button.closest('[data-repeater-item], .fp-exp-repeater__item');
                        if (!row) {
                            return;
                        }
                        row.remove();
                        reindexItems();
                        updateHint();
                    });
                });
            }

            function addRow() {
                const nextIndex =
                    parseInt(repeater.dataset.repeaterNextIndex || String(itemsContainer.children.length), 10) || 0;
                let html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
                html = html.replace(/__INDEX_PLUS_1__/g, String(nextIndex + 1));
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html;
                const newNode = wrapper.firstElementChild;
                if (!newNode) {
                    return;
                }
                assignTemplateNames(newNode);
                bindRemoveButtons(newNode);
                itemsContainer.appendChild(newNode);

                const numberSpan = newNode.querySelector('.fp-exp-repeater__item-number');
                if (numberSpan) {
                    if (numberSpan.textContent.includes('__INDEX_PLUS_1__')) {
                        numberSpan.textContent = String(nextIndex + 1);
                    }
                    if (numberSpan.textContent.includes('__INDEX__')) {
                        numberSpan.textContent = String(nextIndex + 1);
                    }
                }

                initEnhancementsOnNewRow(newNode);
                repeater.dataset.repeaterNextIndex = String(nextIndex + 1);
                const focusTarget = newNode.querySelector('input, select, textarea');
                if (focusTarget) {
                    focusTarget.focus();
                }
                updateHint();
            }

            const addButton = repeater.querySelector('[data-repeater-add]');
            if (addButton) {
                addButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    addRow();
                });
            }

            bindRemoveButtons(itemsContainer);
            initRecurrenceTimeSets(itemsContainer);
            updateHint();

            itemsContainer.addEventListener('dragstart', (event) => {
                const row = event.target.closest('[data-repeater-item]');
                if (!row) {
                    return;
                }
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', 'dragging');
                itemsContainer.dataset.draggingId = String(Array.from(itemsContainer.children).indexOf(row));
            });

            itemsContainer.addEventListener('dragover', (event) => {
                if (!itemsContainer.dataset.draggingId) {
                    return;
                }
                event.preventDefault();
                const afterElement = getDragAfterElement(itemsContainer, event.clientY);
                const draggingIndex = parseInt(itemsContainer.dataset.draggingId, 10);
                const draggingItem = itemsContainer.children[draggingIndex];
                if (!draggingItem) {
                    return;
                }
                if (!afterElement) {
                    itemsContainer.appendChild(draggingItem);
                } else if (afterElement !== draggingItem) {
                    itemsContainer.insertBefore(draggingItem, afterElement);
                }
            });

            itemsContainer.addEventListener('drop', () => {
                delete itemsContainer.dataset.draggingId;
            });

            itemsContainer.addEventListener('dragend', () => {
                delete itemsContainer.dataset.draggingId;
            });
        });
    }

    window.fpExpAdmin = window.fpExpAdmin || {};
    window.fpExpAdmin.initRepeaters = initRepeaters;
})();
