# **US-AD-7-3: Deactivate or Delete a Coupon**

As an admin,\
I want to deactivate or delete a coupon,\
So that I can stop coupon usage or remove unused coupons from the platform.
===========================================================================

## **Flow**

# 🔧 Admin → Opens coupon management🔧 Admin → Selects a coupon🔧 Admin → Chooses "Deactivate" or "Delete"

### **If Deactivate is selected**

# ⚙️ System → Displays a confirmation message🔧 Admin → Confirms the action⚙️ System → Changes the coupon status to "Inactive"⚙️ System → Prevents the coupon from being used in future purchases

### **If Delete is selected**

# ⚙️ System → Checks whether the coupon has ever been used

#### **If the coupon has never been used**

# ⚙️ System → Displays a deletion confirmation message🔧 Admin → Confirms the deletion⚙️ System → Permanently deletes the coupon⚙️ System → Removes the coupon from coupon management

#### **If the coupon has been used**

# ⚙️ System → Prevents the deletion⚙️ System → Displays a message that the coupon can only be deactivated

## **Notes**

# - Deactivated coupons cannot be used in future purchases.

- A coupon can only be deleted if it has never been used.

- Coupons with usage records cannot be deleted.

- Used coupons must be deactivated instead.

- Deletion is permanent and cannot be undone.

#

###
