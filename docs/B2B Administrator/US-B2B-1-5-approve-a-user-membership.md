### **US-B2B-1-5: Approve a User Membership**

**As a B2B administrator,**I want to approve a pending user,\
So that the user receives subscription access through my B2B subscription.
--------------------------------------------------------------------------

#### **Flow**

🏢 B2B Administrator → Opens the B2B subscription members list\
🏢 B2B Administrator → Opens a pending membership request\
🏢 B2B Administrator → Selects **Approve**⚙️ System → Checks that the parent B2B subscription is active\
⚙️ System → Checks that the parent B2B subscription has not expired\
⚙️ System → Checks that an available user seat exists\
⚙️ System → Changes the membership status to `Approved
`⚙️ System → Allocates one user seat\
⚙️ System → Creates subscription access for the approved user\
⚙️ System → Links the user's access to the parent B2B subscription and membership\
⚙️ System → Sets the user's access expiration date to the parent B2B subscription expiration date\
⚙️ System → Gives the user access to the subscription's eligible courses\
⚙️ System → Notifies the user 
------------------------------

#### **Notes**

## - The approved user receives the same course-access benefits as a user who purchased the corresponding Normal subscription.

- The approved user does not create a new payment or individual purchase record.

- The source of the user's subscription access must be recorded as `B2B`.

- The user's access must remain linked to the B2B administrator's subscription.

- The user's access cannot continue beyond the parent B2B subscription expiration date.

- Only approved users consume subscription capacity.

- Pending and rejected users do not consume capacity.

- The system must prevent approval when the capacity is full.

- Approval should store who approved the user and when.

- Approving the same membership more than once must not consume multiple seats or create duplicate access records.

- Removing the user must revoke or deactivate the related B2B subscription access.
