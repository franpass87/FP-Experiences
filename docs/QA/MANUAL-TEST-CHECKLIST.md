# FP Experiences - Manual Test Checklist

This document provides comprehensive manual testing checklists for all modules of the FP Experiences plugin.

## Pre-Testing Setup

- [ ] WordPress 6.2+ installed
- [ ] PHP 8.0+ active
- [ ] WooCommerce 7.0+ installed and active
- [ ] Plugin activated
- [ ] Database tables created
- [ ] Test experience created
- [ ] Admin user logged in

---

## 1. Core Services Module

### Logger Service
- [ ] Log messages appear in debug log (WP_DEBUG enabled)
- [ ] Different log levels work (debug, info, warning, error)
- [ ] Log context data included
- [ ] Log file rotation works (if enabled)

### Cache Service
- [ ] Cache stores and retrieves data correctly
- [ ] Cache expires after TTL
- [ ] Cache clears on demand
- [ ] No cache key collisions

### Options Service
- [ ] Settings save correctly
- [ ] Settings load correctly
- [ ] Default values applied when missing
- [ ] Settings persist across page loads

### Security Services
- [ ] Nonces generated uniquely
- [ ] Nonces verify correctly
- [ ] Invalid nonces rejected
- [ ] Capability checks block unauthorized access

---

## 2. Booking System Module

### Slot Management
- [ ] Create slot for experience occurrence
- [ ] Slot capacity calculated correctly
- [ ] Overlapping slots prevented
- [ ] Buffer conflicts detected
- [ ] Slot deletion works
- [ ] Slot capacity updates correctly
- [ ] Remaining capacity calculated correctly

### Availability Calculation
- [ ] Single date availability shown
- [ ] Date range availability works
- [ ] Recurring availability generated
- [ ] Capacity-based availability correct
- [ ] Buffer time considered
- [ ] Timezone handling correct

### Cart Management
- [ ] Add experience to cart
- [ ] Remove from cart
- [ ] Update quantity
- [ ] Cart persists in session
- [ ] Cart syncs with WooCommerce
- [ ] Cart expires after timeout

### Checkout Process
- [ ] Checkout form renders
- [ ] Form validation works
- [ ] Nonce verification works
- [ ] Order created in WooCommerce
- [ ] Reservation created
- [ ] Confirmation email sent
- [ ] Payment processing works

### Pricing Calculation
- [ ] Base price correct
- [ ] Participant-based pricing works
- [ ] Discounts apply correctly
- [ ] Currency formatting correct

---

## 3. Gift Voucher Module

### Voucher Creation
- [ ] Voucher generated with unique code
- [ ] Expiration date set correctly
- [ ] Amount/percentage configured
- [ ] Metadata saved

### Voucher Redemption
- [ ] Valid voucher redeems
- [ ] Invalid code rejected
- [ ] Expired voucher rejected
- [ ] Already redeemed voucher rejected
- [ ] Discount applies to cart

### Voucher Delivery
- [ ] Email sent on creation
- [ ] Email template renders correctly
- [ ] Personalization works
- [ ] Delivery failures handled

---

## 4. REST API Module

### Availability Endpoints
- [ ] GET /availability/{id} returns data
- [ ] Date range filtering works
- [ ] Invalid experience ID returns 404
- [ ] Authentication required
- [ ] Response format valid JSON

### Calendar Endpoints
- [ ] GET /calendar/{id} returns calendar data
- [ ] Month view data correct
- [ ] Slot details included
- [ ] Timezone handling correct

### Checkout Endpoints
- [ ] POST /checkout/cart-set works
- [ ] POST /checkout/process creates order
- [ ] Nonce verification works
- [ ] Error handling works

### Gift Endpoints
- [ ] POST /gift/redeem validates voucher
- [ ] Redemption processes correctly
- [ ] Error responses formatted correctly

---

## 5. Shortcodes Module

### List Shortcode
- [ ] [fp_experiences_list] renders
- [ ] Pagination works
- [ ] Filtering works
- [ ] Sorting works
- [ ] Responsive on mobile

