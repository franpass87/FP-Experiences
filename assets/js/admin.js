(function (window, document) {
    'use strict';

    if (!window || !document) {
        return;
    }

    initBrandingContrast(
        document.querySelector('[data-fp-contrast-report]'),
        document.querySelector('.fp-exp-settings__form')
    );

    const apiFetch = window.wp?.apiFetch;
    if (!apiFetch) {
        return;
    }

    initCalendar(window.fpExpCalendar, document.getElementById('fp-exp-calendar-app'));
    initTools(window.fpExpTools, document.querySelector('[data-fp-exp-tools]'));

    function initCalendar(config, container) {
        if (!config || !container) {
            return;
        }

        const state = {
            view: 'month',
            focus: initialiseFocusDate(container.dataset.bootstrap),
            slots: [],
            loading: false,
            experienceId: 0,
        };

        const views = ['month', 'week', 'day'];

        fetchSlots();

        function initialiseFocusDate(raw) {
            if (!raw) {
                return new Date();
            }

            try {
                const parsed = JSON.parse(raw);
                if (parsed && parsed.range && parsed.range.start) {
                    return new Date(parsed.range.start + 'T00:00:00Z');
                }
            } catch (error) {
                // ignore malformed bootstrap payload
            }

            return new Date();
        }

        function formatDateISO(date) {
            const year = date.getUTCFullYear();
            const month = String(date.getUTCMonth() + 1).padStart(2, '0');
            const day = String(date.getUTCDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        }

        function cloneDate(date) {
            return new Date(date.getTime());
        }

        function computeRange() {
            const focus = cloneDate(state.focus);
            const start = cloneDate(focus);
            const end = cloneDate(focus);

            if ('week' === state.view) {
                const day = focus.getUTCDay() || 7;
                start.setUTCDate(focus.getUTCDate() - day + 1);
                end.setUTCDate(start.getUTCDate() + 6);
            } else if ('day' === state.view) {
                // single day window
            } else {
                start.setUTCDate(1);
                end.setUTCMonth(start.getUTCMonth() + 1, 0);
            }

            return {
                start: formatDateISO(start),
                end: formatDateISO(end),
            };
        }

        function setLoading(isLoading) {
            state.loading = isLoading;
            container.classList.toggle('is-loading', isLoading);
        }

        function fetchSlots() {
            const range = computeRange();
            const params = new URLSearchParams({
                view: state.view,
                start: range.start,
                end: range.end,
            });

            if (state.experienceId) {
                params.append('experience', String(state.experienceId));
            }

            setLoading(true);

            apiFetch({
                path: `${config.endpoints.slots}?${params.toString()}`,
                method: 'GET',
                headers: {
                    'X-WP-Nonce': config.nonce,
                },
            })
                .then((response) => {
                    state.slots = Array.isArray(response?.slots) ? response.slots : [];
                    render();
                })
                .catch(() => {
                    state.slots = [];
                    renderError();
                })
                .finally(() => {
                    setLoading(false);
                });
        }

        function renderError() {
            container.innerHTML = '<div class="fp-exp-calendar__error">' + (config.i18n.updateError || 'Error loading calendar') + '</div>';
        }

        function render() {
            const wrapper = document.createElement('div');
            wrapper.className = 'fp-exp-calendar__inner';

            renderToolbar(wrapper);
            renderGrid(wrapper);

            container.innerHTML = '';
            container.appendChild(wrapper);
        }

        function renderToolbar(wrapper) {
            const toolbar = document.createElement('div');
            toolbar.className = 'fp-exp-calendar__toolbar';

            const nav = document.createElement('div');
            nav.className = 'fp-exp-calendar__nav';

            const prevButton = document.createElement('button');
            prevButton.type = 'button';
            prevButton.className = 'button';
            prevButton.textContent = config.i18n.previous;
            prevButton.addEventListener('click', () => {
                shiftFocus(-1);
            });

            const nextButton = document.createElement('button');
            nextButton.type = 'button';
            nextButton.className = 'button';
            nextButton.textContent = config.i18n.next;
            nextButton.addEventListener('click', () => {
                shiftFocus(1);
            });

            nav.appendChild(prevButton);
            nav.appendChild(nextButton);

            const title = document.createElement('div');
            title.className = 'fp-exp-calendar__title';
            title.textContent = buildRangeLabel();

            const viewSwitch = document.createElement('div');
            viewSwitch.className = 'fp-exp-calendar__views';
            views.forEach((view) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'button' + (view === state.view ? ' button-primary' : '');
                button.textContent = config.i18n[view] || view;
                button.addEventListener('click', () => {
                    state.view = view;
                    fetchSlots();
                });
                viewSwitch.appendChild(button);
            });

            toolbar.appendChild(nav);
            toolbar.appendChild(title);
            toolbar.appendChild(viewSwitch);

            wrapper.appendChild(toolbar);
        }

        function buildRangeLabel() {
            const range = computeRange();
            if ('day' === state.view) {
                return new Date(range.start + 'T00:00:00Z').toLocaleDateString();
            }

            if ('week' === state.view) {
                const start = new Date(range.start + 'T00:00:00Z').toLocaleDateString();
                const end = new Date(range.end + 'T00:00:00Z').toLocaleDateString();
                return `${start} → ${end}`;
            }

            const focus = state.focus;
            return focus.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        }

        function shiftFocus(direction) {
            const focus = state.focus;
            if ('day' === state.view) {
                focus.setUTCDate(focus.getUTCDate() + direction);
            } else if ('week' === state.view) {
                focus.setUTCDate(focus.getUTCDate() + (direction * 7));
            } else {
                focus.setUTCMonth(focus.getUTCMonth() + direction);
            }

            fetchSlots();
        }

        function renderGrid(wrapper) {
            if (!state.slots.length) {
                const empty = document.createElement('div');
                empty.className = 'fp-exp-calendar__empty';
                empty.textContent = config.i18n.noSlots;
                wrapper.appendChild(empty);
                return;
            }

            if ('day' === state.view) {
                wrapper.appendChild(renderDayView());
            } else if ('week' === state.view) {
                wrapper.appendChild(renderWeekView());
            } else {
                wrapper.appendChild(renderMonthView());
            }
        }

        function renderMonthView() {
            const range = computeRange();
            const startDate = new Date(range.start + 'T00:00:00Z');
            const endDate = new Date(range.end + 'T00:00:00Z');
            const grid = document.createElement('div');
            grid.className = 'fp-exp-calendar__grid fp-exp-calendar__grid--month';

            const header = document.createElement('div');
            header.className = 'fp-exp-calendar__row fp-exp-calendar__row--header';
            const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            weekdays.forEach((day) => {
                const cell = document.createElement('div');
                cell.className = 'fp-exp-calendar__cell';
                cell.textContent = day;
                header.appendChild(cell);
            });
            grid.appendChild(header);

            let cursor = new Date(startDate);
            const firstDay = startDate.getUTCDay() || 7;
            cursor.setUTCDate(startDate.getUTCDate() - (firstDay - 1));

            while (cursor <= endDate || cursor.getUTCDay() !== 1) {
                const row = document.createElement('div');
                row.className = 'fp-exp-calendar__row';

                for (let i = 0; i < 7; i += 1) {
                    const dateKey = formatDateISO(cursor);
                    const cell = createDroppableCell(dateKey, cursor.getUTCMonth() === startDate.getUTCMonth());
                    populateCellSlots(cell, dateKey);
                    row.appendChild(cell);
                    cursor.setUTCDate(cursor.getUTCDate() + 1);
                }

                grid.appendChild(row);

                if (cursor > endDate && cursor.getUTCDay() === 1) {
                    break;
                }
            }

            return grid;
        }

        function renderWeekView() {
            const range = computeRange();
            const start = new Date(range.start + 'T00:00:00Z');
            const grid = document.createElement('div');
            grid.className = 'fp-exp-calendar__grid fp-exp-calendar__grid--week';

            for (let i = 0; i < 7; i += 1) {
                const day = new Date(start);
                day.setUTCDate(start.getUTCDate() + i);
                const dateKey = formatDateISO(day);
                const column = createDroppableCell(dateKey, true);
                const heading = document.createElement('strong');
                heading.textContent = day.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric' });
                column.appendChild(heading);
                populateCellSlots(column, dateKey, true);
                grid.appendChild(column);
            }

            return grid;
        }

        function renderDayView() {
            const range = computeRange();
            const start = new Date(range.start + 'T00:00:00Z');
            const wrapper = document.createElement('div');
            wrapper.className = 'fp-exp-calendar__grid fp-exp-calendar__grid--day';
            const column = createDroppableCell(formatDateISO(start), true);
            populateCellSlots(column, formatDateISO(start), true);
            wrapper.appendChild(column);

            return wrapper;
        }

        function createDroppableCell(dateKey, isCurrentMonth) {
            const cell = document.createElement('div');
            cell.className = 'fp-exp-calendar__cell';
            if (!isCurrentMonth) {
                cell.classList.add('is-muted');
            }
            cell.dataset.date = dateKey;
            cell.addEventListener('dragover', (event) => {
                event.preventDefault();
                cell.classList.add('is-dragover');
            });
            cell.addEventListener('dragleave', () => {
                cell.classList.remove('is-dragover');
            });
            cell.addEventListener('drop', (event) => {
                event.preventDefault();
                cell.classList.remove('is-dragover');
                const payload = event.dataTransfer?.getData('text/plain');
                if (!payload) {
                    return;
                }
                try {
                    const data = JSON.parse(payload);
                    if (data && data.id && data.start && data.duration) {
                        handleMoveSlot(data, cell.dataset.date);
                    }
                } catch (error) {
                    // ignore invalid payload
                }
            });

            const label = document.createElement('span');
            label.className = 'fp-exp-calendar__date';
            const date = new Date(dateKey + 'T00:00:00Z');
            label.textContent = date.getUTCDate();
            cell.appendChild(label);

            return cell;
        }

        function populateCellSlots(cell, dateKey, showTime) {
            const slots = state.slots.filter((slot) => slot.start?.startsWith(dateKey));
            slots.forEach((slot) => {
                cell.appendChild(renderSlot(slot, showTime));
            });
        }

        function renderSlot(slot, showTime) {
            const element = document.createElement('div');
            element.className = 'fp-exp-calendar__slot';
            element.draggable = true;
            element.dataset.slotId = slot.id;
            element.dataset.start = slot.start;
            element.dataset.end = slot.end;
            element.dataset.duration = slot.duration || 0;

            element.addEventListener('dragstart', (event) => {
                if (!event.dataTransfer) {
                    return;
                }
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', JSON.stringify({
                    id: slot.id,
                    start: slot.start,
                    duration: slot.duration || 0,
                }));
            });

            const title = document.createElement('strong');
            title.className = 'fp-exp-calendar__slot-title';
            title.textContent = slot.experience_title || ('#' + slot.experience_id);
            element.appendChild(title);

            if (showTime) {
                const time = document.createElement('div');
                time.className = 'fp-exp-calendar__slot-time';
                const startTime = slot.start ? new Date(slot.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
                const endTime = slot.end ? new Date(slot.end).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
                time.textContent = `${startTime} → ${endTime}`;
                element.appendChild(time);
            }

            const capacity = document.createElement('div');
            capacity.className = 'fp-exp-calendar__slot-capacity';
            const remaining = slot.remaining ?? 0;
            const total = slot.capacity_total ?? 0;
            capacity.textContent = `${remaining}/${total}`;
            element.appendChild(capacity);

            const actions = document.createElement('div');
            actions.className = 'fp-exp-calendar__slot-actions';
            const editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.className = 'button button-small';
            editButton.textContent = '✎';
            editButton.title = config.i18n.capacityPrompt;
            editButton.addEventListener('click', () => {
                promptCapacityUpdate(slot);
            });
            actions.appendChild(editButton);
            element.appendChild(actions);

            return element;
        }

        function handleMoveSlot(payload, targetDate) {
            if (!targetDate) {
                return;
            }

            const originalStart = new Date(payload.start);
            const durationMinutes = parseInt(payload.duration, 10) || 0;
            const [hour, minute] = [originalStart.getUTCHours(), originalStart.getUTCMinutes()];
            const newStart = new Date(targetDate + 'T00:00:00Z');
            newStart.setUTCHours(hour, minute, 0, 0);
            const newEnd = new Date(newStart);
            newEnd.setUTCMinutes(newEnd.getUTCMinutes() + durationMinutes);

            const confirmed = window.confirm(
                (config.i18n.moveConfirm || 'Move slot to %s at %s?')
                    .replace('%s', newStart.toLocaleDateString())
                    .replace('%s', newStart.toLocaleTimeString())
            );

            if (!confirmed) {
                return;
            }

            apiFetch({
                path: `${config.endpoints.move}/${payload.id}/move`,
                method: 'POST',
                headers: {
                    'X-WP-Nonce': config.nonce,
                },
                data: {
                    start: newStart.toISOString(),
                    end: newEnd.toISOString(),
                },
            })
                .then(() => {
                    fetchSlots();
                    notifySuccess(config.i18n.updateSuccess);
                })
                .catch(() => {
                    notifyError(config.i18n.updateError);
                });
        }

        function promptCapacityUpdate(slot) {
            const totalPrompt = window.prompt(config.i18n.capacityPrompt, slot.capacity_total || '');
            if (null === totalPrompt) {
                return;
            }

            const capacityTotal = parseInt(totalPrompt, 10);
            if (Number.isNaN(capacityTotal)) {
                notifyError(config.i18n.updateError);
                return;
            }

            const perType = {};
            if (slot.capacity_per_type) {
                Object.keys(slot.capacity_per_type).forEach((key) => {
                    const label = (config.i18n.perTypePrompt || '').replace('%s', key);
                    const response = window.prompt(label, slot.capacity_per_type[key]);
                    if (null === response || '' === response) {
                        perType[key] = slot.capacity_per_type[key];
                        return;
                    }
                    const parsed = parseInt(response, 10);
                    perType[key] = Number.isNaN(parsed) ? slot.capacity_per_type[key] : parsed;
                });
            }

            apiFetch({
                path: `${config.endpoints.capacity}/${slot.id}`,
                method: 'POST',
                headers: {
                    'X-WP-Nonce': config.nonce,
                },
                data: {
                    capacity_total: capacityTotal,
                    capacity_per_type: perType,
                },
            })
                .then(() => {
                    fetchSlots();
                    notifySuccess(config.i18n.updateSuccess);
                })
                .catch(() => {
                    notifyError(config.i18n.updateError);
                });
        }

        function notifySuccess(message) {
            if (message) {
                window.wp?.a11y?.speak?.(message, 'assertive');
            }
        }

        function notifyError(message) {
            if (message) {
                window.wp?.a11y?.speak?.(message, 'assertive');
            }
        }
    }

    function initBrandingContrast(container, form) {
        if (!container || !form) {
            return;
        }

        const defaults = {
            primary: container.dataset.defaultPrimary || '#8B1E3F',
            background: container.dataset.defaultBackground || '#FFFFFF',
            text: container.dataset.defaultText || '#1F1F1F',
        };

        const warningPrimary = container.dataset.warningPrimary || 'Primary color contrast against the background is %s:1.';
        const warningText = container.dataset.warningText || 'Body text contrast is %s:1.';
        const passMessage = container.dataset.passMessage || 'Current palette passes AA contrast checks.';
        const titleText = container.dataset.title || 'Accessibility checks';

        const titleNode = container.querySelector('.fp-exp-contrast-notice__title');
        if (titleNode) {
            titleNode.textContent = titleText;
        }

        const inputs = form.querySelectorAll('input[name^="fp_exp_branding"]');
        if (!inputs.length) {
            return;
        }

        const render = () => {
            const palette = {
                primary: normaliseHex(getPaletteValue(form, 'primary', defaults.primary)),
                background: normaliseHex(getPaletteValue(form, 'background', defaults.background)),
                text: normaliseHex(getPaletteValue(form, 'text', defaults.text)),
            };

            const messages = [];
            const primaryContrast = contrastRatio(palette.primary, palette.background);
            if (primaryContrast < 4.5) {
                messages.push(warningPrimary.replace('%s', primaryContrast.toFixed(2)));
            }

            const textContrast = contrastRatio(palette.text, palette.background);
            if (textContrast < 4.5) {
                messages.push(warningText.replace('%s', textContrast.toFixed(2)));
            }

            updateContrastNotice(container, messages, passMessage);
        };

        inputs.forEach((input) => {
            input.addEventListener('input', render);
            input.addEventListener('change', render);
        });

        render();
    }

    function getPaletteValue(form, key, fallback) {
        const field = form.querySelector(`[name="fp_exp_branding[${key}]"]`);
        const value = field ? (field.value || '').trim() : '';
        return value || fallback;
    }

    function updateContrastNotice(container, messages, passMessage) {
        const list = container.querySelector('.fp-exp-contrast-notice__list');
        const messageNode = container.querySelector('.fp-exp-contrast-notice__message');

        if (list) {
            list.innerHTML = '';
        }

        if (messageNode) {
            messageNode.textContent = '';
        }

        if (!messages.length) {
            container.classList.remove('notice-warning');
            container.classList.add('notice-success');
            container.hidden = false;

            if (messageNode) {
                messageNode.textContent = passMessage;
            } else if (list) {
                const item = document.createElement('li');
                item.textContent = passMessage;
                list.appendChild(item);
            }

            return;
        }

        container.classList.remove('notice-success');
        container.classList.add('notice-warning');
        container.hidden = false;

        if (list) {
            messages.forEach((message) => {
                const item = document.createElement('li');
                item.textContent = message;
                list.appendChild(item);
            });
        }
    }

    function normaliseHex(value) {
        if (!value) {
            return '#000000';
        }

        const hex = value.trim().toLowerCase();
        if (!hex.startsWith('#')) {
            return normaliseHex(`#${hex}`);
        }

        if (hex.length === 4) {
            return `#${hex[1]}${hex[1]}${hex[2]}${hex[2]}${hex[3]}${hex[3]}`;
        }

        return hex.slice(0, 7);
    }

    function hexToRgb(hex) {
        const normalised = normaliseHex(hex).replace('#', '');
        const int = parseInt(normalised, 16);
        const r = (int >> 16) & 255;
        const g = (int >> 8) & 255;
        const b = int & 255;

        return {
            r: r / 255,
            g: g / 255,
            b: b / 255,
        };
    }

    function relativeLuminance(rgb) {
        const transform = (channel) => {
            if (channel <= 0.03928) {
                return channel / 12.92;
            }
            return Math.pow((channel + 0.055) / 1.055, 2.4);
        };

        return (
            0.2126 * transform(rgb.r)
            + 0.7152 * transform(rgb.g)
            + 0.0722 * transform(rgb.b)
        );
    }

    function contrastRatio(colorA, colorB) {
        const luminanceA = relativeLuminance(hexToRgb(colorA));
        const luminanceB = relativeLuminance(hexToRgb(colorB));
        const lighter = Math.max(luminanceA, luminanceB);
        const darker = Math.min(luminanceA, luminanceB);

        return (lighter + 0.05) / (darker + 0.05);
    }

    function initTools(config, container) {
        if (!config || !container) {
            return;
        }

        const output = container.querySelector('.fp-exp-tools__output');
        const actions = Array.isArray(config.actions)
            ? config.actions.reduce((map, action) => {
                  map[action.slug] = action.endpoint;
                  return map;
              }, {})
            : {};

        container.addEventListener('click', (event) => {
            const target = event.target;
            if (!target || !target.matches('button[data-action]')) {
                return;
            }

            const action = target.getAttribute('data-action');
            if (!action || !actions[action]) {
                return;
            }

            runToolAction(action, actions[action], target, output, config);
        });
    }

    function runToolAction(slug, endpoint, button, output, config) {
        if (!endpoint) {
            return;
        }

        button.disabled = true;
        const previous = button.textContent;
        if (config?.i18n?.running) {
            button.textContent = config.i18n.running;
        }
        if (output) {
            output.textContent = config?.i18n?.running || '';
        }

        apiFetch({
            url: endpoint,
            method: 'POST',
            headers: {
                'X-WP-Nonce': config?.nonce || window.fpExpTools?.nonce || '',
            },
        })
            .then((response) => {
                const message = response?.message || config?.i18n?.success || '';
                if (output) {
                    output.textContent = message;
                }
                if (message) {
                    window.wp?.a11y?.speak?.(message, 'assertive');
                }
            })
            .catch(() => {
                const message = config?.i18n?.error || '';
                if (output) {
                    output.textContent = message;
                }
                if (message) {
                    window.wp?.a11y?.speak?.(message, 'assertive');
                }
            })
            .finally(() => {
                button.disabled = false;
                button.textContent = previous;
            });
    }
})(window, document);
