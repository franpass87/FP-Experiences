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
        rtbError: rtbError
    };

})();
