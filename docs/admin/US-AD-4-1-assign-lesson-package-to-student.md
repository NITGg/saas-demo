# **US-AD-4-1: Assign a Lesson Package to a Student**

**As an** admin,\
**I want to** assign a lesson package to a specific student,\
**So that** the student receives Flexes after paying outside the platform.
==========================================================================

## **Flow**

# 1. **🔧 Admin** → Opens the student profile

2. **🔧 Admin** → Selects `Assign Package`

3. **🔧 Admin** → Selects an active package

4. **🔧 Admin** → Adds the external payment details

5. **🔧 Admin** → Confirms the assignment

6. **⚙️ System** → Activates the package

7. **⚙️ System** → Adds the Flexes to the student balance

8. **⚙️ System** → Records the admin assignment

## **Payment Details**

# - Amount paid

- Payment method

- Payment reference

- Payment date

- Optional note

## **Notes**

# - The payment is completed outside the platform.

- No online payment gateway is used.

- The student must not already have an active package.

- Only active packages can be assigned.

- The package uses its current Flex count and expiration period.

- The system records the admin who assigned the package.
