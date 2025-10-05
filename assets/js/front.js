(function () {
    'use strict';

    const { __: i18n__, _n: i18n_n, sprintf: i18n_sprintf } = (window.wp && window.wp.i18n) || {
        __: (text) => text,
        _n: (single, plural, number) => (number === 1 ? single : plural),
        sprintf: (format, ...args) => {
            let index = 0;
            return String(format || '').replace(/%[sd]/g, () => {
                const value = args[index];
                index += 1;
                return value == null ? '' : String(value);
            });
        },
    };

    const pluginConfig = window.fpExpConfig || {};
    const trackingConfig = pluginConfig.tracking || {};
    const firedEvents = [];
    const defaultCurrency = pluginConfig.currency || 'EUR';
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);

        return div.innerHTML;
    }

    function formatCurrency(value, currency) {
        const amount = typeof value === 'number' ? value : parseFloat(String(value || '0'));
        const safeCurrency = currency || defaultCurrency;

        if (Number.isNaN(amount)) {
            return '';
        }

        if (typeof Intl !== 'undefined' && Intl.NumberFormat) {
            try {
                return new Intl.NumberFormat(undefined, {
                    style: 'currency',
                    currency: safeCurrency,
                }).format(amount);
            } catch (error) {
                // Fallback to basic formatting below.
            }
        }

        return `${amount.toFixed(2)} ${safeCurrency}`;
    }

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

    function setupExperienceWidgetPlacement(page) {
        const widget = page.querySelector('#fp-exp-widget');
        const overview = page.querySelector('#fp-exp-section-overview');

        if (!widget || !overview) {
            return;
        }

        const mobileQuery = window.matchMedia('(max-width: 1023px)');
        const placeholder = document.createElement('div');
        placeholder.setAttribute('data-fp-widget-placeholder', '');
        placeholder.hidden = true;

        const parent = widget.parentNode;
        if (!parent) {
            return;
        }

        if (widget.nextSibling) {
            parent.insertBefore(placeholder, widget.nextSibling);
        } else {
            parent.appendChild(placeholder);
        }

        const moveToOverview = () => {
            const container = overview.parentNode;
            if (!container) {
                return;
            }

            const next = overview.nextSibling;
            if (next === widget) {
                return;
            }

            container.insertBefore(widget, next);
            widget.classList.add('is-mobile-inline');
        };

        const restoreToSidebar = () => {
            widget.classList.remove('is-mobile-inline');

            const placeholderParent = placeholder.parentNode;
            if (!placeholderParent) {
                return;
            }

            if (widget.previousSibling === placeholder) {
                return;
            }

            placeholderParent.insertBefore(widget, placeholder);
        };

        const applyPlacement = (event) => {
            const matches = event && typeof event.matches === 'boolean' ? event.matches : mobileQuery.matches;

            if (matches) {
                moveToOverview();
            } else {
                restoreToSidebar();
            }
        };

        applyPlacement(mobileQuery);

        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', applyPlacement);
        } else if (typeof mobileQuery.addListener === 'function') {
            mobileQuery.addListener(applyPlacement);
        }
    }

    function setupExperiencePages() {
        document.querySelectorAll('[data-fp-shortcode="experience"]').forEach((page) => {
            setupExperienceScroll(page);
            setupExperienceAccordion(page);
            setupExperienceWidgetPlacement(page);
            setupExperienceSticky(page);
            setupGiftForm(page);
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
                    const safeKey = typeof CSS !== 'undefined' && typeof CSS.escape === 'function'
                        ? CSS.escape(targetKey)
                        : targetKey;
                    target = page.querySelector(`#fp-exp-section-${safeKey}`);
                }

                if (!target) {
                    return;
                }

                event.preventDefault();

                const rect = target.getBoundingClientRect();
                const offset = rect.top + window.scrollY - 80;
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

        const updatePadding = () => {
            window.requestAnimationFrame(() => {
                const height = stickyBar.offsetHeight || 0;
                page.style.setProperty('--fp-exp-sticky-height', `${Math.ceil(height)}px`);
            });
        };

        const handleResize = () => {
            updatePadding();
        };

        stickyBar.classList.remove('is-hidden');
        page.classList.add('has-sticky-bar');
        updatePadding();

        window.addEventListener('resize', handleResize);

        const resizeObserver = typeof window.ResizeObserver === 'function'
            ? new window.ResizeObserver(updatePadding)
            : null;
        let mutationObserver = null;

        if (resizeObserver) {
            resizeObserver.observe(stickyBar);
        } else {
            mutationObserver = typeof window.MutationObserver === 'function'
                ? new window.MutationObserver(updatePadding)
                : null;

            if (mutationObserver) {
                mutationObserver.observe(stickyBar, { attributes: true, childList: true, subtree: true });
            }
        }

        let cleanedUp = false;
        const cleanup = () => {
            if (cleanedUp) {
                return;
            }

            cleanedUp = true;

            window.removeEventListener('resize', handleResize);
            page.classList.remove('has-sticky-bar');
            page.style.removeProperty('--fp-exp-sticky-height');

            if (resizeObserver) {
                resizeObserver.disconnect();
            }

            if (mutationObserver) {
                mutationObserver.disconnect();
            }
        };

        const handleCheckout = () => {
            stickyBar.classList.add('is-hidden');
            cleanup();
            page.removeEventListener('fpExpWidgetCheckout', handleCheckout);
        };

        page.addEventListener('fpExpWidgetCheckout', handleCheckout);
    }

    function setupGiftForm(page) {
        const giftSection = page.querySelector('[data-fp-gift]');
        if (!giftSection) {
            return;
        }

        const configAttr = giftSection.getAttribute('data-fp-gift-config') || '';
        let config = {};

        try {
            config = configAttr ? JSON.parse(configAttr) : {};
        } catch (error) {
            config = {};
        }

        const form = giftSection.querySelector('[data-fp-gift-form]');
        const feedback = giftSection.querySelector('[data-fp-gift-feedback]');
        const success = giftSection.querySelector('[data-fp-gift-success]');

        const toggleButtons = page.querySelectorAll('[data-fp-gift-toggle]');
        const closeButtons = giftSection.querySelectorAll('[data-fp-gift-close]');
        const backdrop = giftSection.querySelector('[data-fp-gift-backdrop]');
        const dialog = giftSection.querySelector('[data-fp-gift-dialog]');
        const focusableSelector = [
            'a[href]:not([tabindex="-1"])',
            'button:not([disabled])',
            'textarea:not([disabled])',
            'input:not([disabled])',
            'select:not([disabled])',
            '[tabindex]:not([tabindex="-1"])',
        ].join(',');
        let focusableElements = [];
        let lastFocusedElement = null;

        const setToggleExpanded = (expanded) => {
            toggleButtons.forEach((button) => {
                button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            });
        };

        const isGiftHidden = () =>
            giftSection.hasAttribute('hidden') || giftSection.hidden || giftSection.getAttribute('aria-hidden') === 'true';

        const isElementVisible = (element) => {
            if (!(element instanceof HTMLElement)) {
                return false;
            }

            if (element.hasAttribute('hidden') || element.getAttribute('aria-hidden') === 'true') {
                return false;
            }

            const rects = element.getClientRects();
            if (rects.length === 0) {
                return false;
            }

            return Array.from(rects).some((rect) => rect.width > 0 && rect.height > 0);
        };

        const updateFocusableElements = () => {
            focusableElements = Array.from(giftSection.querySelectorAll(focusableSelector)).filter((element) =>
                isElementVisible(element)
            );
        };

        const handleKeydown = (event) => {
            if (isGiftHidden()) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                hideGiftSection();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            updateFocusableElements();

            if (!focusableElements.length) {
                return;
            }

            const first = focusableElements[0];
            const last = focusableElements[focusableElements.length - 1];
            const activeElement = document.activeElement;

            if (event.shiftKey) {
                if (activeElement === first || !giftSection.contains(activeElement)) {
                    event.preventDefault();
                    last.focus();
                }
            } else if (activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };

        const setGiftExpanded = (expanded) => {
            if (expanded) {
                giftSection.hidden = false;
                giftSection.removeAttribute('hidden');
                giftSection.setAttribute('aria-hidden', 'false');
                giftSection.classList.add('is-open');
                if (document.body && document.body.classList) {
                    document.body.classList.add('fp-modal-open');
                }
                updateFocusableElements();
                document.addEventListener('keydown', handleKeydown);
            } else {
                giftSection.classList.remove('is-open');
                giftSection.setAttribute('aria-hidden', 'true');
                if (document.body && document.body.classList) {
                    document.body.classList.remove('fp-modal-open');
                }
                document.removeEventListener('keydown', handleKeydown);
                giftSection.hidden = true;
                giftSection.setAttribute('hidden', '');
                focusableElements = [];
            }

            setToggleExpanded(expanded);
        };

        const focusGiftElement = (element) => {
            if (!(element instanceof HTMLElement)) {
                return;
            }

            window.setTimeout(() => {
                element.focus();
            }, 80);
        };

        const hideGiftSection = ({ restoreFocus = true } = {}) => {
            setGiftExpanded(false);

            if (giftSection.id && window.location.hash === `#${giftSection.id}`) {
                try {
                    window.history.replaceState(null, '', window.location.href.split('#')[0]);
                } catch (_error) {
                    window.location.hash = '';
                }
            }

            const focusTarget = restoreFocus ? lastFocusedElement : null;
            lastFocusedElement = null;

            if (focusTarget instanceof HTMLElement) {
                window.setTimeout(() => {
                    focusTarget.focus();
                }, 80);
            }
        };

        const showGiftSection = ({ focusFirstField = false } = {}) => {
            const wasHidden = isGiftHidden();
            lastFocusedElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;

            setGiftExpanded(true);

            if (giftSection.id) {
                const giftHash = `#${giftSection.id}`;
                if (window.location.hash !== giftHash) {
                    try {
                        window.history.replaceState(null, '', giftHash);
                    } catch (_error) {
                        window.location.hash = giftHash;
                    }
                }
            }

            let focusTarget = null;

            if (focusFirstField && form && wasHidden) {
                focusTarget = form.querySelector('input, textarea, select');
            }

            if (!(focusTarget instanceof HTMLElement)) {
                focusTarget = dialog instanceof HTMLElement ? dialog : giftSection;
            }

            focusGiftElement(focusTarget);
        };

        toggleButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                showGiftSection({ focusFirstField: true });
            });
        });

        closeButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                hideGiftSection();
            });
        });

        if (backdrop) {
            backdrop.addEventListener('click', (event) => {
                event.preventDefault();
                hideGiftSection();
            });
        }

        giftSection.addEventListener('focusin', updateFocusableElements);

        if (isGiftHidden()) {
            giftSection.setAttribute('aria-hidden', 'true');
            setToggleExpanded(false);
        } else {
            setGiftExpanded(true);
        }

        if (giftSection.id && window.location.hash === `#${giftSection.id}`) {
            showGiftSection({ focusFirstField: true });
        }

        if (!form) {
            return;
        }

        const submitButton = form.querySelector('[data-fp-gift-submit]');
        let submitting = false;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (submitting) {
                return;
            }

            clearGiftFeedback();

            if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
                showGiftFeedback(feedback, i18n__('Please complete the required fields.', 'fp-experiences'), true);
                return;
            }

            const payload = buildGiftPayload(form, config);

            if (!payload) {
                showGiftFeedback(feedback, i18n__('Unable to prepare the gift checkout. Please try again later.', 'fp-experiences'), true);
                return;
            }

            submitting = true;
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.setAttribute('aria-busy', 'true');
            }

            try {
                const response = await fetch(`${pluginConfig.restUrl}gift/purchase`, {
                    method: 'POST',
                    headers: buildRestHeaders(),
                    body: JSON.stringify(payload),
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok || (data && data.code)) {
                    const message = data && data.message ? data.message : i18n__('We could not start the gift checkout. Please try again.', 'fp-experiences');
                    showGiftFeedback(feedback, message, true);
                    return;
                }

                if (data && data.checkout_url) {
                    try {
                        const value = typeof data.value === 'number' ? data.value : parseFloat(data.value || '0');
                        const currency = (data && data.currency) ? String(data.currency) : (pluginConfig.currency || 'EUR');
                        const quantity = typeof payload.quantity === 'number' && payload.quantity > 0 ? payload.quantity : 1;
                        const unitPrice = quantity > 0 && !Number.isNaN(value) ? value / quantity : value;
                        const addons = Array.isArray(payload.addons) ? payload.addons.map((addon) => String(addon)) : [];
                        const ecommerce = {
                            value: Number.isNaN(value) ? 0 : value,
                            currency,
                            items: [
                                {
                                    item_id: String(payload.experience_id || ''),
                                    item_name: config.experienceTitle || '',
                                    quantity,
                                    price: Number.isNaN(unitPrice) ? 0 : unitPrice,
                                },
                            ],
                        };
                        pushTrackingEvent('gift_purchase', {
                            ecommerce,
                            gift: {
                                experienceId: payload.experience_id,
                                experienceTitle: config.experienceTitle || '',
                                quantity: payload.quantity,
                                addons,
                            },
                        });
                    } catch (error) {
                        // no-op
                    }

                    window.location.href = data.checkout_url;

                    return;
                }

                showGiftFeedback(success, i18n__('Gift checkout initialised. Follow the next steps to complete payment.', 'fp-experiences'), false);
            } catch (error) {
                showGiftFeedback(feedback, i18n__('We could not start the gift checkout. Please try again.', 'fp-experiences'), true);
            } finally {
                submitting = false;
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.removeAttribute('aria-busy');
                }
            }
        });

        function buildGiftPayload(formElement, formConfig) {
            const formData = new FormData(formElement);
            const experienceId = parseInt(String(formConfig.experienceId || 0), 10);

            if (!experienceId || Number.isNaN(experienceId)) {
                return null;
            }

            const quantity = Math.max(1, parseInt(String(formData.get('quantity') || '1'), 10));
            const addons = formData.getAll('addons[]').map((value) => String(value)).filter((value) => value !== '');

            return {
                experience_id: experienceId,
                quantity,
                addons,
                purchaser: {
                    name: String(formData.get('purchaser[name]') || ''),
                    email: String(formData.get('purchaser[email]') || ''),
                },
                recipient: {
                    name: String(formData.get('recipient[name]') || ''),
                    email: String(formData.get('recipient[email]') || ''),
                },
                message: String(formData.get('message') || ''),
            };
        }

        function showGiftFeedback(container, message, isError) {
            if (!container) {
                return;
            }

            container.textContent = message;
            container.hidden = false;

            if (isError) {
                container.classList.add('is-error');
            } else {
                container.classList.remove('is-error');
            }
        }

        function clearGiftFeedback() {
            if (feedback) {
                feedback.hidden = true;
                feedback.textContent = '';
                feedback.classList.remove('is-error');
            }

            if (success) {
                success.hidden = true;
                success.textContent = '';
            }
        }
    }

    function setupGiftRedeem() {
        const sections = document.querySelectorAll('[data-fp-gift-redeem]');

        if (!sections.length) {
            return;
        }

        sections.forEach((section) => {
            const lookupForm = section.querySelector('[data-fp-gift-redeem-lookup]');
            const redeemForm = section.querySelector('[data-fp-gift-redeem-form]');
            const feedback = section.querySelector('[data-fp-gift-redeem-feedback]');
            const detailFeedback = section.querySelector('[data-fp-gift-redeem-feedback-details]');
            const success = section.querySelector('[data-fp-gift-redeem-success]');
            const details = section.querySelector('[data-fp-gift-redeem-details]');
            const codeInput = lookupForm ? lookupForm.querySelector('[data-fp-gift-code]') : null;
            const lookupButton = lookupForm ? lookupForm.querySelector('[data-fp-gift-redeem-lookup-submit]') : null;
            const slotSelect = redeemForm ? redeemForm.querySelector('[data-fp-gift-redeem-slot]') : null;
            const redeemButton = redeemForm ? redeemForm.querySelector('[data-fp-gift-redeem-submit]') : null;
            const titleNode = section.querySelector('[data-fp-gift-redeem-title]');
            const excerptNode = section.querySelector('[data-fp-gift-redeem-excerpt]');
            const imageNode = section.querySelector('[data-fp-gift-redeem-image]');
            const addonsWrapper = section.querySelector('[data-fp-gift-redeem-addons-wrapper]');
            const addonsList = section.querySelector('[data-fp-gift-redeem-addons]');
            const quantityNode = section.querySelector('[data-fp-gift-redeem-quantity]');
            const validityNode = section.querySelector('[data-fp-gift-redeem-validity]');
            const valueNode = section.querySelector('[data-fp-gift-redeem-value]');
            const codeLabel = section.querySelector('[data-fp-gift-redeem-code]');
            let currentVoucher = null;
            let lookupLoading = false;
            let redeemLoading = false;

            function toggleButton(button, state) {
                if (!button) {
                    return;
                }

                button.disabled = state;

                if (state) {
                    button.setAttribute('aria-busy', 'true');
                } else {
                    button.removeAttribute('aria-busy');
                }
            }

            function clearFeedbackMessages() {
                [feedback, detailFeedback, success].forEach((target) => {
                    if (!target) {
                        return;
                    }

                    target.hidden = true;
                    target.textContent = '';
                    target.classList.remove('is-error');
                });
            }

            function showFeedback(target, message, isError) {
                if (!target) {
                    return;
                }

                if (isError) {
                    target.classList.add('is-error');
                } else {
                    target.classList.remove('is-error');
                }

                target.innerHTML = message;
                target.hidden = false;
            }

            function resetDetails() {
                currentVoucher = null;

                if (details) {
                    details.hidden = true;
                }

                if (redeemForm) {
                    redeemForm.reset();
                }

                if (slotSelect) {
                    slotSelect.innerHTML = '';
                    slotSelect.disabled = true;
                }

                if (redeemButton) {
                    redeemButton.disabled = true;
                }

                if (titleNode) {
                    titleNode.textContent = '';
                }

                if (excerptNode) {
                    excerptNode.innerHTML = '';
                }

                if (imageNode && imageNode.tagName === 'IMG') {
                    imageNode.src = '';
                    imageNode.alt = '';
                    imageNode.hidden = true;
                }

                if (addonsList) {
                    addonsList.innerHTML = '';
                }

                if (addonsWrapper) {
                    addonsWrapper.hidden = true;
                }

                if (quantityNode) {
                    quantityNode.textContent = '';
                }

                if (validityNode) {
                    validityNode.textContent = '';
                }

                if (valueNode) {
                    valueNode.textContent = '';
                }

                if (codeLabel) {
                    codeLabel.textContent = '';
                }
            }

            async function fetchVoucher(code) {
                const normalized = String(code || '').trim().toLowerCase();

                if (!normalized) {
                    showFeedback(feedback, escapeHtml(i18n__('Enter a voucher code to continue.', 'fp-experiences')), true);

                    return;
                }

                if (lookupLoading) {
                    return;
                }

                lookupLoading = true;
                toggleButton(lookupButton, true);
                clearFeedbackMessages();
                resetDetails();

                try {
                    const response = await fetch(`${pluginConfig.restUrl}gift/voucher/${encodeURIComponent(normalized)}`, {
                        method: 'GET',
                        headers: buildRestHeaders(),
                    });

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok || (data && data.code)) {
                        const message = data && data.message ? data.message : i18n__('We could not find that voucher. Check the code and try again.', 'fp-experiences');
                        showFeedback(feedback, escapeHtml(message), true);

                        return;
                    }

                    currentVoucher = Object.assign({}, data, { code: normalized });

                    if (details) {
                        details.hidden = false;
                    }

                    if (titleNode) {
                        titleNode.textContent = data.experience && data.experience.title ? data.experience.title : '';
                    }

                    if (excerptNode) {
                        excerptNode.innerHTML = data.experience && data.experience.excerpt ? data.experience.excerpt : '';
                    }

                    if (imageNode && imageNode.tagName === 'IMG') {
                        const imageUrl = data.experience && data.experience.image ? data.experience.image : '';
                        if (imageUrl) {
                            imageNode.src = imageUrl;
                            imageNode.alt = data.experience && data.experience.title ? data.experience.title : '';
                            imageNode.hidden = false;
                        } else {
                            imageNode.src = '';
                            imageNode.alt = '';
                            imageNode.hidden = true;
                        }
                    }

                    if (codeLabel) {
                        codeLabel.textContent = normalized.toUpperCase();
                    }

                    if (quantityNode) {
                        const guests = parseInt(String(data.quantity || '0'), 10);
                        quantityNode.textContent = guests > 0 ? guests.toString() : '';
                    }

                    if (validityNode) {
                        validityNode.textContent = data.valid_until_label || '';
                    }

                    if (valueNode) {
                        const formatted = formatCurrency(data.value || 0, data.currency || defaultCurrency);
                        valueNode.textContent = formatted;
                    }

                    if (Array.isArray(data.addons) && data.addons.length && addonsList) {
                        addonsList.innerHTML = '';
                        data.addons.forEach((addon) => {
                            const item = document.createElement('li');
                            const label = addon.label ? String(addon.label) : '';
                            const qty = addon.quantity ? parseInt(String(addon.quantity), 10) : 0;
                            const qtyLabel = qty > 0 ? ` × ${qty}` : '';
                            item.textContent = `${label}${qtyLabel}`;
                            addonsList.appendChild(item);
                        });
                        if (addonsWrapper) {
                            addonsWrapper.hidden = false;
                        }
                    } else {
                        if (addonsList) {
                            addonsList.innerHTML = '';
                        }
                        if (addonsWrapper) {
                            addonsWrapper.hidden = true;
                        }
                    }

                    if (slotSelect) {
                        slotSelect.innerHTML = '';

                        const slots = Array.isArray(data.slots) ? data.slots : [];

                        if (slots.length) {
                            slots.forEach((slot) => {
                                if (!slot || !slot.id) {
                                    return;
                                }

                                const option = document.createElement('option');
                                option.value = String(slot.id);
                                const remaining = typeof slot.remaining === 'number' ? slot.remaining : null;
                                let label = slot.label || i18n__('Available slot', 'fp-experiences');

                                if (remaining !== null && remaining > 0) {
                                    label = `${label} · ${i18n_n('%d spot left', '%d spots left', remaining).replace('%d', remaining)}`;
                                }

                                option.textContent = label;
                                slotSelect.appendChild(option);
                            });

                            slotSelect.disabled = false;
                            if (redeemButton) {
                                redeemButton.disabled = false;
                            }
                            if (detailFeedback) {
                                detailFeedback.hidden = true;
                                detailFeedback.textContent = '';
                            }
                        } else {
                            slotSelect.disabled = true;
                            if (redeemButton) {
                                redeemButton.disabled = true;
                            }
                            showFeedback(detailFeedback, escapeHtml(i18n__('No upcoming slots are available. Please contact the operator to schedule manually.', 'fp-experiences')), true);
                        }
                    }
                } catch (error) {
                    showFeedback(feedback, escapeHtml(i18n__('Unable to load the voucher at this time. Please try again later.', 'fp-experiences')), true);
                } finally {
                    lookupLoading = false;
                    toggleButton(lookupButton, false);
                }
            }

            async function redeemVoucher(event) {
                event.preventDefault();

                if (redeemLoading) {
                    return;
                }

                if (!currentVoucher) {
                    showFeedback(detailFeedback, escapeHtml(i18n__('Look up a voucher before choosing a slot.', 'fp-experiences')), true);

                    return;
                }

                if (!slotSelect || !slotSelect.value) {
                    showFeedback(detailFeedback, escapeHtml(i18n__('Select an available slot to continue.', 'fp-experiences')), true);
                    if (slotSelect) {
                        slotSelect.focus();
                    }

                    return;
                }

                clearFeedbackMessages();
                redeemLoading = true;
                toggleButton(redeemButton, true);
                if (slotSelect) {
                    slotSelect.disabled = true;
                }

                try {
                    const response = await fetch(`${pluginConfig.restUrl}gift/redeem`, {
                        method: 'POST',
                        headers: buildRestHeaders(),
                        body: JSON.stringify({
                            code: currentVoucher.code,
                            slot_id: parseInt(String(slotSelect.value), 10),
                        }),
                    });

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok || (data && data.code)) {
                        const message = data && data.message ? data.message : i18n__('We could not redeem the voucher. Try a different slot or contact support.', 'fp-experiences');
                        showFeedback(detailFeedback, escapeHtml(message), true);
                        if (slotSelect) {
                            slotSelect.disabled = false;
                        }
                        toggleButton(redeemButton, false);

                        return;
                    }

                    const experienceId = currentVoucher.experience && currentVoucher.experience.id ? currentVoucher.experience.id : null;
                    const experienceLink = data.experience && data.experience.permalink ? data.experience.permalink : (currentVoucher.experience && currentVoucher.experience.permalink ? currentVoucher.experience.permalink : '');
                    const experienceTitle = data.experience && data.experience.title ? data.experience.title : (currentVoucher.experience && currentVoucher.experience.title ? currentVoucher.experience.title : '');
                    const quantity = typeof currentVoucher.quantity === 'number' && currentVoucher.quantity > 0 ? currentVoucher.quantity : 1;
                    const addons = Array.isArray(currentVoucher.addons)
                        ? currentVoucher.addons.map((addon) => {
                            if (typeof addon === 'string') {
                                return addon;
                            }

                            if (addon && typeof addon === 'object' && addon.slug) {
                                return String(addon.slug);
                            }

                            return '';
                        }).filter((slug) => slug !== '')
                        : [];
                    const slotId = slotSelect ? parseInt(String(slotSelect.value), 10) : null;
                    const currency = currentVoucher && currentVoucher.currency
                        ? String(currentVoucher.currency)
                        : (pluginConfig.currency || 'EUR');
                    const ecommerce = {
                        value: 0,
                        currency,
                        items: [
                            {
                                item_id: experienceId ? String(experienceId) : '',
                                item_name: experienceTitle || '',
                                quantity,
                                price: 0,
                            },
                        ],
                    };
                    let successMessage = escapeHtml(i18n__('Voucher redeemed! Check your inbox for confirmation.', 'fp-experiences'));

                    if (experienceLink) {
                        const safeLink = encodeURI(experienceLink);
                        const linkLabel = experienceTitle || i18n__('View experience', 'fp-experiences');
                        successMessage += ` <a href="${safeLink}">${escapeHtml(linkLabel)}</a>`;
                    }

                    showFeedback(success, successMessage, false);

                    pushTrackingEvent('gift_redeem', {
                        ecommerce,
                        gift: {
                            code: currentVoucher.code,
                            experienceId,
                            experienceTitle,
                            quantity,
                            addons,
                            slotId: Number.isFinite(slotId) ? slotId : null,
                            reservationId: data && data.reservation_id ? data.reservation_id : null,
                            orderId: data && data.order_id ? data.order_id : null,
                            value: currentVoucher && typeof currentVoucher.value === 'number' ? currentVoucher.value : null,
                            currency,
                        },
                    });

                    if (redeemButton) {
                        toggleButton(redeemButton, false);
                        redeemButton.disabled = true;
                        redeemButton.setAttribute('aria-disabled', 'true');
                    }
                } catch (error) {
                    showFeedback(detailFeedback, escapeHtml(i18n__('We could not redeem the voucher. Try a different slot or contact support.', 'fp-experiences')), true);
                    if (slotSelect) {
                        slotSelect.disabled = false;
                    }
                    toggleButton(redeemButton, false);

                    return;
                } finally {
                    redeemLoading = false;
                }
            }

            resetDetails();
            clearFeedbackMessages();

            if (lookupForm) {
                lookupForm.addEventListener('submit', (event) => {
                    event.preventDefault();
                    fetchVoucher(codeInput ? codeInput.value : '');
                });
            }

            if (redeemForm) {
                redeemForm.addEventListener('submit', redeemVoucher);
            }

            const initialCode = section.getAttribute('data-fp-initial-code');
            if (initialCode) {
                if (codeInput) {
                    codeInput.value = initialCode;
                }
                fetchVoucher(initialCode);
            }
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
        setupGiftRedeem();
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

        const getQuantityBounds = (input) => {
            if (!(input instanceof HTMLInputElement)) {
                return { min: 0, max: null };
            }

            const rawMin = parseInt(input.getAttribute('min') || '', 10);
            const rawMax = parseInt(input.getAttribute('max') || '', 10);

            return {
                min: Number.isNaN(rawMin) ? 0 : rawMin,
                max: Number.isNaN(rawMax) ? null : rawMax,
            };
        };

        const clampQuantityValue = (input, value) => {
            const bounds = getQuantityBounds(input);
            const numeric = Number.isFinite(value) ? value : parseInt(String(value || '0'), 10);
            let nextValue = Number.isNaN(numeric) ? bounds.min : numeric;

            if (Number.isFinite(bounds.min)) {
                nextValue = Math.max(nextValue, bounds.min);
            }

            if (typeof bounds.max === 'number') {
                nextValue = Math.min(nextValue, bounds.max);
            }

            return nextValue;
        };

        const syncQuantityControlState = (input) => {
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            const bounds = getQuantityBounds(input);
            const value = parseInt(input.value || '0', 10);
            const normalized = Number.isNaN(value) ? bounds.min : value;

            const decreaseButton = input.parentElement
                ? input.parentElement.querySelector('.fp-exp-quantity__control[data-action="decrease"]')
                : null;
            const increaseButton = input.parentElement
                ? input.parentElement.querySelector('.fp-exp-quantity__control[data-action="increase"]')
                : null;

            if (decreaseButton) {
                decreaseButton.disabled = normalized <= bounds.min;
            }

            if (increaseButton) {
                increaseButton.disabled = typeof bounds.max === 'number' ? normalized >= bounds.max : false;
            }
        };

        const syncAllQuantityControls = () => {
            container.querySelectorAll('.fp-exp-quantity__input').forEach((input) => {
                syncQuantityControlState(input);
            });
        };

        const updateTicketQuantityState = (input, nextValue) => {
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            const ticketRow = input.closest('tr[data-ticket]');
            if (!ticketRow) {
                return;
            }

            const slug = ticketRow.getAttribute('data-ticket');
            if (!slug) {
                return;
            }

            state.tickets[slug] = nextValue;
        };

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
        syncAllQuantityControls();

        if (typeof window !== 'undefined' && 'MutationObserver' in window) {
            const ticketsTable = container.querySelector('.fp-exp-party-table');
            if (ticketsTable) {
                const quantityObserver = new MutationObserver(() => syncAllQuantityControls());
                quantityObserver.observe(ticketsTable, { childList: true, subtree: true });
                window.addEventListener('beforeunload', () => quantityObserver.disconnect(), { once: true });
            }
        }

        const addonElements = Array.from(container.querySelectorAll('.fp-exp-addon'));
        if (addonElements.length) {
            const seenAddons = new Set();
            const observer = typeof window !== 'undefined' && 'IntersectionObserver' in window
                ? new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) {
                            return;
                        }

                        const target = entry.target;
                        const slug = target.getAttribute('data-addon');

                        if (!slug || seenAddons.has(slug)) {
                            return;
                        }

                        seenAddons.add(slug);

                        const labelEl = target.querySelector('.fp-exp-addon__label');
                        const priceEl = target.querySelector('.fp-exp-addon__price');
                        const label = labelEl ? labelEl.textContent.trim() : '';
                        const priceValue = priceEl ? parseFloat(priceEl.getAttribute('data-price') || '') : NaN;
                        const ecommerce = buildEcommercePayload(config, { quantity: 1, total: getBasePrice(config) });
                        const payload = Object.assign({
                            addon: {
                                slug,
                                label,
                            },
                            experience: {
                                id: String(config.experienceId || ''),
                                title: config.experienceTitle || '',
                            },
                            ecommerce,
                        }, trackingMeta);

                        if (Number.isFinite(priceValue)) {
                            payload.addon.price = priceValue;
                        }

                        pushTrackingEvent('add_on_view', payload);
                    });
                }, { threshold: 0.4 })
                : null;

            addonElements.forEach((element) => {
                if (observer) {
                    observer.observe(element);
                    return;
                }

                const slug = element.getAttribute('data-addon');
                if (!slug || seenAddons.has(slug)) {
                    return;
                }

                seenAddons.add(slug);

                const labelEl = element.querySelector('.fp-exp-addon__label');
                const priceEl = element.querySelector('.fp-exp-addon__price');
                const label = labelEl ? labelEl.textContent.trim() : '';
                const priceValue = priceEl ? parseFloat(priceEl.getAttribute('data-price') || '') : NaN;
                const ecommerce = buildEcommercePayload(config, { quantity: 1, total: getBasePrice(config) });
                const payload = Object.assign({
                    addon: {
                        slug,
                        label,
                    },
                    experience: {
                        id: String(config.experienceId || ''),
                        title: config.experienceTitle || '',
                    },
                    ecommerce,
                }, trackingMeta);

                if (Number.isFinite(priceValue)) {
                    payload.addon.price = priceValue;
                }

                pushTrackingEvent('add_on_view', payload);
            });

            if (observer) {
                window.addEventListener('beforeunload', () => observer.disconnect(), { once: true });
            }
        }

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

        const dayButtons = Array.from(container.querySelectorAll('.fp-exp-calendar__day'));

        dayButtons.forEach((button) => {
            if (!button.disabled && !button.hasAttribute('aria-pressed')) {
                button.setAttribute('aria-pressed', 'false');
            }

            button.addEventListener('click', () => {
                if (button.disabled || button.getAttribute('data-available') !== '1') {
                    return;
                }

                const date = button.getAttribute('data-date');
                if (!date) {
                    return;
                }

                state.selectedDate = date;
                state.selectedSlot = null;

                dayButtons.forEach((day) => {
                    const isActive = day === button;
                    day.classList.toggle('is-selected', isActive);
                    if (!day.disabled) {
                        day.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    }
                });

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
                const input = target.parentElement
                    ? target.parentElement.querySelector('.fp-exp-quantity__input')
                    : null;
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }

                const delta = action === 'increase' ? 1 : -1;
                const currentValue = parseInt(input.value || '0', 10) || 0;
                const proposedValue = currentValue + delta;
                const nextValue = clampQuantityValue(input, proposedValue);

                input.value = String(nextValue);
                syncQuantityControlState(input);
                updateTicketQuantityState(input, nextValue);
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

        container.addEventListener('input', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement) || !target.classList.contains('fp-exp-quantity__input')) {
                return;
            }

            const rawValue = parseInt(target.value || '0', 10);
            const nextValue = clampQuantityValue(target, rawValue);

            if (String(nextValue) !== target.value) {
                target.value = String(nextValue);
            }

            syncQuantityControlState(target);
            updateTicketQuantityState(target, nextValue);
            refreshSummary();
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
            const parsedRemaining = parseInt(slot.remaining, 10);
            const remaining = Number.isNaN(parsedRemaining) ? 0 : parsedRemaining;
            const template = i18n_n('%d spot', '%d spots', remaining);
            let countLabel = template;
            if (typeof i18n_sprintf === 'function') {
                try {
                    countLabel = i18n_sprintf(template, remaining);
                } catch (error) {
                    countLabel = template.replace('%d', String(remaining));
                }
            } else {
                countLabel = template.replace('%d', String(remaining));
            }
            button.textContent = `${slot.time} · ${countLabel}`;
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
