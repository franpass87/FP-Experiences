/**
 * Frontend JavaScript - Entry point per il frontend
 * Questo file serve come entry point per il frontend
 */

// Carica i moduli frontend necessari
(function() {
    'use strict';
    
    // Verifica che jQuery sia disponibile
    if (typeof jQuery === 'undefined') {
        console.warn('FP Experiences: jQuery non trovato');
        return;
    }
    
    // Inizializza quando il documento è pronto
    jQuery(document).ready(function($) {
        console.log('FP Experiences Frontend: Inizializzato');
        
        // Selettori base
        const widget = document.querySelector('.fp-exp.fp-exp-widget');
        const dateInput = document.getElementById('fp-exp-date-input');
        const calendarEl = document.querySelector('.fp-exp-calendar');
        const slotsEl = document.querySelector('.fp-exp-slots');

        if (!widget) {
            return; // nessun widget in pagina
        }

        // Dataset dal markup del widget (slots precalcolati per giorno)
        let config = {};
        try {
            const raw = widget.getAttribute('data-config') || '{}';
            config = JSON.parse(raw);
        } catch (e) {
            console.warn('[FP-EXP] Config frontend non valida', e);
        }

        // 1) Apri il datepicker quando si clicca/focalizza l'input
        if (dateInput) {
            // su molti browser mobile l'evento click non apre: forziamo focus → showPicker se disponibile
            const openNativePicker = () => {
                try {
                    if (typeof dateInput.showPicker === 'function') {
                        dateInput.showPicker();
                    } else {
                        dateInput.focus();
                    }
                } catch (e) {
                    dateInput.focus();
                }
            };
            dateInput.addEventListener('click', openNativePicker);
            dateInput.addEventListener('focus', () => {
                // su desktop apriamo immediatamente
                openNativePicker();
            });
        }

        // Utility: stato caricamento e render
        const setSlotsLoading = (isLoading) => {
            if (!slotsEl) return;
            if (isLoading) {
                const loadingLabel = (slotsEl.getAttribute('data-loading-label') || 'Caricamento…');
                slotsEl.innerHTML = '<p class="fp-exp-slots__placeholder">' + loadingLabel + '</p>';
            }
        };

        const showSlotsError = (message) => {
            if (!slotsEl) return;
            const text = message || 'Impossibile caricare gli slot. Riprova.';
            slotsEl.innerHTML = '<p class="fp-exp-slots__placeholder">' + text + '</p>';
        };

        const formatTimeRange = (startIso, endIso) => {
            try {
                const tz = (config && config.timezone) || undefined;
                const opts = { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: tz };
                const fmt = new Intl.DateTimeFormat(undefined, opts);
                const start = new Date(startIso.replace(' ', 'T'));
                const end = new Date(endIso.replace(' ', 'T'));
                return fmt.format(start) + ' - ' + fmt.format(end);
            } catch (e) {
                return (startIso && endIso) ? (startIso.substring(11,16) + ' - ' + endIso.substring(11,16)) : 'Slot';
            }
        };

        const renderSlots = (items) => {
            if (!slotsEl) return;
            const emptyLabel = slotsEl.getAttribute('data-empty-label') || '';
            if (!items || items.length === 0) {
                slotsEl.innerHTML = '<p class="fp-exp-slots__placeholder">' + (emptyLabel || 'Nessuna fascia disponibile') + '</p>';
                return;
            }
            const list = document.createElement('ul');
            list.className = 'fp-exp-slots__list';
            items.forEach((slot) => {
                const li = document.createElement('li');
                li.className = 'fp-exp-slots__item';
                const label = (slot && slot.label) || (slot.start && slot.end ? formatTimeRange(slot.start, slot.end) : 'Slot');
                li.textContent = label;
                li.dataset.start = slot.start || '';
                li.dataset.end = slot.end || '';
                list.appendChild(li);
            });
            slotsEl.innerHTML = '';
            slotsEl.appendChild(list);
        };

        // Fetch dinamico dagli endpoint REST del plugin
        const fetchAvailability = async (date) => {
            const experienceId = (config && config.experienceId) || 0;
            if (!experienceId || !date) return [];
            try {
                const base = (window.fpExpApiBase && typeof window.fpExpApiBase === 'string')
                    ? window.fpExpApiBase
                    : (window.wpApiSettings && wpApiSettings.root) || (location.origin + '/wp-json/');
                // assicurati della barra finale
                const root = base.endsWith('/') ? base : base + '/';
                const url = new URL(root + 'fp-exp/v1/availability');
                url.searchParams.set('experience', String(experienceId));
                // per il giorno singolo usiamo start=end=YYYY-MM-DD
                url.searchParams.set('start', date);
                url.searchParams.set('end', date);
                const res = await fetch(url.toString(), { credentials: 'same-origin' });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                const slots = Array.isArray(data && data.slots) ? data.slots : [];
                return slots.map((s) => ({
                    start: s.start || '',
                    end: s.end || '',
                    label: (s.start && s.end) ? formatTimeRange(s.start, s.end) : undefined
                }));
            } catch (e) {
                console.warn('[FP-EXP] Errore fetch availability', e);
                throw e;
            }
        };

        // Cache mensile: { 'YYYY-MM': Set('YYYY-MM-DD'→count) }
        const monthCache = new Map();
        const monthKeyOf = (dateStr) => dateStr.slice(0, 7);
        const prefetchMonth = async (yyyyMm) => {
            const experienceId = (config && config.experienceId) || 0;
            if (!experienceId || !yyyyMm) return;
            if (monthCache.has(yyyyMm)) return;
            try {
                const base = (window.fpExpApiBase && typeof window.fpExpApiBase === 'string')
                    ? window.fpExpApiBase
                    : (window.wpApiSettings && wpApiSettings.root) || (location.origin + '/wp-json/');
                const root = base.endsWith('/') ? base : base + '/';
                const start = yyyyMm + '-01';
                const endDate = new Date(start + 'T00:00:00');
                endDate.setMonth(endDate.getMonth() + 1); endDate.setDate(0);
                const end = endDate.toISOString().slice(0,10);
                const url = new URL(root + 'fp-exp/v1/availability');
                url.searchParams.set('experience', String(experienceId));
                url.searchParams.set('start', start);
                url.searchParams.set('end', end);
                const res = await fetch(url.toString(), { credentials: 'same-origin' });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                const slots = Array.isArray(data && data.slots) ? data.slots : [];
                const dayCount = new Map();
                slots.forEach((s) => {
                    const d = (s.start || '').slice(0,10);
                    dayCount.set(d, (dayCount.get(d) || 0) + 1);
                });
                monthCache.set(yyyyMm, dayCount);
                // aggiorna badge conteggi nel calendario inline
                if (calendarEl) {
                    calendarEl.querySelectorAll('.fp-exp-calendar__day[data-date^="' + yyyyMm + '"]').forEach((btn) => {
                        const d = btn.getAttribute('data-date');
                        const count = (d && dayCount.get(d)) || 0;
                        btn.dataset.available = count > 0 ? '1' : '0';
                    });
                }
            } catch (e) {
                console.warn('[FP-EXP] Prefetch mese fallito', e);
            }
        };

        // Mappa YYYY-MM-DD → array di slot dal dataset calendar
        const calendarMap = (() => {
            const map = new Map();
            const calendar = (config && config.calendar) || {};
            Object.keys(calendar).forEach((monthKey) => {
                const days = calendar[monthKey] && calendar[monthKey].days ? calendar[monthKey].days : {};
                Object.keys(days).forEach((dateKey) => {
                    map.set(dateKey, days[dateKey] || []);
                });
            });
            return map;
        })();

        const showCalendarIfConfigured = () => {
            if (!calendarEl) return;
            const shouldShow = (widget.getAttribute('data-config') || '').includes('"show_calendar":true') || calendarEl.getAttribute('data-show-calendar') === '1';
            if (shouldShow) {
                calendarEl.hidden = false;
            }
        };
        showCalendarIfConfigured();

        // Navigazione mesi semplice (prev/next) se presente calendario inline
        const setupMonthNavigation = () => {
            if (!calendarEl) return;
            // Crea toolbar se non esiste
            if (!calendarEl.querySelector('.fp-exp-calendar__weekdays')) return; // usa calendario già renderizzato dal template
            let toolbar = calendarEl.querySelector('.fp-exp-calendar__toolbar');
            if (!toolbar) {
                toolbar = document.createElement('div');
                toolbar.className = 'fp-exp-calendar__toolbar';
                const prev = document.createElement('button');
                prev.type = 'button';
                prev.className = 'fp-exp-calendar__nav-prev';
                prev.setAttribute('aria-label', 'Mese precedente');
                prev.textContent = '‹';
                const next = document.createElement('button');
                next.type = 'button';
                next.className = 'fp-exp-calendar__nav-next';
                next.setAttribute('aria-label', 'Mese successivo');
                next.textContent = '›';
                toolbar.appendChild(prev);
                toolbar.appendChild(next);
                calendarEl.insertBefore(toolbar, calendarEl.firstChild);

                const navigate = async (delta) => {
                    // Trova il primo giorno visibile e calcola il mese target
                    const firstBtn = calendarEl.querySelector('.fp-exp-calendar__day');
                    if (!firstBtn) return;
                    const anyDate = firstBtn.getAttribute('data-date');
                    if (!anyDate) return;
                    const dt = new Date(anyDate + 'T00:00:00');
                    dt.setMonth(dt.getMonth() + delta);
                    const yyyyMm = dt.toISOString().slice(0,7);
                    await prefetchMonth(yyyyMm);
                    // Scorri alla prima sezione mese corrispondente se presente
                    const monthSection = calendarEl.querySelector('.fp-exp-calendar__month[data-month="' + yyyyMm + '"]');
                    // niente smooth scroll: evitare movimento automatico della pagina
                    if (monthSection && typeof monthSection.scrollIntoView === 'function') {
                        monthSection.scrollIntoView({ behavior: 'auto', block: 'start' });
                    }
                };

                prev.addEventListener('click', () => navigate(-1));
                next.addEventListener('click', () => navigate(1));
            }
        };
        setupMonthNavigation();

        // 2) Alla modifica della data, mostra gli slot del giorno
        if (dateInput) {
            dateInput.addEventListener('change', async () => {
                const date = dateInput.value; // formato YYYY-MM-DD
                let items = calendarMap.get(date) || [];
                if (!items || items.length === 0) {
                    // fallback a chiamata API
                    setSlotsLoading(true);
                    try {
                        items = await fetchAvailability(date);
                    } catch (e) {
                        showSlotsError('Impossibile caricare gli slot. Riprova.');
                        items = [];
                    }
                }
                renderSlots(items);
                // evidenzia nel calendario inline
                if (calendarEl) {
                    calendarEl.querySelectorAll('.fp-exp-calendar__day').forEach((btn) => btn.classList.remove('is-selected'));
                    const btn = calendarEl.querySelector('.fp-exp-calendar__day[data-date="' + date + '"]');
                    if (btn) btn.classList.add('is-selected');
                }
                // prefetch del mese della data selezionata
                prefetchMonth(monthKeyOf(date));

                // niente smooth scroll
                if (slotsEl && typeof slotsEl.scrollIntoView === 'function') {
                    slotsEl.scrollIntoView({ behavior: 'auto', block: 'start' });
                }
            });
        }

        // 3) Click su giorno del calendario → imposta input e renderizza
        if (calendarEl) {
            calendarEl.addEventListener('click', (ev) => {
                const target = ev.target.closest('.fp-exp-calendar__day');
                if (!target || target.disabled) return;
                const date = target.getAttribute('data-date');
                if (!date) return;
                if (dateInput) {
                    dateInput.value = date;
                    dateInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            // prefetch del mese visibile iniziale
            const firstVisible = calendarEl.querySelector('.fp-exp-calendar__day');
            if (firstVisible) {
                const d = firstVisible.getAttribute('data-date');
                if (d) prefetchMonth(monthKeyOf(d));
            }
        }

        // 4) Click sugli slot → selezione e aggiornamento form RTB
        (function setupSlotSelection() {
            if (!slotsEl) return;
            const rtbForm = document.querySelector('form.fp-exp-rtb-form');
            const startInput = rtbForm ? rtbForm.querySelector('input[name="start"]') : null;
            const endInput = rtbForm ? rtbForm.querySelector('input[name="end"]') : null;
            const submitBtn = rtbForm ? rtbForm.querySelector('.fp-exp-summary__cta') : null;

            const clearSelection = () => {
                const prev = slotsEl.querySelectorAll('.fp-exp-slots__item.is-selected');
                prev.forEach((el) => el.classList.remove('is-selected'));
            };

            slotsEl.addEventListener('click', (ev) => {
                const li = ev.target && ev.target.closest('.fp-exp-slots__item');
                if (!li) return;
                const start = li.getAttribute('data-start') || '';
                const end = li.getAttribute('data-end') || '';

                clearSelection();
                li.classList.add('is-selected');

                if (startInput) startInput.value = start;
                if (endInput) endInput.value = end;

                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            });
        })();
    });
    
})();