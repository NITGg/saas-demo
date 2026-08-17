## **US-B2B-1-4: Automatically Approve an Invited User**

**As a platform administrator,**I want to configure whether invited users are automatically approved,\
So that the platform can support automatic or manual membership approval.
-------------------------------------------------------------------------

### **Flow**

🔧 Admin → Opens B2B subscription settings\
🔧 Admin → Configures **Automatically approve invited users**🔧 Admin → Saves the setting\
⚙️ System → Applies the setting to new membership requests
----------------------------------------------------------

### **When Automatic Approval Is Enabled**

👤 Invited User → Registers or logs in through the invitation link\
⚙️ System → Checks the available B2B capacity\
⚙️ System → Approves the membership\
⚙️ System → Allocates one user seat\
⚙️ System → Gives the user access to the subscription's eligible courses
------------------------------------------------------------------------

### **When Automatic Approval Is Disabled**

👤 Invited User → Registers or logs in through the invitation link\
⚙️ System → Creates a `Pending Approval` membership\
⚙️ System → Does not allocate a seat yet\
⚙️ System → Does not give subscription access yet\
⚙️ System → Notifies the B2B administrator
------------------------------------------

### **Notes**

## - Automatic approval must still respect the purchased user capacity.

- If no seat is available, the membership must remain pending.

- The setting should affect new membership requests only.

- Changing the setting should not automatically change existing pending or approved memberships unless explicitly requested.
