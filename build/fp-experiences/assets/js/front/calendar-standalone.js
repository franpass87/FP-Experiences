/**
 * FP Experiences - Standalone Calendar JavaScript
 * Inizializza e gestisce il calendario standalone (shortcode fp_exp_calendar)
 */
(function() {
    'use strict';

    if (!window.FPFront) {
        window.FPFront = {};
    }

    function init() {
        // Trova tutti i calendari standalone nella pagina
        var calendars = document.querySelectorAll('[data-fp-shortcode="calendar"]');
        
        calendars.forEach(function(calendar) {
            initializeCalendar(calendar);
        });
    }

    function initializeCalendar(calendar) {
        var experienceId = parseInt(calendar.getAttribute('data-experience'), 10) || 0;
        var slotsData = calendar.getAttribute('data-slots');
        var slotsEl = calendar.querySelector('.fp-exp-slots');
        var calendarEl = calendar.querySelector('.fp-exp-calendar');
        
        if (!experienceId || !slotsData) {
            // Calendar standalone: dati mancanti
            return;
        }

        // Parse slots data
        var slotsMap = {};
        try {
            slotsMap = JSON.parse(slotsData);
        } catch (e) {
            // Errore parsing slots
            return;
        }

        // Popola la calendarMap con i dati del server
        if (window.FPFront.availability) {
            var calendarMap = window.FPFront.availability.getCalendarMap && window.FPFront.availability.getCalendarMap();
            if (calendarMap) {
                Object.keys(slotsMap).forEach(function(dateKey) {
                    calendarMap.set(dateKey, slotsMap[dateKey] || []);
                });
            }
        }

        // Configura il modulo availability per questo calendario
        if (window.FPFront.availability && window.FPFront.availability.init) {
            window.FPFront.availability.init({
                config: { 
                    experienceId: experienceId,
                    calendar: { slots: slotsMap }
                },
                widget: calendar,
                calendarEl: calendarEl
            });
        }

        // Inizializza il modulo slots
        if (window.FPFront.slots && window.FPFront.slots.init) {
            window.FPFront.slots.init({ slotsEl: slotsEl });
        }

        // Inizializza il modulo calendar (navigazione mesi)
        if (window.FPFront.calendar && window.FPFront.calendar.init) {
            window.FPFront.calendar.init({
                calendarEl: calendarEl,
                widget: calendar
            });
        }

        // Gestione click sui giorni del calendario
        calendar.addEventListener('click', function(e) {
            var dayBtn = e.target.closest('.fp-exp-calendar__day');
            if (!dayBtn) return;

            var date = dayBtn.getAttribute('data-date');
            var isAvailable = dayBtn.getAttribute('data-available') === '1';

            if (!date || !isAvailable) return;

            // Rimuovi selezione precedente
            calendar.querySelectorAll('.fp-exp-calendar__day').forEach(function(btn) {
                btn.classList.remove('is-selected');
                btn.setAttribute('aria-pressed', 'false');
            });

            // Seleziona il giorno cliccato
            dayBtn.classList.add('is-selected');
            dayBtn.setAttribute('aria-pressed', 'true');

            // Mostra gli slot per questo giorno
            showSlotsForDate(date, slotsMap, slotsEl);
        });
    }

    function showSlotsForDate(date, slotsMap, slotsEl) {
        if (!slotsEl) return;

        var slots = slotsMap[date] || [];

        if (slots.length === 0) {
            var emptyLabel = slotsEl.getAttribute('data-empty-label') || 'Nessuna fascia disponibile per questa data';
            slotsEl.innerHTML = '<p class="fp-exp-slots__placeholder">' + emptyLabel + '</p>';
            return;
        }

        // Renderizza gli slot usando il modulo slots se disponibile
        if (window.FPFront.slots && window.FPFront.slots.renderSlots) {
            // Aggiungi label formattata agli slot se non presente
            var slotsWithLabels = slots.map(function(slot) {
                if (!slot.label && slot.start && slot.end && window.FPFront.availability && window.FPFront.availability.formatTimeRange) {
                    slot.label = window.FPFront.availability.formatTimeRange(slot.start, slot.end);
                }
                return slot;
            });
            window.FPFront.slots.renderSlots(slotsWithLabels);
        } else {
            // Fallback: rendering manuale
            var list = document.createElement('ul');
            list.className = 'fp-exp-slots__list';
            
            slots.forEach(function(slot) {
                var li = document.createElement('li');
                li.className = 'fp-exp-slots__item';
                
                var label = slot.time || slot.label || 'Slot';
                if (!label && slot.start && slot.end && window.FPFront.availability && window.FPFront.availability.formatTimeRange) {
                    label = window.FPFront.availability.formatTimeRange(slot.start, slot.end);
                }
                
                li.textContent = label;
                li.setAttribute('data-start', slot.start || slot.start_iso || '');
                li.setAttribute('data-end', slot.end || slot.end_iso || '');
                
                if (slot.remaining !== undefined) {
                    var badge = document.createElement('span');
                    badge.className = 'fp-exp-slots__remaining';
                    badge.textContent = slot.remaining + ' posti';
                    li.appendChild(badge);
                }
                
                list.appendChild(li);
            });
            
            slotsEl.innerHTML = '';
            slotsEl.appendChild(list);
        }
    }

    // Inizializza quando il DOM è pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.FPFront.calendarStandalone = { init: init };
})();
