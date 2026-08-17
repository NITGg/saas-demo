## **US-US-OF-1-2: Apply Offer Automatically**

**As a** user,\
**I want to** get the offer automatically during checkout,\
**So that** I receive the discounted price.
-------------------------------------------

## **Flow**

## 1. 🎓 User → Selects item (course / package / subscription)

2. 🎓 User → Opens checkout

3. ⚙️ System → Validates:

   - Offer is active

   - Within date range

   - Applies to this specific item

4. ⚙️ System → Calculates discount:

   - Applies percentage or fixed value

   - Ensures final price ≥ 0

5. ⚙️ System → Displays final price

6. 🎓 User → Completes payment

## **Notes**

## - Offer is applied automatically.

- If multiple offers exist, only one is applied (system rule).

- Offer works for multi-course targeting (only selected courses get discount).

#
