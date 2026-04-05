/**
 * Calendario admin pagina Operazioni (#fp-exp-calendar-app).
 * Estratto da admin.js: il bundle min non carica admin.js.
 * Rigenerazione: `node tools/extract-calendar-admin-app.js` dalla root del plugin.
 */
(function () {
    'use strict';

    window.fpExpAdmin = window.fpExpAdmin || {};

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

                    // Reservation details
                    if (slot.reservations && slot.reservations.length > 0) {
                        const resList = document.createElement('div');
                        resList.className = 'fp-exp-calendar__reservations';

                        slot.reservations.forEach((res) => {
                            const row = document.createElement('div');
                            row.className = 'fp-exp-calendar__reservation-row';

                            const name = document.createElement('span');
                            name.className = 'fp-exp-calendar__reservation-name';
                            name.textContent = res.customer_name || '—';
                            row.appendChild(name);

                            const paxParts = [];
                            if (res.pax && typeof res.pax === 'object') {
                                Object.entries(res.pax).forEach(([type, qty]) => {
                                    if (qty > 0) { paxParts.push(`${type}: ${qty}`); }
                                });
                            }
                            if (paxParts.length) {
                                const pax = document.createElement('span');
                                pax.className = 'fp-exp-calendar__reservation-pax';
                                pax.textContent = paxParts.join(', ');
                                row.appendChild(pax);
                            }

                            const statusBadge = document.createElement('span');
                            statusBadge.className = 'fp-exp-calendar__reservation-status is-' + (res.status || 'pending');
                            const statusLabels = {
                                paid: 'Pagato',
                                pending: 'In attesa',
                                pending_request: 'Richiesta',
                                approved_confirmed: 'Confermato',
                                approved_pending_payment: 'Da pagare',
                                checked_in: 'Check-in',
                            };
                            statusBadge.textContent = statusLabels[res.status] || res.status || '';
                            row.appendChild(statusBadge);

                            resList.appendChild(row);
                        });

                        item.appendChild(resList);
                    }

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
                    let bookedCount = 0;
                    daySlots.forEach((s) => {
                        if (s.reservations && s.reservations.length) {
                            bookedCount += s.reservations.length;
                        }
                    });
                    if (bookedCount > 0) {
                        const bookedBadge = document.createElement('div');
                        bookedBadge.className = 'fp-exp-calendar__grid-booked';
                        bookedBadge.textContent = bookedCount + ' prenotaz.';
                        cell.appendChild(bookedBadge);
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

    window.fpExpAdmin.initCalendarApp = initCalendarApp;
})();
