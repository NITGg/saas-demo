## **US-B2B-1-1: Purchase a B2B Subscription**

**As a user,**I want to purchase a B2B subscription with a selected user capacity,\
So that I can manage subscription access for a group of users.
--------------------------------------------------------------

### **Flow**

👤 User → Opens an active subscription plan\
👤 User → Selects the B2B subscription type\
⚙️ System → Displays the available users-capacity options\
👤 User → Selects a capacity, such as 10, 20, or 50 users\
⚙️ System → Displays the base price, discount, and final B2B price\
👤 User → Continues to payment\
👤 User → Completes the payment\
⚙️ System → Confirms the payment\
⚙️ System → Creates the B2B subscription\
⚙️ System → Stores the selected user capacity and purchase price\
⚙️ System → Add new role to user's role this role is `B2B Administrator
`⚙️ System → Calculates the expiration date\
👤 B2B Administrator → Receives a purchase confirmation
-------------------------------------------------------

### **Notes**

## - The role added only after successful payment.

- Failed or cancelled payments must not added the user's role.

- The purchase record must store the original base price, capacity, discount multiplier, and final price.

- The B2B subscription expires according to its configured duration.

- The B2B administrator cannot approve more users than the purchased capacity.

- The subscription type cannot be added after the subscription plan has been created.

- The admin can update the configuration related to the existing type only.

- For a Normal subscription, the admin can update its base price, duration, name, and description.

- For a B2B subscription, the admin can also update the available users capacities and discount ratios.

- added apply only to future purchases.
