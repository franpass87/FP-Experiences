# Use Cases Inventory

**Date**: 2025-01-XX  
**Status**: Complete identification of all use cases needed

## Booking Domain

### ✅ Completed Use Cases

1. **CheckAvailabilityUseCase** - Check slot availability
2. **CreateReservationUseCase** - Create a reservation
3. **GetSlotsUseCase** - Get slots for experience/date range
4. **UpdateSlotUseCase** - Update slot details
5. **MoveSlotUseCase** - Move slot to new time
6. **UpdateSlotCapacityUseCase** - Update slot capacity

### ⏳ Missing Use Cases

1. **ProcessCheckoutUseCase** - Process checkout (from Checkout.php)
   - Create WooCommerce order
   - Validate cart
   - Handle payment
   - Create reservations

2. **CancelReservationUseCase** - Cancel a reservation
   - Release capacity
   - Handle refunds
   - Update status

3. **GetReservationUseCase** - Get reservation details
   - Retrieve by ID
   - Include related data

4. **UpdateReservationUseCase** - Update reservation
   - Change participants
   - Update addons
   - Modify quantity

5. **GetExperienceUseCase** - Get experience details
   - Retrieve experience data
   - Include pricing
   - Include availability

6. **ListExperiencesUseCase** - List experiences with filters
   - Apply filters
   - Pagination
   - Sorting

7. **GetCartUseCase** - Get cart contents
   - Retrieve cart items
   - Calculate totals

8. **AddToCartUseCase** - Add item to cart
   - Validate availability
   - Add to session

9. **RemoveFromCartUseCase** - Remove item from cart
   - Remove from session
   - Release locks

## Gift Domain

### ✅ Completed Use Cases

1. **CreateVoucherUseCase** - Create gift voucher
2. **RedeemVoucherUseCase** - Redeem gift voucher

### ⏳ Missing Use Cases

1. **GetVoucherUseCase** - Get voucher details
2. **ValidateVoucherUseCase** - Validate voucher code
3. **SendVoucherEmailUseCase** - Send voucher email
4. **ScheduleVoucherDeliveryUseCase** - Schedule voucher delivery

## Settings Domain

### ✅ Completed Use Cases

1. **GetSettingsUseCase** - Get plugin settings
2. **UpdateSettingsUseCase** - Update plugin settings

## Order Management

### ⏳ Missing Use Cases

1. **CreateOrderUseCase** - Create WooCommerce order from cart
2. **CompleteOrderUseCase** - Complete order processing
3. **CancelOrderUseCase** - Cancel order and release resources
4. **GetOrderUseCase** - Get order details

## Summary

- **Total Use Cases Needed**: ~20
- **Completed**: 8
- **Missing**: 12
- **Priority**: High (ProcessCheckout, CancelReservation, GetExperience)

---

**Next Steps**: Create high-priority use cases first, then gradually add others.







