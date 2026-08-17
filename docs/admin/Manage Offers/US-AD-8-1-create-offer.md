## **US-AD-8-1: Create an Offer**

**As an** admin,\
**I want to** create an offer,\
**So that** users can get automatic discounts on specific items.
================================================================

## **Flow**

# 1. 🔧 Admin → Opens offer management

2. 🔧 Admin → Selects `Create Offer`

3. 🔧 Admin → Enters offer name

4. 🔧 Admin → Selects discount type (percentage / fixed)

5. 🔧 Admin → Enters discount value

6. 🔧 Admin → Selects applicable types:

   - Courses

   - Packages

   - Subscriptions

   - Any combination

7. 🔧 Admin → Selects target scope:

   - All courses or selected courses

   - All packages or selected packages

   - All subscriptions or selected subscriptions

8. 🔧 Admin → Sets start date and end date

9. 🔧 Admin → Activates the offer

10. ⚙️ System → Saves the offer

## **Notes**

# - Offer does not require a code.

- Only one active offer can apply per item.

- Offer is applied automatically during its valid period.

- Offer can target one or multiple items.

- Offer is valid only between start and end date.

#

###
