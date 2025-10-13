/**
 * FP Experiences - RTB Summary Module
 * Aggiorna il riepilogo/preventivo lato client usando endpoint RTB.
 */
(function() {
    'use strict';

    if (!window.FPFront) window.FPFront = {};

    var _ctx = {
        widget: null,
        slotsEl: null,
        config: {},
        rtbForm: null,
        startInput: null,
        endInput: null,
        ticketsHidden: null,
        addonsHidden: null,
        summaryEl: null,
        ctaBtn: null
    };

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
        if (_ctx.startInput && _ctx.endInput) {
            return Boolean((_ctx.startInput.value || '').trim() && (_ctx.endInput.value || '').trim());
        }
        return !!(_ctx.slotsEl && _ctx.slotsEl.querySelector('.fp-exp-slots__item.is-selected'));
    }

    function init(ctx) {
        _ctx = Object.assign(_ctx, ctx || {});
        var summary = document.querySelector('.fp-exp-summary');
        if (!summary) return;

        _ctx.summaryEl = summary;
        _ctx.rtbForm = document.querySelector('form.fp-exp-rtb-form');
        _ctx.startInput = _ctx.rtbForm ? _ctx.rtbForm.querySelector('input[name="start"]') : null;
        _ctx.endInput = _ctx.rtbForm ? _ctx.rtbForm.querySelector('input[name="end"]') : null;
        _ctx.ticketsHidden = _ctx.rtbForm ? _ctx.rtbForm.querySelector('input[name="tickets"]') : null;
        _ctx.addonsHidden = _ctx.rtbForm ? _ctx.rtbForm.querySelector('input[name="addons"]') : null;
        _ctx.ctaBtn = document.querySelector('.fp-exp-summary__cta');

        var statusEl = summary.querySelector('[data-fp-summary-status]');
        var bodyEl = summary.querySelector('[data-fp-summary-body]');
        var linesEl = summary.querySelector('[data-fp-summary-lines]');
        var adjustmentsEl = summary.querySelector('[data-fp-summary-adjustments]');
        var totalRowEl = summary.querySelector('[data-fp-summary-total-row]');
        var totalEl = summary.querySelector('[data-fp-summary-total]');
        var disclaimerEl = summary.querySelector('.fp-exp-summary__disclaimer');

        var loadingLabel = summary.getAttribute('data-loading-label') || 'Aggiornamento prezzo…';
        var errorLabel = summary.getAttribute('data-error-label') || 'Impossibile aggiornare il prezzo. Riprova.';
        var emptyLabel = summary.getAttribute('data-empty-label') || 'Seleziona i biglietti per vedere il riepilogo';

        var debounceTimer = null;

        function setStatus(text) {
            if (statusEl) {
                statusEl.hidden = false;
                var p = statusEl.querySelector('.fp-exp-summary__message');
                if (p) p.textContent = text || emptyLabel;
            }
            if (bodyEl) bodyEl.hidden = true;
        }

        function showBody() {
            if (statusEl) statusEl.hidden = true;
            if (bodyEl) bodyEl.hidden = false;
        }

        function updateCtaState() {
            if (!_ctx.ctaBtn) return;
            var tickets = collectTickets();
            var anyTicket = tickets && Object.keys(tickets).length > 0;
            var slotOk = hasSelectedSlot();
            _ctx.ctaBtn.disabled = !(anyTicket && slotOk);
        }

        var experienceId = (_ctx.config && _ctx.config.experienceId) || 0;
        var quoteUrl = (function() {
            var base = (window.fpExpApiBase && typeof window.fpExpApiBase === 'string')
                ? window.fpExpApiBase
                : (window.wpApiSettings && wpApiSettings.root) || (location.origin + '/wp-json/');
            var root = base.endsWith('/') ? base : base + '/';
            return root + 'fp-exp/v1/rtb/quote';
        })();

        function requestQuote() {
            if (!experienceId) {
                setStatus(emptyLabel);
                updateCtaState();
                return;
            }
            var tickets = collectTickets();
            var addons = collectAddons();
            if (_ctx.ticketsHidden) _ctx.ticketsHidden.value = JSON.stringify(tickets);
            if (_ctx.addonsHidden) _ctx.addonsHidden.value = JSON.stringify(addons);
            if (!tickets || Object.keys(tickets).length === 0) {
                setStatus(emptyLabel);
                updateCtaState();
                return;
            }

            setStatus(loadingLabel);

            var payload = {
                nonce: (_ctx.config && (_ctx.config.rtbNonce || _ctx.config.nonce)) || (_ctx.rtbForm ? _ctx.rtbForm.getAttribute('data-nonce') : ''),
                experience_id: experienceId,
                slot_id: 0,
                start: _ctx.startInput ? _ctx.startInput.value : '',
                end: _ctx.endInput ? _ctx.endInput.value : '',
                tickets: tickets,
                addons: addons
            };

            var quoteHeaders = { 'Content-Type': 'application/json' };
            if (typeof fpExpConfig !== 'undefined' && fpExpConfig.restNonce) {
                quoteHeaders['X-WP-Nonce'] = fpExpConfig.restNonce;
            }
            
            fetch(quoteUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: quoteHeaders,
                body: JSON.stringify(payload)
            })
            .then(function(res) { if (!res.ok) { throw new Error('HTTP ' + res.status + ': ' + res.statusText); } return res.json(); })
            .then(function(data) {
                if (!data || data.success !== true || !data.breakdown) {
                    // Quote response invalid
                    setStatus(errorLabel);
                    return;
                }
                var breakdown = data.breakdown;
                if (linesEl) {
                    linesEl.innerHTML = '';
                    var renderLine = function(line) {
                        var li = document.createElement('li');
                        li.className = 'fp-exp-summary__line';
                        var label = document.createElement('span');
                        label.className = 'fp-exp-summary__line-label';
                        label.textContent = String(line.label || '') + ' × ' + String(line.quantity || 0);
                        var amount = document.createElement('span');
                        amount.className = 'fp-exp-summary__line-amount';
                        amount.textContent = formatCurrency(line.line_total, breakdown.currency);
                        li.appendChild(label);
                        li.appendChild(amount);
                        return li;
                    };
                    (Array.isArray(breakdown.tickets) ? breakdown.tickets : []).forEach(function(l) { linesEl.appendChild(renderLine(l)); });
                    (Array.isArray(breakdown.addons) ? breakdown.addons : []).forEach(function(l) { linesEl.appendChild(renderLine(l)); });
                }
                if (adjustmentsEl) {
                    var list = Array.isArray(breakdown.adjustments) ? breakdown.adjustments : [];
                    if (list.length === 0) {
                        adjustmentsEl.hidden = true;
                        adjustmentsEl.innerHTML = '';
                    } else {
                        adjustmentsEl.hidden = false;
                        adjustmentsEl.innerHTML = '';
                        list.forEach(function(adj) {
                            var li = document.createElement('li');
                            li.className = 'fp-exp-summary__adjustment';
                            var label = document.createElement('span');
                            label.className = 'fp-exp-summary__adjustment-label';
                            label.textContent = adj.label || '';
                            var amount = document.createElement('span');
                            amount.className = 'fp-exp-summary__adjustment-amount';
                            amount.textContent = formatCurrency(adj.amount, breakdown.currency);
                            li.appendChild(label);
                            li.appendChild(amount);
                            adjustmentsEl.appendChild(li);
                        });
                    }
                }
                if (totalEl) totalEl.textContent = formatCurrency(breakdown.total, breakdown.currency);
                if (totalRowEl) totalRowEl.hidden = false;
                if (disclaimerEl) {
                    var taxLabel = summary.getAttribute('data-tax-label') || '';
                    if (taxLabel) { disclaimerEl.textContent = taxLabel; disclaimerEl.hidden = false; }
                }
                showBody();
                updateCtaState();
            })
            .catch(function(error) {
                // Quote request failed
                setStatus(errorLabel);
                updateCtaState();
            });
        }

        function debounceQuote() {
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(requestQuote, 300);
        }

        _ctx.widget.addEventListener('change', function(ev) {
            if (ev.target && ev.target.closest('.fp-exp-quantity__input')) { debounceQuote(); updateCtaState(); }
            if (ev.target && ev.target.matches('.fp-exp-addons input[type="checkbox"]')) { debounceQuote(); }
        });
        _ctx.widget.addEventListener('input', function(ev) {
            if (ev.target && ev.target.closest('.fp-exp-quantity__input')) { debounceQuote(); updateCtaState(); }
        });
        if (_ctx.slotsEl) {
            _ctx.slotsEl.addEventListener('click', function(ev) {
                if (ev.target && ev.target.closest('.fp-exp-slots__item')) { debounceQuote(); updateCtaState(); }
            });
        }
        updateCtaState();
    }

    window.FPFront.summaryRtb = { init: init };
})();


