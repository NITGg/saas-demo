## **US-B2B-1-7: Remove an Approved User**

**As a B2B administrator,**I want to remove an approved user ,\
So that the user no longer has access through my B2B subscription.
------------------------------------------------------------------

### **Flow**

🏢 B2B Administrator → Opens the approved members list\
🏢 B2B Administrator → Selects a user\
🏢 B2B Administrator → Selects **Remove**🏢 B2B Administrator → Confirms the action\
⚙️ System → Changes the membership status to `Removed
`⚙️ System → Revokes the user's B2B subscription access\
⚙️ System → Checks the Seat-Return setting\
⚙️ System → If enabled, returns the user's seat\
⚙️ System → If disabled, keeps the seat counted as consumed\
⚙️ System → Updates the used and available capacity\
⚙️ System → Notifies the user 
------------------------------

### **Seat-Return Setting**

## Return seat after removing a user :- Enabled- Disabled

### **When Seat Return Is Enabled**

## - The removed user no longer consumes a seat.

- The available capacity increases by one.

- Another user can be approved in that seat.

### **When Seat Return Is Disabled**

## - The removed user no longer has access.

- The consumed seat remains counted.

- The available capacity does not increase.

- The seat remains consumed until the B2B subscription expires.

### **Notes**

## - The removed user is treated as unsubscribed only from this B2B subscription.

- Removing the user must immediately revoke the access granted by this B2B membership.

- Removing the user must not cancel or affect any separate Normal subscription owned by the user .

- Removing the user must not affect access granted by another valid B2B subscription, when multiple B2B memberships are supported.

- Removing the user must not delete the user's account.

- The user's main role not change.

- The user's B2B membership status changes from `Approved` to `Removed`.

- A membership with status `Removed` no longer grants access to the B2B subscription or its eligible courses.

- The membership and removal history must be preserved.

- The system should record who removed the user and when.

- Removing the same user more than once must not revoke access repeatedly or update the seat count multiple times.

###
