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

        // Funzione per mostrare slot inline sotto il giorno cliccato
        const showSlotsInline = async (dayElement, date) => {
            // Rimuovi slot inline esistenti
            calendarEl.querySelectorAll('.fp-exp-slots-inline').forEach(el => el.remove());
            
            // Crea contenitore per gli slot inline
            const inlineContainer = document.createElement('div');
            inlineContainer.className = 'fp-exp-slots-inline';
            
            // Aggiungi loading state con timeout
            inlineContainer.innerHTML = '<div class="fp-exp-slots-inline__loading">Caricamento slot...</div>';
            
            // Inserisci il contenitore dopo il giorno cliccato
            dayElement.parentNode.insertBefore(inlineContainer, dayElement.nextSibling);
            
            // Timeout di sicurezza per evitare caricamento infinito
            const loadingTimeout = setTimeout(() => {
                if (inlineContainer.querySelector('.fp-exp-slots-inline__loading')) {
                    inlineContainer.innerHTML = '<div class="fp-exp-slots-inline__empty">Nessuna fascia disponibile per questa data</div>';
                }
            }, 3000);
            
            try {
                // Carica gli slot per la data selezionata
                let items = calendarMap.get(date) || [];
                if (!items || items.length === 0) {
                    // Fallback a chiamata API se non in cache
                    try {
                        items = await fetchAvailability(date);
                    } catch (apiError) {
                        console.warn('[FP-EXP] API fetch fallita, usando dati locali:', apiError);
                        items = [];
                    }
                }
                
                // Cancella il timeout
                clearTimeout(loadingTimeout);
                
                // Renderizza gli slot
                if (items && items.length > 0) {
                    const slotsList = document.createElement('div');
                    slotsList.className = 'fp-exp-slots-inline__list';
                    
                    items.forEach(slot => {
                        const slotElement = document.createElement('div');
                        slotElement.className = 'fp-exp-slots-inline__item';
                        slotElement.textContent = slot.time || slot.label || 'Slot';
                        slotElement.setAttribute('data-start', slot.start || slot.start_iso || '');
                        slotElement.setAttribute('data-end', slot.end || slot.end_iso || '');
                        
                        // Aggiungi click handler per selezione slot
                        slotElement.addEventListener('click', (ev) => {
                            ev.stopPropagation();
                            // Rimuovi selezione precedente
                            inlineContainer.querySelectorAll('.fp-exp-slots-inline__item.is-selected').forEach(el => {
                                el.classList.remove('is-selected');
                            });
                            // Seleziona slot corrente
                            slotElement.classList.add('is-selected');
                            
                            // Aggiorna anche la sezione slot principale se esiste
                            if (slotsEl && window.FPFront.slots && window.FPFront.slots.renderSlots) {
                                window.FPFront.slots.renderSlots(items);
                                // Seleziona lo slot corrispondente nella sezione principale
                                const mainSlot = slotsEl.querySelector(`[data-start="${slot.start || slot.start_iso}"]`);
                                if (mainSlot) {
                                    mainSlot.classList.add('is-selected');
                                }
                            }
                        });
                        
                        slotsList.appendChild(slotElement);
                    });
                    
                    inlineContainer.innerHTML = '';
                    inlineContainer.appendChild(slotsList);
                } else {
                    // Nessun slot disponibile per questa data
                    inlineContainer.innerHTML = '<div class="fp-exp-slots-inline__empty">Nessuna fascia disponibile per questa data</div>';
                }
            } catch (error) {
                // Cancella il timeout
                clearTimeout(loadingTimeout);
                console.error('[FP-EXP] Errore caricamento slot inline:', error);
                inlineContainer.innerHTML = '<div class="fp-exp-slots-inline__error">Errore caricamento slot</div>';
            }
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

        // Mappa YYYY-MM-DD → array di slot dal dataset calendar
        const calendarMap = (window.FPFront.availability && window.FPFront.availability.getCalendarMap && window.FPFront.availability.getCalendarMap()) || new Map();

        const showCalendarIfConfigured = () => {
            if (!calendarEl) return;
            const shouldShow = (widget.getAttribute('data-config') || '').includes('"show_calendar":true') || calendarEl.getAttribute('data-show-calendar') === '1';
            if (shouldShow) {
                calendarEl.hidden = false;
            }
        };
        // Inizializza modulo calendario (visibilità, toolbar, prefetch iniziale)
        if (window.FPFront.calendar && window.FPFront.calendar.init) {
            window.FPFront.calendar.init({ calendarEl, widget });
        }

        // 2) Alla modifica della data, mostra gli slot del giorno
        if (dateInput) {
            dateInput.addEventListener('change', async () => {
                const date = dateInput.value; // formato YYYY-MM-DD
                let items = calendarMap.get(date) || [];
                let isLoading = false;
                
                if (!items || items.length === 0) {
                    // fallback a chiamata API
                    setSlotsLoading(true);
                    isLoading = true;
                    try {
                        items = await fetchAvailability(date);
                    } catch (e) {
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
                    }
                }
                
                // evidenzia nel calendario inline
                if (calendarEl) {
                    calendarEl.querySelectorAll('.fp-exp-calendar__day').forEach((btn) => btn.classList.remove('is-selected'));
                    const btn = calendarEl.querySelector('.fp-exp-calendar__day[data-date="' + date + '"]');
                    if (btn) btn.classList.add('is-selected');
                }
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

        // 3) Click su giorno del calendario → imposta input e renderizza slot inline
        if (calendarEl) {
            calendarEl.addEventListener('click', (ev) => {
                const target = ev.target.closest('.fp-exp-calendar__day');
                if (!target || target.disabled) return;
                const date = target.getAttribute('data-date');
                if (!date) return;
                
                // Rimuovi selezione precedente
                calendarEl.querySelectorAll('.fp-exp-calendar__day.is-selected').forEach(day => {
                    day.classList.remove('is-selected');
                });
                
                // Seleziona il giorno corrente
                target.classList.add('is-selected');
                
                // Carica e mostra gli slot direttamente sotto il giorno cliccato
                showSlotsInline(target, date);
                
                if (dateInput) {
                    dateInput.value = date;
                    dateInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            // già gestito nel modulo calendar
        }

        // 3.b) Gestisci CTA con data-fp-scroll (hero e sticky): mostra calendario e scrolla
        (function setupCtaScrollHandlers() {
            // Delega a tutto il documento per coprire sia il bottone in hero sia quello sticky
            document.addEventListener('click', function(ev) {
                var btn = ev.target && (ev.target.closest('[data-fp-scroll]'));
                if (!btn) return;
                var targetKey = btn.getAttribute('data-fp-scroll') || '';
                if (!targetKey) return;

                // Mappa dei target noti
                var targetEl = null;
                if (targetKey === 'calendar') {
                    targetEl = calendarEl || document.querySelector('[data-fp-scroll-target="calendar"], .fp-exp-calendar');
                } else if (targetKey === 'gallery') {
                    targetEl = document.querySelector('[data-fp-scroll-target="gallery"], .fp-exp-gallery');
                }

                // Se calendar è nascosto per configurazione, mostralo
                if (targetKey === 'calendar') {
                    if (calendarEl) { calendarEl.hidden = false; }
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

        // Inizializza il modulo availability con il contesto corrente
        if (window.FPFront.availability && window.FPFront.availability.init) {
            window.FPFront.availability.init({ config, calendarEl, widget });
        }
    });
    
})();