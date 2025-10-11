/* assets/js/front/summary-woo.js */
/**
 * FP Experiences - Woo Summary & Checkout Module
 * Riepilogo prezzi client-side e flusso checkout WooCommerce via REST interni.
 */
(function() {
    'use strict';

    if (!window.FPFront) window.FPFront = {};

    var _ctx = { widget: null, slotsEl: null, config: {} };

    function formatCurrency(amount, currency) {
        try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'EUR' }).format(Number(amount || 0)); } catch (e) { return String(amount || 0); }
    }

    function collectTickets() {
        var map = {};
        document.querySelectorAll('.fp-exp-party-table tbody tr[data-ticket]').forEach(function(row) {
            var slug = row.getAttribute('data-ticket') || '';
            var input = row.querySelector('.fp-exp-quantity__input');
            if (!slug || !input) return;
            var qty = parseInt(input.value, 10) || 0;
            if (qty > 0) map[slug] = qty;
        });
        return map;
    }

    function collectAddons() {
        var map = {};
        document.querySelectorAll('.fp-exp-addons li[data-addon]').forEach(function(li) {
            var slug = li.getAttribute('data-addon') || '';
            var checkbox = li.querySelector('input[type="checkbox"]');
            if (!slug || !checkbox) return;
            if (checkbox.checked) map[slug] = 1;
        });
        return map;
    }

    function hasSelectedSlot() {
        return !!(_ctx.slotsEl && _ctx.slotsEl.querySelector('.fp-exp-slots__item.is-selected'));
    }

    function init(ctx) {
        _ctx = Object.assign(_ctx, ctx || {});
        var summary = document.querySelector('.fp-exp-summary');
        if (!summary) return;

        var statusEl = summary.querySelector('[data-fp-summary-status]');
        var bodyEl = summary.querySelector('[data-fp-summary-body]');
        var linesEl = summary.querySelector('[data-fp-summary-lines]');
        var adjustmentsEl = summary.querySelector('[data-fp-summary-adjustments]');
        var totalRowEl = summary.querySelector('[data-fp-summary-total-row]');
        var totalEl = summary.querySelector('[data-fp-summary-total]');
        var ctaBtn = document.querySelector('.fp-exp-summary__cta');

        var loadingLabel = summary.getAttribute('data-loading-label') || 'Aggiornamento prezzo…';
        var errorLabel = summary.getAttribute('data-error-label') || 'Impossibile aggiornare il prezzo. Riprova.';
        var emptyLabel = summary.getAttribute('data-empty-label') || 'Seleziona i biglietti per vedere il riepilogo';
        var currency = (_ctx.config && _ctx.config.currency) || 'EUR';

        function setStatus(text) {
            if (statusEl) {
                statusEl.hidden = false;
                var p = statusEl.querySelector('.fp-exp-summary__message');
                if (p) p.textContent = text || emptyLabel;
            }
            if (bodyEl) bodyEl.hidden = true;
        }

        function showBody() { if (statusEl) statusEl.hidden = true; if (bodyEl) bodyEl.hidden = false; }

        function updateWooCommerceCtaState() {
            if (!ctaBtn) return;
            var tickets = collectTickets();
            var anyTicket = tickets && Object.keys(tickets).length > 0;
            var slotOk = hasSelectedSlot();
            ctaBtn.disabled = !(anyTicket && slotOk);
            if (!anyTicket) ctaBtn.textContent = 'Seleziona almeno 1 biglietto';
            else if (!slotOk) ctaBtn.textContent = 'Seleziona data e orario';
            else ctaBtn.textContent = 'Procedi al pagamento';
        }

        function updatePriceSummary() {
            var tickets = collectTickets();
            var addons = collectAddons();
            var selectedSlot = _ctx.slotsEl && _ctx.slotsEl.querySelector('.fp-exp-slots__item.is-selected');
            if (!tickets || Object.keys(tickets).length === 0) { setStatus(emptyLabel); return; }
            if (!selectedSlot) { setStatus('Seleziona data e orario per vedere il prezzo'); return; }

            var total = 0;
            Object.entries(tickets).forEach(function(entry) {
                var slug = entry[0]; var qty = entry[1];
                var priceEl = document.querySelector('tr[data-ticket="' + slug + '"] .fp-exp-ticket__price[data-price]');
                var price = priceEl ? parseFloat(priceEl.getAttribute('data-price') || '0') : 0;
                total += price * qty;
            });
            Object.entries(addons).forEach(function(entry) {
                var slug = entry[0]; var qty = entry[1];
                var priceEl = document.querySelector('li[data-addon="' + slug + '"] .fp-exp-addon__price[data-price]');
                var price = priceEl ? parseFloat(priceEl.getAttribute('data-price') || '0') : 0;
                total += price * qty;
            });

            if (linesEl) {
                linesEl.innerHTML = '';
                Object.entries(tickets).forEach(function(entry) {
                    var slug = entry[0]; var qty = entry[1]; if (qty <= 0) return;
                    var priceEl = document.querySelector('tr[data-ticket="' + slug + '"] .fp-exp-ticket__price[data-price]');
                    var price = priceEl ? parseFloat(priceEl.getAttribute('data-price') || '0') : 0;
                    var labelEl = document.querySelector('tr[data-ticket="' + slug + '"] .fp-exp-ticket__label');
                    var ticketLabel = labelEl ? labelEl.textContent.trim() : ('Biglietto ' + slug);
                    var li = document.createElement('li'); li.className = 'fp-exp-summary__line';
                    var label = document.createElement('span'); label.className = 'fp-exp-summary__line-label'; label.textContent = ticketLabel + ' × ' + qty;
                    var amount = document.createElement('span'); amount.className = 'fp-exp-summary__line-amount'; amount.textContent = formatCurrency(price * qty, currency);
                    li.appendChild(label); li.appendChild(amount); linesEl.appendChild(li);
                });
                Object.entries(addons).forEach(function(entry) {
                    var slug = entry[0]; var qty = entry[1]; if (qty <= 0) return;
                    var priceEl = document.querySelector('li[data-addon="' + slug + '"] .fp-exp-addon__price[data-price]');
                    var price = priceEl ? parseFloat(priceEl.getAttribute('data-price') || '0') : 0;
                    var labelEl = document.querySelector('li[data-addon="' + slug + '"] .fp-exp-addon__label');
                    var addonLabel = labelEl ? labelEl.textContent.trim() : ('Extra ' + slug);
                    var li = document.createElement('li'); li.className = 'fp-exp-summary__line';
                    var label = document.createElement('span'); label.className = 'fp-exp-summary__line-label'; label.textContent = addonLabel + ' × ' + qty;
                    var amount = document.createElement('span'); amount.className = 'fp-exp-summary__line-amount'; amount.textContent = formatCurrency(price * qty, currency);
                    li.appendChild(label); li.appendChild(amount); linesEl.appendChild(li);
                });
            }
            if (totalEl) totalEl.textContent = formatCurrency(total, currency);
            if (totalRowEl) totalRowEl.hidden = false;
            showBody();
        }

        ctaBtn && ctaBtn.addEventListener('click', function() { /* gestito da front.js checkout flow */ });

        _ctx.widget.addEventListener('change', updateWooCommerceCtaState);
        _ctx.widget.addEventListener('input', updateWooCommerceCtaState);
        if (_ctx.slotsEl) { _ctx.slotsEl.addEventListener('click', updateWooCommerceCtaState); }

        _ctx.widget.addEventListener('change', function(ev) { if (ev.target && ev.target.closest('.fp-exp-quantity__input')) updatePriceSummary(); if (ev.target && ev.target.matches('.fp-exp-addons input[type="checkbox"]')) updatePriceSummary(); });
        _ctx.widget.addEventListener('input', function(ev) { if (ev.target && ev.target.closest('.fp-exp-quantity__input')) updatePriceSummary(); });
        if (_ctx.slotsEl) { _ctx.slotsEl.addEventListener('click', function(ev) { if (ev.target && ev.target.closest('.fp-exp-slots__item')) updatePriceSummary(); }); }

        updateWooCommerceCtaState();
        updatePriceSummary();
    }

    window.FPFront.summaryWoo = { init: init };
})();




