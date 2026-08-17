# **US-AD-5-3: Deactivate or Delete a Subscription Plan**

As an admin,\
I want to deactivate or delete a subscription plan,\
So that I can manage subscription availability and remove unnecessary plans.
============================================================================

## **Flow**

# 🔧 Admin → Opens subscription management🔧 Admin → Selects a subscription plan🔧 Admin → Chooses "Deactivate" or "Delete"

### **If Deactivate is selected**

# ⚙️ System → Displays a confirmation message🔧 Admin → Confirms the action⚙️ System → Changes the subscription status to "Inactive"⚙️ System → Removes the subscription from the available subscriptions list⚙️ System → Prevents new users from purchasing the subscription

### **If Delete is selected**

# ⚙️ System → Checks the subscription's purchase, payment, and usage history

#### **If the subscription has never been purchased or used**

# ⚙️ System → Displays a deletion confirmation message🔧 Admin → Confirms the deletion⚙️ System → Permanently deletes the subscription⚙️ System → Removes the subscription from subscription management

#### **If the subscription has purchase, payment, or usage records**

# ⚙️ System → Prevents the deletion⚙️ System → Displays a message that the subscription can only be deactivated

## **Notes**

# - Existing student subscriptions remain active until expiration.

- Deactivating a subscription does not affect existing subscribers.

- New users cannot purchase an inactive subscription.

- The admin can reactivate a deactivated subscription later.

- A subscription can only be deleted if it has never been purchased or used.

- A subscription with purchase, payment, or usage records can only be deactivated.

- Deletion is permanent and cannot be undone.

###
