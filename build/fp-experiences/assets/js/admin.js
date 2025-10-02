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
                select: chooseButton.dataset.labelSelect || getString('selectImage') || 'Select image',
                change: chooseButton.dataset.labelChange || getString('changeImage') || 'Change image',
                remove: getString('removeImage') || removeButton.textContent || 'Remove image',
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

            function resolveSource(data) {
                if (!data) {
                    return { url: '', width: 0, height: 0 };
                }

                const sizes = data.sizes || {};
                const preferred = sizes.thumbnail || sizes.medium || sizes.medium_large || sizes.large || sizes.full;
                const source = preferred || {};

                return {
                    url: source.url || data.url || '',
                    width: source.width || data.width || 0,
                    height: source.height || data.height || 0,
                };
            }

            function renderImage(attachment) {
                if (!attachment) {
                    input.value = '';
                    renderPlaceholder();
                    return;
                }

                const source = resolveSource(attachment);
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
                initMediaControls(newNode);
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

    function initRecurrence(root) {
        const toggle = root.querySelector('[data-recurrence-toggle]');
        const settings = root.querySelector('[data-recurrence-settings]');
        if (!toggle || !settings) {
            return;
        }

        initRecurrenceTimeSets(settings);

        const adminConfig = window.fpExpAdmin || {};
        const restConfig = adminConfig.rest || null;

        const frequency = settings.querySelector('[data-recurrence-frequency]');
        const daysContainer = settings.querySelector('[data-recurrence-days]');
        const status = settings.querySelector('[data-recurrence-status]');
        const errors = settings.querySelector('[data-recurrence-errors]');
        const previewWrapper = settings.querySelector('[data-recurrence-preview-list]');
        const previewList = previewWrapper ? previewWrapper.querySelector('ul') : null;
        const previewButton = settings.querySelector('[data-recurrence-preview]');
        const generateButton = settings.querySelector('[data-recurrence-generate]');

        function toggleSettings() {
            settings.toggleAttribute('hidden', !toggle.checked);
        }

        function updateDaysVisibility() {
            if (!frequency) {
                return;
            }

            const show = frequency.value === 'weekly';

            if (daysContainer) {
                daysContainer.toggleAttribute('hidden', !show);
            }

            const perSetContainers = Array.from(settings.querySelectorAll('[data-time-set-days]'));
            perSetContainers.forEach((container) => {
                container.toggleAttribute('hidden', !show);
            });
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

            if (!toggle.checked) {
                showError(getString('recurrenceMissingTimes'));
                return null;
            }

            const recurrence = {
                enabled: true,
                start_date: getInputValue(settings, 'input[name="fp_exp_availability[recurrence][start_date]"]'),
                end_date: getInputValue(settings, 'input[name="fp_exp_availability[recurrence][end_date]"]'),
                frequency: frequency ? frequency.value : 'weekly',
                days: [],
                duration: parseInt(getInputValue(settings, 'input[name="fp_exp_availability[recurrence][duration]"]') || '0', 10) || 0,
                time_sets: [],
            };

            if (recurrence.frequency === 'weekly' && daysContainer) {
                const checkedDays = Array.from(daysContainer.querySelectorAll('input[type="checkbox"]:checked'));
                recurrence.days = checkedDays.map((input) => input.value);
            }

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

                if (recurrence.frequency === 'weekly') {
                    const daysContainer = container.querySelector('[data-time-set-days]');
                    if (daysContainer) {
                        const checkedDays = Array.from(daysContainer.querySelectorAll('input[type="checkbox"]:checked'));
                        const days = checkedDays.map((input) => input.value).filter(Boolean);
                        if (days.length) {
                            setData.days = days;
                        }
                    }
                }

                recurrence.time_sets.push(setData);
            });

            if (!recurrence.time_sets.length) {
                showError(getString('recurrenceMissingTimes'));
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

        toggle.addEventListener('change', () => {
            toggleSettings();
        });

        toggleSettings();

        if (frequency) {
            frequency.addEventListener('change', updateDaysVisibility);
            updateDaysVisibility();
        }

        if (previewButton) {
            previewButton.addEventListener('click', async (event) => {
                event.preventDefault();
                clearStatus();
                clearPreview();
                const payload = collectPayload();
                if (!payload) {
                    return;
                }

                setStatus(getString('recurrenceLoading') || '');

                try {
                    const result = await sendRequest(restConfig ? restConfig.preview : null, payload);
                    renderPreview(result && Array.isArray(result.preview) ? result.preview : []);
                    setStatus('', false);
                } catch (error) {
                    renderPreview([]);
                    setStatus(getString('recurrencePreviewError') || '', true);
                }
            });
        }

        if (generateButton) {
            generateButton.addEventListener('click', async (event) => {
                event.preventDefault();
                clearStatus();
                clearPreview();
                const payload = collectPayload();
                if (!payload) {
                    return;
                }

                if (!payload.experience_id) {
                    showError(getString('recurrencePostMissing'));
                    return;
                }

                setStatus(getString('recurrenceLoading') || '');

                try {
                    const result = await sendRequest(restConfig ? restConfig.generate : null, payload);
                    const created = result && typeof result.created === 'number' ? result.created : 0;
                    renderPreview(result && Array.isArray(result.preview) ? result.preview : []);
                    const success = getString('recurrenceGenerateSuccess');
                    setStatus(success ? success.replace('%d', String(created)) : '', false);
                } catch (error) {
                    setStatus(getString('recurrenceGenerateError') || '', true);
                }
            });
        }
    }

                setStatus(getString('recurrenceLoading') || '');

                try {
                    const result = await sendRequest(restConfig ? restConfig.generate : null, payload);
                    const created = result && typeof result.created === 'number' ? result.created : 0;
                    renderPreview(result && Array.isArray(result.preview) ? result.preview : []);
                    const success = getString('recurrenceGenerateSuccess');
                    setStatus(success ? success.replace('%d', String(created)) : '', false);
                } catch (error) {
                    setStatus(getString('recurrenceGenerateError') || '', true);
                }
            });
        }
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

        const calendarConfig = window.fpExpCalendar || {};
        const endpoints = calendarConfig.endpoints || {};
        const slotsEndpoint = endpoints.slots;
        if (!slotsEndpoint) {
            return;
        }

        const loadingNode = container.querySelector('.fp-exp-calendar__loading');
        const bodyNode = container.querySelector('[data-calendar-content]');
        const errorNode = container.querySelector('[data-calendar-error]');

        if (!loadingNode || !bodyNode || !errorNode) {
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

        nav.appendChild(prevButton);
        nav.appendChild(nextButton);
        toolbar.appendChild(nav);

        const listNode = document.createElement('div');
        listNode.className = 'fp-exp-calendar__list';

        clear(bodyNode);
        bodyNode.appendChild(toolbar);
        bodyNode.appendChild(listNode);

        let currentMonth = parseBootstrapStart();
        if (!currentMonth) {
            const now = new Date();
            currentMonth = createUTCDate(now.getUTCFullYear(), now.getUTCMonth(), 1);
        }

        function renderSlots(slots) {
            clear(listNode);

            if (!slots.length) {
                const empty = document.createElement('p');
                empty.className = 'fp-exp-calendar__empty';
                empty.textContent = calendarConfig.i18n && calendarConfig.i18n.noSlots
                    ? calendarConfig.i18n.noSlots
                    : 'No slots scheduled for this period.';
                listNode.appendChild(empty);

                return;
            }

            const groups = new Map();

            slots.forEach((slot) => {
                const start = typeof slot.start === 'string' ? slot.start : '';
                const dayKey = start ? start.slice(0, 10) : '';
                if (!dayKey) {
                    return;
                }

                if (!groups.has(dayKey)) {
                    groups.set(dayKey, []);
                }

                groups.get(dayKey).push(slot);
            });

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
                    list.appendChild(item);
                });

                daySection.appendChild(list);
                listNode.appendChild(daySection);
            });
        }

        function resolveEndpoint(url) {
            try {
                return new URL(url).toString();
            } catch (error) {
                return new URL(url, window.location.origin).toString();
            }
        }

        async function loadMonth(date) {
            const range = monthRange(date);
            titleNode.textContent = formatMonthTitle(range.start);
            setLoading(true);
            showError('');

            const requestUrl = resolveEndpoint(slotsEndpoint);
            const url = new URL(requestUrl);
            url.searchParams.set('start', formatRequestDate(range.start));
            url.searchParams.set('end', formatRequestDate(range.end));

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
                        const message = errorBody && errorBody.message ? String(errorBody.message) : response.statusText;
                        throw new Error(message || 'Request failed');
                    }

                    payload = await response.json().catch(() => ({}));
                }

                const slots = payload && Array.isArray(payload.slots) ? payload.slots : [];
                renderSlots(slots);
                bodyNode.hidden = false;
            } catch (error) {
                const fallback = calendarConfig.i18n && calendarConfig.i18n.loadError
                    ? calendarConfig.i18n.loadError
                    : 'Unable to load the calendar. Please try again.';
                renderSlots([]);
                const message = error && error.message ? String(error.message) : fallback;
                showError(message);
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

        loadMonth(currentMonth);
    }

    ready(() => {
        const root = document.querySelector('[data-fp-exp-admin]');
        if (root) {
            initTabs(root);
            initRepeaters(root);
            initMediaControls(root);
            initRecurrence(root);
            initFormValidation(root);
        }

        initCalendarApp();
        initTools();
    });
})();
