# **US-LS-3-2: Complete a Lesson**

**As a** teacher,\
**I want to** complete an in-progress lesson,\
**So that** the lesson, Flex usage, and earnings are recorded.
==============================================================

## **Flow**

# 1. **👨‍🏫 Teacher** → Conducts the lesson in the meeting room

2. **👨‍🏫 Teacher** → Taps `Complete Lesson`

3. **⚙️ System** → Check the completion is within the allowed completion deadline

4. **⚙️ System** → End the meeting room

5. **⚙️ System** → Changes the lesson status to `Completed`

6. **⚙️ System** → Records the completion time

7. **⚙️ System** → Permanently consumes the reserved Flex

8. **⚙️ System** → Distributes the Flex value between the teacher and platform

9. **⚙️ System** → Adds the lesson to lesson history

10. **⚙️ System** → Notifies the student

## **Notes**

# - Only `In Progress` lessons can be completed.

- Only the assigned teacher can complete the lesson.

- The teacher can add optional lesson notes.

- The lesson cannot be completed more than once.

- Completing the lesson closes the meeting room.

- Teacher and platform earnings use the percentages active when the lesson is completed.

#
