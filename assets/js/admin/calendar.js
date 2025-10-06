/**
 * Admin Calendar Module
 */
(function () {
    'use strict';

    function initCalendarApp() {
        const calendarContainer = document.querySelector('[data-fp-calendar-app]');
        if (!calendarContainer) return;

        // Inizializza calendario se necessario
        if (window.fpExpCalendar) {
            window.fpExpCalendar.init(calendarContainer);
        }
    }

    function initRecurrence(root) {
        const recurrenceElements = root.querySelectorAll('[data-fp-recurrence]');
        if (!recurrenceElements.length) return;

        recurrenceElements.forEach(element => {
            const typeSelect = element.querySelector('[data-fp-recurrence-type]');
            const timeSets = element.querySelectorAll('[data-fp-recurrence-time-set]');
            
            if (typeSelect) {
                typeSelect.addEventListener('change', function() {
                    updateRecurrenceDisplay(element, this.value);
                });
                
                // Inizializza display
                updateRecurrenceDisplay(element, typeSelect.value);
            }
            
            // Inizializza time sets
            timeSets.forEach(timeSet => {
                initRecurrenceTimeSets(timeSet);
            });
        });
    }

    function updateRecurrenceDisplay(element, type) {
        const timeSets = element.querySelectorAll('[data-fp-recurrence-time-set]');
        const summary = element.querySelector('[data-fp-recurrence-summary]');
        
        // Mostra/nascondi time sets in base al tipo
        timeSets.forEach(timeSet => {
            const timeSetType = timeSet.getAttribute('data-fp-recurrence-time-set');
            if (timeSetType === type) {
                timeSet.style.display = 'block';
            } else {
                timeSet.style.display = 'none';
            }
        });
        
        // Aggiorna summary
        if (summary) {
            updateRecurrenceSummary(element, type);
        }
    }

    function initRecurrenceTimeSets(scope) {
        const addButton = scope.querySelector('[data-fp-recurrence-add-time]');
        const list = scope.querySelector('[data-fp-recurrence-times]');
        const template = scope.querySelector('[data-fp-recurrence-time-template]');
        
        if (!addButton || !list || !template) return;
        
        addButton.addEventListener('click', function(e) {
            e.preventDefault();
            addRecurrenceTime(list, template);
        });
        
        // Gestione rimozione time
        list.addEventListener('click', function(e) {
            if (e.target.matches('[data-fp-recurrence-remove-time]')) {
                e.preventDefault();
                const item = e.target.closest('[data-fp-recurrence-time]');
                if (item) {
                    removeRecurrenceTime(item);
                }
            }
        });
    }

    function addRecurrenceTime(list, template) {
        const clone = template.content.cloneNode(true);
        const item = clone.querySelector('[data-fp-recurrence-time]');
        
        if (item) {
            list.appendChild(item);
        }
    }

    function removeRecurrenceTime(item) {
        item.remove();
    }

    function updateRecurrenceSummary(element, type) {
        const summary = element.querySelector('[data-fp-recurrence-summary]');
        if (!summary) return;
        
        // Logica per aggiornare il summary in base al tipo e ai valori
        // Questa è una versione semplificata
        summary.textContent = `Recurrence type: ${type}`;
    }

    // Esporta funzioni
    window.fpExpAdmin = window.fpExpAdmin || {};
    window.fpExpAdmin.initCalendarApp = initCalendarApp;
    window.fpExpAdmin.initRecurrence = initRecurrence;

})();
