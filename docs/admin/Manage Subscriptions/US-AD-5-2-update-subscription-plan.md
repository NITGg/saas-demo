# **US-AD-5-2: Update a Subscription Plan**

**As an** admin,\
**I want to** update a subscription plan and its offer and B2B settings,\
**So that** I can control pricing, promotions, and B2B behavior.
================================================================

## **Flow**

# 1. 🔧 Admin → Opens subscription management

2. 🔧 Admin → Selects a subscription***

## **Basic Info Update**

# 3. 🔧 Admin → Updates name, price, duration, or description***

## **Offer Update (Optional)**

# 4. 🔧 Admin → Enables / disables offer

5. 🔧 Admin → Updates discount type, value, or dates***

## **B2B Update**

# 6. 🔧 Admin → Enables or disables B2B purchase

7. 🔧 Admin → If B2B is enabled:

   - Adds new seat options

   - Updates existing seat options

   - Removes seat options

8. 🔧 Admin → Updates discount percentage for each seat option

9. ⚙️ System → Recalculates B2B prices***

## **Save Changes**

# 10. 🔧 Admin → Saves changes

11. ⚙️ System → Updates the subscription

12. ⚙️ System → Applies changes to future purchases***

## **Notes**

### **General Rules**

# - Changes apply only to future purchases.

- Existing subscriptions are not affected.

### **Offer Rules**

# - Offer is optional.

- Only one offer per subscription.

- Offer applies only to normal purchase.

- Offer does NOT apply to B2B pricing.

- Offer is active only between start and end date.

- Final price cannot be less than zero.

### **B2B Rules**

# - Updating price affects future B2B purchases.

- Updating seat options affects only new purchases.

- Removing a seat option does not affect existing subscriptions.

- Disabling B2B prevents future B2B purchases only.

###
