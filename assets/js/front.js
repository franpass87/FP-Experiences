/**
 * Frontend JavaScript - Entry point per il frontend
 * Questo file serve come entry point per il frontend
 */

// Inizializza "Leggi di più" IMMEDIATAMENTE (standalone, non aspetta jQuery)
(function initReadMoreImmediate() {
    'use strict';
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupReadMore);
    } else {
        setupReadMore();
    }
    
    function setupReadMore() {
        const listingSection = document.querySelector('.fp-listing');
        if (!listingSection) {
            console.log('FP-Experiences: Sezione listing non trovata');
            return;
        }
        
        console.log('FP-Experiences: Read More STANDALONE attivo');
        
        // Click handler con delegazione
        listingSection.addEventListener('click', function(ev) {
            const btn = ev.target.closest('[data-fp-read-more]');
            if (!btn) return;
            
            ev.preventDefault();
            console.log('FP-Experiences: Toggle Read More');
            
            const wrapper = btn.closest('.fp-listing__description-wrapper');
            if (!wrapper) return;
            
            const description = wrapper.querySelector('[data-fp-text-clamp]');
            const textSpan = btn.querySelector('.fp-listing__read-more-text');
            if (!description || !textSpan) return;
            
            const isExpanded = btn.getAttribute('aria-expanded') === 'true';
            const expandText = textSpan.getAttribute('data-expand-text') || 'Leggi di più';
            const collapseText = textSpan.getAttribute('data-collapse-text') || 'Mostra meno';
            
            if (isExpanded) {
                description.classList.remove('is-expanded');
                description.classList.add('is-clamped');
                btn.setAttribute('aria-expanded', 'false');
                textSpan.textContent = expandText;
            } else {
                description.classList.remove('is-clamped');
                description.classList.add('is-expanded');
                btn.setAttribute('aria-expanded', 'true');
                textSpan.textContent = collapseText;
            }
        });
        
        // Nascondi pulsante se testo non è troncato
        function checkDescriptions() {
            document.querySelectorAll('[data-fp-text-clamp]').forEach(function(desc) {
                const wrapper = desc.closest('.fp-listing__description-wrapper');
                if (!wrapper) return;
                
                const btn = wrapper.querySelector('[data-fp-read-more]');
                if (!btn) return;
                
                // +2px tolleranza per sub-pixel rendering
                const isOverflowing = desc.scrollHeight > desc.clientHeight + 2;
                btn.style.display = isOverflowing ? 'inline-flex' : 'none';
            });
        }
        
        checkDescriptions();
        
        let resizeTimer;
        const handleResizeReadMore = function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(checkDescriptions, 150);
        };
        window.addEventListener('resize', handleResizeReadMore);
        
        // Cleanup event listener quando la pagina viene scaricata
        window.addEventListener('beforeunload', () => {
            window.removeEventListener('resize', handleResizeReadMore);
            clearTimeout(resizeTimer);
        });
    }
})();

