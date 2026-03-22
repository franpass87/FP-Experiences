/**
 * FP Experiences — Tracking module (dataLayer / GTM)
 *
 * Centralises every dataLayer.push() so the rest of the codebase only calls
 * thin helpers exposed on window.FPFront.tracking.
 *
 * Guard: every public method silently no-ops when GA4/GTM tracking is
 * disabled in the plugin settings (fpExpConfig.tracking.enabled.ga4).
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /*  Internal helpers                                                    */
    /* ------------------------------------------------------------------ */

    function isEnabled() {
        return !!(
            typeof fpExpConfig !== 'undefined' &&
            fpExpConfig.tracking &&
            fpExpConfig.tracking.enabled &&
            fpExpConfig.tracking.enabled.ga4
        );
    }

    function pushEvent(eventName, ecommerce) {
        if (!isEnabled()) {
            return;
        }
        window.dataLayer = window.dataLayer || [];
        // Clear previous ecommerce object to avoid merging
        window.dataLayer.push({ ecommerce: null });
        window.dataLayer.push({
            event: eventName,
            ecommerce: ecommerce
        });
    }

    function pushCustomEvent(eventName, data) {
        if (!isEnabled()) {
            return;
        }
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push(Object.assign({ event: eventName }, data || {}));
    }

    // Funnel events bypass the isEnabled() guard — they must reach the dataLayer
    // regardless of the GA4 tracking toggle (GTM decides what to do with them).
    function pushFunnelEvent(eventName, data) {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push(Object.assign({ event: eventName }, data || {}));
    }

    function getCurrency() {
        if (typeof fpExpConfig !== 'undefined' && fpExpConfig.currency) {
            return fpExpConfig.currency;
        }
        return 'EUR';
    }

    /* ------------------------------------------------------------------ */
    /*  GA4 Enhanced Ecommerce helpers                                      */
    /* ------------------------------------------------------------------ */

    /**
     * view_item_list — fired when the experience listing is shown.
     *
     * @param {Array} items  Array of {item_id, item_name, index, price}
     */
    function viewItemList(items) {
        if (!items || !items.length) {
            return;
        }
        pushEvent('view_item_list', {
            item_list_id: 'fp_experiences',
            item_list_name: 'Experiences',
            items: items.map(function (item, idx) {
                return {
                    item_id: String(item.item_id || ''),
                    item_name: String(item.item_name || ''),
                    index: item.index || idx + 1,
                    price: item.price != null ? Number(item.price) : undefined,
                    quantity: 1
                };
            })
        });
    }

    /**
     * select_item — fired when a visitor clicks an experience card.
     *
     * @param {Object} item  {item_id, item_name, index, price}
     */
    function selectItem(item) {
        if (!item) {
            return;
        }
        pushEvent('select_item', {
            item_list_id: 'fp_experiences',
            item_list_name: 'Experiences',
            items: [{
                item_id: String(item.item_id || ''),
                item_name: String(item.item_name || ''),
                index: item.index || 0,
                price: item.price != null ? Number(item.price) : undefined,
                quantity: 1
            }]
        });
    }

    /**
     * add_to_cart — fired after the cart/set REST call succeeds.
     *
     * @param {Object} data  {item_id, item_name, price, quantity, currency}
     */
    function addToCart(data) {
        if (!data) {
            return;
        }
        var currency = data.currency || getCurrency();
        var price = data.price != null ? Number(data.price) : 0;
        var quantity = data.quantity || 1;
        pushEvent('add_to_cart', {
            currency: currency,
            value: price * quantity,
            items: [{
                item_id: String(data.item_id || ''),
                item_name: String(data.item_name || ''),
                price: price,
                quantity: quantity
            }]
        });
    }

    /**
     * begin_checkout — fired when the checkout page loads.
     *
     * @param {Object} data  {items, value, currency}
     */
    function beginCheckout(data) {
        if (!data) {
            return;
        }
        pushEvent('begin_checkout', {
            currency: data.currency || getCurrency(),
            value: data.value != null ? Number(data.value) : 0,
            items: (data.items || []).map(function (item) {
                return {
                    item_id: String(item.item_id || ''),
                    item_name: String(item.item_name || ''),
                    price: item.price != null ? Number(item.price) : 0,
                    quantity: item.quantity || 1
                };
            })
        });
    }

    /**
     * gift_purchase — fired before redirecting to WooCommerce checkout
     * for a gift voucher purchase.
     *
     * @param {Object} data  {experience_id, experience_name, quantity, value, currency}
     */
    function giftPurchase(data) {
        if (!data) {
            return;
        }
        pushEvent('gift_purchase', {
            currency: data.currency || getCurrency(),
            value: data.value != null ? Number(data.value) : 0,
            items: [{
                item_id: String(data.experience_id || ''),
                item_name: String(data.experience_name || ''),
                item_category: 'gift_voucher',
                quantity: data.quantity || 1
            }]
        });
    }

    /**
     * RTB (Request to Book) lifecycle events.
     */
    function rtbSubmit(data) {
        pushCustomEvent('fpExp.request_submit', {
            experience_id: data && data.experience_id ? String(data.experience_id) : '',
            mode: data && data.mode ? String(data.mode) : ''
        });
    }

    function rtbSuccess(data) {
        pushCustomEvent('fpExp.request_success', {
            experience_id: data && data.experience_id ? String(data.experience_id) : '',
            request_id: data && data.request_id ? String(data.request_id) : ''
        });
    }

    function rtbError(data) {
        pushCustomEvent('fpExp.request_error', {
            experience_id: data && data.experience_id ? String(data.experience_id) : '',
            error: data && data.error ? String(data.error) : ''
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Funnel events — standardised for FP-Marketing-Tracking-Layer       */
    /* ------------------------------------------------------------------ */

    function getExperienceId() {
        if (typeof fpExpConfig !== 'undefined' && fpExpConfig.experienceId) {
            return String(fpExpConfig.experienceId);
        }
        var el = document.querySelector('[data-fp-exp-id]');
        return el ? String(el.getAttribute('data-fp-exp-id') || '') : '';
    }

    function getExperienceTitle() {
        if (typeof fpExpConfig !== 'undefined' && fpExpConfig.experienceTitle) {
            return String(fpExpConfig.experienceTitle);
        }
        var el = document.querySelector('[data-fp-exp-title]');
        return el ? String(el.getAttribute('data-fp-exp-title') || '') : '';
    }

    /** booking_start — utente interagisce per la prima volta con il widget (data/biglietti) */
    function bookingStart(data) {
        pushFunnelEvent('booking_start', Object.assign({
            experience_id: getExperienceId(),
            experience_title: getExperienceTitle()
        }, data || {}));
    }

    /** booking_step_complete — utente completa uno step del flusso (slot, biglietti, checkout) */
    function bookingStepComplete(data) {
        pushFunnelEvent('booking_step_complete', Object.assign({
            experience_id: getExperienceId(),
            experience_title: getExperienceTitle()
        }, data || {}));
    }

    /** booking_date_selected — utente seleziona una data dal calendario/date picker */
    function bookingDateSelected(data) {
        pushFunnelEvent('booking_date_selected', Object.assign({
            experience_id: getExperienceId(),
            experience_title: getExperienceTitle()
        }, data || {}));
    }

    /** booking_slot_selected — utente seleziona uno slot orario */
    function bookingSlotSelected(data) {
        pushFunnelEvent('booking_slot_selected', Object.assign({
            experience_id: getExperienceId(),
            experience_title: getExperienceTitle()
        }, data || {}));
    }

    /** booking_abandon — utente lascia la pagina dopo aver iniziato il flusso */
    function bookingAbandon(data) {
        pushFunnelEvent('booking_abandon', Object.assign({
            experience_id: getExperienceId(),
            experience_title: getExperienceTitle()
        }, data || {}));
    }

    /** rtb_start — utente inizia a compilare il form RTB */
    function rtbStart(data) {
        pushFunnelEvent('rtb_start', Object.assign({
            experience_id: getExperienceId(),
            experience_title: getExperienceTitle()
        }, data || {}));
    }

    /** gift_start — utente apre il modal/form acquisto gift */
    function giftStart(data) {
        pushFunnelEvent('gift_start', Object.assign({
            experience_id: getExperienceId(),
            experience_title: getExperienceTitle()
        }, data || {}));
    }

    /* ------------------------------------------------------------------ */
    /*  Auto-init DOM listeners for funnel events                          */
    /* ------------------------------------------------------------------ */

    function initFunnelListeners() {
        var _bookingStarted  = false;
        var _bookingDone     = false;
        var _rtbStarted      = false;
        var _rtbDone         = false;
        var _giftStarted     = false;

        // ---- BOOKING (WooCommerce checkout flow) ----
        var widget = document.querySelector('[data-fp-shortcode="experience"]');
        if (widget) {
            // booking_start: prima interazione con slot o biglietti
            widget.addEventListener('focusin', function(e) {
                if (_bookingStarted) return;
                var target = e.target;
                if (!target) return;
                if (
                    target.closest('.fp-exp-slots__item') ||
                    target.closest('.fp-exp-quantity__input') ||
                    target.closest('.fp-exp-party-table')
                ) {
                    _bookingStarted = true;
                    bookingStart({ step: 'slot_selection' });
                }
            }, true);

            widget.addEventListener('click', function(e) {
                if (_bookingStarted) return;
                var target = e.target;
                if (!target) return;
                if (target.closest('.fp-exp-slots__item') || target.closest('.fp-exp-calendar')) {
                    _bookingStarted = true;
                    bookingStart({ step: 'slot_selection' });
                }
            });

            // booking_step_complete: slot selezionato
            var slotsEl = widget.querySelector('.fp-exp-slots');
            if (slotsEl) {
                slotsEl.addEventListener('click', function(e) {
                    if (e.target && e.target.closest('.fp-exp-slots__item')) {
                        bookingStepComplete({ step: 'slot_selected' });
                    }
                });
            }

            // booking_step_complete: CTA "Procedi al pagamento" cliccato
            var ctaBtn = widget.querySelector('.fp-exp-summary__cta');
            if (ctaBtn) {
                ctaBtn.addEventListener('click', function() {
                    if (!ctaBtn.disabled) {
                        _bookingDone = true;
                        bookingStepComplete({ step: 'checkout_started' });
                    }
                });
            }

            // booking_abandon: lascia la pagina dopo aver iniziato
            window.addEventListener('beforeunload', function() {
                if (_bookingStarted && !_bookingDone) {
                    bookingAbandon({ step: 'abandoned' });
                }
            });
        }

        // ---- RTB (Request to Book) ----
        var rtbForm = document.querySelector('form.fp-exp-rtb-form');
        if (rtbForm) {
            // rtb_start: prima interazione con il form RTB
            rtbForm.addEventListener('focusin', function() {
                if (_rtbStarted) return;
                _rtbStarted = true;
                rtbStart();
            }, true);

            // booking_step_complete: CTA RTB cliccato
            var rtbCta = document.querySelector('.fp-exp-summary__cta');
            if (rtbCta) {
                rtbCta.addEventListener('click', function() {
                    if (!rtbCta.disabled) {
                        bookingStepComplete({ step: 'rtb_submitted' });
                    }
                });
            }

            // Segna come completato quando la risposta RTB è positiva
            document.addEventListener('fpExp:rtbSuccess', function() {
                _rtbDone = true;
            });

            window.addEventListener('beforeunload', function() {
                if (_rtbStarted && !_rtbDone) {
                    bookingAbandon({ step: 'rtb_abandoned' });
                }
            });
        }

        // ---- GIFT ----
        var giftToggle = document.querySelector('[data-fp-gift-toggle]');
        var giftForm   = document.querySelector('[data-fp-gift-form]');
        if (giftToggle) {
            giftToggle.addEventListener('click', function() {
                if (_giftStarted) return;
                _giftStarted = true;
                giftStart();
            });
        } else if (giftForm) {
            // Fallback: prima interazione col form gift
            giftForm.addEventListener('focusin', function() {
                if (_giftStarted) return;
                _giftStarted = true;
                giftStart();
            }, true);
        }
    }

    // Avvia i listener quando il DOM è pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFunnelListeners);
    } else {
        initFunnelListeners();
    }

    /* ------------------------------------------------------------------ */
    /*  Expose on global namespace                                          */
    /* ------------------------------------------------------------------ */

    window.FPFront = window.FPFront || {};
    window.FPFront.tracking = {
        isEnabled: isEnabled,
        pushEvent: pushEvent,
        pushCustomEvent: pushCustomEvent,
        viewItemList: viewItemList,
        selectItem: selectItem,
        addToCart: addToCart,
        beginCheckout: beginCheckout,
        giftPurchase: giftPurchase,
        rtbSubmit: rtbSubmit,
        rtbSuccess: rtbSuccess,
        rtbError: rtbError,
        // Funnel
        bookingStart: bookingStart,
        bookingStepComplete: bookingStepComplete,
        bookingDateSelected: bookingDateSelected,
        bookingSlotSelected: bookingSlotSelected,
        bookingAbandon: bookingAbandon,
        rtbStart: rtbStart,
        giftStart: giftStart
    };

})();
