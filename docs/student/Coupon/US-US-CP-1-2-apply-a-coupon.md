## **US-US-CP-1-2: Apply a Coupon**

**As a** user,\
**I want to** apply a coupon during checkout,\
**So that** I get a discount.
=============================

## **Flow**

# 1. 🎓 User → Selects item (course / package / subscription)

2. 🎓 User → Opens checkout

3. 🎓 User → Enters coupon code

4. ⚙️ System → Validates:

   - Coupon is active

   - Within date range

   - Within usage limits

   - Matches item type (course/package/subscription)

   - Matches target scope (all or selected items)

5. ⚙️ System → Calculates discount:

   - Applies percentage or fixed value

   - Applies max discount limit

6. ⚙️ System → Displays final price

7. 🎓 User → Completes payment

8. ⚙️ System → Records coupon usage

## **Example**

- Price = 200 EGP

- Discount = 50% → 100 EGP

- Max discount = 50 EGP\
  → Final discount = 50 EGP
===========================

## **Notes**

# - Coupon must match both type and target scope.

- Coupon cannot be used beyond its usage limit.

- Discount is applied before payment.

#