// Carica i moduli frontend necessari
(function() {
    'use strict';
    
    // Verifica che jQuery sia disponibile
    if (typeof jQuery === 'undefined') {
        // jQuery non trovato
        return;
    }
    
    // Inizializza quando il documento è pronto
    jQuery(document).ready(function($) {
        // Namespace globale leggero per futura modularizzazione
        if (!window.FPFront) window.FPFront = {};
        // Frontend inizializzato
        
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
        
        // Esegui al resize (con debounce) - cleanup su unmount per evitare memory leak
        let resizeTimeout;
        const handleResize = () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(repositionWidgetForMobile, 150);
        };
        window.addEventListener('resize', handleResize);
        
        // Cleanup event listener quando la pagina viene scaricata
        window.addEventListener('beforeunload', () => {
            window.removeEventListener('resize', handleResize);
            clearTimeout(resizeTimeout);
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
            // Config frontend non valida
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
                // ✅ XSS fix: usa createElement + textContent invece di innerHTML
                const placeholder = document.createElement('p');
                placeholder.className = 'fp-exp-slots__placeholder';
                placeholder.textContent = loadingLabel;
                slotsEl.innerHTML = '';
                slotsEl.appendChild(placeholder);
            } else {
                // Rimuovi il loading state - il contenuto verrà sostituito dal renderSlots o dal fallback
                slotsEl.innerHTML = '';
            }
        };

        // Funzione semplificata - non più necessaria con input date nativo
        const showSlotsInline = async (dayElement, date) => {
            // showSlotsInline non più necessaria - sistema semplificato
        };

        const showSlotsError = (message) => {
            if (!slotsEl) return;
            const text = message || 'Impossibile caricare gli slot. Riprova.';
            // ✅ XSS fix: usa createElement + textContent invece di innerHTML
            const placeholder = document.createElement('p');
            placeholder.className = 'fp-exp-slots__placeholder';
            placeholder.textContent = text;
            slotsEl.innerHTML = '';
            slotsEl.appendChild(placeholder);
        };

        const formatTimeRange = (startIso, endIso) => window.FPFront.availability ? window.FPFront.availability.formatTimeRange(startIso, endIso) : 'Slot';

        // Inizializza modulo slots una volta
        if (window.FPFront.slots) window.FPFront.slots.init({ slotsEl });

        // Fetch dinamico dagli endpoint REST del plugin
        const fetchAvailability = async (date) => window.FPFront.availability ? window.FPFront.availability.fetchAvailability(date) : [];

        // Cache mensile: { 'YYYY-MM': Set('YYYY-MM-DD'→count) }
        const monthKeyOf = (dateStr) => (window.FPFront.availability ? window.FPFront.availability.monthKeyOf(dateStr) : (dateStr || '').slice(0,7));
        const prefetchMonth = async (yyyyMm) => window.FPFront.availability && window.FPFront.availability.prefetchMonth(yyyyMm);

        // Inizializza il modulo availability prima di usare il calendario
        if (window.FPFront.availability && window.FPFront.availability.init) {
            window.FPFront.availability.init({ config, widget });
            // Availability module initialized
        }

        // Mappa YYYY-MM-DD → array di slot dal dataset (dopo l'inizializzazione!)
        const getCalendarMap = () => (window.FPFront.availability && window.FPFront.availability.getCalendarMap && window.FPFront.availability.getCalendarMap()) || new Map();
        let calendarMap = getCalendarMap();

        // Sistema semplificato - input date nativo

        // 2) Alla modifica della data, mostra gli slot del giorno
        if (dateInput) {
            dateInput.addEventListener('change', async () => {
                const date = dateInput.value; // formato YYYY-MM-DD
                // Date changed to: date
                
                // Get fresh reference to calendarMap
                calendarMap = getCalendarMap();
                let items = calendarMap.get(date) || [];
                let isLoading = false;
                
                if (!items || items.length === 0) {
                    // fallback a chiamata API
                    setSlotsLoading(true);
                    isLoading = true;
                    try {
                        items = await fetchAvailability(date);
                    } catch (e) {
                        // API fetch failed
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
                    // No loading, items available
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
                    // Prefetch dell'intero mese in una sola chiamata API
                    await prefetchMonth(monthKey);
                    
                    // Get fresh reference to calendarMap after prefetch
                    calendarMap = getCalendarMap();
                    
                    // Genera tutti i giorni del mese
                    const dayButtons = [];
                    
                    for (let day = 1; day <= daysInMonth; day++) {
                        const dateKey = monthKey + '-' + String(day).padStart(2, '0');
                        const isPast = new Date(dateKey) < new Date(new Date().setHours(0, 0, 0, 0));
                        
                        // Controlla se ci sono slot per questa data dalla calendarMap (ora popolata dal prefetch)
                        const cachedSlots = calendarMap.get(dateKey);
                        const slotCount = (cachedSlots && cachedSlots.length) || 0;
                        const isAvailable = slotCount > 0;
                        
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
                    // Errore generazione calendario
                    grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: var(--fp-color-error);">Errore caricamento calendario</div>';
                }
            }
        };
        
        // Aggiungi il campo 'label' agli slot nella calendarMap per la visualizzazione
        calendarMap = getCalendarMap(); // Refresh reference
        if (calendarMap && calendarMap.size > 0) {
            // Adding labels to dates in calendarMap
            calendarMap.forEach((slots, dateKey) => {
                const slotsWithLabels = slots.map(slot => {
                    if (!slot.label && slot.start_iso && slot.end_iso) {
                        slot.label = formatTimeRange(slot.start_iso, slot.end_iso);
                    }
                    return slot;
                });
                calendarMap.set(dateKey, slotsWithLabels);
            });
        } else {
            // CalendarMap is empty or not initialized
        }
        
        // Inizializza il calendario immediatamente (non aspettare DOMContentLoaded che potrebbe non triggerare)
        const calendarNav = document.querySelector('.fp-exp-calendar-nav');
        if (calendarNav) {
            
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
            const handleCtaScroll = function(ev) {
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
            };
            document.addEventListener('click', handleCtaScroll);
            
            // Cleanup event listener quando la pagina viene scaricata
            window.addEventListener('beforeunload', () => {
                document.removeEventListener('click', handleCtaScroll);
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
                        updateStickyBarPrice();
                    }
                    // Aggiorna anche quando si selezionano/deselezionano gli addon
                    if (ev.target && ev.target.matches('.fp-exp-addons input[type="checkbox"]')) {
                        updatePriceSummary();
                        updateStickyBarPrice();
                    }
                });
                widget.addEventListener('input', (ev) => {
                    if (ev.target && ev.target.closest('.fp-exp-quantity__input')) {
                        updatePriceSummary();
                        updateStickyBarPrice();
                    }
                });
                if (slotsEl) {
                    slotsEl.addEventListener('click', (ev) => {
                        if (ev.target && ev.target.closest('.fp-exp-slots__item')) {
                            updatePriceSummary();
                            updateStickyBarPrice();
                        }
                    });
                }

                // Stato iniziale
                updatePriceSummary();
                updateStickyBarPrice();
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

            // Funzione per aggiornare il prezzo nella sticky bar in tempo reale
            function updateStickyBarPrice() {
                const stickyBar = document.querySelector('[data-fp-sticky-bar]');
                if (!stickyBar) return;

                const priceValueEl = stickyBar.querySelector('.fp-exp-page__sticky-price-value');
                if (!priceValueEl) return;

                const priceLabelEl = stickyBar.querySelector('.fp-exp-page__sticky-price-label');

                const tickets = collectTickets();
                const addons = collectAddons();
                const currency = (config && config.currency) || 'EUR';

                // Calcola il totale
                let total = 0;
                let hasTickets = false;

                // Somma biglietti
                Object.entries(tickets).forEach(([slug, qty]) => {
                    if (qty > 0) hasTickets = true;
                    const priceEl = document.querySelector(`tr[data-ticket="${slug}"] .fp-exp-ticket__price[data-price]`);
                    const price = priceEl ? parseFloat(priceEl.getAttribute('data-price') || '0') : 0;
                    total += price * qty;
                });

                // Somma addon
                Object.entries(addons).forEach(([slug, qty]) => {
                    const priceEl = document.querySelector(`li[data-addon="${slug}"] .fp-exp-addon__price[data-price]`);
                    const price = priceEl ? parseFloat(priceEl.getAttribute('data-price') || '0') : 0;
                    total += price * qty;
                });

                // Se non ci sono biglietti selezionati, usa il prezzo iniziale dalla configurazione
                if (!hasTickets && config && config.priceFrom && parseFloat(config.priceFrom) > 0) {
                    total = parseFloat(config.priceFrom);
                }

                // Mostra/nascondi la label "Da" in base alla presenza di biglietti
                if (priceLabelEl) {
                    if (hasTickets) {
                        priceLabelEl.style.display = 'none';
                    } else {
                        priceLabelEl.style.display = '';
                    }
                }

                // Formatta e aggiorna il prezzo nella sticky bar
                try {
                    const fmt = new Intl.NumberFormat(undefined, { style: 'currency', currency: currency });
                    priceValueEl.textContent = fmt.format(total);
                } catch (e) {
                    priceValueEl.textContent = String(total);
                }
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
                    // Dati slot mancanti per checkout WooCommerce
                    return;
                }

                // Verifica che i nonce siano disponibili
                if (typeof fpExpConfig === 'undefined') {
                    // fpExpConfig non definito
                    alert('Errore di configurazione. Aggiorna la pagina e riprova.');
                    return;
                }

                if (!fpExpConfig.restNonce) {
                    // restNonce mancante
                    alert('Sessione non valida. Aggiorna la pagina e riprova.');
                    return;
                }

                // Richiedi nonce fresco prima del checkout per evitare problemi di cache
                let freshCheckoutNonce = '';
                
                // Definisci restBaseUrl FUORI dal try per usarlo in entrambi i blocchi
                const restBaseUrl = (typeof fpExpConfig !== 'undefined' && fpExpConfig.restUrl) 
                    || (window.wpApiSettings && wpApiSettings.root) 
                    || (window.location.origin + '/wp-json/fp-exp/v1/');
                
                try {
                    ctaBtn.disabled = true;
                    ctaBtn.textContent = 'Preparazione pagamento...';
                    
                    // Richiedi nonce fresco da /checkout/nonce
                    const nonceUrl = new URL(restBaseUrl + 'checkout/nonce', window.location.origin);
                    const nonceResponse = await fetch(nonceUrl, {
                        method: 'GET',
                        credentials: 'same-origin'
                    });
                    
                    if (!nonceResponse.ok) {
                        throw new Error(`Errore richiesta nonce (${nonceResponse.status})`);
                    }
                    
                    const nonceData = await nonceResponse.json();
                    if (!nonceData || !nonceData.nonce) {
                        throw new Error('Nonce non ricevuto dal server');
                    }
                    
                    freshCheckoutNonce = nonceData.nonce;
                    console.log('FP-EXP: Nonce ottenuto:', freshCheckoutNonce);
                } catch (e) {
                    console.error('FP-EXP: Errore nonce:', e);
                    ctaBtn.disabled = false;
                    ctaBtn.textContent = 'Procedi al pagamento';
                    alert('Sessione non valida. Aggiorna la pagina e riprova.');
                    return;
                }

                console.log('FP-EXP: Procedo con cart/set...');

                // Usa il sistema di checkout integrato del plugin
                try {
                    ctaBtn.textContent = 'Aggiunta al carrello...';
                    console.log('FP-EXP: Preparazione chiamata cart/set');
                    
                    // Verifica variabili PRIMA di procedere
                    console.log('FP-EXP: Verifica variabili:', {
                        experienceId: experienceId,
                        start: start,
                        end: end,
                        tickets: tickets,
                        hasCollectAddons: typeof collectAddons === 'function'
                    });
                    
                    if (!experienceId) {
                        throw new Error('Experience ID mancante');
                    }
                    if (!start || !end) {
                        throw new Error('Slot mancante (start/end)');
                    }
                    if (!tickets || Object.keys(tickets).length === 0) {
                        throw new Error('Nessun ticket selezionato');
                    }

                    console.log('FP-EXP: Variabili OK, costruisco URL...');
                    console.log('FP-EXP: restBaseUrl vale:', restBaseUrl);
                    console.log('FP-EXP: window.location.origin vale:', window.location.origin);

                    // Aggiungi al carrello interno del plugin
                    try {
                        const setCartUrl = new URL(restBaseUrl + 'cart/set', window.location.origin);
                        console.log('FP-EXP: URL costruito:', setCartUrl.toString());
                    } catch (urlError) {
                        console.error('FP-EXP: Errore costruzione URL:', urlError);
                        throw new Error('Errore costruzione URL: ' + urlError.message);
                    }
                    
                    const setCartUrl = new URL(restBaseUrl + 'cart/set', window.location.origin);
                    console.log('FP-EXP: URL finale:', setCartUrl.toString());
                    
                    const cartData = {
                        experience_id: experienceId,
                        slot_id: 0,
                        slot_start: start,
                        slot_end: end,
                        tickets: tickets,
                        addons: (typeof collectAddons === 'function' ? collectAddons() : {})
                    };
                    
                    console.log('FP-EXP: Dati carrello preparati:', cartData);

                    console.log('FP-EXP: Invio fetch a cart/set...');

                    const setCartResponse = await fetch(setCartUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': (typeof fpExpConfig !== 'undefined' && fpExpConfig.restNonce) || ''
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(cartData)
                    });

                    console.log('FP-EXP: Fetch completato!');
                    console.log('FP-EXP: Risposta cart/set:', setCartResponse.status, setCartResponse.ok);

                    if (!setCartResponse.ok) {
                        let errorData = {};
                        try {
                            const text = await setCartResponse.text();
                            errorData = text ? JSON.parse(text) : {};
                        } catch (e) {
                            // Impossibile parsare risposta errore cart/set
                        }
                        throw new Error(errorData.message || `Errore aggiunta al carrello (${setCartResponse.status})`);
                    }

                    // ✅ v0.5.0: Redirect to WooCommerce checkout page
                    // Cart will be automatically synced via template_redirect hook
                    ctaBtn.textContent = 'Reindirizzamento...';
                    
                    console.log('FP-EXP: Cart/set OK, procedo con redirect...');
                    
                    // Redirect to WooCommerce checkout page (with fallback)
                    const checkoutPageUrl = (typeof fpExpConfig !== 'undefined' && fpExpConfig.checkoutUrl) || '/checkout/';
                    console.log('FP-EXP: Redirect a:', checkoutPageUrl);
                    window.location.href = checkoutPageUrl;

                } catch (error) {
                    // Errore checkout WooCommerce
                    ctaBtn.disabled = false;
                    
                    // Messaggio specifico per errori di sessione
                    const errorMessage = error.message || '';
                    if (errorMessage.includes('sessione') || errorMessage.includes('scaduta') || errorMessage.includes('session')) {
                        ctaBtn.textContent = 'Sessione scaduta - Ricarica';
                        alert('La tua sessione è scaduta. Aggiorna la pagina (F5) e riprova.');
                    } else {
                        ctaBtn.textContent = 'Errore - Riprova';
                    }
                    
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
                // RTB disabilitato, configurando flusso WooCommerce
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

            // Funzione per aggiornare il prezzo nella sticky bar in tempo reale (modalità RTB)
            const updateStickyBarPrice = () => {
                const stickyBar = document.querySelector('[data-fp-sticky-bar]');
                if (!stickyBar) return;

                const priceValueEl = stickyBar.querySelector('.fp-exp-page__sticky-price-value');
                if (!priceValueEl) return;

                const priceLabelEl = stickyBar.querySelector('.fp-exp-page__sticky-price-label');

                const tickets = collectTickets();
                const addons = collectAddons();
                const currency = (config && config.currency) || 'EUR';

                // Calcola il totale
                let total = 0;
                let hasTickets = false;

                // Somma biglietti
                Object.entries(tickets).forEach(([slug, qty]) => {
                    if (qty > 0) hasTickets = true;
                    const priceEl = document.querySelector(`tr[data-ticket="${slug}"] .fp-exp-ticket__price[data-price]`);
                    const price = priceEl ? parseFloat(priceEl.getAttribute('data-price') || '0') : 0;
                    total += price * qty;
                });

                // Somma addon
                Object.entries(addons).forEach(([slug, qty]) => {
                    const priceEl = document.querySelector(`li[data-addon="${slug}"] .fp-exp-addon__price[data-price]`);
                    const price = priceEl ? parseFloat(priceEl.getAttribute('data-price') || '0') : 0;
                    total += price * qty;
                });

                // Se non ci sono biglietti selezionati, usa il prezzo iniziale dalla configurazione
                if (!hasTickets && config && config.priceFrom && parseFloat(config.priceFrom) > 0) {
                    total = parseFloat(config.priceFrom);
                }

                // Mostra/nascondi la label "Da" in base alla presenza di biglietti
                if (priceLabelEl) {
                    if (hasTickets) {
                        priceLabelEl.style.display = 'none';
                    } else {
                        priceLabelEl.style.display = '';
                    }
                }

                // Formatta e aggiorna il prezzo nella sticky bar
                try {
                    const fmt = new Intl.NumberFormat(undefined, { style: 'currency', currency: currency });
                    priceValueEl.textContent = fmt.format(total);
                } catch (e) {
                    priceValueEl.textContent = String(total);
                }
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

                const quoteHeaders = { 'Content-Type': 'application/json' };
                if (typeof fpExpConfig !== 'undefined' && fpExpConfig.restNonce) {
                    quoteHeaders['X-WP-Nonce'] = fpExpConfig.restNonce;
                }
                
                fetch(quoteUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: quoteHeaders,
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
                        // Quote response invalid
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
                    // Quote request failed
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
                    updateStickyBarPrice();
                }
                if (ev.target && ev.target.matches('.fp-exp-addons input[type="checkbox"]')) {
                    debounceQuote();
                    updateStickyBarPrice();
                }
            });
            widget.addEventListener('input', (ev) => {
                if (ev.target && ev.target.closest('.fp-exp-quantity__input')) {
                    debounceQuote();
                    updateCtaState();
                    updateStickyBarPrice();
                }
            });

            // Aggiorna anche quando si seleziona uno slot (per regole prezzo legate all'orario)
            if (slotsEl && rtbEnabled) {
                slotsEl.addEventListener('click', (ev) => {
                    if (ev.target && ev.target.closest('.fp-exp-slots__item')) {
                        debounceQuote();
                        updateCtaState();
                        updateStickyBarPrice();
                    }
                });
            }
            updateCtaState();
            updateStickyBarPrice();
            // Inizializza modulo RTB Summary
            if (window.FPFront.summaryRtb && window.FPFront.summaryRtb.init) {
                window.FPFront.summaryRtb.init({ widget, slotsEl, config });
            }
        })();

        // 7) Gestione modale regalo
        (function setupGiftModal() {
            const giftToggleBtn = document.querySelector('[data-fp-gift-toggle]');
            const giftModal = document.querySelector('[data-fp-gift]');
            const giftBackdrop = giftModal ? giftModal.querySelector('[data-fp-gift-backdrop]') : null;
            const giftCloseBtn = giftModal ? giftModal.querySelector('[data-fp-gift-close]') : null;
            const giftDialog = giftModal ? giftModal.querySelector('[data-fp-gift-dialog]') : null;
            const giftForm = giftModal ? giftModal.querySelector('[data-fp-gift-form]') : null;
            const giftSubmitBtn = giftForm ? giftForm.querySelector('[data-fp-gift-submit]') : null;
            const giftFeedback = giftModal ? giftModal.querySelector('[data-fp-gift-feedback]') : null;

            if (!giftToggleBtn || !giftModal) {
                return; // No gift functionality on this page
            }

            // Parse gift configuration
            let giftConfig = {};
            try {
                const configAttr = giftModal.getAttribute('data-fp-gift-config');
                giftConfig = configAttr ? JSON.parse(configAttr) : {};
            } catch (e) {
                // Invalid gift config
            }

            // Open modal
            const openGiftModal = () => {
                giftModal.hidden = false;
                giftModal.setAttribute('aria-hidden', 'false');
                giftToggleBtn.setAttribute('aria-expanded', 'true');
                
                // Add is-open class for CSS transition
                setTimeout(() => {
                    giftModal.classList.add('is-open');
                }, 10);
                
                // Focus on dialog
                if (giftDialog) {
                    setTimeout(() => {
                        giftDialog.focus();
                    }, 100);
                }
                
                // Prevent body scroll
                document.body.style.overflow = 'hidden';
            };

            // Close modal
            const closeGiftModal = () => {
                // Remove is-open class first for transition
                giftModal.classList.remove('is-open');
                
                // Wait for transition to complete before hiding
                setTimeout(() => {
                    giftModal.hidden = true;
                    giftModal.setAttribute('aria-hidden', 'true');
                    giftToggleBtn.setAttribute('aria-expanded', 'false');
                    
                    // Restore body scroll
                    document.body.style.overflow = '';
                    
                    // Return focus to toggle button
                    giftToggleBtn.focus();
                }, 250); // Match CSS transition duration
            };

            // Toggle button click
            giftToggleBtn.addEventListener('click', (ev) => {
                ev.preventDefault();
                openGiftModal();
            });

            // Close button click
            if (giftCloseBtn) {
                giftCloseBtn.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    closeGiftModal();
                });
            }

            // Backdrop click
            if (giftBackdrop) {
                giftBackdrop.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    closeGiftModal();
                });
            }

            // Escape key to close
            const handleEscapeKey = (ev) => {
                if (ev.key === 'Escape' && !giftModal.hidden) {
                    closeGiftModal();
                }
            };
            document.addEventListener('keydown', handleEscapeKey);
            
            // Cleanup event listener quando la pagina viene scaricata
            window.addEventListener('beforeunload', () => {
                document.removeEventListener('keydown', handleEscapeKey);
            });

            // Form submission
            if (giftForm && giftSubmitBtn) {
                giftForm.addEventListener('submit', async (ev) => {
                    ev.preventDefault();

                    // Basic validation
                    if (!giftForm.checkValidity()) {
                        giftForm.reportValidity();
                        return;
                    }

                    // Collect form data
                    const formData = new FormData(giftForm);
                    const data = {
                        experience_id: giftConfig.experienceId || 0,
                        purchaser: {
                            name: formData.get('purchaser[name]') || '',
                            email: formData.get('purchaser[email]') || ''
                        },
                        recipient: {
                            name: formData.get('recipient[name]') || '',
                            email: formData.get('recipient[email]') || ''
                        },
                        delivery: {
                            send_on: formData.get('delivery[send_on]') || ''
                        },
                        quantity: parseInt(formData.get('quantity'), 10) || 1,
                        message: formData.get('message') || '',
                        addons: formData.getAll('addons[]') || []
                    };

                    // Disable submit button
                    giftSubmitBtn.disabled = true;
                    giftSubmitBtn.textContent = 'Elaborazione...';

                    try {
                        // Call gift voucher endpoint
                        const restBaseUrl = (typeof fpExpConfig !== 'undefined' && fpExpConfig.restUrl) 
                            || (window.wpApiSettings && wpApiSettings.root) 
                            || (window.location.origin + '/wp-json/fp-exp/v1/');
                        const response = await fetch(restBaseUrl + 'gift/purchase', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': (typeof fpExpConfig !== 'undefined' && fpExpConfig.restNonce) || ''
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(data)
                        });

                        if (!response.ok) {
                            let errorData = {};
                            try {
                                errorData = await response.json();
                            } catch (e) {
                                // Error parsing gift response
                            }
                            throw new Error(errorData.message || 'Errore durante la creazione del voucher regalo');
                        }

                        const result = await response.json();

                        // Check for payment_url or checkout_url
                        const checkoutUrl = result.payment_url || result.checkout_url || (result.data && result.data.payment_url);

                        if (checkoutUrl) {
                            // Redirect to checkout
                            window.location.href = checkoutUrl;
                        } else {
                            // Show success message
                            if (giftFeedback) {
                                giftFeedback.hidden = false;
                                giftFeedback.className = 'fp-gift__feedback fp-gift__feedback--success';
                                giftFeedback.textContent = 'Voucher regalo creato con successo!';
                            }
                            
                            // Reset form
                            giftForm.reset();
                            
                            // Close modal after 2 seconds
                            setTimeout(() => {
                                closeGiftModal();
                                if (giftFeedback) {
                                    giftFeedback.hidden = true;
                                    giftFeedback.textContent = '';
                                }
                            }, 2000);
                        }
                    } catch (error) {
                        // Gift voucher error
                        
                        // Show error message
                        if (giftFeedback) {
                            giftFeedback.hidden = false;
                            giftFeedback.className = 'fp-gift__feedback fp-gift__feedback--error';
                            giftFeedback.textContent = error.message || 'Si è verificato un errore. Riprova.';
                        }
                    } finally {
                        // Re-enable submit button
                        giftSubmitBtn.disabled = false;
                        giftSubmitBtn.textContent = 'Procedi al pagamento';
                    }
                });
            }
        })();

        // 8) Gestione "Leggi di più" - ORA GESTITO IN STANDALONE all'inizio del file
        // (Codice rimosso per evitare duplicati - vedi riga ~7)
    });
    
})();