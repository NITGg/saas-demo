# **US-PK-2-1: View My Packages and Payment History**

**As a** student,\
**I want to** view my packages and payment history,\
**So that** I can track my Flex balance, package validity, and previous payments.***
====================================================================================

## **Flow**

# 1. **🎓 Student** → Opens the "My Packages" screen

2. **⚙️ System** → Displays the student's active package

3. **⚙️ System** → Displays previous packages

4. **🎓 Student** → Reviews package information

5. **🎓 Student** → Opens the "Payment History" section

6. **⚙️ System** → Displays previous payment transactions

7. **🎓 Student** → Selects a payment transaction

8. **⚙️ System** → Displays the payment details

## **Package Information**

# Each package can display:* Package name

* Total number of Flexes

* Remaining Flexes

* Used Flexes

* Activation date

* Expiration date

* Package status

## **Payment History Information**

# Each payment can display:* Package name

* Payment amount

* Payment date

* Payment method

* Payment status

* Transaction number

## **Notes**

# - The student can only view their own packages and payments.

- The active package should appear first.

- Previous packages can include fully used and expired packages.

- Expired packages cannot be used.

- Fully used packages have zero remaining Flexes.

- Successful payments should be linked to their purchased packages.

- Failed payments do not activate a package or add Flexes.

- Payment records should remain available even after a package expires or is fully used.

#