### Experience Shortcode
- [ ] [fp_experience id="X"] renders
- [ ] All meta fields displayed
- [ ] Booking form integrated
- [ ] Image gallery works

### Calendar Shortcode
- [ ] [fp_experience_calendar] renders
- [ ] Month navigation works
- [ ] Slot selection works
- [ ] Availability indicated

### Checkout Shortcode
- [ ] [fp_experience_checkout] renders
- [ ] Form validation works
- [ ] Cart displayed
- [ ] Payment integration works

---

## 6. Integrations Module

### Google Calendar
- [ ] Connection test works
- [ ] Event created on booking
- [ ] Event updated on modification
- [ ] Event deleted on cancellation

### Brevo Email
- [ ] Connection test works
- [ ] Emails sent successfully
- [ ] Templates render correctly
- [ ] Delivery status tracked

### GA4 Tracking
- [ ] Events tracked on booking
- [ ] Events tracked on checkout
- [ ] Custom parameters included
- [ ] Tracking disabled when configured

### WooCommerce Integration
- [ ] Product created from experience
- [ ] Cart integration works
- [ ] Checkout integration works
- [ ] Order creation works

---

## 7. Admin UI Module

### Settings Page
- [ ] Settings form renders
- [ ] Settings save successfully
- [ ] Settings validation works
- [ ] Tab navigation works

### Experience Meta Boxes
- [ ] All meta boxes visible
- [ ] Meta fields save correctly
- [ ] Validation works
- [ ] Conditional fields show/hide

### Calendar Admin
- [ ] Calendar displays correctly
- [ ] Slot creation works
- [ ] Slot editing works
- [ ] Slot deletion works

### Orders Page
- [ ] Order listing works
- [ ] Filtering works
- [ ] Search works
- [ ] Order details display

---

## 8. Frontend QA

### HTML Structure
- [ ] Valid HTML5 markup
- [ ] Semantic elements used
- [ ] Heading hierarchy correct
- [ ] Alt text on images

### Responsiveness
- [ ] Mobile layout (320px+)
- [ ] Tablet layout (768px+)
- [ ] Desktop layout (1024px+)
- [ ] Touch targets adequate

### Accessibility
- [ ] Keyboard navigation works
- [ ] Screen reader compatible
- [ ] Color contrast adequate
- [ ] Focus indicators visible

### JavaScript
- [ ] Works with JS enabled
- [ ] Graceful degradation without JS
- [ ] No console errors
- [ ] AJAX requests handle errors

---

## 9. Security Testing

### Nonce Validation
- [ ] All forms have nonces
- [ ] All AJAX requests have nonces
- [ ] Invalid nonces rejected
- [ ] Nonce expiration handled

### Capability Checks
- [ ] Admin actions check capabilities
- [ ] REST API endpoints check capabilities
- [ ] Unauthorized access blocked

### Input Sanitization
- [ ] All inputs sanitized
- [ ] SQL injection prevented
- [ ] XSS prevented

### Output Escaping
- [ ] All outputs escaped
- [ ] Context-appropriate escaping
- [ ] No XSS vulnerabilities

---

## 10. Performance Testing

### Load Times
- [ ] Admin pages load < 2s
- [ ] Frontend pages load < 3s
- [ ] REST API responses < 500ms

### Database Queries
- [ ] Query count minimized
- [ ] No N+1 queries
- [ ] Queries optimized

### Asset Loading
- [ ] CSS/JS minified
- [ ] Assets load in correct order
- [ ] No render-blocking resources

---

## 11. Cross-Browser Testing

- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile Safari (iOS)
- [ ] Chrome Mobile (Android)

---

## 12. Multilanguage Testing

- [ ] All strings translated
- [ ] Date/time formats localized
- [ ] Currency formatting correct
- [ ] Language switching works
- [ ] Fallback to default language

---

## Test Results Template

**Date:** _______________
**Tester:** _______________
**Version:** _______________

**Total Tests:** _______________
**Passed:** _______________
**Failed:** _______________
**Skipped:** _______________

**Issues Found:**
1. 
2. 
3. 

**Notes:**
_________________________________________________________________
_________________________________________________________________







