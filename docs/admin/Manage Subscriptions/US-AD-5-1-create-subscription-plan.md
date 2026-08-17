# **US-AD-5-1: Create a Subscription Plan** 

### **As an** admin, **I want to** create a subscription plan, **So that** users can purchase it normally or as a B2B subscription.

## **Flow**

### 1. 🔧 Admin → Opens subscription management

2. 🔧 Admin → Selects `Create Subscription`

3. 🔧 Admin → Enters the name, normal price, and number of days

4. 🔧 Admin → Adds an optional description***

## **Offer Settings (Optional)**

### 5. 🔧 Admin → Enables `Offer`

6. 🔧 Admin → Selects discount type (percentage / fixed)

7. 🔧 Admin → Enters discount value

8. 🔧 Admin → Sets offer start date and end date***

## **B2B Settings**

### 9. 🔧 Admin → Chooses whether B2B purchase is available

10. 🔧 Admin → If B2B is enabled, adds seat options:- 10 seats

- 20 seats

- 50 seats11. 🔧 Admin → Defines discount percentage for each seat option

12. ⚙️ System → Calculates B2B price for each seat option***

## **Activation**

### 13. 🔧 Admin → Activates the subscription

14. ⚙️ System → Calculates final displayed prices

15. ⚙️ System → Displays the subscription to users***

## **B2B Price Calculation**

### Original Price = Normal Price × Number of SeatsDiscount Amount = Original Price × Discount % ÷ 100B2B Price = Original Price - Discount Amount***

## **Notes**

### - The normal price must be ≥ 0.

- The number of days must be > 0.

- The number of seats must be > 0.

- Discount percentage must be between 0% and 100%.

- Each seat option has its own discount.

### **Offer Rules**

### - Offer is optional.

- Only one offer per subscription.

- Offer applies only to **normal purchase**.

- Offer does NOT apply to B2B pricing.

- Offer is active only between start and end date.

- Final price cannot be less than zero.

### **General Rules**

### - Only active subscriptions are available.

- Normal purchase keeps user role.

- B2B purchase changes role to B2B Administrator.

- Role changes only after successful payment.

###
