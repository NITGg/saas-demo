# **US-LS-2-3: Teacher Accept/Reject**

**As a** teacher,\
**I want to** review the student's response and suggested times,\
**So that** I can accept a suitable time or reject the lesson request.
======================================================================

## **Flow**

# 1. **👨‍🏫 Teacher** → Opens a request with "Waiting for Teacher" status

2. **👨‍🏫 Teacher** → Reviews the student's response and suggested times

3. **👨‍🏫 Teacher** → Selects one action:

   - Accept a suggested time

   - Reject the lesson request

4. **⚙️ System** → Updates the lesson status

5. **⚙️ System** → Notifies the student

## **Results**

# - **Accept a suggested time** → Status becomes "Confirmed" and one Flex is reserved

- **Reject the lesson request** → Status becomes "Rejected by Teacher"

## **Notes**

# - The teacher can accept any available time suggested by the student.

- The teacher can add an optional rejection reason.

- The selected time must still be available.

- A rejected request cannot be confirmed later.

#
