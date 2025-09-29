(function () {
    'use strict';

    const pluginConfig = window.fpExpConfig || {};
    const trackingConfig = pluginConfig.tracking || {};
    const firedEvents = [];
    const defaultCurrency = pluginConfig.currency || 'EUR';
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

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
        document.querySelectorAll('[data-fp-shortcode="list"]').forEach(setupListFilters);
    }

    function setupListFilters(section) {
        const cards = Array.from(section.querySelectorAll('.fp-exp-card'));
        const filterChoices = section.querySelectorAll('.fp-exp-filter__choice');

        if (!filterChoices.length) {
            return;
        }

        filterChoices.forEach((choice) => {
            choice.addEventListener('click', () => {
                const type = choice.getAttribute('data-filter-type');
                const value = choice.getAttribute('data-filter-value');

                if (!type || !value) {
                    return;
                }

                choice.classList.toggle('is-active');

                const activeFilters = Array.from(section.querySelectorAll('.fp-exp-filter__choice.is-active'));
                const criteria = activeFilters.reduce((acc, item) => {
                    const filterType = item.getAttribute('data-filter-type');
                    const filterValue = item.getAttribute('data-filter-value');

                    if (filterType && filterValue) {
                        if (!acc[filterType]) {
                            acc[filterType] = new Set();
                        }
                        acc[filterType].add(filterValue.toLowerCase());
                    }

                    return acc;
                }, {});

                cards.forEach((card) => {
                    card.hidden = !matchesFilters(card, criteria);
                });
            });
        });
    }

    function matchesFilters(card, criteria) {
        const types = Object.keys(criteria);

        if (!types.length) {
            return true;
        }

        return types.every((type) => {
            const values = criteria[type];

            if (!values || !values.size) {
                return true;
            }

            if (type === 'price') {
                const price = parseFloat(card.getAttribute('data-price-from') || '0');
                const min = Math.min(...Array.from(values).map(parseFloat));
                return price >= min;
            }

            if (type === 'duration') {
                const label = (card.getAttribute('data-duration-label') || '').toLowerCase();
                return values.has(label);
            }

            if (type === 'theme') {
                const themes = (card.getAttribute('data-themes') || '').toLowerCase().split('|');
                return Array.from(values).some((value) => themes.includes(value));
            }

            return true;
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

        if (rtbForm) {
            const fields = rtbForm.querySelectorAll('input, textarea');
            fields.forEach((field) => {
                const eventName = field.type === 'checkbox' ? 'change' : 'input';
                field.addEventListener(eventName, () => {
                    clearFieldInvalidState(field);
                });
            });
        }

        pushTrackingEvent('view_item', {
            ecommerce: buildEcommercePayload(config, { quantity: 1, total: getBasePrice(config) }),
        });

        container.addEventListener('fpExpWidgetSummaryUpdate', (event) => {
            const detail = event.detail || {};
            const payload = { ecommerce: buildEcommercePayload(config, detail) };

            if (detail.slotId && detail.slotId !== lastSlot) {
                pushTrackingEvent('select_item', payload);
                lastSlot = detail.slotId;
            }

            const signature = JSON.stringify(detail.tickets || {});
            if (detail.quantity > 0 && signature !== lastTicketsSignature) {
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
                updateSummary(summaryContainer, summaryButton, state, config);
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
                updateSummary(summaryContainer, summaryButton, state, config);
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
                updateSummary(summaryContainer, summaryButton, state, config);
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
                updateSummary(summaryContainer, summaryButton, state, config);
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
                detail.total = ecommerce.value;
                detail.quantity = detail.quantity || calculateQuantity(detail.tickets);
                detail.currency = ecommerce.currency;

                container.dispatchEvent(new CustomEvent('fpExpWidgetCheckout', {
                    bubbles: true,
                    detail,
                }));

                pushTrackingEvent('begin_checkout', { ecommerce });
            });
        }

        if (rtbForm) {
            rtbForm.addEventListener('submit', (event) => {
                event.preventDefault();

                if (!state.lastSummary || !state.lastSummary.slotId) {
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
                pushTrackingEvent('fpExp.request_submit', { ecommerce });

                if (rtbStatus) {
                    rtbStatus.textContent = rtbStatus.getAttribute('data-loading') || '...';
                }

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
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(requestPayload),
                })
                    .then((response) => response.json())
                    .then((response) => {
                        const success = Boolean(response && response.success);
                        const message = (response && response.message) || '';

                        if (rtbStatus) {
                            rtbStatus.textContent = message || (success
                                ? (rtbStatus.getAttribute('data-success') || 'Request submitted successfully.')
                                : (rtbStatus.getAttribute('data-error') || 'Unable to submit your request. Please try again.'));
                        }

                        if (success) {
                            pushTrackingEvent('fpExp.request_success', { ecommerce });
                            rtbForm.reset();
                            if (submitButton) {
                                submitButton.disabled = true;
                            }
                            hideErrorSummary(rtbErrorSummary);
                        } else {
                            if (submitButton) {
                                submitButton.disabled = false;
                            }
                            pushTrackingEvent('fpExp.request_error', { ecommerce });
                        }
                    })
                    .catch(() => {
                        if (rtbStatus) {
                            rtbStatus.textContent = rtbStatus.getAttribute('data-error') || 'Unable to submit your request. Please try again.';
                        }
                        if (submitButton) {
                            submitButton.disabled = false;
                        }
                        pushTrackingEvent('fpExp.request_error', { ecommerce });
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

    function updateSummary(summaryContainer, summaryButton, state, config) {
        if (!summaryContainer) {
            return;
        }

        const ticketEntries = Object.entries(state.tickets || {}).filter(([, qty]) => Number(qty) > 0);
        const addonEntries = Object.entries(state.addons || {}).filter(([, active]) => Boolean(active));

        if (!ticketEntries.length) {
            state.lastSummary = null;
            summaryContainer.innerHTML = `<p class="fp-exp-summary__empty">${summaryContainer.getAttribute('data-empty-label') || ''}</p>`;
            if (summaryButton) {
                summaryButton.disabled = true;
            }
            if (rtbStatus) {
                rtbStatus.textContent = '';
            }
            return;
        }

        const list = document.createElement('ul');
        list.className = 'fp-exp-summary__list';

        let total = 0;

        let totalQuantity = 0;

        ticketEntries.forEach(([slug, qty]) => {
            const ticket = (config.tickets || []).find((item) => item.slug === slug);
            if (!ticket) {
                return;
            }

            const price = parseFloat(ticket.price || 0);
            const count = parseInt(qty, 10) || 0;
            total += price * count;
            totalQuantity += count;

            const li = document.createElement('li');
            li.textContent = `${ticket.label} × ${count}`;
            list.appendChild(li);
        });

        addonEntries.forEach(([slug]) => {
            const addon = (config.addons || []).find((item) => item.slug === slug);
            if (!addon) {
                return;
            }
            total += parseFloat(addon.price || 0);
            const li = document.createElement('li');
            li.textContent = `${addon.label}`;
            list.appendChild(li);
        });

        if (state.selectedSlot) {
            const slot = (config.slots || []).find((item) => String(item.id) === String(state.selectedSlot));
            if (slot) {
                const info = document.createElement('li');
                info.textContent = `${slot.start} · ${slot.time}`;
                list.insertBefore(info, list.firstChild);
            }
        }

        summaryContainer.innerHTML = '';
        summaryContainer.appendChild(list);

        const ticketMap = {};
        ticketEntries.forEach(([slug, qty]) => {
            ticketMap[slug] = parseInt(qty, 10) || 0;
        });

        const addonMap = {};
        addonEntries.forEach(([slug]) => {
            addonMap[slug] = 1;
        });

        const detail = {
            experienceId: config.experienceId,
            total,
            tickets: ticketMap,
            addons: addonMap,
            slotId: state.selectedSlot,
            quantity: totalQuantity,
            currency: defaultCurrency,
        };

        state.lastSummary = detail;

        if (summaryButton) {
            summaryButton.disabled = !state.selectedSlot;
        }

        if (rtbForm) {
            const slotInput = rtbForm.querySelector('input[name="slot_id"]');
            const ticketsInput = rtbForm.querySelector('input[name="tickets"]');
            const addonsInput = rtbForm.querySelector('input[name="addons"]');
            const submit = rtbForm.querySelector('.fp-exp-summary__cta');

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
                submit.disabled = !detail.slotId || !detail.quantity;
            }
        }

        const event = new CustomEvent('fpExpWidgetSummaryUpdate', {
            bubbles: true,
            detail,
        });
        summaryContainer.dispatchEvent(event);
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
