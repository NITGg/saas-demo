# **US-AD-8-3: Deactivate or Delete an Offer**

As an admin,\
I want to deactivate or delete an offer,\
So that I can stop applying the offer or remove unused offers from the platform.
--------------------------------------------------------------------------------

## **Flow**

## 🔧 Admin → Opens offer management🔧 Admin → Selects an offer🔧 Admin → Chooses "Deactivate" or "Delete"

### **If Deactivate is selected**

## ⚙️ System → Displays a confirmation message🔧 Admin → Confirms the action⚙️ System → Changes the offer status to "Inactive"⚙️ System → Prevents the offer from being applied to future purchases

### **If Delete is selected**

## ⚙️ System → Checks whether the offer has ever been used

#### **If the offer has never been used**

## ⚙️ System → Displays a deletion confirmation message🔧 Admin → Confirms the deletion⚙️ System → Permanently deletes the offer⚙️ System → Removes the offer from offer management

#### **If the offer has been used**

## ⚙️ System → Prevents the deletion⚙️ System → Displays a message that the offer can only be deactivated

## **Notes**

## - Deactivated offers are not applied at checkout.

- Existing purchases that used the offer remain unchanged.

- An offer can only be deleted if it has never been used.

- Offers with usage records cannot be deleted.

- Used offers must be deactivated instead.

- Deletion is permanent and cannot be undone.
