## **US-B2B-1-2: Generate a B2B Invitation Link**

**As a B2B administrator,**I want to generate an invitation link,\
So that users can join my B2B subscription.
-------------------------------------------

### **Flow**

🏢 B2B Administrator → Opens the active B2B subscription\
🏢 B2B Administrator → Selects **Generate Invitation Link**⚙️ System → Checks that the subscription is active\
⚙️ System → Generates a unique invitation link\
⚙️ System → Links the invitation to:* The B2B administrator

* The B2B subscription

* The invitation configuration🏢 B2B Administrator → Copies and shares the invitation link
------------------------------------------------------------------------------------------

### **Notes**

## - The invitation link must not directly grant subscription access.

- The link should identify the related B2B administrator and subscription.

- The link may have an expiration date.

- The link may be active, expired, disabled, or revoked.

- The B2B administrator may generate a new link or revoke an existing one.

- Possessing the link does not guarantee approval or access.
