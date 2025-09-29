(function () {
    'use strict';

    const { __: i18n__, _n: i18n_n } = (window.wp && window.wp.i18n) || {
        __: (text) => text,
        _n: (single, plural, number) => (number === 1 ? single : plural),
    };

    const pluginConfig = window.fpExpConfig || {};
    const trackingConfig = pluginConfig.tracking || {};
    const firedEvents = [];
    const defaultCurrency = pluginConfig.currency || 'EUR';
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function buildRestHeaders(base) {
        const headers = Object.assign({ 'Content-Type': 'application/json' }, base || {});
        if (pluginConfig.restNonce) {
            headers['X-WP-Nonce'] = pluginConfig.restNonce;
        }

        return headers;
    }

    function getFocusableElements(container) {
        if (!container) {
            return [];
        }

        return Array.from(container.querySelectorAll(focusableSelector)).filter((element) => {
            if (!(element instanceof HTMLElement)) {
                return false;
            }
            if (element.hasAttribute('disabled') || element.getAttribute('aria-hidden') === 'true') {
                return false;
            }
            if (element.closest('[hidden]')) {
                return false;
            }

            const rect = element.getBoundingClientRect();
            return rect.width > 0 || rect.height > 0;
        });
    }

    function activateFocusTrap(container) {
        const handleKeydown = (event) => {
            if (event.key !== 'Tab') {
                return;
            }

            const focusable = getFocusableElements(container);
            if (!focusable.length) {
                event.preventDefault();
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            const active = document.activeElement;

            if (event.shiftKey && active === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        };

        const enforceFocus = (event) => {
            if (!container.contains(event.target)) {
                const focusable = getFocusableElements(container);
                if (focusable.length) {
                    focusable[0].focus();
                }
            }
        };

        document.addEventListener('keydown', handleKeydown, true);
        document.addEventListener('focusin', enforceFocus, true);

        return function cleanup() {
            document.removeEventListener('keydown', handleKeydown, true);
            document.removeEventListener('focusin', enforceFocus, true);
        };
    }

    function showErrorSummary(container, errors) {
        if (!container) {
            return;
        }

        container.innerHTML = '';

        if (!errors.length) {
            container.hidden = true;
            return;
        }

        const intro = document.createElement('p');
        intro.className = 'fp-exp-error-summary__intro';
        intro.textContent = container.getAttribute('data-intro') || 'Please review the highlighted fields.';
        container.appendChild(intro);

        const list = document.createElement('ul');
        list.className = 'fp-exp-error-summary__list';

        errors.forEach((error) => {
            const item = document.createElement('li');
            if (error.id) {
                const link = document.createElement('a');
                link.href = `#${error.id}`;
                link.textContent = error.message;
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    const target = container.ownerDocument.getElementById(error.id);
                    if (target && typeof target.focus === 'function') {
                        target.focus();
                    }
                });
                item.appendChild(link);
            } else {
                item.textContent = error.message;
            }
            list.appendChild(item);
        });

        container.appendChild(list);
        container.hidden = false;
        container.focus();
    }

    function hideErrorSummary(container) {
        if (!container) {
            return;
        }

        container.hidden = true;
        container.innerHTML = '';
    }

    function markFieldInvalid(field) {
        if (!field) {
            return;
        }

        field.setAttribute('aria-invalid', 'true');
        field.classList.add('is-invalid');
    }

    function clearFieldInvalidState(field) {
        if (!field) {
            return;
        }

        field.removeAttribute('aria-invalid');
        field.classList.remove('is-invalid');
    }

    function validateEmail(value) {
        if (!value) {
            return false;
        }

        const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return pattern.test(value);
    }

    const currencyFormatters = {};

    function formatMoney(value, currency) {
        const amount = Number(value);
        if (Number.isNaN(amount)) {
            return '';
        }

        const locale = document.documentElement.lang || 'en';
        const code = currency || defaultCurrency;
        const key = `${locale}|${code}`;

        if (!currencyFormatters[key]) {
            try {
                currencyFormatters[key] = new Intl.NumberFormat(locale, {
                    style: 'currency',
                    currency: code,
                    currencyDisplay: 'symbol',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            } catch (error) {
                currencyFormatters[key] = null;
            }
        }

        if (currencyFormatters[key]) {
            return currencyFormatters[key].format(amount);
        }

        return `${code} ${amount.toFixed(2)}`;
    }

    function describeSlot(slot) {
        if (!slot) {
            return null;
        }

        const locale = document.documentElement.lang || 'en';

        if (slot.start_iso) {
            const date = new Date(slot.start_iso);
            if (!Number.isNaN(date.getTime())) {
                try {
                    const dateLabel = new Intl.DateTimeFormat(locale, {
                        weekday: 'short',
                        month: 'short',
                        day: 'numeric',
                    }).format(date);
                    const timeLabel = new Intl.DateTimeFormat(locale, {
                        hour: '2-digit',
                        minute: '2-digit',
                    }).format(date);
                    return {
                        label: timeLabel,
                        detail: dateLabel,
                    };
                } catch (error) {
                    // Fallback to raw values below.
                }
            }
        }

        const label = slot.time || slot.start || '';
        const detail = slot.start || '';

        return {
            label,
            detail,
        };
    }

    function validateRtbForm(form) {
        const errors = [];
        if (!form) {
            return errors;
        }

        const messages = {
            name: form.getAttribute('data-error-name') || 'Enter your name.',
            email: form.getAttribute('data-error-email') || 'Enter your email address.',
            emailFormat: form.getAttribute('data-error-email-format') || 'Enter a valid email address.',
            privacy: form.getAttribute('data-error-privacy') || 'Accept the privacy policy to continue.',
        };

        const nameField = form.querySelector('input[name="name"]');
        if (nameField) {
            if (!nameField.value.trim()) {
                markFieldInvalid(nameField);
                errors.push({ id: nameField.id, message: messages.name });
            }
        }

        const emailField = form.querySelector('input[name="email"]');
        if (emailField) {
            const value = emailField.value.trim();
            if (!value) {
                markFieldInvalid(emailField);
                errors.push({ id: emailField.id, message: messages.email });
            } else if (!validateEmail(value)) {
                markFieldInvalid(emailField);
                errors.push({ id: emailField.id, message: messages.emailFormat });
            }
        }

        const privacyField = form.querySelector('input[name="consent_privacy"]');
        if (privacyField && !privacyField.checked) {
            markFieldInvalid(privacyField);
            errors.push({ id: privacyField.id, message: messages.privacy });
        }

        return errors;
    }

    function setupWidgetDialog(container, behavior) {
        if (!behavior || !behavior.sticky) {
            return;
        }

        const body = container.querySelector('.fp-exp-widget__body');
        const openButton = container.querySelector('[data-fp-widget-open]');
        const closeButton = container.querySelector('[data-fp-widget-close]');

        if (!body || !openButton || !closeButton) {
            return;
        }

        let cleanup = null;
        let lastFocused = null;

        const openDialog = () => {
            if (container.classList.contains('is-open')) {
                return;
            }

            lastFocused = document.activeElement;
            container.classList.add('is-open');
            container.setAttribute('aria-expanded', 'true');
            openButton.setAttribute('aria-expanded', 'true');
            body.removeAttribute('hidden');
            cleanup = activateFocusTrap(body);

            const focusable = getFocusableElements(body);
            if (focusable.length) {
                focusable[0].focus();
            }
        };

        const closeDialog = () => {
            container.classList.remove('is-open');
            container.setAttribute('aria-expanded', 'false');
            openButton.setAttribute('aria-expanded', 'false');
            body.setAttribute('hidden', 'hidden');
            if (cleanup) {
                cleanup();
                cleanup = null;
            }

            const fallback = openButton;
            const target = lastFocused instanceof HTMLElement ? lastFocused : fallback;
            if (target && typeof target.focus === 'function') {
                target.focus();
            }
        };

        openButton.addEventListener('click', (event) => {
            event.preventDefault();
            if (container.classList.contains('is-open')) {
                closeDialog();
            } else {
                openDialog();
            }
        });

        closeButton.addEventListener('click', (event) => {
            event.preventDefault();
            closeDialog();
        });

        container.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && container.classList.contains('is-open')) {
                event.preventDefault();
                closeDialog();
            }
        });

        body.setAttribute('hidden', 'hidden');
        container.setAttribute('aria-expanded', 'false');
        openButton.setAttribute('aria-expanded', 'false');
    }

    function setCookie(name, value, days) {
        const expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = `${name}=${value};path=/;expires=${expires};SameSite=Lax`;
    }

    function hasCookie(name) {
        return document.cookie.split(';').some((part) => part.trim().startsWith(`${name}=`));
    }

    function captureUtm() {
        if (hasCookie('fp_exp_utm')) {
            return;
        }

        const params = new URLSearchParams(window.location.search);
        const keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'msclkid'];
        const data = {};

        keys.forEach((key) => {
            const value = params.get(key);
            if (value) {
                data[key] = value;
            }
        });

        if (Object.keys(data).length) {
            setCookie('fp_exp_utm', encodeURIComponent(JSON.stringify(data)), 90);
        }
    }

    function setupMeetingPoints() {
        const containers = document.querySelectorAll('[data-fp-meeting-point]');
        if (!containers.length) {
            return;
        }

        containers.forEach((container) => {
            const link = container.querySelector('[data-fp-map-link]');
            if (!(link instanceof HTMLAnchorElement)) {
                return;
            }

            const lat = (container.getAttribute('data-lat') || '').trim();
            const lng = (container.getAttribute('data-lng') || '').trim();
            const address = (container.getAttribute('data-address') || '').trim();

            let query = '';
            if (lat && lng) {
                query = `${lat},${lng}`;
            } else if (address) {
                query = address;
            }

            if (!query) {
                link.removeAttribute('href');
                link.classList.add('is-disabled');
                link.setAttribute('aria-disabled', 'true');
                return;
            }

            const url = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
            link.href = url;
        });
    }

    function setupExperiencePages() {
        document.querySelectorAll('[data-fp-shortcode="experience"]').forEach((page) => {
            setupExperienceScroll(page);
            setupExperienceAccordion(page);
            setupExperienceSticky(page);
        });
    }

    function setupExperienceScroll(page) {
        const triggers = page.querySelectorAll('[data-fp-scroll]');

        if (!triggers.length) {
            return;
        }

        triggers.forEach((trigger) => {
            trigger.addEventListener('click', (event) => {
                const targetKey = trigger.getAttribute('data-fp-scroll');
                if (!targetKey) {
                    return;
                }

                let target = null;
                if (targetKey === 'widget') {
                    target = page.querySelector('#fp-exp-widget');
                } else {
                    target = page.querySelector(`[data-fp-section="${targetKey}"]`);
                }

                if (!target) {
                    return;
                }

                event.preventDefault();

                const offset = target.getBoundingClientRect().top + window.scrollY - 80;
                window.scrollTo({ top: Math.max(0, offset), behavior: 'smooth' });
            });
        });
    }

    function setupExperienceAccordion(page) {
        const accordions = page.querySelectorAll('[data-fp-accordion]');

        accordions.forEach((accordion) => {
            const triggers = Array.from(accordion.querySelectorAll('[data-fp-accordion-trigger]'));

            triggers.forEach((trigger, index) => {
                const panelId = trigger.getAttribute('aria-controls') || '';
                const panel = panelId ? document.getElementById(panelId) : null;

                trigger.addEventListener('click', () => {
                    toggleAccordionItem(trigger, panel, triggers);
                });

                trigger.addEventListener('keydown', (event) => {
                    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        const direction = event.key === 'ArrowDown' ? 1 : -1;
                        const nextIndex = (index + direction + triggers.length) % triggers.length;
                        triggers[nextIndex].focus();
                    } else if (event.key === 'Home') {
                        event.preventDefault();
                        triggers[0].focus();
                    } else if (event.key === 'End') {
                        event.preventDefault();
                        triggers[triggers.length - 1].focus();
                    }
                });
            });
        });
    }

    function toggleAccordionItem(trigger, panel, triggers) {
        const expanded = trigger.getAttribute('aria-expanded') === 'true';
        const willExpand = !expanded;

        triggers.forEach((otherTrigger) => {
            if (otherTrigger === trigger) {
                return;
            }

            const otherPanelId = otherTrigger.getAttribute('aria-controls') || '';
            const otherPanel = otherPanelId ? document.getElementById(otherPanelId) : null;
            otherTrigger.setAttribute('aria-expanded', 'false');
            if (otherPanel) {
                otherPanel.hidden = true;
            }
        });

        trigger.setAttribute('aria-expanded', willExpand ? 'true' : 'false');
        if (panel) {
            panel.hidden = !willExpand;
        }
    }

    function setupExperienceSticky(page) {
        const stickyBar = page.querySelector('[data-fp-sticky-bar]');
        const widget = page.querySelector('#fp-exp-widget');

        if (!stickyBar || !widget) {
            return;
        }

        if (typeof window.IntersectionObserver !== 'function') {
            stickyBar.classList.remove('is-hidden');
            return;
        }

        const mediaQuery = window.matchMedia('(min-width: 1024px)');
        stickyBar.classList.add('is-hidden');

        const observer = new IntersectionObserver((entries) => {
            const entry = entries[0];
            if (!entry) {
                return;
            }

            if (mediaQuery.matches) {
                stickyBar.classList.add('is-hidden');
                return;
            }

            if (entry.isIntersecting) {
                stickyBar.classList.add('is-hidden');
            } else {
                stickyBar.classList.remove('is-hidden');
            }
        }, { threshold: 0.4 });

        observer.observe(widget);

        const handleMediaChange = () => {
            if (mediaQuery.matches) {
                stickyBar.classList.add('is-hidden');
            }
        };

        if (typeof mediaQuery.addEventListener === 'function') {
            mediaQuery.addEventListener('change', handleMediaChange);
        } else if (typeof mediaQuery.addListener === 'function') {
            mediaQuery.addListener(handleMediaChange);
        }

        page.addEventListener('fpExpWidgetCheckout', () => {
            stickyBar.classList.add('is-hidden');
        });
    }

    function pushTrackingEvent(eventName, data) {
        const channels = trackingConfig.enabled || {};

        if (!channels.ga4 && !channels.google_ads && !channels.meta_pixel) {
            return;
        }

        const signaturePayload = JSON.stringify({ eventName, data: data || {} });

        if (firedEvents.indexOf(signaturePayload) !== -1) {
            return;
        }

        firedEvents.push(signaturePayload);

        if (firedEvents.length > 40) {
            firedEvents.shift();
        }

        if (channels.ga4) {
            window.dataLayer = window.dataLayer || [];
            const payload = Object.assign({ event: eventName }, data || {});
            window.dataLayer.push(payload);
        }

        if (channels.google_ads && typeof window.gtag === 'function' && data && data.ecommerce) {
            window.gtag('event', eventName, {
                value: data.ecommerce.value || 0,
                currency: data.ecommerce.currency || defaultCurrency,
            });
        }

        if (channels.meta_pixel && typeof window.fbq === 'function' && data && data.ecommerce) {
            const metaPayload = {
                value: data.ecommerce.value || 0,
                currency: data.ecommerce.currency || defaultCurrency,
            };

            if (eventName === 'view_item') {
                window.fbq('track', 'ViewContent', metaPayload);
            } else if (eventName === 'add_to_cart') {
                window.fbq('track', 'AddToCart', metaPayload);
            } else if (eventName === 'begin_checkout') {
                window.fbq('track', 'InitiateCheckout', metaPayload);
            }
        }
    }

    function calculateQuantity(tickets) {
        if (!tickets) {
            return 0;
        }

        return Object.values(tickets).reduce((total, qty) => total + (parseInt(qty, 10) || 0), 0);
    }

    function getBasePrice(config) {
        if (!config || !Array.isArray(config.tickets)) {
            return 0;
        }

        return config.tickets.reduce((min, ticket) => {
            const price = parseFloat(ticket.price || 0);
            if (!min || (price > 0 && price < min)) {
                return price;
            }
            return min;
        }, 0);
    }

    function buildEcommercePayload(config, detail) {
        const quantity = detail && typeof detail.quantity === 'number'
            ? detail.quantity
            : calculateQuantity(detail ? detail.tickets : null);
        const basePrice = getBasePrice(config) || 0;
        const value = detail && typeof detail.total === 'number' ? detail.total : basePrice * (quantity || 1);

        const item = {
            item_id: String(config.experienceId || ''),
            item_name: config.experienceTitle || '',
            price: quantity > 0 ? value / quantity : basePrice,
            quantity: quantity || 1,
        };

        if (detail && detail.slotId) {
            item.item_variant = String(detail.slotId);
        }

        return {
            currency: defaultCurrency,
            value,
            items: [item],
        };
    }

    function onReady(callback) {
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            callback();
        } else {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        }
    }

    function init() {
        captureUtm();
        document.querySelectorAll('[data-fp-shortcode="widget"]').forEach(setupWidget);
        document.querySelectorAll('[data-fp-shortcode="list"]').forEach(setupListing);
        setupMeetingPoints();
        setupExperiencePages();
    }

    function setupListing(section) {
        if (!section) {
            return;
        }

        const itemsValue = section.getAttribute('data-fp-items');
        if (itemsValue) {
            try {
                const parsed = JSON.parse(itemsValue);
                if (Array.isArray(parsed) && parsed.length) {
                    const items = parsed.map((item, index) => {
                        const payload = {
                            item_id: String(item.item_id || ''),
                            item_name: item.item_name || '',
                            index: item.index || index + 1,
                        };

                        if (item.price && typeof item.price === 'number') {
                            payload.price = item.price;
                        }

                        return payload;
                    });

                    if (items.length) {
                        pushTrackingEvent('view_item_list', {
                            ecommerce: {
                                item_list_name: 'Experiences listing',
                                items,
                            },
                        });
                    }
                }
            } catch (error) {
                // Ignore JSON errors.
            }
        }

        section.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            const clickable = target.closest('a');
            if (!clickable) {
                return;
            }

            const card = clickable.closest('.fp-listing__card');
            if (!card) {
                return;
            }

            const itemId = card.getAttribute('data-experience-id') || '';
            const itemName = card.getAttribute('data-experience-name') || '';
            const priceValue = parseFloat(card.getAttribute('data-experience-price') || '');

            const item = {
                item_id: String(itemId),
                item_name: itemName,
            };

            if (!Number.isNaN(priceValue) && priceValue > 0) {
                item.price = priceValue;
            }

            pushTrackingEvent('select_item', {
                ecommerce: {
                    items: [item],
                },
            });
        });
    }

    function setupWidget(container) {
        const configValue = container.getAttribute('data-config');
        if (!configValue) {
            return;
        }

        let config;
        try {
            config = JSON.parse(configValue);
        } catch (error) {
            return;
        }

        const displayContext = config.displayContext || container.getAttribute('data-display-context') || 'widget';
        const trackingMeta = displayContext ? { context: displayContext } : {};

        const state = {
            selectedDate: null,
            selectedSlot: null,
            tickets: {},
            addons: {},
        };

        let lastSlot = null;
        let lastTicketsSignature = '';

        setupWidgetDialog(container, config.behavior || {});

        const slotsByDate = groupSlotsByDate(config.slots || []);
        const slotsContainer = container.querySelector('.fp-exp-slots');
        const summaryContainer = container.querySelector('.fp-exp-summary');
        const summaryButton = container.querySelector('.fp-exp-summary__cta');
        const rtbConfig = config.rtb || {};
        const rtbEnabled = Boolean(rtbConfig.enabled);
        const rtbNonce = config.nonce || '';
        const rtbForm = rtbEnabled ? container.querySelector('[data-fp-rtb-form]') : null;
        const rtbStatus = rtbForm ? rtbForm.querySelector('.fp-exp-rtb-form__status') : null;
        const rtbErrorSummary = rtbForm ? rtbForm.querySelector('[data-fp-error-summary]') : null;
        const summaryUi = createSummaryUi(summaryContainer, summaryButton, rtbForm, rtbStatus);
        const slotLookup = new Map((config.slots || []).map((slot) => [String(slot.id), slot]));
        const quoteCache = new Map();

        const fetchBreakdown = (detail) => {
            if (!detail || !detail.slotId) {
                return Promise.resolve(null);
            }

            const payload = {
                nonce: config.nonce || '',
                experience_id: parseInt(config.experienceId || detail.experienceId || 0, 10) || 0,
                slot_id: parseInt(detail.slotId, 10) || 0,
                tickets: detail.tickets || {},
                addons: detail.addons || {},
            };

            const signature = JSON.stringify(payload);
            if (quoteCache.has(signature)) {
                return Promise.resolve(quoteCache.get(signature));
            }

            return fetch(`${pluginConfig.restUrl}rtb/quote`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: buildRestHeaders(),
                body: JSON.stringify(payload),
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('quote_http_error');
                    }
                    return response.json();
                })
                .then((response) => {
                    if (!response || !response.success || !response.breakdown) {
                        throw new Error('quote_invalid');
                    }
                    quoteCache.set(signature, response.breakdown);
                    return response.breakdown;
                });
        };

        const refreshSummary = () => {
            updateSummary(summaryUi, state, config, slotLookup, fetchBreakdown);
        };

        if (rtbForm) {
            const fields = rtbForm.querySelectorAll('input, textarea');
            fields.forEach((field) => {
                const eventName = field.type === 'checkbox' ? 'change' : 'input';
                field.addEventListener(eventName, () => {
                    clearFieldInvalidState(field);
                });
            });
        }

        pushTrackingEvent('view_item', Object.assign(
            {
                ecommerce: buildEcommercePayload(config, { quantity: 1, total: getBasePrice(config) }),
            },
            trackingMeta
        ));

        refreshSummary();

        container.addEventListener('fpExpWidgetSummaryUpdate', (event) => {
            const detail = event.detail || {};
            detail.context = displayContext;
            const payload = Object.assign({ ecommerce: buildEcommercePayload(config, detail) }, trackingMeta);

            if (detail.slotId && detail.slotId !== lastSlot) {
                pushTrackingEvent('select_item', payload);
                lastSlot = detail.slotId;
            }

            const signature = JSON.stringify(detail.tickets || {});
            if (detail.quantity > 0 && signature !== lastTicketsSignature && !detail.pricingPending) {
                pushTrackingEvent('add_to_cart', payload);
                lastTicketsSignature = signature;
            }
        });

        container.querySelectorAll('.fp-exp-calendar__day').forEach((button) => {
            button.addEventListener('click', () => {
                const date = button.getAttribute('data-date');
                if (!date) {
                    return;
                }

                state.selectedDate = date;
                state.selectedSlot = null;
                renderSlots(slotsContainer, slotsByDate[date] || [], state);
                refreshSummary();
            });
        });

        container.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            if (target.closest('.fp-exp-slot-option')) {
                const slotButton = target.closest('.fp-exp-slot-option');
                if (!slotButton) {
                    return;
                }
                state.selectedSlot = slotButton.getAttribute('data-slot-id');
                container.querySelectorAll('.fp-exp-slot-option').forEach((btn) => btn.classList.remove('is-active'));
                slotButton.classList.add('is-active');
                refreshSummary();
            }

            if (target.classList.contains('fp-exp-quantity__control')) {
                const action = target.getAttribute('data-action');
                const input = target.parentElement.querySelector('.fp-exp-quantity__input');
                if (!input) {
                    return;
                }

                const currentValue = parseInt(input.value || '0', 10);
                let nextValue = currentValue;

                if (action === 'increase') {
                    nextValue = currentValue + 1;
                    const max = parseInt(input.getAttribute('max') || '99', 10);
                    if (!Number.isNaN(max)) {
                        nextValue = Math.min(nextValue, max || nextValue);
                    }
                } else {
                    nextValue = Math.max(0, currentValue - 1);
                }

                input.value = String(nextValue);
                const ticketRow = target.closest('tr[data-ticket]');
                if (ticketRow) {
                    const slug = ticketRow.getAttribute('data-ticket');
                    if (slug) {
                        state.tickets[slug] = nextValue;
                    }
                }
                refreshSummary();
            }

            if (target.matches('.fp-exp-addon input[type="checkbox"], .fp-exp-addon input[type="checkbox"] *')) {
                const checkbox = target.closest('.fp-exp-addon input[type="checkbox"]');
                if (!checkbox) {
                    return;
                }
                const addonRow = checkbox.closest('.fp-exp-addon');
                if (!addonRow) {
                    return;
                }
                const slug = addonRow.getAttribute('data-addon');
                if (!slug) {
                    return;
                }
                state.addons[slug] = checkbox.checked;
                refreshSummary();
            }
        });

        if (!rtbEnabled && summaryButton) {
            summaryButton.addEventListener('click', () => {
                if (!state.selectedSlot) {
                    return;
                }

                const detail = Object.assign({
                    experienceId: config.experienceId,
                    slotId: state.selectedSlot,
                    tickets: Object.assign({}, state.tickets),
                    addons: Object.assign({}, state.addons),
                }, state.lastSummary || {});

                const ecommerce = buildEcommercePayload(config, detail);
                const payload = Object.assign({ ecommerce }, trackingMeta);
                detail.total = ecommerce.value;
                detail.quantity = detail.quantity || calculateQuantity(detail.tickets);
                detail.currency = ecommerce.currency;
                detail.displayContext = displayContext;

                container.dispatchEvent(new CustomEvent('fpExpWidgetCheckout', {
                    bubbles: true,
                    detail: Object.assign({ displayContext }, detail),
                }));

                pushTrackingEvent('begin_checkout', payload);
            });
        }

        if (rtbForm) {
            rtbForm.addEventListener('submit', (event) => {
                event.preventDefault();

                if (!state.lastSummary || !state.lastSummary.slotId || state.lastSummary.pricingPending) {
                    return;
                }

                const formData = new FormData(rtbForm);
                const payload = Object.fromEntries(formData.entries());
                let tickets = {};
                let addons = {};

                if (rtbErrorSummary) {
                    hideErrorSummary(rtbErrorSummary);
                }

                const validationErrors = validateRtbForm(rtbForm);
                if (validationErrors.length) {
                    showErrorSummary(rtbErrorSummary, validationErrors);
                    return;
                }

                try {
                    tickets = JSON.parse(payload.tickets || '{}');
                } catch (error) {
                    tickets = {};
                }

                try {
                    addons = JSON.parse(payload.addons || '{}');
                } catch (error) {
                    addons = {};
                }

                const ecommerce = buildEcommercePayload(config, state.lastSummary);
                pushTrackingEvent('fpExp.request_submit', Object.assign({ ecommerce }, trackingMeta));

                const loadingMessage = rtbStatus
                    ? (rtbStatus.getAttribute('data-loading') || i18n__('Sending your request…', 'fp-experiences'))
                    : i18n__('Sending your request…', 'fp-experiences');
                const successMessage = rtbStatus
                    ? (rtbStatus.getAttribute('data-success') || i18n__('Request received! We will reply soon.', 'fp-experiences'))
                    : i18n__('Request received! We will reply soon.', 'fp-experiences');
                const errorMessage = rtbStatus
                    ? (rtbStatus.getAttribute('data-error') || i18n__('Unable to submit your request. Please try again.', 'fp-experiences'))
                    : i18n__('Unable to submit your request. Please try again.');

                setRtbStatus(summaryUi, 'loading', loadingMessage);

                const submitButton = rtbForm.querySelector('.fp-exp-summary__cta');
                if (submitButton) {
                    submitButton.disabled = true;
                }

                const requestedMode = (payload.mode || rtbConfig.mode || '').toString();
                const forcedFlag = typeof payload.forced !== 'undefined'
                    ? payload.forced === '1' || payload.forced === 'true'
                    : Boolean(rtbConfig.forced);

                const requestPayload = {
                    nonce: rtbNonce,
                    experience_id: parseInt(payload.experience_id || '0', 10) || 0,
                    slot_id: parseInt(payload.slot_id || '0', 10) || 0,
                    tickets,
                    addons,
                    mode: requestedMode,
                    forced: forcedFlag,
                    contact: {
                        name: payload.name || '',
                        email: payload.email || '',
                        phone: payload.phone || '',
                    },
                    notes: payload.notes || '',
                    consent: {
                        marketing: Boolean(payload.consent_marketing),
                        privacy: Boolean(payload.consent_privacy),
                    },
                };

                fetch(`${pluginConfig.restUrl}rtb/request`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: buildRestHeaders(),
                    body: JSON.stringify(requestPayload),
                })
                    .then((response) => response.json())
                    .then((response) => {
                        const success = Boolean(response && response.success);
                        const message = (response && response.message) || '';

                        setRtbStatus(summaryUi, success ? 'success' : 'error', message || (success ? successMessage : errorMessage));

                        if (success) {
                            pushTrackingEvent('fpExp.request_success', Object.assign({ ecommerce }, trackingMeta));
                            rtbForm.reset();
                            if (submitButton) {
                                submitButton.disabled = true;
                            }
                            hideErrorSummary(rtbErrorSummary);
                        } else {
                            if (submitButton) {
                                submitButton.disabled = false;
                            }
                            pushTrackingEvent('fpExp.request_error', Object.assign({ ecommerce }, trackingMeta));
                        }
                    })
                    .catch(() => {
                        setRtbStatus(summaryUi, 'error', errorMessage);
                        if (submitButton) {
                            submitButton.disabled = false;
                        }
                        pushTrackingEvent('fpExp.request_error', Object.assign({ ecommerce }, trackingMeta));
                    });
            });
        }
    }

    function renderSlots(container, slots, state) {
        if (!container) {
            return;
        }

        container.innerHTML = '';

        if (!slots.length) {
            const placeholder = document.createElement('p');
            placeholder.className = 'fp-exp-slots__placeholder';
            placeholder.textContent = container.getAttribute('data-empty-label') || '';
            container.appendChild(placeholder);
            return;
        }

        const list = document.createElement('div');
        list.className = 'fp-exp-slots__list';

        slots.forEach((slot) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'fp-exp-slot-option';
            button.setAttribute('data-slot-id', slot.id);
            button.textContent = `${slot.time} · ${slot.remaining} ${slot.remaining === 1 ? 'spot' : 'spots'}`;
            if (state.selectedSlot === String(slot.id)) {
                button.classList.add('is-active');
            }
            list.appendChild(button);
        });

        container.appendChild(list);
    }

    function createSummaryUi(container, button, rtbForm, rtbStatus) {
        if (!container) {
            return null;
        }

        return {
            container,
            button,
            body: container.querySelector('[data-fp-summary-body]'),
            lines: container.querySelector('[data-fp-summary-lines]'),
            adjustments: container.querySelector('[data-fp-summary-adjustments]'),
            totalRow: container.querySelector('[data-fp-summary-total-row]'),
            totalAmount: container.querySelector('[data-fp-summary-total]'),
            status: container.querySelector('[data-fp-summary-status]'),
            disclaimer: container.querySelector('[data-fp-summary-disclaimer]'),
            emptyLabel: container.getAttribute('data-empty-label') || '',
            loadingLabel: container.getAttribute('data-loading-label') || '',
            errorLabel: container.getAttribute('data-error-label') || '',
            slotLabel: container.getAttribute('data-slot-label') || '',
            taxLabel: container.getAttribute('data-tax-label') || '',
            baseLabel: container.getAttribute('data-base-label') || '',
            rtbForm,
            rtbStatus,
        };
    }

    function resetRtbStatus(ui) {
        if (!ui || !ui.rtbStatus) {
            return;
        }

        ui.rtbStatus.textContent = '';
        ui.rtbStatus.classList.remove('is-error', 'is-success', 'is-loading');
    }

    function setRtbStatus(ui, state, message) {
        if (!ui || !ui.rtbStatus) {
            return;
        }

        ui.rtbStatus.textContent = message || '';
        ui.rtbStatus.classList.remove('is-error', 'is-success', 'is-loading');
        if (state) {
            ui.rtbStatus.classList.add(`is-${state}`);
        }
    }

    function setSummaryState(ui, state, message) {
        if (!ui) {
            return;
        }

        const states = ['empty', 'loading', 'error', 'ready', 'pending'];
        states.forEach((name) => ui.container.classList.remove(`is-${name}`));
        if (state) {
            ui.container.classList.add(`is-${state}`);
        }

        if (ui.status) {
            ui.status.innerHTML = '';
            if (message) {
                const paragraph = document.createElement('p');
                paragraph.className = 'fp-exp-summary__message';
                paragraph.textContent = message;
                ui.status.appendChild(paragraph);
            }
        }
    }

    function renderSummaryLines(ui, lines, adjustments, total, currency, options = {}) {
        if (!ui) {
            return;
        }

        if (ui.lines) {
            ui.lines.innerHTML = '';
            lines.forEach((line) => {
                const item = document.createElement('li');
                item.className = 'fp-exp-summary__line';

                const text = document.createElement('div');
                text.className = 'fp-exp-summary__line-text';

                const label = document.createElement('span');
                label.className = 'fp-exp-summary__line-label';
                label.textContent = line.label;
                text.appendChild(label);

                if (line.detail) {
                    const meta = document.createElement('span');
                    meta.className = 'fp-exp-summary__line-meta';
                    meta.textContent = line.detail;
                    text.appendChild(meta);
                }

                item.appendChild(text);

                if (typeof line.amount === 'number') {
                    const amount = document.createElement('span');
                    amount.className = 'fp-exp-summary__line-amount';
                    amount.textContent = formatMoney(line.amount, line.currency || currency);
                    item.appendChild(amount);
                }

                ui.lines.appendChild(item);
            });
            ui.lines.hidden = !lines.length;
        }

        if (ui.adjustments) {
            ui.adjustments.innerHTML = '';
            if (adjustments && adjustments.length) {
                adjustments.forEach((adjustment) => {
                    const item = document.createElement('li');
                    item.className = 'fp-exp-summary__adjustment';

                    const label = document.createElement('span');
                    label.className = 'fp-exp-summary__adjustment-label';
                    label.textContent = adjustment.label;
                    item.appendChild(label);

                    const amount = document.createElement('span');
                    amount.className = 'fp-exp-summary__adjustment-amount';
                    amount.textContent = formatMoney(adjustment.amount, adjustment.currency || currency);
                    if (adjustment.amount < 0) {
                        amount.classList.add('is-negative');
                    }
                    item.appendChild(amount);

                    ui.adjustments.appendChild(item);
                });
                ui.adjustments.hidden = false;
            } else {
                ui.adjustments.hidden = true;
            }
        }

        if (ui.totalRow) {
            if (typeof total === 'number') {
                ui.totalRow.hidden = false;
                if (ui.totalAmount) {
                    ui.totalAmount.textContent = formatMoney(total, currency);
                }
            } else {
                ui.totalRow.hidden = true;
                if (ui.totalAmount) {
                    ui.totalAmount.textContent = '';
                }
            }
        }

        if (ui.body) {
            ui.body.hidden = !lines.length && !(adjustments && adjustments.length);
        }

        if (ui.disclaimer) {
            if (options.showDisclaimer && ui.taxLabel) {
                ui.disclaimer.hidden = false;
                ui.disclaimer.textContent = ui.taxLabel;
            } else {
                ui.disclaimer.hidden = true;
                ui.disclaimer.textContent = '';
            }
        }
    }

    function dispatchSummaryEvent(ui, detail) {
        if (!ui || !ui.container) {
            return;
        }

        ui.container.dispatchEvent(new CustomEvent('fpExpWidgetSummaryUpdate', {
            bubbles: true,
            detail,
        }));
    }

    function updateRtbFormFields(ui, detail) {
        if (!ui || !ui.rtbForm) {
            return;
        }

        const slotInput = ui.rtbForm.querySelector('input[name="slot_id"]');
        const ticketsInput = ui.rtbForm.querySelector('input[name="tickets"]');
        const addonsInput = ui.rtbForm.querySelector('input[name="addons"]');
        const submit = ui.rtbForm.querySelector('.fp-exp-summary__cta');

        if (slotInput) {
            slotInput.value = detail.slotId ? String(detail.slotId) : '';
        }
        if (ticketsInput) {
            ticketsInput.value = JSON.stringify(detail.tickets || {});
        }
        if (addonsInput) {
            addonsInput.value = JSON.stringify(detail.addons || {});
        }
        if (submit) {
            submit.disabled = !detail.slotId || !detail.quantity || detail.pricingPending;
        }
    }

    function updateSummary(ui, state, config, slotLookup, fetchBreakdown) {
        if (!ui) {
            return;
        }

        const ticketEntries = Object.entries(state.tickets || {}).filter(([, qty]) => Number(qty) > 0);
        const addonEntries = Object.entries(state.addons || {}).filter(([, active]) => Boolean(active));

        const ticketLines = [];
        const ticketMap = {};
        let totalQuantity = 0;

        ticketEntries.forEach(([slug, qty]) => {
            const ticket = (config.tickets || []).find((item) => item.slug === slug);
            if (!ticket) {
                return;
            }

            const count = parseInt(qty, 10) || 0;
            if (count <= 0) {
                return;
            }

            ticketLines.push({
                type: 'ticket',
                slug,
                label: ticket.label,
                detail: `× ${count}`,
                quantity: count,
            });
            ticketMap[slug] = count;
            totalQuantity += count;
        });

        const addonLines = [];
        const addonMap = {};
        addonEntries.forEach(([slug]) => {
            const addon = (config.addons || []).find((item) => item.slug === slug);
            if (!addon) {
                return;
            }

            addonLines.push({
                type: 'addon',
                slug,
                label: addon.label,
                detail: '',
            });
            addonMap[slug] = 1;
        });

        const slot = state.selectedSlot ? slotLookup.get(String(state.selectedSlot)) : null;
        const slotDescription = describeSlot(slot);

        const baseLines = [];
        if (slotDescription) {
            baseLines.push({
                type: 'slot',
                slug: 'slot',
                label: slotDescription.label,
                detail: slotDescription.detail,
            });
        }

        const selectionLines = baseLines.concat(ticketLines, addonLines);

        const detail = {
            experienceId: config.experienceId,
            slotId: state.selectedSlot ? String(state.selectedSlot) : null,
            tickets: ticketMap,
            addons: addonMap,
            quantity: totalQuantity,
            currency: defaultCurrency,
            pricingPending: !slot,
        };

        updateRtbFormFields(ui, detail);
        resetRtbStatus(ui);

        if (!ticketEntries.length) {
            state.lastSummary = null;
            state.pendingSignature = null;
            renderSummaryLines(ui, [], [], null, detail.currency, { showDisclaimer: false });
            setSummaryState(ui, 'empty', ui.emptyLabel);
            if (ui.button) {
                ui.button.disabled = true;
            }
            dispatchSummaryEvent(ui, detail);
            return;
        }

        renderSummaryLines(ui, selectionLines, [], null, detail.currency, { showDisclaimer: false });

        if (!slot) {
            detail.pricingPending = true;
            state.lastSummary = detail;
            state.pendingSignature = null;
            setSummaryState(ui, 'pending', ui.slotLabel || ui.emptyLabel);
            if (ui.button) {
                ui.button.disabled = true;
            }
            dispatchSummaryEvent(ui, detail);
            return;
        }

        if (ui.button) {
            ui.button.disabled = true;
        }

        const signature = JSON.stringify({
            slot: detail.slotId,
            tickets: detail.tickets,
            addons: detail.addons,
        });

        state.pendingSignature = signature;
        detail.pricingPending = true;
        state.lastSummary = detail;
        setSummaryState(ui, 'loading', ui.loadingLabel || ui.emptyLabel);
        dispatchSummaryEvent(ui, detail);

        fetchBreakdown(detail)
            .then((breakdown) => {
                if (!breakdown || state.pendingSignature !== signature) {
                    return;
                }

                const enrichedLines = [];

                if (breakdown.base_price && breakdown.base_price > 0 && ui.baseLabel) {
                    enrichedLines.push({
                        type: 'base',
                        slug: 'base',
                        label: ui.baseLabel,
                        detail: '',
                        amount: breakdown.base_price,
                        currency: breakdown.currency,
                    });
                }

                if (slotDescription) {
                    enrichedLines.push({
                        type: 'slot',
                        slug: 'slot',
                        label: slotDescription.label,
                        detail: slotDescription.detail,
                    });
                }

                (breakdown.tickets || []).forEach((item) => {
                    enrichedLines.push({
                        type: 'ticket',
                        slug: item.slug,
                        label: item.label,
                        detail: `× ${item.quantity}`,
                        amount: item.line_total,
                        currency: breakdown.currency,
                    });
                });

                (breakdown.addons || []).forEach((item) => {
                    enrichedLines.push({
                        type: 'addon',
                        slug: item.slug,
                        label: item.label,
                        detail: item.quantity > 1 ? `× ${item.quantity}` : '',
                        amount: item.line_total,
                        currency: breakdown.currency,
                    });
                });

                const adjustments = (breakdown.adjustments || []).map((adjustment) => ({
                    label: adjustment.label,
                    amount: adjustment.amount,
                    currency: breakdown.currency,
                }));

                renderSummaryLines(ui, enrichedLines, adjustments, breakdown.total, breakdown.currency, {
                    showDisclaimer: true,
                });

                const statusParts = [];
                if (slotDescription) {
                    statusParts.push(`${slotDescription.detail} · ${slotDescription.label}`);
                }
                if (breakdown.total_guests) {
                    statusParts.push(
                        i18n_n('%d guest', '%d guests', breakdown.total_guests, 'fp-experiences')
                            .replace('%d', breakdown.total_guests)
                    );
                }
                const statusMessage = statusParts.join(' · ') || ui.emptyLabel;

                const readyDetail = Object.assign({}, detail, {
                    total: breakdown.total,
                    subtotal: breakdown.subtotal,
                    currency: breakdown.currency,
                    pricingPending: false,
                    breakdown,
                });

                state.lastSummary = readyDetail;
                setSummaryState(ui, 'ready', statusMessage);
                if (ui.button) {
                    ui.button.disabled = !readyDetail.slotId || !readyDetail.quantity;
                }
                updateRtbFormFields(ui, readyDetail);
                dispatchSummaryEvent(ui, readyDetail);
            })
            .catch(() => {
                if (state.pendingSignature !== signature) {
                    return;
                }

                setSummaryState(ui, 'error', ui.errorLabel || ui.emptyLabel);
                if (ui.button) {
                    ui.button.disabled = true;
                }
                setRtbStatus(ui, 'error', ui.errorLabel || '');
                const errorDetail = Object.assign({}, detail, {
                    pricingPending: true,
                    error: true,
                });
                state.lastSummary = errorDetail;
                dispatchSummaryEvent(ui, errorDetail);
            });
    }

    function groupSlotsByDate(slots) {
        return (slots || []).reduce((acc, slot) => {
            if (!slot.start) {
                return acc;
            }
            if (!acc[slot.start]) {
                acc[slot.start] = [];
            }
            acc[slot.start].push(slot);
            return acc;
        }, {});
    }

    onReady(init);
}());
