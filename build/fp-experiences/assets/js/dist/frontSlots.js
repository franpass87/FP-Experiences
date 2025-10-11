/* assets/js/front/slots.js */
/**
 * FP Experiences - Frontend Slots Module
 * Gestisce rendering degli slot e selezione.
 */
(function() {
    'use strict';

    if (!window.FPFront) {
        window.FPFront = {};
    }

    var _slotsEl = null;

    function renderSlots(items) {
        if (!_slotsEl) return;
        var emptyLabel = _slotsEl.getAttribute('data-empty-label') || '';
        if (!items || items.length === 0) {
            _slotsEl.innerHTML = '<p class="fp-exp-slots__placeholder">' + (emptyLabel || 'Nessuna fascia disponibile') + '</p>';
            return;
        }
        var list = document.createElement('ul');
        list.className = 'fp-exp-slots__list';
        items.forEach(function(slot) {
            var li = document.createElement('li');
            li.className = 'fp-exp-slots__item';
            var startVal = (slot && (slot.start || slot.start_iso)) || '';
            var endVal = (slot && (slot.end || slot.end_iso)) || '';
            var label = (slot && slot.label) || '';
            li.textContent = label || 'Slot';
            li.setAttribute('data-start', String(startVal));
            li.setAttribute('data-end', String(endVal));
            list.appendChild(li);
        });
        _slotsEl.innerHTML = '';
        _slotsEl.appendChild(list);
    }

    function clearSelection() {
        if (!_slotsEl) return;
        var prev = _slotsEl.querySelectorAll('.fp-exp-slots__item.is-selected');
        prev.forEach(function(el) { el.classList.remove('is-selected'); });
    }

    function getSelectedSlot() {
        if (!_slotsEl) return null;
        return _slotsEl.querySelector('.fp-exp-slots__item.is-selected');
    }

    function init(ctx) {
        _slotsEl = (ctx && ctx.slotsEl) || null;
    }

    window.FPFront.slots = {
        init: init,
        renderSlots: renderSlots,
        clearSelection: clearSelection,
        getSelectedSlot: getSelectedSlot
    };
})();




