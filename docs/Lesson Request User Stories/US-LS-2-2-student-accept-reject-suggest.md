# **US-LS-2-2: Student Accept/Reject/Suggest**

**As a** student,\
**I want to** respond to the teacher's suggested time,\
**So that** we can agree on a suitable lesson time.
===================================================

## **Flow**

# 1. **🎓 Student** → Opens a request with "Waiting for Student" status

2. **🎓 Student** → Reviews the teacher's suggested time

3. **🎓 Student** → Selects one action:

   - Accept

   - Reject

   - Suggest another time

4. **⚙️ System** → Updates the lesson status

5. **⚙️ System** → Notifies the teacher

## **Results**

# - **Accept** → Status becomes "Confirmed" and one Flex is reserved

- **Reject** → Status becomes "Suggested Time Rejected by Student"

- **Reject** → or something else

- **Suggest another time** → Status becomes "Waiting for Teacher"

## **Notes**

# - The student must still have an active package and available Flex before confirmation.

#
