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
        // Namespace globale leggero per futura modularizzazione
        if (!window.FPFront) window.FPFront = {};
        console.log('FP Experiences Frontend: Inizializzato');
        
        // Selettori base
        const widget = document.querySelector('.fp-exp.fp-exp-widget');
        const dateInput = document.getElementById('fp-exp-date-input');
        const slotsEl = document.querySelector('.fp-exp-slots');

        // Gestione posizionamento widget in mobile (dopo gallery)
        const widgetAside = document.querySelector('.fp-exp-page__aside');
        const gallerySection = document.querySelector('.fp-exp-section.fp-exp-gallery');
        
        const repositionWidgetForMobile = () => {
            const isMobile = window.innerWidth < 1024;
            
            if (isMobile && widgetAside && gallerySection) {
                // In mobile, sposta il widget dopo la gallery
                if (!widgetAside.classList.contains('is-mobile-inline')) {
                    widgetAside.classList.add('is-mobile-inline');
                    // Inserisci il widget dopo la gallery
                    if (gallerySection.nextSibling) {
                        gallerySection.parentNode.insertBefore(widgetAside, gallerySection.nextSibling);
                    } else {
                        gallerySection.parentNode.appendChild(widgetAside);
                    }
                }
            } else if (!isMobile && widgetAside) {
                // In desktop, rimuovi la classe e ripristina la posizione originale
                if (widgetAside.classList.contains('is-mobile-inline')) {
                    widgetAside.classList.remove('is-mobile-inline');
                    // Ripristina il widget nella sua posizione originale (dopo il main)
                    const fpGrid = document.querySelector('.fp-grid.fp-exp-page__layout');
                    const mainElement = document.querySelector('.fp-main.fp-exp-page__main');
                    if (fpGrid && mainElement && mainElement.nextSibling) {
                        fpGrid.insertBefore(widgetAside, mainElement.nextSibling);
                    } else if (fpGrid && mainElement) {
                        fpGrid.appendChild(widgetAside);
                    }
                }
            }
        };
        
        // Esegui al caricamento
        repositionWidgetForMobile();
        
        // Esegui al resize (con debounce)
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(repositionWidgetForMobile, 150);
        });

        if (!widget) {
            return; // nessun widget in pagina
        }

        // Dataset dal markup del widget (slots precalcolati per giorno)
        let config = {};
        try {
            const raw = widget.getAttribute('data-config') || '{}';
            config = JSON.parse(raw);
            // Popola window.FPFront.config per i moduli
            window.FPFront.config = config;
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
            } else {
                // Rimuovi il loading state - il contenuto verrà sostituito dal renderSlots o dal fallback
                slotsEl.innerHTML = '';
            }
        };

        // Funzione semplificata - non più necessaria con input date nativo
        const showSlotsInline = async (dayElement, date) => {
            console.log('[FP-EXP] showSlotsInline non più necessaria - sistema semplificato');
        };

        const showSlotsError = (message) => {
            if (!slotsEl) return;
            const text = message || 'Impossibile caricare gli slot. Riprova.';
            slotsEl.innerHTML = '<p class="fp-exp-slots__placeholder">' + text + '</p>';
        };

        const formatTimeRange = (startIso, endIso) => window.FPFront.availability ? window.FPFront.availability.formatTimeRange(startIso, endIso) : 'Slot';

        // Inizializza modulo slots una volta
        if (window.FPFront.slots) window.FPFront.slots.init({ slotsEl });

        // Fetch dinamico dagli endpoint REST del plugin
        const fetchAvailability = async (date) => window.FPFront.availability ? window.FPFront.availability.fetchAvailability(date) : [];

        // Cache mensile: { 'YYYY-MM': Set('YYYY-MM-DD'→count) }
        const monthKeyOf = (dateStr) => (window.FPFront.availability ? window.FPFront.availability.monthKeyOf(dateStr) : (dateStr || '').slice(0,7));
        const prefetchMonth = async (yyyyMm) => window.FPFront.availability && window.FPFront.availability.prefetchMonth(yyyyMm);

        // Mappa YYYY-MM-DD → array di slot dal dataset
        const calendarMap = (window.FPFront.availability && window.FPFront.availability.getCalendarMap && window.FPFront.availability.getCalendarMap()) || new Map();

        // Sistema semplificato - input date nativo

        // 2) Alla modifica della data, mostra gli slot del giorno
        if (dateInput) {
            dateInput.addEventListener('change', async () => {
                const date = dateInput.value; // formato YYYY-MM-DD
                console.log('[FP-EXP] Date changed to:', date);
                
                let items = calendarMap.get(date) || [];
                let isLoading = false;
                
                if (!items || items.length === 0) {
                    // fallback a chiamata API
                    setSlotsLoading(true);
                    isLoading = true;
                    try {
                        items = await fetchAvailability(date);
                    } catch (e) {
                        console.error('[FP-EXP] API fetch failed:', e);
                        showSlotsError('Impossibile caricare gli slot. Riprova.');
                        items = [];
                        return; // Esci qui per evitare di chiamare renderSlots con errori
                    }
                }
                
                // Renderizza gli slot (rimuove automaticamente il loading state)
                if (window.FPFront.slots && window.FPFront.slots.renderSlots) {
                    window.FPFront.slots.renderSlots(items);
                } else if (isLoading) {
                    // Fallback: se il modulo slots non è disponibile, rimuovi manualmente il loading
                    setSlotsLoading(false);
                    if (items && items.length > 0) {
                        // Mostra gli slot manualmente se non c'è il modulo
                        const list = document.createElement('ul');
                        list.className = 'fp-exp-slots__list';
                        items.forEach(slot => {
                            const li = document.createElement('li');
                            li.className = 'fp-exp-slots__item';
                            li.textContent = slot.time || slot.label || 'Slot';
                            li.setAttribute('data-start', slot.start || slot.start_iso || '');
                            li.setAttribute('data-end', slot.end || slot.end_iso || '');
                            list.appendChild(li);
                        });
                        if (slotsEl) {
                            slotsEl.innerHTML = '';
                            slotsEl.appendChild(list);
                        }
                    } else {
                        // Nessun slot disponibile
                        if (slotsEl) {
                            slotsEl.innerHTML = '<p class="fp-exp-slots__placeholder">Nessuna fascia disponibile per questa data</p>';
                        }
                    }
                } else {
                    console.log('[FP-EXP] No loading, items available:', items);
                    // Se non stiamo caricando e abbiamo items, renderizza direttamente
                    if (items && items.length > 0) {
                        if (window.FPFront.slots && window.FPFront.slots.renderSlots) {
                            window.FPFront.slots.renderSlots(items);
                        } else {
                            // Fallback manuale
                            const list = document.createElement('ul');
                            list.className = 'fp-exp-slots__list';
                            items.forEach(slot => {
                                const li = document.createElement('li');
                                li.className = 'fp-exp-slots__item';
                                li.textContent = slot.time || slot.label || 'Slot';
                                li.setAttribute('data-start', slot.start || slot.start_iso || '');
                                li.setAttribute('data-end', slot.end || slot.end_iso || '');
                                list.appendChild(li);
                            });
                            if (slotsEl) {
                                slotsEl.innerHTML = '';
                                slotsEl.appendChild(list);
                            }
                        }
                    } else {
                        // Nessun slot disponibile
                        if (slotsEl) {
                            slotsEl.innerHTML = '<p class="fp-exp-slots__placeholder">Nessuna fascia disponibile per questa data</p>';
                        }
                    }
                }
                
                // Sistema semplificato - evidenziazione non necessaria
                // prefetch del mese della data selezionata
                if (window.FPFront.availability && window.FPFront.availability.prefetchMonth && window.FPFront.availability.monthKeyOf) {
                    window.FPFront.availability.prefetchMonth(window.FPFront.availability.monthKeyOf(date));
                }

                // Rimosso scroll automatico per evitare il salto verso il basso
                // if (slotsEl && typeof slotsEl.scrollIntoView === 'function') {
                //     slotsEl.scrollIntoView({ behavior: 'auto', block: 'start' });
                // }
            });
        }

        // 3) Click su giorno del calendario con navigazione
        // Nota: calendarNav viene dichiarato e inizializzato più avanti
        // Questo codice sarà eseguito dopo l'inizializzazione
        
        // Funzione per aggiornare il mese del calendario
        const updateCalendarMonth = async (calendarNav, date) => {
            const monthKey = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
            
            // Nomi mesi in italiano
            const monthNames = [
                'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
                'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'
            ];
            
            const monthName = monthNames[date.getMonth()];
            const year = date.getFullYear();
            
            
            // Aggiorna i titoli
            const monthEl = calendarNav.querySelector('.fp-exp-calendar-nav__month');
            const yearEl = calendarNav.querySelector('.fp-exp-calendar-nav__year');
            const content = calendarNav.querySelector('.fp-exp-calendar-nav__content');
            
            if (monthEl) monthEl.textContent = monthName;
            if (yearEl) yearEl.textContent = year;
            if (content) content.setAttribute('data-current-month', monthKey);
            
            // Genera i giorni del mese
            const daysInMonth = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
            const grid = calendarNav.querySelector('.fp-exp-calendar-nav__grid');
            
            if (grid) {
                // Pulisce il grid e mostra caricamento
                grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: var(--fp-color-muted);">Caricamento...</div>';
                
                try {
                    // Genera tutti i giorni del mese
                    const dayButtons = [];
                    
                    for (let day = 1; day <= daysInMonth; day++) {
                        const dateKey = monthKey + '-' + String(day).padStart(2, '0');
                        const isPast = new Date(dateKey) < new Date(new Date().setHours(0, 0, 0, 0));
                        
                        // Controlla se ci sono slot per questa data usando i dati del calendario
                        let slotCount = 0;
                        let isAvailable = false;
                        
                        // Prova prima dai dati del calendario passati dal backend
                        if (window.FPFront && window.FPFront.config && window.FPFront.config.calendar) {
                            const calendarData = window.FPFront.config.calendar;
                            
                            if (calendarData[monthKey] && calendarData[monthKey].days && calendarData[monthKey].days[dateKey]) {
                                const daySlots = calendarData[monthKey].days[dateKey];
                                slotCount = daySlots.length;
                                isAvailable = slotCount > 0;
                                
                                // Aggiungi il campo 'label' e salva in cache per future navigazioni
                                const slotsWithLabels = daySlots.map(slot => {
                                    if (!slot.label && slot.start_iso && slot.end_iso) {
                                        slot.label = formatTimeRange(slot.start_iso, slot.end_iso);
                                    }
                                    return slot;
                                });
                                calendarMap.set(dateKey, slotsWithLabels);
                            }
                        }
                        
                        // Se non trovato nei dati del calendario, prova la cache
                        if (!isAvailable) {
                            const cachedSlots = calendarMap.get(dateKey);
                            if (cachedSlots && cachedSlots.length > 0) {
                                slotCount = cachedSlots.length;
                                isAvailable = true;
                            }
                        }
                        
                        // Se ancora non disponibile, chiama l'API come fallback
                        if (!isAvailable) {
                            try {
                                const apiSlots = await fetchAvailability(dateKey);
                                if (apiSlots && apiSlots.length > 0) {
                                    slotCount = apiSlots.length;
                                    isAvailable = true;
                                    // Salva in cache per future navigazioni
                                    calendarMap.set(dateKey, apiSlots);
                                }
                            } catch (error) {
                                console.warn('[FP-EXP] Errore caricamento slot per', dateKey, ':', error);
                                // Continua anche se c'è un errore
                            }
                        }
                        
                        const dayButton = document.createElement('button');
                        dayButton.type = 'button';
                        dayButton.className = 'fp-exp-calendar-nav__day' + (isPast ? ' is-past' : '');
                        dayButton.setAttribute('data-date', dateKey);
                        dayButton.setAttribute('data-available', isAvailable ? '1' : '0');
                        dayButton.setAttribute('data-month', monthKey);
                        if (isPast || !isAvailable) dayButton.disabled = true;
                        
                        let slotsHtml = '';
                        if (slotCount > 0) {
                            slotsHtml = `<span class="fp-exp-calendar-nav__day-slots">${slotCount} slot</span>`;
                        }
                        
                        dayButton.innerHTML = `
                            <span class="fp-exp-calendar-nav__day-number">${day}</span>
                            ${slotsHtml}
                        `;
                        
                        dayButtons.push(dayButton);
                    }
                    
                    // Sostituisce il contenuto di caricamento con tutti i giorni
                    grid.innerHTML = '';
                    dayButtons.forEach(button => grid.appendChild(button));
                    
                } catch (error) {
                    console.error('[FP-EXP] Errore generazione calendario:', error);
                    grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: var(--fp-color-error);">Errore caricamento calendario</div>';
                }
            }
        };

        // Inizializza il modulo availability prima di usare il calendario
        if (window.FPFront.availability && window.FPFront.availability.init) {
            window.FPFront.availability.init({ config, widget });
            console.log('[FP-EXP] Availability module initialized');
        }
        
        // Inizializza il calendario immediatamente (non aspettare DOMContentLoaded che potrebbe non triggerare)
        const calendarNav = document.querySelector('.fp-exp-calendar-nav');
        if (calendarNav) {
            // Inizializza calendarMap con i dati del backend
            if (window.FPFront && window.FPFront.config && window.FPFront.config.calendar) {
                const calendarData = window.FPFront.config.calendar;
                console.log('[FP-EXP] Initializing calendarMap with backend data:', calendarData);
                
                // Popola calendarMap con tutti i dati del calendario
                Object.keys(calendarData).forEach(monthKey => {
                    const monthData = calendarData[monthKey];
                    
                    if (monthData.days) {
                        Object.keys(monthData.days).forEach(dateKey => {
                            const daySlots = monthData.days[dateKey];
                            // Aggiungi il campo 'label' a ogni slot per la visualizzazione
                            const slotsWithLabels = daySlots.map(slot => {
                                if (!slot.label && slot.start_iso && slot.end_iso) {
                                    slot.label = formatTimeRange(slot.start_iso, slot.end_iso);
                                }
                                return slot;
                            });
                            calendarMap.set(dateKey, slotsWithLabels);
                        });
                    }
                });
                
                console.log('[FP-EXP] CalendarMap initialized with', calendarMap.size, 'dates');
            } else {
                console.log('[FP-EXP] No calendar data found in config');
            }
            
            // Click sui giorni del calendario
            calendarNav.addEventListener('click', (ev) => {
                const target = ev.target.closest('.fp-exp-calendar-nav__day');
                if (!target || target.disabled) return;
                
                const date = target.getAttribute('data-date');
                if (!date) return;
                
                // Rimuovi selezione precedente
                calendarNav.querySelectorAll('.fp-exp-calendar-nav__day.is-selected').forEach(day => {
                    day.classList.remove('is-selected');
                });
                
                // Seleziona il giorno corrente
                target.classList.add('is-selected');
                
                // Imposta la data nell'input nascosto
                if (dateInput) {
                    dateInput.value = date;
                    dateInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            
            // Navigazione mesi
            calendarNav.addEventListener('click', (ev) => {
                const button = ev.target.closest('[data-action]');
                if (!button) return;
                
                const action = button.getAttribute('data-action');
                const content = calendarNav.querySelector('.fp-exp-calendar-nav__content');
                const monthEl = calendarNav.querySelector('.fp-exp-calendar-nav__month');
                const yearEl = calendarNav.querySelector('.fp-exp-calendar-nav__year');
                
                if (!content || !monthEl || !yearEl) return;
                
                const currentMonth = content.getAttribute('data-current-month') || '2025-01';
                const currentDate = new Date(currentMonth + '-01');
                
                let newDate;
                switch (action) {
                    case 'prev-month':
                        newDate = new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1);
                        break;
                    case 'next-month':
                        newDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 1);
                        break;
                    default:
                        return;
                }
                
                // Aggiorna il calendario
                updateCalendarMonth(calendarNav, newDate);
            });
            
            // Inizializza il calendario con il mese corrente
            const currentDate = new Date();
            updateCalendarMonth(calendarNav, currentDate);
        }

        // 3.b) Gestisci CTA con data-fp-scroll (hero e sticky): mostra sezione date e scrolla
        (function setupCtaScrollHandlers() {
            // Delega a tutto il documento per coprire sia il bottone in hero sia quello sticky
            document.addEventListener('click', function(ev) {
                var btn = ev.target && (ev.target.closest('[data-fp-scroll]'));
                if (!btn) return;
                // Evita qualunque navigazione predefinita (es. <a href="…">)
                try { ev.preventDefault(); ev.stopPropagation(); } catch (e) {}
                var targetKey = btn.getAttribute('data-fp-scroll') || '';
                if (!targetKey) return;

                // Mappa dei target noti
                var targetEl = null;
                if (targetKey === 'calendar' || targetKey === 'dates') {
                    targetEl = calendarNav || document.querySelector('[data-fp-scroll-target="dates"], .fp-exp-calendar-nav');
                } else if (targetKey === 'gallery') {
                    targetEl = document.querySelector('[data-fp-scroll-target="gallery"], .fp-exp-gallery');
                }

                // Se sezione date è nascosta per configurazione, mostrala
                if (targetKey === 'calendar' || targetKey === 'dates') {
                    // Se disponibile input data, mettilo a fuoco per invogliare la selezione
                    if (dateInput) {
                        try { dateInput.focus(); } catch (e) {}
                    }
                }

                if (targetEl && typeof targetEl.scrollIntoView === 'function') {
                    targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        })();

        // 4) Click sugli slot → selezione e aggiornamento form RTB o WooCommerce
        (function setupSlotSelection() {
            if (!slotsEl) return;
            
            // Controlla se RTB è abilitato
            const rtbEnabled = (config && config.rtb && config.rtb.enabled === true);
            const rtbForm = rtbEnabled ? document.querySelector('form.fp-exp-rtb-form') : null;
            const startInput = rtbForm ? rtbForm.querySelector('input[name="start"]') : null;
            const endInput = rtbForm ? rtbForm.querySelector('input[name="end"]') : null;
            const submitBtn = document.querySelector('.fp-exp-summary__cta'); // Sempre disponibile

            const clearSelection = () => { if (window.FPFront.slots) { window.FPFront.slots.init({ slotsEl }); window.FPFront.slots.clearSelection(); } };

            slotsEl.addEventListener('click', (ev) => {
                const li = ev.target && ev.target.closest('.fp-exp-slots__item');
                if (!li) return;
                const start = li.getAttribute('data-start') || '';
                const end = li.getAttribute('data-end') || '';

                clearSelection();
                li.classList.add('is-selected');

                // Aggiorna campi RTB solo se RTB è abilitato
                if (rtbEnabled && startInput) startInput.value = start;
                if (rtbEnabled && endInput) endInput.value = end;

                // Aggiorna sempre il pulsante (sia RTB che WooCommerce)
                const ctaButton = document.querySelector('.fp-exp-summary__cta');
                if (ctaButton) {
                    ctaButton.disabled = false;
                }
            });
            if (window.FPFront.slots) window.FPFront.slots.init({ slotsEl });
        })();

        // 5) Controlli quantità biglietti (+ / -)
        // Inizializza modulo quantità
        if (window.FPFront.quantity && window.FPFront.quantity.init) {
            window.FPFront.quantity.init({ widget });
        }

        // Funzione per gestire il flusso WooCommerce quando RTB è disabilitato
        function setupWooCommerceFlow() {
            const ctaBtn = document.querySelector('.fp-exp-summary__cta');
            if (!ctaBtn) return;

            const updateWooCommerceCtaState = () => {
                const tickets = collectTickets();
                const anyTicket = tickets && Object.keys(tickets).length > 0;
                const slotOk = hasSelectedSlot();
                ctaBtn.disabled = !(anyTicket && slotOk);
                
                if (!anyTicket) {
                    ctaBtn.textContent = 'Seleziona almeno 1 biglietto';
                } else if (!slotOk) {
                    ctaBtn.textContent = 'Seleziona data e orario';
                } else {
                    ctaBtn.textContent = 'Procedi al pagamento';
                }
            };

            // Sistema di riepilogo prezzi per WooCommerce
            const setupWooCommercePriceSummary = () => {
                const summary = document.querySelector('.fp-exp-summary');
                if (!summary) return;

                const statusEl = summary.querySelector('[data-fp-summary-status]');
                const bodyEl = summary.querySelector('[data-fp-summary-body]');
                const linesEl = summary.querySelector('[data-fp-summary-lines]');
                const adjustmentsEl = summary.querySelector('[data-fp-summary-adjustments]');
                const totalRowEl = summary.querySelector('[data-fp-summary-total-row]');
                const totalEl = summary.querySelector('[data-fp-summary-total]');

                const loadingLabel = summary.getAttribute('data-loading-label') || 'Aggiornamento prezzo…';
                const errorLabel = summary.getAttribute('data-error-label') || 'Impossibile aggiornare il prezzo. Riprova.';
                const emptyLabel = summary.getAttribute('data-empty-label') || 'Seleziona i biglietti per vedere il riepilogo';

                const formatCurrency = (amount, currency) => {
                    try {
                        const fmt = new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'EUR' });
                        return fmt.format(Number(amount || 0));
                    } catch (e) {
                        return String(amount || 0);
                    }
                };

                const setStatus = (text) => {
                    if (statusEl) {
                        statusEl.hidden = false;
                        const p = statusEl.querySelector('.fp-exp-summary__message');
                        if (p) p.textContent = text || emptyLabel;
                    }
                    if (bodyEl) bodyEl.hidden = true;
                };

                const showBody = () => {
                    if (statusEl) statusEl.hidden = true;
                    if (bodyEl) bodyEl.hidden = false;
                };

                const updatePriceSummary = () => {
                    const tickets = collectTickets();
                    const addons = collectAddons();
                    const selectedSlot = slotsEl && slotsEl.querySelector('.fp-exp-slots__item.is-selected');

                    if (!tickets || Object.keys(tickets).length === 0) {
                        setStatus(emptyLabel);
                        return;
                    }

                    if (!selectedSlot) {
                        setStatus('Seleziona data e orario per vedere il prezzo');
                        return;
                    }

                    // Calcolo semplificato per WooCommerce (senza chiamate API)
                    let total = 0;
                    // Prendi la valuta dalla config o usa EUR come default
                    const currency = (config && config.currency) || 'EUR';
                    
                    // Calcola totale biglietti usando i prezzi reali dal DOM
                    Object.entries(tickets).forEach(([slug, qty]) => {
                        const priceEl = document.querySelector(`tr[data-ticket="${slug}"] .fp-exp-ticket__price[data-price]`);
                        const price = priceEl ? parseFloat(priceEl.getAttribute('data-price') || '0') : 0;
                        total += price * qty;
                    });

                    // Calcola totale addon
                    Object.entries(addons).forEach(([slug, qty]) => {
                        const priceEl = document.querySelector(`li[data-addon="${slug}"] .fp-exp-addon__price[data-price]`);
                        const price = priceEl ? parseFloat(priceEl.getAttribute('data-price') || '0') : 0;
                        total += price * qty;
                    });

                    // Render linee
                    if (linesEl) {
                        linesEl.innerHTML = '';
                        Object.entries(tickets).forEach(([slug, qty]) => {
                            if (qty > 0) {
                                const priceEl = document.querySelector(`tr[data-ticket="${slug}"] .fp-exp-ticket__price[data-price]`);
                                const price = priceEl ? parseFloat(priceEl.getAttribute('data-price') || '0') : 0;
                                const labelEl = document.querySelector(`tr[data-ticket="${slug}"] .fp-exp-ticket__label`);
                                const ticketLabel = labelEl ? labelEl.textContent.trim() : `Biglietto ${slug}`;
                                
                                const li = document.createElement('li');
                                li.className = 'fp-exp-summary__line';
                                const label = document.createElement('span');
                                label.className = 'fp-exp-summary__line-label';
                                label.textContent = `${ticketLabel} × ${qty}`;
                                const amount = document.createElement('span');
                                amount.className = 'fp-exp-summary__line-amount';
                                amount.textContent = formatCurrency(price * qty, currency);
                                li.appendChild(label);
                                li.appendChild(amount);
                                linesEl.appendChild(li);
                            }
                        });

                        // Render addon
                        Object.entries(addons).forEach(([slug, qty]) => {
                            if (qty > 0) {
                                const priceEl = document.querySelector(`li[data-addon="${slug}"] .fp-exp-addon__price[data-price]`);
                                const price = priceEl ? parseFloat(priceEl.getAttribute('data-price') || '0') : 0;
                                const labelEl = document.querySelector(`li[data-addon="${slug}"] .fp-exp-addon__label`);
                                const addonLabel = labelEl ? labelEl.textContent.trim() : `Extra ${slug}`;
                                
                                const li = document.createElement('li');
                                li.className = 'fp-exp-summary__line';
                                const label = document.createElement('span');
                                label.className = 'fp-exp-summary__line-label';
                                label.textContent = `${addonLabel} × ${qty}`;
                                const amount = document.createElement('span');
                                amount.className = 'fp-exp-summary__line-amount';
                                amount.textContent = formatCurrency(price * qty, currency);
                                li.appendChild(label);
                                li.appendChild(amount);
                                linesEl.appendChild(li);
                            }
                        });
                    }

                    // Totale
                    if (totalEl) totalEl.textContent = formatCurrency(total, currency);
                    if (totalRowEl) totalRowEl.hidden = false;

                    showBody();
                };

                // Aggiorna riepilogo su cambi
                widget.addEventListener('change', (ev) => {
                    if (ev.target && ev.target.closest('.fp-exp-quantity__input')) {
                        updatePriceSummary();
                    }
                    // Aggiorna anche quando si selezionano/deselezionano gli addon
                    if (ev.target && ev.target.matches('.fp-exp-addons input[type="checkbox"]')) {
                        updatePriceSummary();
                    }
                });
                widget.addEventListener('input', (ev) => {
                    if (ev.target && ev.target.closest('.fp-exp-quantity__input')) {
                        updatePriceSummary();
                    }
                });
                if (slotsEl) {
                    slotsEl.addEventListener('click', (ev) => {
                        if (ev.target && ev.target.closest('.fp-exp-slots__item')) {
                            updatePriceSummary();
                        }
                    });
                }

                // Stato iniziale
                updatePriceSummary();
            };

            setupWooCommercePriceSummary();

            function collectTickets() {
                const map = {};
                document.querySelectorAll('.fp-exp-party-table tbody tr[data-ticket]').forEach((row) => {
                    const slug = row.getAttribute('data-ticket') || '';
                    const input = row.querySelector('.fp-exp-quantity__input');
                    if (!slug || !input) return;
                    const qty = parseInt(input.value, 10) || 0;
                    if (qty > 0) map[slug] = qty;
                });
                return map;
            }

            function hasSelectedSlot() {
                return !!(slotsEl && slotsEl.querySelector('.fp-exp-slots__item.is-selected'));
            }

            function collectAddons() {
                const map = {};
                document.querySelectorAll('.fp-exp-addons li[data-addon]').forEach((li) => {
                    const slug = li.getAttribute('data-addon') || '';
                    const checkbox = li.querySelector('input[type="checkbox"]');
                    if (!slug || !checkbox) return;
                    // Aggiungi solo gli addon selezionati
                    if (checkbox.checked) {
                        map[slug] = 1;
                    }
                });
                return map;
            }

            // Gestisci click sul pulsante "Procedi al pagamento"
            ctaBtn.addEventListener('click', async () => {
                if (ctaBtn.disabled) return;

                const tickets = collectTickets();
                const selectedSlot = slotsEl && slotsEl.querySelector('.fp-exp-slots__item.is-selected');
                
                if (!selectedSlot || !tickets || Object.keys(tickets).length === 0) {
                    return;
                }

                const start = selectedSlot.getAttribute('data-start') || '';
                const end = selectedSlot.getAttribute('data-end') || '';
                const experienceId = (config && config.experienceId) || 0;

                if (!start || !end || !experienceId) {
                    console.error('[FP-EXP] Dati slot mancanti per checkout WooCommerce');
                    return;
                }

                // Usa il sistema di checkout integrato del plugin
                try {
                    ctaBtn.disabled = true;
                    ctaBtn.textContent = 'Aggiunta al carrello...';

                    // Aggiungi al carrello interno del plugin
                    const setCartUrl = new URL('/wp-json/fp-exp/v1/cart/set', window.location.origin);
                    
                    const setCartResponse = await fetch(setCartUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': (typeof fpExpConfig !== 'undefined' && fpExpConfig.restNonce) || ''
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            experience_id: experienceId,
                            slot_id: 0, // Il plugin creerà lo slot automaticamente
                            slot_start: start,
                            slot_end: end,
                            tickets: tickets,
                            addons: collectAddons()
                        })
                    });

                    if (!setCartResponse.ok) {
                        const errorData = await setCartResponse.json();
                        throw new Error(errorData.message || 'Errore aggiunta al carrello');
                    }

                    ctaBtn.textContent = 'Creazione ordine...';

                    // Ora crea l'ordine direttamente usando l'endpoint di checkout
                    const checkoutUrl = new URL('/wp-json/fp-exp/v1/checkout', window.location.origin);
                    
                    const checkoutResponse = await fetch(checkoutUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': (typeof fpExpConfig !== 'undefined' && fpExpConfig.checkoutNonce) || 
                                         (typeof fpExpConfig !== 'undefined' && fpExpConfig.restNonce) || ''
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            nonce: (typeof fpExpConfig !== 'undefined' && fpExpConfig.checkoutNonce) || '',
                            contact: {
                                first_name: 'Cliente',
                                last_name: 'Temporaneo', 
                                email: 'temp@example.com',
                                phone: ''
                            },
                            billing: {
                                first_name: 'Cliente',
                                last_name: 'Temporaneo',
                                email: 'temp@example.com', 
                                phone: ''
                            },
                            consent: {
                                privacy: true,
                                marketing: false
                            }
                        })
                    });

                    if (!checkoutResponse.ok) {
                        const errorData = await checkoutResponse.json();
                        throw new Error(errorData.message || 'Errore creazione ordine');
                    }

                    const result = await checkoutResponse.json();
                    
                    if (result.payment_url) {
                        // Reindirizza alla pagina di pagamento dell'ordine
                        window.location.href = result.payment_url;
                    } else {
                        throw new Error('URL di pagamento non ricevuto');
                    }

                } catch (error) {
                    console.error('[FP-EXP] Errore checkout WooCommerce:', error);
                    ctaBtn.disabled = false;
                    ctaBtn.textContent = 'Errore - Riprova';
                    
                    // Reset dopo 3 secondi
                    setTimeout(() => {
                        ctaBtn.textContent = 'Procedi al pagamento';
                        updateWooCommerceCtaState();
                    }, 3000);
                }
            });

            // Aggiorna stato del pulsante quando cambiano le quantità o gli slot
            widget.addEventListener('change', updateWooCommerceCtaState);
            widget.addEventListener('input', updateWooCommerceCtaState);
            
            if (slotsEl) {
                slotsEl.addEventListener('click', updateWooCommerceCtaState);
            }

            // Stato iniziale
            updateWooCommerceCtaState();
        }

        // 6) Aggiornamento riepilogo prezzi (preventivo) al cambio quantità
        (function setupPriceSummary() {
            const summary = document.querySelector('.fp-exp-summary');
            if (!summary) return;

            // Controlla se RTB è abilitato
            const rtbEnabled = (config && config.rtb && config.rtb.enabled === true);
            if (!rtbEnabled) {
                console.log('[FP-EXP] RTB disabilitato, configurando flusso WooCommerce');
                if (window.FPFront.summaryWoo && window.FPFront.summaryWoo.init) {
                    window.FPFront.summaryWoo.init({ widget, slotsEl, config });
                }
                setupWooCommerceFlow();
                return;
            }

            const rtbForm = document.querySelector('form.fp-exp-rtb-form');
            const startInput = rtbForm ? rtbForm.querySelector('input[name="start"]') : null;
            const endInput = rtbForm ? rtbForm.querySelector('input[name="end"]') : null;
            const ticketsHidden = rtbForm ? rtbForm.querySelector('input[name="tickets"]') : null;
            const addonsHidden = rtbForm ? rtbForm.querySelector('input[name="addons"]') : null;
            const ctaBtn = document.querySelector('.fp-exp-summary__cta');
            // Crea hint dinamico sotto la CTA se non esiste
            let ctaHint = null;
            if (ctaBtn && !ctaHint) {
                ctaHint = document.createElement('p');
                ctaHint.className = 'fp-exp-summary__cta-hint';
                ctaHint.setAttribute('aria-live', 'polite');
                ctaHint.style.marginTop = '6px';
                ctaHint.style.fontSize = '0.92em';
                ctaHint.style.color = 'var(--fp-exp-text-muted, #666)';
                ctaHint.hidden = true;
                if (ctaBtn.parentNode) {
                    ctaBtn.parentNode.insertBefore(ctaHint, ctaBtn.nextSibling);
                }
            }

            const statusEl = summary.querySelector('[data-fp-summary-status]');
            const bodyEl = summary.querySelector('[data-fp-summary-body]');
            const linesEl = summary.querySelector('[data-fp-summary-lines]');
            const adjustmentsEl = summary.querySelector('[data-fp-summary-adjustments]');
            const totalRowEl = summary.querySelector('[data-fp-summary-total-row]');
            const totalEl = summary.querySelector('[data-fp-summary-total]');
            const disclaimerEl = summary.querySelector('.fp-exp-summary__disclaimer');

            const loadingLabel = summary.getAttribute('data-loading-label') || 'Aggiornamento prezzo…';
            const errorLabel = summary.getAttribute('data-error-label') || 'Impossibile aggiornare il prezzo. Riprova.';
            const emptyLabel = summary.getAttribute('data-empty-label') || 'Seleziona i biglietti per vedere il riepilogo';

            const formatCurrency = (amount, currency) => {
                try {
                    const fmt = new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'EUR' });
                    return fmt.format(Number(amount || 0));
                } catch (e) {
                    return String(amount || 0);
                }
            };

            const setStatus = (text) => {
                if (statusEl) {
                    statusEl.hidden = false;
                    const p = statusEl.querySelector('.fp-exp-summary__message');
                    if (p) p.textContent = text || emptyLabel;
                }
                if (bodyEl) bodyEl.hidden = true;
            };

            const showBody = () => {
                if (statusEl) statusEl.hidden = true;
                if (bodyEl) bodyEl.hidden = false;
            };

            const collectTickets = () => {
                const map = {};
                document.querySelectorAll('.fp-exp-party-table tbody tr[data-ticket]').forEach((row) => {
                    const slug = row.getAttribute('data-ticket') || '';
                    const input = row.querySelector('.fp-exp-quantity__input');
                    if (!slug || !input) return;
                    const qty = parseInt(input.value, 10) || 0;
                    if (qty > 0) map[slug] = qty;
                });
                return map;
            };

            const collectAddons = () => {
                const map = {};
                document.querySelectorAll('.fp-exp-addons li[data-addon]').forEach((li) => {
                    const slug = li.getAttribute('data-addon') || '';
                    const checkbox = li.querySelector('input[type="checkbox"]');
                    if (!slug || !checkbox) return;
                    // Aggiungi solo gli addon selezionati
                    if (checkbox.checked) {
                        map[slug] = 1;
                    }
                });
                return map;
            };

            const experienceId = (config && config.experienceId) || 0;
            const quoteUrl = (() => {
                const base = (window.fpExpApiBase && typeof window.fpExpApiBase === 'string')
                    ? window.fpExpApiBase
                    : (window.wpApiSettings && wpApiSettings.root) || (location.origin + '/wp-json/');
                const root = base.endsWith('/') ? base : base + '/';
                return root + 'fp-exp/v1/rtb/quote';
            })();

            let debounceTimer = null;
            const hasSelectedSlot = () => {
                if (startInput && endInput) {
                    return Boolean((startInput.value || '').trim() && (endInput.value || '').trim());
                }
                return !!(slotsEl && slotsEl.querySelector('.fp-exp-slots__item.is-selected'));
            };

            const updateCtaState = () => {
                if (!ctaBtn) return;
                const tickets = collectTickets();
                const anyTicket = tickets && Object.keys(tickets).length > 0;
                const slotOk = hasSelectedSlot();
                ctaBtn.disabled = !(anyTicket && slotOk);
                if (ctaHint) {
                    if (!anyTicket) {
                        ctaHint.textContent = 'Seleziona almeno 1 biglietto.';
                        ctaHint.hidden = false;
                    } else if (!slotOk) {
                        ctaHint.textContent = 'Seleziona data e orario.';
                        ctaHint.hidden = false;
                    } else {
                        ctaHint.textContent = '';
                        ctaHint.hidden = true;
                    }
                }
            };

            const requestQuote = () => {
                if (!experienceId) {
                    setStatus(emptyLabel);
                    updateCtaState();
                    return;
                }
                const tickets = collectTickets();
                const addons = collectAddons();

                // Aggiorna hidden inputs per invio form
                if (ticketsHidden) ticketsHidden.value = JSON.stringify(tickets);
                if (addonsHidden) addonsHidden.value = JSON.stringify(addons);

                // Se nessun biglietto selezionato mostra stato vuoto
                if (!tickets || Object.keys(tickets).length === 0) {
                    setStatus(emptyLabel);
                    updateCtaState();
                    return;
                }

                setStatus(loadingLabel);

                const payload = {
                    nonce: (config && config.rtbNonce) || (config && config.nonce) || (rtbForm ? rtbForm.getAttribute('data-nonce') : ''),
                    experience_id: experienceId,
                    slot_id: 0,
                    start: startInput ? startInput.value : '',
                    end: endInput ? endInput.value : '',
                    tickets: tickets,
                    addons: addons,
                };

                fetch(quoteUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                })
                .then((res) => {
                    if (!res.ok) {
                        throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                    }
                    return res.json();
                })
                .then((data) => {
                    if (!data || data.success !== true || !data.breakdown) {
                        console.warn('[FP-EXP] Quote response invalid:', data);
                        setStatus(errorLabel);
                        return;
                    }

                    const breakdown = data.breakdown;
                    // Render linee
                    if (linesEl) {
                        linesEl.innerHTML = '';
                        const renderLine = (line) => {
                            const li = document.createElement('li');
                            li.className = 'fp-exp-summary__line';
                            const label = document.createElement('span');
                            label.className = 'fp-exp-summary__line-label';
                            label.textContent = `${line.label} × ${line.quantity}`;
                            const amount = document.createElement('span');
                            amount.className = 'fp-exp-summary__line-amount';
                            amount.textContent = formatCurrency(line.line_total, breakdown.currency);
                            li.appendChild(label);
                            li.appendChild(amount);
                            return li;
                        };
                        (Array.isArray(breakdown.tickets) ? breakdown.tickets : []).forEach((l) => linesEl.appendChild(renderLine(l)));
                        (Array.isArray(breakdown.addons) ? breakdown.addons : []).forEach((l) => linesEl.appendChild(renderLine(l)));
                    }

                    // Render adjustments
                    if (adjustmentsEl) {
                        const list = Array.isArray(breakdown.adjustments) ? breakdown.adjustments : [];
                        if (list.length === 0) {
                            adjustmentsEl.hidden = true;
                            adjustmentsEl.innerHTML = '';
                        } else {
                            adjustmentsEl.hidden = false;
                            adjustmentsEl.innerHTML = '';
                            list.forEach((adj) => {
                                const li = document.createElement('li');
                                li.className = 'fp-exp-summary__adjustment';
                                const label = document.createElement('span');
                                label.className = 'fp-exp-summary__adjustment-label';
                                label.textContent = adj.label || '';
                                const amount = document.createElement('span');
                                amount.className = 'fp-exp-summary__adjustment-amount';
                                amount.textContent = formatCurrency(adj.amount, breakdown.currency);
                                li.appendChild(label);
                                li.appendChild(amount);
                                adjustmentsEl.appendChild(li);
                            });
                        }
                    }

                    // Totale
                    if (totalEl) totalEl.textContent = formatCurrency(breakdown.total, breakdown.currency);
                    if (totalRowEl) totalRowEl.hidden = false;

                    // Disclaimer tasse (semplice toggle se presente etichetta)
                    if (disclaimerEl) {
                        const taxLabel = summary.getAttribute('data-tax-label') || '';
                        if (taxLabel) {
                            disclaimerEl.textContent = taxLabel;
                            disclaimerEl.hidden = false;
                        }
                    }

                    showBody();
                    updateCtaState();
                })
                .catch((error) => {
                    console.error('[FP-EXP] Quote request failed:', error);
                    setStatus(errorLabel);
                    updateCtaState();
                });
            };

            const debounceQuote = () => {
                if (debounceTimer) clearTimeout(debounceTimer);
                debounceTimer = setTimeout(requestQuote, 300);
            };

            // Trigger su cambi quantità biglietti e extra
            widget.addEventListener('change', (ev) => {
                if (ev.target && ev.target.closest('.fp-exp-quantity__input')) {
                    debounceQuote();
                    updateCtaState();
                }
                if (ev.target && ev.target.matches('.fp-exp-addons input[type="checkbox"]')) {
                    debounceQuote();
                }
            });
            widget.addEventListener('input', (ev) => {
                if (ev.target && ev.target.closest('.fp-exp-quantity__input')) {
                    debounceQuote();
                    updateCtaState();
                }
            });

            // Aggiorna anche quando si seleziona uno slot (per regole prezzo legate all'orario)
            if (slotsEl && rtbEnabled) {
                slotsEl.addEventListener('click', (ev) => {
                    if (ev.target && ev.target.closest('.fp-exp-slots__item')) {
                        debounceQuote();
                        updateCtaState();
                    }
                });
            }
            updateCtaState();
            // Inizializza modulo RTB Summary
            if (window.FPFront.summaryRtb && window.FPFront.summaryRtb.init) {
                window.FPFront.summaryRtb.init({ widget, slotsEl, config });
            }
        })();
    });
    
})();