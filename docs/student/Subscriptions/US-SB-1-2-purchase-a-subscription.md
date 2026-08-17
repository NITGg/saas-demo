# **US-SB-1-2: Purchase a Subscription**

**As a** student,\
**I want to** purchase a subscription,\
**So that** I can access its available courses.
===============================================

## **Flow**

# 1. **🎓 Student** → Selects a subscription

2. **🎓 Student** → Selects `Buy Subscription`

3. **⚙️ System** → Displays the payment screen

4. **🎓 Student** → Completes the payment

5. **⚙️ System** → Confirms that the payment is successful

6. **⚙️ System** → Activates the subscription

7. **⚙️ System** → Calculates the expiration date

8. **⚙️ System** → Gives the student access to eligible courses

9. **⚙️ System** → Adds the subscription price to the platform wallet

10. **⚙️ System** → Records the full subscription price as platform revenue

11. **⚙️ System** → Records the payment and financial transaction

12. **🎓 Student** → Receives a purchase confirmation

## **Notes**

# - The subscription is activated only after successful payment.

- The full subscription price belongs to the platform.

- Subscription revenue is not divided between the platform and teachers.

- Failed or cancelled payments do not activate the subscription or add platform revenue.

- The expiration date is calculated from the activation date and number of days.

- An expired subscription cannot be used to access courses.

## **Subscription Statuses**

# Pending Payment      |      +---- Payment Failed      |      +---- Cancelled      |      v    Active      |      v    Expired

#
