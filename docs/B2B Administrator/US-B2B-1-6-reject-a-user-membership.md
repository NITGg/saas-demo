## **US-B2B-1-6: Reject a User Membership**

### **As a B2B administrator,**I want to reject a pending user request, So that unauthorized users cannot access my B2B subscription.

### **Flow**

### 🏢 B2B Administrator → Opens the pending members list 🏢 B2B Administrator → Selects a user 🏢 B2B Administrator → Selects **Reject**🏢 B2B Administrator → Optionally enters a reason ⚙️ System → Changes the membership status to `Rejected
`⚙️ System → Does not allocate a user seat ⚙️ System → Notifies the user 

### **Notes**

### - Rejecting a pending membership does not affect the available capacity.

- A rejected user does not receive subscription access.

- The system should preserve the rejection history.
