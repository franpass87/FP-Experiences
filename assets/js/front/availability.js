/**
 * FP Experiences - Frontend Availability Module
 * Gestisce: formatTimeRange, fetchAvailability, prefetchMonth, calendarMap.
 */
(function() {
    'use strict';

    if (!window.FPFront) {
        window.FPFront = {};
    }

    var _config = {};
    var _calendarEl = null;
    var _widget = null;
    var _monthCache = new Map();
    var _calendarMap = new Map();

    function _parseUtc(value) {
        if (!value) return null;
        var str = String(value);
        var hasT = str.indexOf('T') !== -1;
        var hasSpace = str.indexOf(' ') !== -1;
        var hasZone = /[zZ]|[+-]\d{2}:?\d{2}$/.test(str);
        var normalised = str;
        if (hasSpace) normalised = str.replace(' ', 'T');
        if (!hasZone) {
            if (!hasT && !hasSpace) return null;
            normalised = normalised + 'Z';
        }
        var d = new Date(normalised);
        return isNaN(d.getTime()) ? null : d;
    }

    function formatTimeRange(startIso, endIso) {
        try {
            var startDate = _parseUtc(String(startIso));
            var endDate = _parseUtc(String(endIso));
            if (!startDate || !endDate) throw new Error('Invalid date');
            var tz = (_config && _config.timezone) || undefined;
            var opts = { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: tz };
            var fmt = new Intl.DateTimeFormat(undefined, opts);
            return fmt.format(startDate) + ' - ' + fmt.format(endDate);
        } catch (e) {
            // Errore formatTimeRange - fallback a estrazione pattern
            var take = function(v) { var m = String(v).match(/\d{2}:\d{2}/); return (m && m[0]) || ''; };
            var s = take(startIso);
            var ed = take(endIso);
            return (s && ed) ? (s + ' - ' + ed) : 'Slot';
        }
    }

    async function fetchAvailability(date) {
        var experienceId = (_config && _config.experienceId) || 0;
        if (!experienceId || !date) return [];
        try {
            var base = (window.fpExpApiBase && typeof window.fpExpApiBase === 'string')
                ? window.fpExpApiBase
                : (window.wpApiSettings && wpApiSettings.root) || (location.origin + '/wp-json/');
            var root = base.endsWith('/') ? base : base + '/';
            var url = new URL(root + 'fp-exp/v1/availability');
            url.searchParams.set('experience', String(experienceId));
            url.searchParams.set('start', date);
            url.searchParams.set('end', date);
            var res = await fetch(url.toString(), { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            var data = await res.json();
            var slots = Array.isArray(data && data.slots) ? data.slots : [];
            return slots.map(function(s) { return {
                start: s.start || '',
                end: s.end || '',
                label: (s.start && s.end) ? formatTimeRange(s.start, s.end) : undefined
            }; });
        } catch (e) {
            // Errore fetch availability
            throw e;
        }
    }

    function monthKeyOf(dateStr) {
        return (dateStr || '').slice(0, 7);
    }

    async function prefetchMonth(yyyyMm) {
        var experienceId = (_config && _config.experienceId) || 0;
        if (!experienceId || !yyyyMm) return;
        // Rimosso controllo cache per permettere sempre il prefetch quando si cambia mese
        try {
            var base = (window.fpExpApiBase && typeof window.fpExpApiBase === 'string')
                ? window.fpExpApiBase
                : (window.wpApiSettings && wpApiSettings.root) || (location.origin + '/wp-json/');
            var root = base.endsWith('/') ? base : base + '/';
            var start = yyyyMm + '-01';
            var endDate = new Date(start + 'T00:00:00');
            endDate.setMonth(endDate.getMonth() + 1); endDate.setDate(0);
            var end = endDate.toISOString().slice(0,10);
            var url = new URL(root + 'fp-exp/v1/availability');
            url.searchParams.set('experience', String(experienceId));
            url.searchParams.set('start', start);
            url.searchParams.set('end', end);
            var res = await fetch(url.toString(), { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            var data = await res.json();
            var slots = Array.isArray(data && data.slots) ? data.slots : [];
            var dayCount = new Map();
            var daySlots = new Map();
            
            // Raggruppa gli slot per giorno
            slots.forEach(function(s) {
                var d = (s.start || '').slice(0,10);
                dayCount.set(d, (dayCount.get(d) || 0) + 1);
                
                // Aggiungi lo slot alla lista del giorno con label formattata
                if (!daySlots.has(d)) {
                    daySlots.set(d, []);
                }
                daySlots.get(d).push({
                    start: s.start || '',
                    end: s.end || '',
                    label: (s.start && s.end) ? formatTimeRange(s.start, s.end) : undefined,
                    remaining: s.capacity_remaining || 0
                });
            });
            
            // Salva nella cache mensile e nella calendarMap
            _monthCache.set(yyyyMm, dayCount);
            daySlots.forEach(function(slotList, dateKey) {
                _calendarMap.set(dateKey, slotList);
            });
            
            if (_calendarEl) {
                _calendarEl.querySelectorAll('.fp-exp-calendar__day[data-date^="' + yyyyMm + '"]').forEach(function(btn) {
                    var d = btn.getAttribute('data-date');
                    var count = (d && dayCount.get(d)) || 0;
                    btn.dataset.available = count > 0 ? '1' : '0';
                });
            }
        } catch (e) {
            // Prefetch mese fallito - logga l'errore per debug
            console.error('FP Experiences: Prefetch mese fallito per', yyyyMm, e);
            // Non bloccare il rendering del calendario, ma segna il mese come tentato
            _monthCache.set(yyyyMm, new Map());
        }
    }

    function _buildCalendarMap() {
        _calendarMap = new Map();
        var calendar = (_config && _config.calendar) || {};
        Object.keys(calendar).forEach(function(monthKey) {
            var days = calendar[monthKey] && calendar[monthKey].days ? calendar[monthKey].days : {};
            Object.keys(days).forEach(function(dateKey) {
                _calendarMap.set(dateKey, days[dateKey] || []);
            });
        });
    }

    function init(ctx) {
        _config = (ctx && ctx.config) || {};
        _calendarEl = (ctx && ctx.calendarEl) || null;
        _widget = (ctx && ctx.widget) || null;
        _buildCalendarMap();
    }

    function getCalendarMap() {
        return _calendarMap;
    }

    window.FPFront.availability = {
        init: init,
        formatTimeRange: formatTimeRange,
        fetchAvailability: fetchAvailability,
        prefetchMonth: prefetchMonth,
        monthKeyOf: monthKeyOf,
        getCalendarMap: getCalendarMap
    };
})();


