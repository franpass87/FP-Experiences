/**
 * FP Experiences - Frontend Calendar Module
 * Mostra/nasconde calendario, navigazione mese e prefetch.
 */
(function() {
    'use strict';

    if (!window.FPFront) window.FPFront = {};

    var _ctx = { calendarEl: null, widget: null };

    function showCalendarIfConfigured() {
        if (!_ctx.calendarEl || !_ctx.widget) return;
        var shouldShow = (_ctx.widget.getAttribute('data-config') || '').indexOf('"show_calendar":true') !== -1 || _ctx.calendarEl.getAttribute('data-show-calendar') === '1';
        if (shouldShow) { _ctx.calendarEl.hidden = false; }
    }

    function setupMonthNavigation() {
        if (!_ctx.calendarEl) return;
        if (!_ctx.calendarEl.querySelector('.fp-exp-calendar__weekdays')) return;
        var toolbar = _ctx.calendarEl.querySelector('.fp-exp-calendar__toolbar');
        if (!toolbar) {
            toolbar = document.createElement('div');
            toolbar.className = 'fp-exp-calendar__toolbar';
            var prev = document.createElement('button'); prev.type = 'button'; prev.className = 'fp-exp-calendar__nav-prev'; prev.setAttribute('aria-label', 'Mese precedente'); prev.textContent = '‹';
            var next = document.createElement('button'); next.type = 'button'; next.className = 'fp-exp-calendar__nav-next'; next.setAttribute('aria-label', 'Mese successivo'); next.textContent = '›';
            toolbar.appendChild(prev); toolbar.appendChild(next);
            _ctx.calendarEl.insertBefore(toolbar, _ctx.calendarEl.firstChild);

            var navigate = async function(delta) {
                var firstBtn = _ctx.calendarEl.querySelector('.fp-exp-calendar__day');
                if (!firstBtn) return;
                var anyDate = firstBtn.getAttribute('data-date');
                if (!anyDate) return;
                var dt = new Date(anyDate + 'T00:00:00');
                dt.setMonth(dt.getMonth() + delta);
                var yyyyMm = dt.toISOString().slice(0,7);
                if (window.FPFront.availability && window.FPFront.availability.prefetchMonth) {
                    await window.FPFront.availability.prefetchMonth(yyyyMm);
                }
            };

            prev.addEventListener('click', function() { navigate(-1); });
            next.addEventListener('click', function() { navigate(1); });
        }
    }

    function prefetchInitial() {
        if (!_ctx.calendarEl) return;
        var firstVisible = _ctx.calendarEl.querySelector('.fp-exp-calendar__day');
        if (firstVisible) {
            var d = firstVisible.getAttribute('data-date');
            if (d && window.FPFront.availability && window.FPFront.availability.monthKeyOf && window.FPFront.availability.prefetchMonth) {
                window.FPFront.availability.prefetchMonth(window.FPFront.availability.monthKeyOf(d));
            }
        }
    }

    function init(ctx) {
        _ctx = Object.assign(_ctx, ctx || {});
        showCalendarIfConfigured();
        setupMonthNavigation();
        prefetchInitial();
    }

    window.FPFront.calendar = { init: init };
})();


