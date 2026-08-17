# **US-LS-2-1: Teacher Accept/Reject/Suggest**

**As a** teacher,\
**I want to** accept, reject, or suggest another lesson time,\
**So that** I can respond to the student's request.
===================================================

## **Flow**

# 1. **👨‍🏫 Teacher** → Opens a pending lesson request

2. **👨‍🏫 Teacher** → Reviews the student, subject, date, time, and note

3. **👨‍🏫 Teacher** → Selects one action:

   - Accept

   - Reject

   - Suggest another time

4. **⚙️ System** → Updates the lesson status

5. **⚙️ System** → Notifies the student

## **Results**

# - **Accept** → Status becomes "Confirmed" and one Flex is reserved

- **Reject** → Status becomes "Rejected by Teacher"

- **Suggest another time** → Status becomes "Waiting for Student"

## **Notes**

# - The teacher can add a rejection reason.

- Suggested times must be available.

- The lesson is added to both schedules after confirmation.

#
