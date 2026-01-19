(function () {
    const translateAdmin = (window.fpExpTranslate && window.fpExpTranslate.localize) ? window.fpExpTranslate.localize : (value) => value;

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
        const value = window.fpExpAdmin.strings[key];
        if (typeof value === 'string') {
            return translateAdmin(value);
        }

        return translateAdmin(value || '');
    }

    function initEmailSubjectPreview(root) {
        const container = root.closest('.fp-exp-admin');
        if (!container) {
            return;
        }
        // Trova i campi soggetto nella pagina impostazioni email
        const fields = Array.from(document.querySelectorAll('input[name^="fp_exp_emails[subjects]"]'));
        if (!fields.length) {
            return;
        }

        fields.forEach((input) => {
            const wrapper = document.createElement('p');
            wrapper.className = 'description';
            const preview = document.createElement('span');
            preview.setAttribute('data-email-subject-preview', '');
            wrapper.appendChild(document.createTextNode('Anteprima: '));
            wrapper.appendChild(preview);
            input.parentNode && input.parentNode.appendChild(wrapper);

            const placeholders = {
                '{experience_title}': 'Esperienza di esempio',
                '{date}': '01/01/2026',
                '{time}': '10:00',
                '{order_number}': '12345',
            };

            const render = () => {
                const raw = String(input.value || '');
                let output = raw;
                Object.entries(placeholders).forEach(([token, value]) => {
                    output = output.split(token).join(value);
                });
                preview.textContent = output || '(vuoto)';
            };

            input.addEventListener('input', render);
            render();
        });
    }

    function resolveAttachmentSource(data) {
        if (!data) {
            return { url: '', width: 0, height: 0 };
        }

        const sizes = data.sizes || {};
        const preferred =
            sizes.thumbnail || sizes.medium || sizes.medium_large || sizes.large || sizes.full || {};

        return {
            url: preferred.url || data.url || '',
            width: preferred.width || data.width || 0,
            height: preferred.height || data.height || 0,
        };
    }

    function initTabs(root) {
        const tabs = Array.from(root.querySelectorAll('.fp-exp-tab'));
        const panels = Array.from(root.querySelectorAll('.fp-exp-tab-panel'));
        if (!tabs.length) {
            return;
        }

        function activateTab(targetSlug, focus = true) {
            tabs.forEach((tab) => {
                const isActive = tab.dataset.tab === targetSlug;
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                tab.classList.toggle('is-active', isActive);
            });
            panels.forEach((panel) => {
                const isActive = panel.dataset.tabPanel === targetSlug;
                panel.toggleAttribute('hidden', !isActive);
                panel.classList.toggle('is-active', isActive);
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

    function initMediaControls(root) {
        if (!root || !window.wp || !window.wp.media) {
            return;
        }

        const controls = Array.from(root.querySelectorAll('[data-fp-media-control]'));

        controls.forEach((control) => {
            if (!control || control.dataset.fpMediaBound === '1') {
                return;
            }

            const input = control.querySelector('[data-fp-media-input]');
            const chooseButton = control.querySelector('[data-fp-media-choose]');
            const removeButton = control.querySelector('[data-fp-media-remove]');
            const preview = control.querySelector('[data-fp-media-preview]');
            const placeholder = preview ? preview.querySelector('[data-fp-media-placeholder]') : null;

            if (!input || !chooseButton || !removeButton || !preview) {
                return;
            }

            control.dataset.fpMediaBound = '1';

            const labels = {
                select: chooseButton.dataset.labelSelect || getString('selectImage') || 'Seleziona immagine',
                change: chooseButton.dataset.labelChange || getString('changeImage') || 'Modifica immagine',
                remove: getString('removeImage') || removeButton.textContent || 'Rimuovi immagine',
            };

            removeButton.textContent = labels.remove;

            function setButtonState(hasImage) {
                chooseButton.textContent = hasImage ? labels.change : labels.select;
                removeButton.hidden = !hasImage;
            }

            function renderPlaceholder() {
                const existing = preview.querySelector('[data-fp-media-image]');
                if (existing) {
                    existing.remove();
                }
                if (placeholder) {
                    placeholder.hidden = false;
                }
                setButtonState(false);
            }

            function renderImage(attachment) {
                if (!attachment) {
                    input.value = '';
                    renderPlaceholder();
                    return;
                }

                const source = resolveAttachmentSource(attachment);
                if (!source.url) {
                    input.value = '';
                    renderPlaceholder();
                    return;
                }

                let image = preview.querySelector('[data-fp-media-image]');
                if (!image) {
                    image = document.createElement('img');
                    image.setAttribute('data-fp-media-image', '1');
                    image.setAttribute('loading', 'lazy');
                    preview.appendChild(image);
                }

                image.src = source.url;
                image.alt = attachment.alt || attachment.title || '';

                if (source.width) {
                    image.width = source.width;
                } else {
                    image.removeAttribute('width');
                }

                if (source.height) {
                    image.height = source.height;
                } else {
                    image.removeAttribute('height');
                }

                if (placeholder) {
                    placeholder.hidden = true;
                }

                input.value = String(attachment.id || '');
                setButtonState(true);
            }

            const initialImage = preview.querySelector('[data-fp-media-image]');
            if (initialImage) {
                setButtonState(true);
                if (placeholder) {
                    placeholder.hidden = true;
                }
            } else {
                renderPlaceholder();
            }

            let frame;

            chooseButton.addEventListener('click', (event) => {
                event.preventDefault();

                if (frame) {
                    frame.open();
                    return;
                }

                frame = window.wp.media({
                    title: labels.select,
                    multiple: false,
                    library: { type: 'image' },
                    button: { text: labels.change },
                });

                frame.on('select', () => {
                    const selection = frame.state().get('selection');
                    const chosen = selection && selection.first ? selection.first() : null;
                    if (!chosen) {
                        return;
                    }
                    renderImage(chosen.toJSON());
                });

                frame.open();
            });

            removeButton.addEventListener('click', (event) => {
                event.preventDefault();
                input.value = '';
                renderPlaceholder();
            });
        });
    }

    function initGalleryControls(root) {
        if (!root || !window.wp || !window.wp.media) {
            return;
        }

        const controls = Array.from(root.querySelectorAll('[data-fp-gallery-control]'));

        controls.forEach((control) => {
            if (!(control instanceof HTMLElement) || control.dataset.fpGalleryBound === '1') {
                return;
            }

            const input = control.querySelector('[data-fp-gallery-input]');
            const list = control.querySelector('[data-fp-gallery-list]');
            const template = control.querySelector('template[data-fp-gallery-item-template]');
            const emptyMessage = control.querySelector('[data-fp-gallery-empty]');
            const addButton = control.querySelector('[data-fp-gallery-add]');
            const clearButton = control.querySelector('[data-fp-gallery-clear]');

            if (
                !(input instanceof HTMLInputElement) ||
                !(list instanceof HTMLElement) ||
                !(template instanceof HTMLTemplateElement) ||
                !(addButton instanceof HTMLButtonElement) ||
                !(clearButton instanceof HTMLButtonElement)
            ) {
                return;
            }

            control.dataset.fpGalleryBound = '1';

            const labels = {
                add: addButton.dataset.labelSelect || getString('selectImages') || addButton.textContent || 'Seleziona immagini',
                addMore: addButton.dataset.labelUpdate || getString('addImages') || addButton.textContent || 'Aggiungi immagini',
                clear: clearButton.dataset.labelClear || getString('clearGallery') || clearButton.textContent || 'Rimuovi tutte le immagini',
            };

            function getItems() {
                return Array.from(list.querySelectorAll('[data-fp-gallery-item]')).filter(
                    (item) => item instanceof HTMLElement
                );
            }

            function getIds() {
                return getItems()
                    .map((item) => Number.parseInt(item.dataset.id || '0', 10))
                    .filter((id) => Number.isInteger(id) && id > 0);
            }

            function updateEmptyState() {
                const hasItems = getItems().length > 0;
                if (emptyMessage instanceof HTMLElement) {
                    emptyMessage.hidden = hasItems;
                }
                addButton.textContent = hasItems ? labels.addMore : labels.add;
                clearButton.hidden = !hasItems;
                clearButton.textContent = labels.clear;
            }

            function serializeIds() {
                const ids = getIds();
                input.value = ids.join(',');
                updateEmptyState();
            }

            function bindItem(item) {
                const removeButton = item.querySelector('[data-fp-gallery-remove]');
                if (removeButton instanceof HTMLButtonElement) {
                    removeButton.addEventListener('click', (event) => {
                        event.preventDefault();
                        item.remove();
                        serializeIds();
                    });
                }

                const moveButtons = Array.from(item.querySelectorAll('[data-fp-gallery-move]'));
                moveButtons.forEach((button) => {
                    if (!(button instanceof HTMLButtonElement)) {
                        return;
                    }

                    button.addEventListener('click', (event) => {
                        event.preventDefault();
                        const direction = button.dataset.fpGalleryMove || button.dataset.direction || '';
                        if (direction === 'prev') {
                            const prev = item.previousElementSibling;
                            if (prev) {
                                list.insertBefore(item, prev);
                                serializeIds();
                            }
                        } else if (direction === 'next') {
                            const next = item.nextElementSibling;
                            if (next) {
                                list.insertBefore(next, item);
                                serializeIds();
                            }
                        }
                    });
                });
            }

            function populateItem(element, data) {
                if (!(element instanceof HTMLElement)) {
                    return;
                }

                const rawId = data && (data.id || data.ID || data.Id || data.value);
                const id = Number.parseInt(rawId ? String(rawId) : '', 10) || 0;
                element.dataset.id = id > 0 ? String(id) : '';

                const placeholder = element.querySelector('[data-fp-gallery-placeholder]');
                const image = element.querySelector('[data-fp-gallery-image]');
                const source = data && data.url ? data : resolveAttachmentSource(data);

                if (image instanceof HTMLImageElement) {
                    if (source.url) {
                        image.src = source.url;
                        image.alt = data && (data.alt || data.title) ? String(data.alt || data.title) : '';
                        image.hidden = false;
                    } else {
                        image.removeAttribute('src');
                        image.alt = '';
                        image.hidden = true;
                    }
                }

                if (placeholder instanceof HTMLElement) {
                    placeholder.hidden = Boolean(source.url);
                }
            }

            function addItems(items) {
                if (!Array.isArray(items) || !items.length) {
                    return;
                }

                const existingIds = new Set(getIds());

                items.forEach((itemData) => {
                    const candidateId = Number.parseInt(
                        itemData && (itemData.id || itemData.ID || itemData.Id || itemData.value) ?
                            String(itemData.id || itemData.ID || itemData.Id || itemData.value) :
                            '',
                        10
                    );
                    if (!candidateId || existingIds.has(candidateId)) {
                        return;
                    }

                    const fragment = template.content.cloneNode(true);
                    const placeholderItem = fragment.querySelector('[data-fp-gallery-item]');
                    if (!(placeholderItem instanceof HTMLElement)) {
                        return;
                    }

                    list.appendChild(fragment);
                    const appended = list.lastElementChild;
                    if (!(appended instanceof HTMLElement)) {
                        return;
                    }

                    const source = resolveAttachmentSource(itemData);
                    populateItem(appended, {
                        id: candidateId,
                        url: source.url,
                        alt: itemData && itemData.alt ? itemData.alt : itemData && itemData.title ? itemData.title : '',
                    });
                    bindItem(appended);
                    existingIds.add(candidateId);
                });

                serializeIds();
            }

            getItems().forEach((item) => {
                bindItem(item);
            });

            serializeIds();

            clearButton.addEventListener('click', (event) => {
                event.preventDefault();
                getItems().forEach((item) => item.remove());
                serializeIds();
            });

            let frame;

            addButton.addEventListener('click', (event) => {
                event.preventDefault();

                if (frame) {
                    frame.open();
                    return;
                }

                frame = window.wp.media({
                    title: labels.add,
                    multiple: true,
                    library: { type: 'image' },
                    button: { text: labels.addMore },
                });

                frame.on('open', () => {
                    const selection = frame.state().get('selection');
                    if (!selection || typeof selection.reset !== 'function') {
                        return;
                    }

                    const ids = getIds();
                    if (!ids.length) {
                        return;
                    }

                    const attachments = ids
                        .map((id) => {
                            const attachment = window.wp.media.attachment(id);
                            if (attachment) {
                                attachment.fetch();
                            }
                            return attachment;
                        })
                        .filter((attachment) => attachment);

                    selection.reset(attachments);
                });

                frame.on('select', () => {
                    const selection = frame.state().get('selection');
                    if (!selection || typeof selection.map !== 'function') {
                        return;
                    }

                    const items = selection.map((attachment) => attachment.toJSON());
                    addItems(
                        items.map((itemData) => {
                            const source = resolveAttachmentSource(itemData);
                            return {
                                id: itemData.id || itemData.ID,
                                url: source.url,
                                alt: itemData.alt || itemData.title || '',
                            };
                        })
                    );
                });

                frame.open();
            });
        });
    }

    function initTaxonomyEditors(root) {
        if (!root) {
            return;
        }

        const editors = Array.from(root.querySelectorAll('[data-fp-taxonomy-editor]'));

        editors.forEach((editor) => {
            if (!(editor instanceof HTMLElement) || editor.dataset.fpTaxonomyInit === '1') {
                return;
            }

            const template = editor.querySelector('template[data-fp-taxonomy-template]');
            const addButton = editor.querySelector('[data-fp-taxonomy-add]');
            const newContainer = editor.querySelector('[data-fp-taxonomy-new]');

            if (!template || !newContainer || !(addButton instanceof HTMLElement)) {
                return;
            }

            editor.dataset.fpTaxonomyInit = '1';

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

            function bindRemove(target) {
                Array.from(target.querySelectorAll('[data-fp-taxonomy-remove]')).forEach((button) => {
                    button.addEventListener('click', (event) => {
                        event.preventDefault();
                        const item = button.closest('[data-fp-taxonomy-item]');
                        if (item) {
                            item.remove();
                        }
                    });
                });
            }

            function addRow(focus = true) {
                const nextIndex = parseInt(editor.dataset.fpTaxonomyNextIndex || '0', 10) || 0;
                const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html;
                const row = wrapper.firstElementChild;

                if (!row) {
                    return;
                }

                assignTemplateNames(row);
                bindRemove(row);
                newContainer.appendChild(row);
                editor.dataset.fpTaxonomyNextIndex = String(nextIndex + 1);

                if (focus) {
                    const focusable = row.querySelector('input, textarea');
                    if (focusable) {
                        focusable.focus();
                    }
                }
            }

            addButton.addEventListener('click', (event) => {
                event.preventDefault();
                addRow(true);
            });

            bindRemove(newContainer);
        });
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
                // Usa la classe come selettore se data-repeater-item non esiste
                const items = Array.from(itemsContainer.querySelectorAll('[data-repeater-item], .fp-exp-repeater__item'));
                items.forEach((item, newIndex) => {
                    // Aggiorna tutti i campi name con il nuovo indice
                    item.querySelectorAll('[name]').forEach((field) => {
                        const name = field.getAttribute('name');
                        if (!name) {
                            return;
                        }
                        // Sostituisce l'indice vecchio con quello nuovo nel name
                        // Esempio: fp_exp_policy[faqs][2][question] -> fp_exp_policy[faqs][0][question]
                        // Gestisce sia fp_exp_policy[faqs][index][field] che altri pattern
                        const newName = name.replace(/\[(\d+)\](\[question\]|\[answer\])/g, `[${newIndex}]$2`);
                        if (newName !== name) {
                            field.setAttribute('name', newName);
                        }
                    });
                    // Aggiorna il numero visualizzato
                    const numberSpan = item.querySelector('.fp-exp-repeater__item-number');
                    if (numberSpan) {
                        numberSpan.textContent = newIndex + 1;
                    }
                });
                // Aggiorna il nextIndex per le nuove aggiunte
                repeater.dataset.repeaterNextIndex = String(items.length);
            }

            function bindRemoveButtons(scope) {
                Array.from(scope.querySelectorAll('[data-repeater-remove]')).forEach((button) => {
                    button.addEventListener('click', () => {
                        // Cerca sia l'attributo data-repeater-item che la classe
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

            function addRow() {
                const nextIndex = parseInt(repeater.dataset.repeaterNextIndex || itemsContainer.children.length, 10) || 0;
                const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html;
                const newNode = wrapper.firstElementChild;
                if (!newNode) {
                    return;
                }
                assignTemplateNames(newNode);
                bindRemoveButtons(newNode);
                itemsContainer.appendChild(newNode);
                
                // Aggiorna il numero visualizzato (sostituisce "#" con il numero corretto)
                const numberSpan = newNode.querySelector('.fp-exp-repeater__item-number');
                if (numberSpan) {
                    numberSpan.textContent = nextIndex + 1;
                }
                
                initMediaControls(newNode);
                initGalleryControls(newNode);
                initRecurrenceTimeSets(newNode);
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

            function addChip(value = '', focus = true) {
                const chip = document.createElement('span');
                chip.className = 'fp-exp-chip';
                chip.setAttribute('data-time-set-chip', '');

                const label = document.createElement('label');
                const sr = document.createElement('span');
                sr.className = 'screen-reader-text';
                sr.textContent = getString('recurrenceTimeLabel') || 'Orario';

                const input = document.createElement('input');
                input.type = 'time';
                if (baseName) {
                    input.name = `${baseName}[${nextIndex}]`;
                }
                input.value = value;

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'fp-exp-chip__remove';
                remove.setAttribute('data-time-set-remove', '');
                remove.setAttribute('aria-label', getString('recurrenceRemoveTime') || 'Rimuovi orario');
                remove.textContent = '×';

                label.appendChild(sr);
                label.appendChild(input);
                chip.appendChild(label);
                chip.appendChild(remove);
                chipsContainer.appendChild(chip);

                nextIndex += 1;
                container.dataset.timeSetNextIndex = String(nextIndex);

                if (focus && input) {
                    input.focus();
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
                button.setAttribute('data-fp-exp-busy', '1');
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
                    button.removeAttribute('data-fp-exp-busy');
                });
            }
        });
    }

    function initCognitiveBiasLimiter(root) {
        const grids = Array.from(root.querySelectorAll('[data-fp-cognitive-bias]'));
        if (!grids.length) {
            return;
        }

        grids.forEach((grid) => {
            if (!(grid instanceof HTMLElement)) {
                return;
            }

            if (grid.dataset.fpCognitiveBiasInit === '1') {
                return;
            }

            const max = parseInt(grid.dataset.max || '0', 10) || 0;
            if (max <= 0) {
                return;
            }

            const field = grid.closest('.fp-exp-field') || root;
            const status = field ? field.querySelector('[data-fp-cognitive-bias-status]') : null;
            const searchInput = field ? field.querySelector('[data-fp-cognitive-bias-search]') : null;
            const emptyState = field ? field.querySelector('[data-fp-cognitive-bias-empty]') : null;
            const template = status && status.dataset.template
                ? status.dataset.template
                : getString('trustBadgesStatus');
            const maxMessage = status && status.dataset.maxMessage
                ? status.dataset.maxMessage
                : getString('trustBadgesMax');

            const checkboxes = Array.from(grid.querySelectorAll('input[type="checkbox"]'));
            if (!checkboxes.length) {
                return;
            }

            const items = checkboxes
                .map((checkbox) => checkbox.closest('.fp-exp-checkbox-grid__item'))
                .filter((item) => item instanceof HTMLElement);

            grid.dataset.fpCognitiveBiasInit = '1';

            function renderStatus(count) {
                if (!status) {
                    return;
                }

                let message = template || '';
                if (message.includes('%1$s')) {
                    message = message.replace('%1$s', String(count));
                }
                if (message.includes('%2$s')) {
                    message = message.replace('%2$s', String(max));
                }

                if (!template) {
                    message = `${count}/${max}`;
                }

                if (count >= max && maxMessage) {
                    message = message ? `${message} ${maxMessage}` : maxMessage;
                }

                status.textContent = message;
            }

            function updateState() {
                const selected = checkboxes.filter((checkbox) => checkbox.checked);
                const count = selected.length;
                const reached = count >= max;

                checkboxes.forEach((checkbox) => {
                    const shouldDisable = reached && !checkbox.checked;
                    checkbox.disabled = shouldDisable;
                    if (shouldDisable) {
                        checkbox.setAttribute('aria-disabled', 'true');
                    } else {
                        checkbox.removeAttribute('aria-disabled');
                    }

                    const label = checkbox.closest('label');
                    if (label) {
                        label.classList.toggle('is-disabled', shouldDisable);
                    }
                });

                grid.classList.toggle('is-max', reached);
                grid.setAttribute('data-selected-count', String(count));
                renderStatus(count);
            }

            function applySearch(rawTerm) {
                const term = typeof rawTerm === 'string' ? rawTerm.trim().toLowerCase() : '';
                let visibleCount = 0;

                items.forEach((item) => {
                    if (!(item instanceof HTMLElement)) {
                        return;
                    }

                    const haystack = item.dataset.search || '';
                    const matches = !term || haystack.includes(term);
                    item.hidden = !matches;

                    if (matches) {
                        visibleCount += 1;
                    }
                });

                grid.classList.toggle('is-filtered', term.length > 0);
                grid.classList.toggle('is-empty', visibleCount === 0);
                grid.setAttribute('data-visible-count', String(visibleCount));

                if (emptyState) {
                    emptyState.hidden = visibleCount !== 0;
                }
            }

            checkboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', updateState);
            });

            if (searchInput instanceof HTMLInputElement) {
                const handleSearch = () => {
                    applySearch(searchInput.value || '');
                    updateState();
                };

                searchInput.addEventListener('input', handleSearch);
                searchInput.addEventListener('search', handleSearch);

                if (searchInput.value) {
                    applySearch(searchInput.value);
                } else {
                    applySearch('');
                }
            } else {
                applySearch('');
            }

            updateState();
        });
    }

    function initRecurrence(root) {
        const settings = root.querySelector('[data-recurrence-settings]');
        if (!settings) {
            return;
        }

        initRecurrenceTimeSets(settings);

        const adminConfig = window.fpExpAdmin || {};
        const restConfig = adminConfig.rest || null;

        const frequencyControls = Array.from(settings.querySelectorAll('[data-recurrence-frequency]'));
        const frequencyCards = frequencyControls
            .map((control) => (control instanceof HTMLElement ? control.closest('[data-recurrence-frequency-card]') : null))
            .filter((card) => card instanceof HTMLElement);
        const daysContainer = settings.querySelector('[data-recurrence-days]');
        const status = settings.querySelector('[data-recurrence-status]');
        const errors = settings.querySelector('[data-recurrence-errors]');
        const previewWrapper = settings.querySelector('[data-recurrence-preview-list]');
        const previewList = previewWrapper ? previewWrapper.querySelector('ul') : null;
        const previewButton = null;
        const generateButton = null;
        const summary = settings.querySelector('[data-recurrence-frequency-summary]');
        const startDateInput = settings.querySelector('input[name="fp_exp_availability[recurrence][start_date]"]');
        const endDateInput = settings.querySelector('input[name="fp_exp_availability[recurrence][end_date]"]');
        const weeklyEmptyMessage =
            daysContainer instanceof HTMLElement ? daysContainer.dataset.recurrenceWeeklyEmpty || '' : '';
        const openEndedSuffix =
            getString('recurrenceOpenEndedSuffix') || 'La ricorrenza resta attiva finché non imposti una data di fine.';

        function getFrequencyValue() {
            if (!frequencyControls.length) {
                return '';
            }

            if (frequencyControls.length === 1) {
                const [control] = frequencyControls;
                if (control instanceof HTMLSelectElement || control instanceof HTMLInputElement) {
                    return control.value;
                }
            }

            const checked = frequencyControls.find(
                (control) => control instanceof HTMLInputElement && control.checked
            );

            if (checked && checked instanceof HTMLInputElement) {
                return checked.value;
            }

            const [fallback] = frequencyControls;
            if (fallback instanceof HTMLSelectElement || fallback instanceof HTMLInputElement) {
                return fallback.value;
            }

            return '';
        }

        function updateDaysVisibility() {
            const show = true;

            if (daysContainer) {
                daysContainer.toggleAttribute('hidden', !show);
            }
        }

        function updateFrequencyNotes() {
            const current = getFrequencyValue();
            const notes = settings.querySelectorAll('[data-recurrence-frequency-help]');

            notes.forEach((note) => {
                if (!(note instanceof HTMLElement)) {
                    return;
                }

                const target = note.dataset.frequency || '';
                note.toggleAttribute('hidden', target !== current);
            });
        }

        function updateFrequencyCards() {
            const current = getFrequencyValue();

            frequencyCards.forEach((card) => {
                if (!(card instanceof HTMLElement)) {
                    return;
                }

                const input = card.querySelector('[data-recurrence-frequency]');
                const value = input instanceof HTMLInputElement ? input.value : '';
                const isSelected = value === current;

                card.classList.toggle('is-selected', isSelected);
            });
        }

        function renderFrequencySummary() {
            if (!(summary instanceof HTMLElement)) {
                return;
            }

            const current = getFrequencyValue();
            const card = frequencyCards.find((item) => {
                if (!(item instanceof HTMLElement)) {
                    return false;
                }

                const input = item.querySelector('[data-recurrence-frequency]');
                if (!(input instanceof HTMLInputElement)) {
                    return false;
                }

                return input.value === current;
            });

            if (!card || !(card instanceof HTMLElement)) {
                summary.textContent = '';
                summary.hidden = true;
                return;
            }

            const template = card.dataset.frequencySummaryTemplate || '';

            if (!template) {
                summary.textContent = '';
                summary.hidden = true;
                return;
            }

            let message = template;

            if (template.includes('%s')) {
                const checkedDays = daysContainer
                    ? Array.from(daysContainer.querySelectorAll('input[type="checkbox"]:checked'))
                    : [];

                const labels = checkedDays
                    .map((input) =>
                        input instanceof HTMLInputElement ? input.dataset.dayLabel || input.value || '' : ''
                    )
                    .filter((label) => Boolean(label));

                const listText = labels.length ? labels.join(', ') : weeklyEmptyMessage;
                message = template.replace('%s', listText);
            }

            if ((!endDateInput || !endDateInput.value) && openEndedSuffix) {
                message = `${message} ${openEndedSuffix}`.trim();
            }

            summary.textContent = message;
            summary.hidden = false;
        }

        function clearStatus() {
            if (!status) {
                return;
            }
            status.textContent = '';
            status.classList.remove('is-error');
        }

        function setStatus(message, isError = false) {
            if (!status) {
                return;
            }
            status.textContent = message || '';
            status.classList.toggle('is-error', Boolean(isError));
        }

        function clearErrors() {
            if (!errors) {
                return;
            }
            errors.textContent = '';
            errors.hidden = true;
        }

        function showError(message) {
            if (!errors) {
                return;
            }
            errors.textContent = message;
            errors.hidden = false;
        }

        function clearPreview() {
            if (previewWrapper) {
                previewWrapper.hidden = true;
            }
            if (previewList) {
                previewList.innerHTML = '';
            }
        }

        function renderPreview(items) {
            if (!previewWrapper || !previewList) {
                return;
            }
            previewList.innerHTML = '';

            if (!items || !items.length) {
                const empty = document.createElement('li');
                empty.textContent = getString('recurrencePreviewEmpty') || '';
                previewList.appendChild(empty);
            } else {
                items.forEach((item) => {
                    const li = document.createElement('li');
                    const start = item.start_local || item.start_utc || '';
                    const end = item.end_local || item.end_utc || '';
                    li.textContent = end ? `${start} → ${end}` : start;
                    previewList.appendChild(li);
                });
            }

            previewWrapper.hidden = false;
        }

        function getInputValue(container, selector) {
            const field = container.querySelector(selector);
            return field ? field.value : '';
        }

        function collectPayload() {
            clearErrors();

            const recurrence = {
                start_date: getInputValue(settings, 'input[name="fp_exp_availability[recurrence][start_date]"]') || '',
                end_date: getInputValue(settings, 'input[name="fp_exp_availability[recurrence][end_date]"]') || '',
                frequency: getFrequencyValue() || 'weekly',
                days: [],
                duration: parseInt(getInputValue(settings, 'input[name="fp_exp_availability[recurrence][duration]"]') || '0', 10) || 0,
                time_slots: [], // Nuovo formato semplificato
                time_sets: [], // Vecchio formato per retrocompatibilità
            };

            if (recurrence.frequency === 'weekly' && daysContainer) {
                const checkedDays = Array.from(daysContainer.querySelectorAll('input[type="checkbox"]:checked'));
                recurrence.days = checkedDays.map((input) => input.value);
            }

            // Nuovo formato: time_slots semplificati (un solo orario per slot)
            const timeSlotRepeater = settings.querySelector('[data-repeater="time_slots"]');
            if (timeSlotRepeater) {
                const slotRows = Array.from(timeSlotRepeater.querySelectorAll('[data-repeater-item]'));
                slotRows.forEach((row) => {
                    const timeInput = row.querySelector('input[type="time"]');
                    const capacityInput = row.querySelector('input[name*="[capacity]"]');
                    const bufferBeforeInput = row.querySelector('input[name*="[buffer_before]"]');
                    const bufferAfterInput = row.querySelector('input[name*="[buffer_after]"]');
                    
                    const time = timeInput ? timeInput.value.trim() : '';
                    if (!time) {
                        return; // Salta slot vuoti
                    }
                    
                    const slotData = {
                        time: time,
                        capacity: capacityInput ? (parseInt(capacityInput.value || '0', 10) || 0) : 0,
                        buffer_before: bufferBeforeInput ? (parseInt(bufferBeforeInput.value || '0', 10) || 0) : 0,
                        buffer_after: bufferAfterInput ? (parseInt(bufferAfterInput.value || '0', 10) || 0) : 0,
                        days: [], // Può essere aggiunto in futuro se necessario
                    };
                    
                    recurrence.time_slots.push(slotData);
                });
            }
            
            // Vecchio formato: time_sets (per retrocompatibilità)
            const timeSetContainers = Array.from(settings.querySelectorAll('[data-time-set]'));
            timeSetContainers.forEach((container) => {
                const labelInput = container.querySelector('input[name*="[label]"]');
                const chipInputs = Array.from(container.querySelectorAll('[data-time-set-chip] input[type="time"]'));
                const times = chipInputs.map((input) => input.value.trim()).filter(Boolean);
                if (!times.length) {
                    return;
                }
                const setData = {
                    label: labelInput ? labelInput.value.trim() : '',
                    times,
                    capacity: 0,
                    buffer_before: 0,
                    buffer_after: 0,
                };

                const capacityInput = container.querySelector('input[name*="[capacity]"]');
                if (capacityInput) {
                    setData.capacity = parseInt(capacityInput.value || '0', 10) || 0;
                }

                const bufferBeforeInput = container.querySelector('input[name*="[buffer_before]"]');
                if (bufferBeforeInput) {
                    setData.buffer_before = parseInt(bufferBeforeInput.value || '0', 10) || 0;
                }

                const bufferAfterInput = container.querySelector('input[name*="[buffer_after]"]');
                if (bufferAfterInput) {
                    setData.buffer_after = parseInt(bufferAfterInput.value || '0', 10) || 0;
                }

                recurrence.time_sets.push(setData);
            });

            // Verifica che ci sia almeno un time slot (nuovo o vecchio formato)
            if (!recurrence.time_slots.length && !recurrence.time_sets.length) {
                showError(getString('recurrenceMissingTimes'));
                return null;
            }
            
            // Verifica che ci siano anche i giorni per la ricorrenza settimanale
            if (recurrence.frequency === 'weekly' && !recurrence.days.length) {
                showError(getString('recurrenceMissingDays'));
                return null;
            }

            let experienceId = 0;
            if (typeof adminConfig.experienceId === 'number') {
                experienceId = adminConfig.experienceId;
            } else if (adminConfig.experienceId) {
                experienceId = parseInt(String(adminConfig.experienceId), 10) || 0;
            }

            const payload = {
                experience_id: experienceId,
                recurrence,
                availability: {
                    slot_capacity: parseInt(getInputValue(root, 'input[name="fp_exp_availability[slot_capacity]"]') || '0', 10) || 0,
                    buffer_before_minutes: parseInt(getInputValue(root, 'input[name="fp_exp_availability[buffer_before_minutes]"]') || '0', 10) || 0,
                    buffer_after_minutes: parseInt(getInputValue(root, 'input[name="fp_exp_availability[buffer_after_minutes]"]') || '0', 10) || 0,
                },
                replace_existing: false,
            };

            return payload;
        }

        async function sendRequest(endpoint, payload) {
            if (!endpoint || !restConfig) {
                throw new Error('missing_endpoint');
            }

            const response = await window.fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': restConfig.nonce || '',
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error('request_failed');
            }

            return response.json();
        }

        updateDaysVisibility();
        updateFrequencyNotes();
        updateFrequencyCards();
        renderFrequencySummary();

        if (frequencyControls.length) {
            frequencyControls.forEach((control) => {
                control.addEventListener('change', () => {
                    updateDaysVisibility();
                    updateFrequencyNotes();
                    updateFrequencyCards();
                    renderFrequencySummary();
                    clearStatus();
                    clearErrors();
                    clearPreview();
                });
            });
        }

        if (daysContainer) {
            daysContainer.addEventListener('change', () => {
                renderFrequencySummary();
            });
        }

        if (startDateInput) {
            startDateInput.addEventListener('change', renderFrequencySummary);
        }

        if (endDateInput) {
            endDateInput.addEventListener('change', renderFrequencySummary);
            endDateInput.addEventListener('input', renderFrequencySummary);
        }

        // preview button rimosso

        // generate button rimosso
    }

    function initTools() {
        const container = document.querySelector('[data-fp-exp-tools]');
        if (!container || !window.fpExpTools) {
            return;
        }

        const config = window.fpExpTools || {};
        const actions = Array.isArray(config.actions) ? config.actions : [];
        const endpoints = new Map();
        actions.forEach((action) => {
            if (!action || !action.slug || !action.endpoint) {
                return;
            }
            endpoints.set(String(action.slug), String(action.endpoint));
        });

        if (!endpoints.size) {
            return;
        }

        const output = container.querySelector('.fp-exp-tools__output');
        const nonce = config.nonce || '';
        const i18n = config.i18n || {};
        const runningText = i18n.running || 'Running…';
        const successText = i18n.success || 'Action completed successfully.';
        const errorText = i18n.error || 'Action failed. Check logs for details.';

        function setMessage(message, isError = false, details = []) {
            if (!output) {
                return;
            }
            output.innerHTML = '';
            output.classList.toggle('is-error', Boolean(isError));

            const hasMessage = Boolean(message);
            if (hasMessage) {
                const paragraph = document.createElement('p');
                paragraph.textContent = message;
                output.appendChild(paragraph);
            }

            const detailList = Array.isArray(details) ? details : [];
            if (detailList.length) {
                const list = document.createElement('ul');
                list.classList.add('fp-exp-tools__details');

                detailList.forEach((detail) => {
                    const text = detail == null ? '' : String(detail);
                    if (!text) {
                        return;
                    }
                    const item = document.createElement('li');
                    item.textContent = text;
                    list.appendChild(item);
                });

                if (list.childElementCount) {
                    output.appendChild(list);
                }
            }

            if (!output.childElementCount) {
                output.textContent = message || '';
            }
        }

        const buttons = Array.from(container.querySelectorAll('[data-action]'));
        buttons.forEach((button) => {
            if (!(button instanceof HTMLElement)) {
                return;
            }

            const slug = button.dataset.action || '';
            const endpoint = slug ? endpoints.get(slug) : null;
            if (!endpoint) {
                return;
            }

            button.addEventListener('click', async (event) => {
                event.preventDefault();

                button.classList.add('is-busy');
                button.setAttribute('aria-disabled', 'true');
                if ('disabled' in button) {
                    button.disabled = true;
                }

                setMessage(runningText, false);

                try {
                    const response = await window.fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': nonce,
                        },
                        body: JSON.stringify({}),
                    });

                    const payload = await response.json().catch(() => ({}));
                    const success = response.ok && payload && payload.success !== false;
                    const message = payload && payload.message ? String(payload.message) : success ? successText : errorText;
                    const details = payload && Array.isArray(payload.details) ? payload.details : [];
                    setMessage(message, !success, details);
                } catch (error) {
                    setMessage(errorText, true);
                } finally {
                    button.classList.remove('is-busy');
                    button.removeAttribute('aria-disabled');
                    if ('disabled' in button) {
                        button.disabled = false;
                    }
                }
            });
        });
    }

    function initCalendarApp() {
        const container = document.getElementById('fp-exp-calendar-app');
        if (!container) {
            return;
        }

		// Bootstrap init

		const calendarConfig = window.fpExpCalendar || {};
        const endpoints = calendarConfig.endpoints || {};
        const slotsEndpoint = endpoints.slots || endpoints.availability;
		// Calendar config verificato
        
        // Se non ci sono esperienze, non inizializzare il calendario
        if (!calendarConfig.has_experiences) {
            const loadingNode = container.querySelector('.fp-exp-calendar__loading');
            if (loadingNode) {
                loadingNode.hidden = true;
            }
			// Nessuna esperienza disponibile
            return;
        }

        const loadingNode = container.querySelector('.fp-exp-calendar__loading');
        const bodyNode = container.querySelector('[data-calendar-content]');
        const errorNode = container.querySelector('[data-calendar-error]');

		if (!loadingNode || !bodyNode || !errorNode) {
			// Nodi UI mancanti
			if (loadingNode) { loadingNode.hidden = true; }
			return;
		}

		// Se mancano gli endpoints, mostra errore in UI invece di uscire silenziosamente
		if (!slotsEndpoint) {
			if (typeof container.dataset.loadingText === 'string') { loadingNode.textContent = container.dataset.loadingText; }
			loadingNode.hidden = true;
			errorNode.hidden = false;
			errorNode.textContent = 'Configurazione calendario non valida: endpoint slots mancante.';
			bodyNode.hidden = false;
			// Endpoint slots mancante
			return;
		}

        function createUTCDate(year, month, day) {
            return new Date(Date.UTC(year, month, day));
        }

        function formatRequestDate(date) {
            const year = date.getUTCFullYear();
            const month = String(date.getUTCMonth() + 1).padStart(2, '0');
            const day = String(date.getUTCDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        }

        function parseBootstrapStart() {
            try {
                const raw = container.getAttribute('data-bootstrap');
                if (!raw) {
                    return null;
                }
                const data = JSON.parse(raw);
                if (!data || !data.range || !data.range.start) {
                    return null;
                }
                const parts = String(data.range.start).split('-');
                if (parts.length < 2) {
                    return null;
                }
                const year = Number.parseInt(parts[0], 10);
                const month = Number.parseInt(parts[1], 10) - 1;
                if (Number.isNaN(year) || Number.isNaN(month)) {
                    return null;
                }

                return createUTCDate(year, month, 1);
            } catch (error) {
                return null;
            }
        }

        function monthRange(date) {
            const year = date.getUTCFullYear();
            const month = date.getUTCMonth();

            return {
                start: createUTCDate(year, month, 1),
                end: createUTCDate(year, month + 1, 0),
            };
        }

        function addMonths(date, amount) {
            const year = date.getUTCFullYear();
            const month = date.getUTCMonth() + amount;

            return createUTCDate(year, month, 1);
        }

        function formatMonthTitle(date) {
            try {
                return new Intl.DateTimeFormat(undefined, {
                    month: 'long',
                    year: 'numeric',
                    timeZone: 'UTC',
                }).format(date);
            } catch (error) {
                const year = date.getUTCFullYear();
                const month = String(date.getUTCMonth() + 1).padStart(2, '0');

                return `${year}-${month}`;
            }
        }

        function formatDayTitle(date) {
            try {
                return new Intl.DateTimeFormat(undefined, {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                    timeZone: 'UTC',
                }).format(date);
            } catch (error) {
                const year = date.getUTCFullYear();
                const month = String(date.getUTCMonth() + 1).padStart(2, '0');
                const day = String(date.getUTCDate()).padStart(2, '0');

                return `${year}-${month}-${day}`;
            }
        }

        function parseSlotDate(value) {
            if (!value) {
                return null;
            }

            const iso = String(value).replace(' ', 'T') + 'Z';
            const date = new Date(iso);

            return Number.isNaN(date.getTime()) ? null : date;
        }

        function formatTime(date) {
            if (!date) {
                return '';
            }

            try {
                return new Intl.DateTimeFormat(undefined, {
                    hour: '2-digit',
                    minute: '2-digit',
                }).format(date);
            } catch (error) {
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');

                return `${hours}:${minutes}`;
            }
        }

        function clear(node) {
            while (node.firstChild) {
                node.removeChild(node.firstChild);
            }
        }

        function setLoading(isLoading) {
            loadingNode.hidden = !isLoading;
            if (isLoading) {
                loadingNode.textContent = container.dataset.loadingText || 'Loading…';
            }
        }

        function showError(message) {
            if (message) {
                errorNode.textContent = message;
                errorNode.hidden = false;
            } else {
                errorNode.hidden = true;
                errorNode.textContent = '';
            }
        }

        const toolbar = document.createElement('div');
        toolbar.className = 'fp-exp-calendar__toolbar';

        const titleNode = document.createElement('div');
        titleNode.className = 'fp-exp-calendar__toolbar-title';
        toolbar.appendChild(titleNode);

        const nav = document.createElement('div');
        nav.className = 'fp-exp-calendar__nav';

        const prevButton = document.createElement('button');
        prevButton.type = 'button';
        prevButton.className = 'button button-secondary';
        prevButton.textContent = calendarConfig.i18n && calendarConfig.i18n.previous
            ? calendarConfig.i18n.previous
            : 'Previous';

        const nextButton = document.createElement('button');
        nextButton.type = 'button';
        nextButton.className = 'button button-secondary';
        nextButton.textContent = calendarConfig.i18n && calendarConfig.i18n.next
            ? calendarConfig.i18n.next
            : 'Next';

        // Experience selector
        const experienceSelect = document.createElement('select');
        experienceSelect.className = 'fp-exp-calendar__experience';
        const expOptions = Array.isArray(calendarConfig.experiences) ? calendarConfig.experiences : [];
        
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = (calendarConfig.i18n && calendarConfig.i18n.selectExperience) ? calendarConfig.i18n.selectExperience : 'Select experience';
        experienceSelect.appendChild(placeholder);
        
        if (expOptions.length) {
            expOptions.forEach((opt) => {
                if (!opt || typeof opt.id !== 'number') {
                    return;
                }
                const option = document.createElement('option');
                option.value = String(opt.id);
                option.textContent = String(opt.title || opt.id);
                experienceSelect.appendChild(option);
            });
        } else {
            // Nessuna esperienza disponibile
            const noExpOption = document.createElement('option');
            noExpOption.value = '';
            noExpOption.textContent = 'Nessuna esperienza disponibile';
            noExpOption.disabled = true;
            noExpOption.selected = true;
            experienceSelect.appendChild(noExpOption);
        }

        // View toggle (List / Calendar)
        const viewsWrapper = document.createElement('div');
        viewsWrapper.className = 'fp-exp-calendar__views';
        const listViewBtn = document.createElement('button');
        listViewBtn.type = 'button';
        listViewBtn.className = 'button button-secondary';
        listViewBtn.textContent = (calendarConfig.i18n && calendarConfig.i18n.listView) ? calendarConfig.i18n.listView : 'Lista';
        const calendarViewBtn = document.createElement('button');
        calendarViewBtn.type = 'button';
        calendarViewBtn.className = 'button button-secondary';
        calendarViewBtn.textContent = (calendarConfig.i18n && calendarConfig.i18n.calendarView) ? calendarConfig.i18n.calendarView : 'Calendario';
        viewsWrapper.appendChild(listViewBtn);
        viewsWrapper.appendChild(calendarViewBtn);

        // Availability filter (client-side)
        const availabilityFilter = document.createElement('select');
        availabilityFilter.className = 'fp-exp-calendar__availability-filter';
        const optAll = document.createElement('option');
        optAll.value = 'all';
        optAll.textContent = (calendarConfig.i18n && calendarConfig.i18n.filterAll) ? calendarConfig.i18n.filterAll : 'Tutte';
        const optAvail = document.createElement('option');
        optAvail.value = 'available';
        optAvail.textContent = (calendarConfig.i18n && calendarConfig.i18n.filterAvailable) ? calendarConfig.i18n.filterAvailable : 'Disponibili';
        const optFull = document.createElement('option');
        optFull.value = 'full';
        optFull.textContent = (calendarConfig.i18n && calendarConfig.i18n.filterFull) ? calendarConfig.i18n.filterFull : 'Al completo';
        availabilityFilter.appendChild(optAll);
        availabilityFilter.appendChild(optAvail);
        availabilityFilter.appendChild(optFull);

        nav.appendChild(experienceSelect);
        nav.appendChild(availabilityFilter);
        nav.appendChild(prevButton);
        nav.appendChild(nextButton);
        nav.appendChild(viewsWrapper);
        toolbar.appendChild(nav);

        const contentNode = document.createElement('div');
        contentNode.className = 'fp-exp-calendar__content';

        clear(bodyNode);
        bodyNode.appendChild(toolbar);
        bodyNode.appendChild(contentNode);
        // Rendi subito visibile la toolbar anche prima del primo fetch
        bodyNode.hidden = false;

        let currentMonth = parseBootstrapStart();
        if (!currentMonth) {
            const now = new Date();
            currentMonth = createUTCDate(now.getUTCFullYear(), now.getUTCMonth(), 1);
        }

        let currentView = 'calendar';
        function setActiveViewButtons() {
            listViewBtn.classList.toggle('is-active', currentView === 'list');
            calendarViewBtn.classList.toggle('is-active', currentView === 'calendar');
            listViewBtn.setAttribute('aria-pressed', currentView === 'list' ? 'true' : 'false');
            calendarViewBtn.setAttribute('aria-pressed', currentView === 'calendar' ? 'true' : 'false');
        }
        setActiveViewButtons();

        function groupSlotsByDay(slots) {
            const groups = new Map();
            slots.forEach((slot) => {
                const start = typeof slot.start === 'string' ? slot.start : '';
                const dayKey = start ? start.slice(0, 10) : '';
                if (!dayKey) { return; }
                if (!groups.has(dayKey)) { groups.set(dayKey, []); }
                groups.get(dayKey).push(slot);
            });
            return groups;
        }

        function renderList(slots) {
            clear(contentNode);
            const listNode = document.createElement('div');
            listNode.className = 'fp-exp-calendar__list';

            if (!slots.length) {
                const empty = document.createElement('p');
                empty.className = 'fp-exp-calendar__empty';
                empty.textContent = calendarConfig.i18n && calendarConfig.i18n.noSlots
                    ? calendarConfig.i18n.noSlots
                    : 'No slots scheduled for this period.';
                listNode.appendChild(empty);
                contentNode.appendChild(listNode);
                return;
            }

            const groups = groupSlotsByDay(slots);
            const orderedDays = Array.from(groups.keys()).sort();

            orderedDays.forEach((dayKey) => {
                const dayDate = parseSlotDate(`${dayKey} 00:00:00`);
                const daySection = document.createElement('section');
                daySection.className = 'fp-exp-calendar__day';

                const header = document.createElement('header');
                header.className = 'fp-exp-calendar__day-header';
                header.textContent = formatDayTitle(dayDate || new Date());
                daySection.appendChild(header);

                const list = document.createElement('ul');
                list.className = 'fp-exp-calendar__slots';

                groups.get(dayKey).forEach((slot) => {
                    const item = document.createElement('li');
                    item.className = 'fp-exp-calendar__slot';
                    if (typeof slot.id === 'number') {
                        item.draggable = true;
                        item.dataset.slotId = String(slot.id);
                        item.dataset.start = String(slot.start || '');
                        item.dataset.end = String(slot.end || '');
                    }

                    const title = document.createElement('div');
                    title.textContent = slot.experience_title
                        ? slot.experience_title
                        : (calendarConfig.i18n && calendarConfig.i18n.untitledExperience
                            ? calendarConfig.i18n.untitledExperience
                            : 'Untitled experience');
                    item.appendChild(title);

                    const startDate = parseSlotDate(slot.start);
                    const endDate = parseSlotDate(slot.end);
                    const startLabel = formatTime(startDate);
                    const endLabel = formatTime(endDate);
                    const timeLabel = startLabel && endLabel ? `${startLabel} – ${endLabel}` : startLabel;

                    if (timeLabel) {
                        const time = document.createElement('div');
                        time.className = 'fp-exp-calendar__slot-meta';
                        const timeValue = document.createElement('strong');
                        timeValue.textContent = timeLabel;
                        time.appendChild(timeValue);
                        item.appendChild(time);
                    }

                    const remaining = typeof slot.remaining === 'number' ? slot.remaining : 0;
                    const total = typeof slot.capacity_total === 'number' ? slot.capacity_total : 0;
                    const reserved = typeof slot.reserved === 'number' ? slot.reserved : 0;

                    const capacityWrapper = document.createElement('div');
                    capacityWrapper.className = 'fp-exp-calendar__slot-meta';

                    const seatsLabel = calendarConfig.i18n && calendarConfig.i18n.seatsAvailable
                        ? calendarConfig.i18n.seatsAvailable
                        : 'seats available';
                    const capacity = document.createElement('span');
                    const strong = document.createElement('strong');
                    strong.textContent = `${remaining}/${total}`;
                    capacity.appendChild(strong);
                    capacity.appendChild(document.createTextNode(` ${seatsLabel}`));
                    capacityWrapper.appendChild(capacity);

                    const reservedLabel = document.createElement('span');
                    reservedLabel.textContent = `${reserved} ${calendarConfig.i18n && calendarConfig.i18n.bookedLabel
                        ? calendarConfig.i18n.bookedLabel
                        : 'booked'}`;
                    capacityWrapper.appendChild(reservedLabel);

                    const perType = slot.capacity_per_type && typeof slot.capacity_per_type === 'object'
                        ? Object.entries(slot.capacity_per_type)
                        : [];

                    if (perType.length) {
                        perType.forEach(([type, amount]) => {
                            const badge = document.createElement('span');
                            badge.textContent = `${type}: ${amount}`;
                            capacityWrapper.appendChild(badge);
                        });
                    }

                    item.appendChild(capacityWrapper);

                    // Drag handlers
                    item.addEventListener('dragstart', (ev) => {
                        ev.dataTransfer && ev.dataTransfer.setData('text/plain', JSON.stringify({
                            id: slot.id,
                            start: slot.start,
                            end: slot.end,
                        }));
                        ev.dataTransfer && (ev.dataTransfer.effectAllowed = 'move');
                    });
                    list.appendChild(item);
                });

                daySection.appendChild(list);
                listNode.appendChild(daySection);
            });

            contentNode.appendChild(listNode);
        }

        function weekdayHeaders() {
            const headers = [];
            const base = createUTCDate(2023, 0, 2); // Monday, Jan 2, 2023
            for (let i = 0; i < 7; i++) {
                const d = createUTCDate(base.getUTCFullYear(), base.getUTCMonth(), base.getUTCDate() + i);
                try {
                    headers.push(new Intl.DateTimeFormat(undefined, { weekday: 'short', timeZone: 'UTC' }).format(d));
                } catch (e) {
                    headers.push(['Mo','Tu','We','Th','Fr','Sa','Su'][i]);
                }
            }
            return headers;
        }

        function startOfGrid(date) {
            const first = createUTCDate(date.getUTCFullYear(), date.getUTCMonth(), 1);
            const w = first.getUTCDay(); // 0=Sun...6=Sat
            const mondayIndex = (w + 6) % 7; // 0=Mon index
            return createUTCDate(first.getUTCFullYear(), first.getUTCMonth(), 1 - mondayIndex);
        }

        function sameDay(a, b) {
            return a.getUTCFullYear() === b.getUTCFullYear() && a.getUTCMonth() === b.getUTCMonth() && a.getUTCDate() === b.getUTCDate();
        }

        function renderGrid(slots, date) {
            clear(contentNode);
            const gridWrap = document.createElement('div');
            gridWrap.className = 'fp-exp-calendar__grid';

            const head = document.createElement('div');
            head.className = 'fp-exp-calendar__grid-head';
            weekdayHeaders().forEach((label) => {
                const h = document.createElement('div');
                h.className = 'fp-exp-calendar__grid-head-cell';
                h.textContent = label;
                head.appendChild(h);
            });
            gridWrap.appendChild(head);

            const body = document.createElement('div');
            body.className = 'fp-exp-calendar__grid-body';

            const groups = groupSlotsByDay(slots);
            const firstCell = startOfGrid(date);
            const today = new Date();
            for (let i = 0; i < 42; i++) {
                const cellDate = createUTCDate(firstCell.getUTCFullYear(), firstCell.getUTCMonth(), firstCell.getUTCDate() + i);
                const key = formatRequestDate(cellDate);
                const daySlots = groups.get(key) || [];

                const cell = document.createElement('button');
                cell.type = 'button';
                cell.className = 'fp-exp-calendar__grid-cell';

                const inCurrentMonth = cellDate.getUTCMonth() === date.getUTCMonth();
                if (!inCurrentMonth) { cell.classList.add('is-out'); }
                if (sameDay(cellDate, today)) { cell.classList.add('is-today'); }

                const dayNum = document.createElement('div');
                dayNum.className = 'fp-exp-calendar__grid-day';
                dayNum.textContent = String(cellDate.getUTCDate());
                cell.appendChild(dayNum);

                if (daySlots.length) {
                    const count = document.createElement('div');
                    count.className = 'fp-exp-calendar__grid-count';
                    count.textContent = `${daySlots.length}`;
                    cell.appendChild(count);

                    let remainingSum = 0; let totalSum = 0;
                    daySlots.forEach((s) => {
                        const rem = typeof s.remaining === 'number' ? s.remaining : 0;
                        const tot = typeof s.capacity_total === 'number' ? s.capacity_total : 0;
                        remainingSum += rem; totalSum += tot;
                    });
                    if (totalSum > 0) {
                        const bar = document.createElement('div');
                        bar.className = 'fp-exp-calendar__grid-capacity';
                        const fill = document.createElement('span');
                        const ratio = Math.max(0, Math.min(1, remainingSum / totalSum));
                        fill.style.width = `${Math.round(ratio * 100)}%`;
                        bar.appendChild(fill);
                        cell.appendChild(bar);
                        // Status badges: fully booked / low availability
                        if (remainingSum === 0) {
                            const badge = document.createElement('span');
                            badge.className = 'fp-exp-calendar__grid-badge is-full';
                            badge.textContent = (calendarConfig.i18n && calendarConfig.i18n.fullLabel) ? calendarConfig.i18n.fullLabel : 'Al completo';
                            cell.appendChild(badge);
                        } else if (ratio <= 0.2) {
                            const badge = document.createElement('span');
                            badge.className = 'fp-exp-calendar__grid-badge is-low';
                            badge.textContent = (calendarConfig.i18n && calendarConfig.i18n.lowLabel) ? calendarConfig.i18n.lowLabel : 'Pochi posti';
                            cell.appendChild(badge);
                        }
                    }
                } else {
                    const empty = document.createElement('div');
                    empty.className = 'fp-exp-calendar__grid-empty';
                    empty.textContent = '\u2013';
                    cell.appendChild(empty);
                }

                cell.addEventListener('click', () => {
                    currentView = 'list';
                    setActiveViewButtons();
                    // Render only chosen day in list view
                    const scoped = (groups.get(key) || []).map((s) => s);
                    renderList(scoped);
                    const headerLabel = formatDayTitle(cellDate);
                    titleNode.textContent = headerLabel;
                });

                // Drop target for DnD
                cell.addEventListener('dragover', (ev) => {
                    ev.preventDefault();
                    cell.classList.add('is-dragover');
                    if (ev.dataTransfer) { ev.dataTransfer.dropEffect = 'move'; }
                });
                cell.addEventListener('dragleave', () => {
                    cell.classList.remove('is-dragover');
                });
                cell.addEventListener('drop', async (ev) => {
                    ev.preventDefault();
                    cell.classList.remove('is-dragover');
                    if (!calendarConfig.endpoints || !calendarConfig.endpoints.move) { return; }
                    try {
                        const raw = ev.dataTransfer ? ev.dataTransfer.getData('text/plain') : '';
                        const dragged = raw ? JSON.parse(raw) : null;
                        if (!dragged || typeof dragged.id !== 'number') { return; }

                        const startOld = parseSlotDate(dragged.start);
                        const endOld = parseSlotDate(dragged.end);
                        if (!startOld || !endOld) { return; }

                        const newStart = createUTCDate(cellDate.getUTCFullYear(), cellDate.getUTCMonth(), cellDate.getUTCDate());
                        newStart.setUTCHours(startOld.getUTCHours(), startOld.getUTCMinutes(), 0, 0);
                        const newEnd = createUTCDate(cellDate.getUTCFullYear(), cellDate.getUTCMonth(), cellDate.getUTCDate());
                        newEnd.setUTCHours(endOld.getUTCHours(), endOld.getUTCMinutes(), 0, 0);

                        const startSql = `${String(newStart.getUTCFullYear()).padStart(4, '0')}-${String(newStart.getUTCMonth()+1).padStart(2,'0')}-${String(newStart.getUTCDate()).padStart(2,'0')} ${String(newStart.getUTCHours()).padStart(2,'0')}:${String(newStart.getUTCMinutes()).padStart(2,'0')}:00`;
                        const endSql = `${String(newEnd.getUTCFullYear()).padStart(4, '0')}-${String(newEnd.getUTCMonth()+1).padStart(2,'0')}-${String(newEnd.getUTCDate()).padStart(2,'0')} ${String(newEnd.getUTCHours()).padStart(2,'0')}:${String(newEnd.getUTCMinutes()).padStart(2,'0')}:00`;

                        let confirmText = calendarConfig.i18n && calendarConfig.i18n.moveConfirm ? calendarConfig.i18n.moveConfirm : 'Move slot to %s at %s?';
                        const dayLabel = formatDayTitle(newStart);
                        const timeLabel = formatTime(newStart);
                        confirmText = confirmText.replace('%s', dayLabel).replace('%s', timeLabel);
                        if (!window.confirm(confirmText)) { return; }

                        const moveUrl = `${String(calendarConfig.endpoints.move).replace(/\/$/, '')}/${dragged.id}/move`;
                        const response = await window.fetch(moveUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': calendarConfig.nonce || '',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ start: startSql, end: endSql }),
                        });
                        if (!response.ok) {
                            const err = await response.json().catch(() => ({}));
                            throw new Error(err && err.message ? String(err.message) : response.statusText);
                        }
                        // Reload month after successful move
                        loadMonth(currentMonth);
                    } catch (e) {
                        const msg = (e && e.message) ? e.message : (calendarConfig.i18n && calendarConfig.i18n.updateError ? calendarConfig.i18n.updateError : 'Errore');
                        showError(msg);
                    }
                });

                body.appendChild(cell);
            }

            contentNode.appendChild(gridWrap);
            contentNode.appendChild(body);
        }

        function renderSlots(slots) {
            if (currentView === 'calendar') {
                renderGrid(slots, currentMonth);
            } else {
                renderList(slots);
            }
        }

        function resolveEndpoint(url) {
            try {
                return new URL(url).toString();
            } catch (error) {
                return new URL(url, window.location.origin).toString();
            }
        }

        function applyClientFilter(slots) {
            const mode = availabilityFilter.value || 'all';
            if (mode === 'all') { return slots; }
            if (mode === 'available') {
                return slots.filter((s) => (typeof s.remaining === 'number' ? s.remaining : (typeof s.capacity_remaining === 'number' ? s.capacity_remaining : 0)) > 0);
            }
            if (mode === 'full') {
                return slots.filter((s) => (typeof s.remaining === 'number' ? s.remaining : (typeof s.capacity_remaining === 'number' ? s.capacity_remaining : 0)) === 0);
            }
            return slots;
        }

        async function loadMonth(date) {
            const range = monthRange(date);
            titleNode.textContent = formatMonthTitle(range.start);
            setLoading(true);
            showError('');
			// Loading month data

            const requestUrl = resolveEndpoint(slotsEndpoint);
            const url = new URL(requestUrl);
            url.searchParams.set('start', formatRequestDate(range.start));
            url.searchParams.set('end', formatRequestDate(range.end));
            const selectedExperience = experienceSelect && experienceSelect.value ? parseInt(String(experienceSelect.value), 10) || 0 : 0;
            
            // Validazione: esperienza deve essere selezionata
            if (selectedExperience <= 0) {
                const expOptions = Array.isArray(calendarConfig.experiences) ? calendarConfig.experiences : [];
                let message;
                if (expOptions.length === 0) {
                    message = 'Nessuna esperienza disponibile. Crea prima un\'esperienza per visualizzare il calendario.';
                } else {
                    message = calendarConfig.i18n && calendarConfig.i18n.selectExperienceFirst
                        ? calendarConfig.i18n.selectExperienceFirst
                        : 'Seleziona un\'esperienza per visualizzare la disponibilità';
                }
                showError(message);
                setLoading(false);
                renderSlots([]);
                bodyNode.hidden = false; // mostra la toolbar e l'errore
                return;
            }
            
            url.searchParams.set('experience', String(selectedExperience));

            try {
                let payload;
                const requestHeaders = {
                    'X-WP-Nonce': calendarConfig.nonce || '',
                };

				if (window.wp && window.wp.apiFetch) {
                    payload = await window.wp.apiFetch({
                        url: url.toString(),
                        method: 'GET',
                        headers: requestHeaders,
                    });
                } else {
                    const response = await window.fetch(url.toString(), {
                        credentials: 'same-origin',
                        headers: requestHeaders,
                    });

                    if (!response.ok) {
                        const errorBody = await response.json().catch(() => ({}));
                        let message = errorBody && errorBody.message ? String(errorBody.message) : response.statusText;
                        
                        // Messaggi di errore specifici per codice HTTP
                        if (!message || message === 'Request failed') {
                            if (response.status === 401 || response.status === 403) {
                                message = calendarConfig.i18n && calendarConfig.i18n.accessDenied
                                    ? calendarConfig.i18n.accessDenied
                                    : 'Accesso negato. Ricarica la pagina e riprova.';
                            } else if (response.status === 404) {
                                message = calendarConfig.i18n && calendarConfig.i18n.notFound
                                    ? calendarConfig.i18n.notFound
                                    : 'Risorsa non trovata.';
                            } else if (response.status >= 500) {
                                message = calendarConfig.i18n && calendarConfig.i18n.serverError
                                    ? calendarConfig.i18n.serverError
                                    : 'Errore del server. Riprova tra qualche minuto.';
                            }
                        }
                        
                        throw new Error(message || 'Request failed');
                    }

                    payload = await response.json().catch(() => ({}));
                }

				const slots = payload && Array.isArray(payload.slots) ? payload.slots : [];
                const filtered = applyClientFilter(slots);
                renderSlots(filtered);
                bodyNode.hidden = false;
				// loadMonth OK
            } catch (error) {
                const fallback = calendarConfig.i18n && calendarConfig.i18n.loadError
                    ? calendarConfig.i18n.loadError
                    : 'Unable to load the calendar. Please try again.';
                renderSlots([]);
                const message = error && error.message ? String(error.message) : fallback;
                showError(message);
				// loadMonth FAILED
            } finally {
                setLoading(false);
            }
        }

        prevButton.addEventListener('click', () => {
            currentMonth = addMonths(currentMonth, -1);
            loadMonth(currentMonth);
        });

        nextButton.addEventListener('click', () => {
            currentMonth = addMonths(currentMonth, 1);
            loadMonth(currentMonth);
        });

        listViewBtn.addEventListener('click', () => {
            if (currentView !== 'list') {
                currentView = 'list';
                setActiveViewButtons();
                loadMonth(currentMonth);
            }
        });

        calendarViewBtn.addEventListener('click', () => {
            if (currentView !== 'calendar') {
                currentView = 'calendar';
                setActiveViewButtons();
                loadMonth(currentMonth);
            }
        });

        // Debouncing per evitare chiamate API multiple
        let loadTimeout = null;
        experienceSelect.addEventListener('change', () => {
            if (loadTimeout) {
                clearTimeout(loadTimeout);
            }
            // Mostra immediatamente lo stato di loading
            setLoading(true);
            showError('');
            loadTimeout = setTimeout(() => {
                loadMonth(currentMonth);
            }, 300);
        });

        availabilityFilter.addEventListener('change', () => {
            // Ricarica per ri-applicare il filtro client side dopo fetch
            setLoading(true);
            showError('');
            loadMonth(currentMonth);
        });

		// Seleziona automaticamente la prima esperienza se presente
        if (experienceSelect && (!experienceSelect.value || experienceSelect.value === '')) {
            const firstOption = Array.from(experienceSelect.options).find((o) => o.value && o.value !== '');
            if (firstOption) {
                experienceSelect.value = firstOption.value;
				// Selezionata esperienza di default
            }
        }

        // Carica il calendario solo DOPO aver selezionato l'esperienza
        loadMonth(currentMonth);
    }

    // Fix per assicurare che gli eventi della tastiera funzionino correttamente
    // Questo risolve problemi con conflitti di altri plugin o con l'editor WordPress
    // In particolare, risolve il problema delle tab ARIA che intercettano gli eventi
    // FUNZIONA PER TUTTE LE TABLIST ARIA (Yoast, FP-Experiences, ecc.) - anche quelle create dinamicamente
    function ensureKeyboardEventsWork() {
        // Fix CRITICO: WordPress core, jQuery UI e altri script intercettano keydown sulle tab ARIA
        // Questo può bloccare gli eventi anche quando il focus è su input/textarea
        // Aggiungiamo un listener GLOBALE su document che ripristina eventi bloccati
        // su QUALSIASI tablist ARIA nella pagina (anche quelle create dinamicamente)
        
        // Listener globale su document in bubble phase per intercettare eventi bloccati
        // su QUALSIASI tablist ARIA nella pagina
        document.addEventListener('keydown', (e) => {
            const activeElement = document.activeElement;
            
            // Se il focus è su un input, textarea o elemento contenteditable
            if (activeElement && (
                activeElement.tagName === 'INPUT' || 
                activeElement.tagName === 'TEXTAREA' || 
                activeElement.isContentEditable === true ||
                activeElement.contentEditable === 'true'
            )) {
                // Per caratteri normali (non tasti speciali)
                if (e.key && e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                    // Se l'evento è stato preventDefault, significa che un altro listener
                    // (probabilmente WordPress core, jQuery UI o plugin) lo ha bloccato
                    if (e.defaultPrevented && e.target === activeElement) {
                        // Salva il valore corrente e la posizione del cursore
                        const valueBefore = activeElement.value || '';
                        const selectionStart = activeElement.selectionStart !== null ? activeElement.selectionStart : valueBefore.length;
                        const selectionEnd = activeElement.selectionEnd !== null ? activeElement.selectionEnd : valueBefore.length;
                        
                        // Aspetta un breve momento per vedere se il valore è cambiato
                        setTimeout(() => {
                            const valueAfter = activeElement.value || '';
                            
                            // Se il valore non è cambiato, significa che l'evento è stato bloccato
                            // Ripristinalo impostando direttamente il valore
                            if (valueAfter === valueBefore) {
                                if (activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA') {
                                    // Per input e textarea, imposta direttamente il valore
                                    const newValue = valueBefore.slice(0, selectionStart) + e.key + valueBefore.slice(selectionEnd);
                                    activeElement.value = newValue;
                                    
                                    // Ripristina la posizione del cursore
                                    const newPosition = selectionStart + 1;
                                    activeElement.setSelectionRange(newPosition, newPosition);
                                    
                                    // Dispatch evento input per notificare il cambio
                                    activeElement.dispatchEvent(new Event('input', { bubbles: true }));
                                    activeElement.dispatchEvent(new Event('change', { bubbles: true }));
                                } else if (activeElement.isContentEditable) {
                                    // Per elementi contenteditable, inserisci il testo
                                    const selection = window.getSelection();
                                    if (selection && selection.rangeCount > 0) {
                                        const range = selection.getRangeAt(0);
                                        range.deleteContents();
                                        const textNode = document.createTextNode(e.key);
                                        range.insertNode(textNode);
                                        range.setStartAfter(textNode);
                                        range.collapse(true);
                                        selection.removeAllRanges();
                                        selection.addRange(range);
                                    }
                                }
                            }
                        }, 10); // Breve timeout per verificare se il valore è cambiato
                    }
                }
            }
        }, { capture: false }); // Bubble phase per intercettare DOPO che l'evento ha raggiunto l'input
    }

    ready(() => {
        const html = document.documentElement;
        if (html) {
            html.classList.add('fp-exp-admin-shell');
        }

        if (document.body) {
            document.body.classList.add('fp-exp-admin-shell');
        }

        // Inizializza il fix per gli eventi della tastiera
        ensureKeyboardEventsWork();

        const root = document.querySelector('[data-fp-exp-admin]');
        if (root) {
            initTabs(root);
            initRepeaters(root);
            initMediaControls(root);
            initGalleryControls(root);
            initTaxonomyEditors(root);
            initRecurrence(root);
            initFormValidation(root);
            initCognitiveBiasLimiter(root);
            initEmailSubjectPreview(root);
        }

        initCalendarApp();
        initTools();
    });
})();
