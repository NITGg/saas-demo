# **US-AD-9-1: Create a Program**

**As an** admin,\
**I want to** create a program,\
**So that** users can purchase a group of courses together.
===========================================================

## **Flow**

# 1. 🔧 Admin → Opens program management

2. 🔧 Admin → Selects `Create Program`

3. 🔧 Admin → Enters program name

4. 🔧 Admin → Adds program description

5. 🔧 Admin → Selects one or more courses

6. 🔧 Admin → Enters program price***

## **Offer Settings (Optional)**

# 7. 🔧 Admin → Enables `Offer`

8. 🔧 Admin → Selects discount type (percentage / fixed)

9. 🔧 Admin → Enters discount value

10. 🔧 Admin → Sets max discount amount

11. 🔧 Admin → Sets offer start date

12. 🔧 Admin → Sets offer end date***

## **Activation**

# 13. 🔧 Admin → Activates the program

14. ⚙️ System → Calculates discounted price (with max cap)

15. ⚙️ System → Displays program to users

## **Notes**

# - A program must contain at least one course.

- Purchasing a program gives access to all included courses.

- Offer is optional.

- Only one offer per program.

- Max discount limits the applied discount.

- Final price cannot be less than zero.

- Offer applies only during its active period.

- Offer applies only to future purchases.
