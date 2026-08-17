## **US-B2B-1-3: Join Through a B2B Invitation Link**

**As an invited user,**I want to register or log in through a B2B invitation link,\
So that my account can be linked to the related B2B subscription.
-----------------------------------------------------------------

### **Flow**

👤 Invited User → Opens the invitation link\
⚙️ System → Validates the invitation link\
⚙️ System → Displays registration or login\
👤 Invited User → Registers a new account or logs in\
⚙️ System → Creates new user\
⚙️ System → Links the account to the B2B administrator and subscription\
⚙️ System → Creates a B2B membership request\
⚙️ System → Determines the initial membership status based on platform settings
-------------------------------------------------------------------------------

### **Possible Membership Statuses**

## **Pending Approval:** The user joined using the invitation link but cannot access the subscription yet.**Approved:** The user was accepted, consumes a seat, and can access the subscription.**Rejected:** The B2B administrator refused the join request.**Removed:** The user was previously approved, but the B2B administrator removed them.**Expired:** The membership is no longer usable because the related B2B subscription expired.

### **Notes**

## - Registering through the link does not change the user's main role.

- The relationship with the B2B administrator should be represented by a membership record.

- A user should not receive subscription access before approval unless automatic approval is enabled.

- Reopening the same link should not create duplicate memberships.

- The system should detect whether the account is already linked to the same subscription.

- An invalid, expired, disabled, or revoked link must not create a membership.
