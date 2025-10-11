/**
 * FP Experiences - Frontend Quantity Controls Module
 * Gestisce i pulsanti +/− e dispatch degli eventi.
 */
(function() {
    'use strict';

    if (!window.FPFront) window.FPFront = {};

    var _ctx = { widget: null };

    function init(ctx) {
        _ctx = Object.assign(_ctx, ctx || {});
        if (!_ctx.widget) return;
        _ctx.widget.addEventListener('click', function(ev) {
            var btn = ev.target && ev.target.closest('.fp-exp-quantity__control');
            if (!btn) return;
            var container = btn.closest('.fp-exp-quantity'); if (!container) return;
            var input = container.querySelector('.fp-exp-quantity__input'); if (!input) return;

            var action = btn.getAttribute('data-action');
            var rawMin = (input.getAttribute('min') || '').trim();
            var rawMax = (input.getAttribute('max') || '').trim();
            var min = rawMin === '' ? 0 : parseInt(rawMin, 10);
            var max = rawMax === '' ? Number.POSITIVE_INFINITY : parseInt(rawMax, 10);
            var current = Number.isFinite(parseInt(input.value, 10)) ? parseInt(input.value, 10) : 0;

            var next = current;
            if (action === 'increase') next = Math.min(max, current + 1);
            else if (action === 'decrease') next = Math.max(min, current - 1);

            if (next !== current) {
                input.value = String(next);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    window.FPFront.quantity = { init: init };
})();


