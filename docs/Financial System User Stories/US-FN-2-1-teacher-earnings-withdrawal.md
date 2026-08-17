# **US-FN-2-1: Teacher Earnings Withdrawal**

**As a** teacher,\
**I want to** request a withdrawal from my available earnings,\
**So that** I can receive the money I have earned.
==================================================

## **Flow**

# 1. **Teacher** → Opens earnings

2. **Teacher** → Selects `Withdraw Earnings`

3. **Teacher** → Enters the withdrawal amount

4. **Teacher** → Selects a withdrawal method

5. **Teacher** → Enters the payment account details

6. **Teacher** → Confirms the request

7. **⚙️ System** → Validates the available balance

8. **⚙️ System** → Reserves the requested amount

9. **⚙️ System** → Creates a pending withdrawal request

10. **⚙️ System** → Notifies the admin

## **Results**

# - Admin approves and pays → Withdrawal becomes `Paid`

- Admin rejects → Reserved amount returns to the available balance

## **Notes**

# - The amount must be greater than zero.

- The amount must not exceed the teacher's available earnings.

- Reserved earnings cannot be included in another withdrawal request.

- Reversed or pending earnings cannot be withdrawn.

- The teacher can track the withdrawal status.

- Withdrawal statuses are `Pending`, `Approved`, `Rejected`, and `Paid`.

#
