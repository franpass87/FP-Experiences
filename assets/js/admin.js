(function () {
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function getString(key) {
        if (!window.fpExpAdmin || !window.fpExpAdmin.strings) {
            return '';
        }
        return window.fpExpAdmin.strings[key] || '';
    }

    function initTabs(root) {
        const tabs = Array.from(root.querySelectorAll('.fp-exp-tab'));
        const panels = Array.from(root.querySelectorAll('.fp-exp-tab-panel'));
        if (!tabs.length || !panels.length) {
            return;
        }

        function activateTab(targetSlug, focus = true) {
            tabs.forEach((tab) => {
                const isActive = tab.dataset.tab === targetSlug;
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            panels.forEach((panel) => {
                const isActive = panel.dataset.tabPanel === targetSlug;
                panel.toggleAttribute('hidden', !isActive);
                if (isActive && focus) {
                    const focusable = panel.querySelector('input, select, textarea, button');
                    if (focusable) {
                        focusable.focus();
                    }
                }
            });
        }

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                const slug = tab.dataset.tab;
                if (!slug) {
                    return;
                }
                activateTab(slug);
                if (history.replaceState) {
                    const url = new URL(window.location.href);
                    url.hash = slug;
                    history.replaceState(null, '', url.toString());
                }
            });
        });

        const targetHash = window.location.hash.replace('#', '');
        if (targetHash && tabs.some((tab) => tab.dataset.tab === targetHash)) {
            activateTab(targetHash, false);
        } else {
            activateTab(tabs[0].dataset.tab, false);
        }
    }

    function initRepeaters(root) {
        const repeaterNodes = Array.from(root.querySelectorAll('[data-repeater]'));
        repeaterNodes.forEach((repeater) => {
            const itemsContainer = repeater.querySelector('.fp-exp-repeater__items');
            const template = repeater.querySelector('template[data-repeater-template]');
            const hint = repeater.querySelector('[data-repeater-hint]');
            if (!itemsContainer || !template) {
                return;
            }

            function bindRemoveButtons(scope) {
                Array.from(scope.querySelectorAll('[data-repeater-remove]')).forEach((button) => {
                    button.addEventListener('click', () => {
                        const row = button.closest('[data-repeater-item]');
                        if (!row) {
                            return;
                        }
                        row.remove();
                        updateHint();
                    });
                });
            }

            function updateHint() {
                if (!hint || repeater.dataset.repeater !== 'tickets') {
                    return;
                }
                const rows = Array.from(itemsContainer.querySelectorAll('[data-repeater-item]'));
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

            function addRow() {
                const nextIndex = parseInt(repeater.dataset.repeaterNextIndex || itemsContainer.children.length, 10) || 0;
                const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html;
                const newNode = wrapper.firstElementChild;
                if (!newNode) {
                    return;
                }
                bindRemoveButtons(newNode);
                itemsContainer.appendChild(newNode);
                repeater.dataset.repeaterNextIndex = String(nextIndex + 1);
                const focusTarget = newNode.querySelector('input, select, textarea');
                if (focusTarget) {
                    focusTarget.focus();
                }
                updateHint();
            }

            const addButton = repeater.querySelector('[data-repeater-add]');
            if (addButton) {
                addButton.addEventListener('click', addRow);
            }

            bindRemoveButtons(itemsContainer);
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

    function getDragAfterElement(container, y) {
        const elements = [...container.querySelectorAll('[data-repeater-item]')];
        return elements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset, element: child };
            }
            return closest;
        }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
    }

    function initFormValidation(root) {
        const form = document.getElementById('post');
        if (!form) {
            return;
        }

        form.addEventListener('submit', (event) => {
            let hasError = false;
            const errors = [];
            const invalids = form.querySelectorAll('input[type="number"]');
            invalids.forEach((input) => {
                if (input.disabled) {
                    return;
                }
                const value = input.value.trim();
                if (value === '') {
                    return;
                }
                const numberValue = parseFloat(value);
                if (numberValue < 0) {
                    hasError = true;
                    if (input.name.includes('[price]')) {
                        errors.push(getString('invalidPrice'));
                    } else {
                        errors.push(getString('invalidQuantity'));
                    }
                    input.focus();
                }
            });

            const publishButtons = document.querySelectorAll('#publish, #save-post');
            publishButtons.forEach((button) => {
                button.classList.add('is-busy');
                button.setAttribute('aria-disabled', 'true');
                button.disabled = true;
            });

            if (hasError) {
                event.preventDefault();
                const message = errors.filter(Boolean).join('\n');
                if (message) {
                    window.alert(message);
                }
                publishButtons.forEach((button) => {
                    button.classList.remove('is-busy');
                    button.removeAttribute('aria-disabled');
                    button.disabled = false;
                });
            }
        });
    }

    ready(() => {
        const root = document.querySelector('[data-fp-exp-admin]');
        if (!root) {
            return;
        }
        initTabs(root);
        initRepeaters(root);
        initFormValidation(root);
    });
})();
