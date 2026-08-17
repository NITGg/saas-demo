## **US-AD-7-1: Create a Coupon**

**As an** admin,\
**I want to** create a coupon,\
**So that** users can get discounts on specific items.
======================================================

## **Flow**

# 1. 🔧 Admin → Opens coupon management

2. 🔧 Admin → Selects `Create Coupon`

3. 🔧 Admin → Enters coupon code

4. 🔧 Admin → Selects discount type (percentage / fixed)

5. 🔧 Admin → Enters discount value

6. 🔧 Admin → Sets max discount amount

7. 🔧 Admin → Selects applicable types:

   - Courses

   - Packages

   - Subscriptions

   - Any combination

8. 🔧 Admin → Selects target scope:

   - All courses or selected courses

   - All packages or selected packages

   - All subscriptions or selected subscriptions

9. 🔧 Admin → Sets start date and end date

10. 🔧 Admin → Sets usage type (one-time / multiple use)

11. 🔧 Admin → Activates the coupon

12. ⚙️ System → Saves the coupon

## **Notes**

# - Coupon code must be unique.

- Discount cannot exceed item price.

- Max discount limits the applied discount.

- Coupon can target one or multiple item types.

- Coupon can apply to all or selected items.

- Coupon is valid only between start and end date.

###
