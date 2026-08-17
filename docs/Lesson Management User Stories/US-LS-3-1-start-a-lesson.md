# **US-LS-3-1: Start and Join a Lesson**

**As a** teacher,\
**I want to** start a confirmed lesson,\
**So that** the teacher and student can join the meeting room.
==============================================================

## **Flow**

# 1. **👨‍🏫 Teacher** → Opens the confirmed lesson

2. **⚙️ System** → Checks that the allowed start time has arrived

3. **👨‍🏫 Teacher** → Taps `Start Lesson`

4. **⚙️ System** → Creates the meeting room

5. **⚙️ System** → Changes the lesson status to `In Progress`

6. **⚙️ System** → Records the actual start time

7. **⚙️ System** → Displays the `Join Lesson` button for the teacher and student

8. **⚙️ System** → Notifies the student

9. **Teacher or Student** → Taps `Join Lesson`

10. **⚙️ System** → Opens the meeting room

## **Notes**

# - Only the assigned teacher can start the lesson.

- Only confirmed lessons can be started.

- The lesson cannot start before the allowed start time.

- Only the assigned teacher and student can join the meeting room.

- The `Join Lesson` button appears after the meeting room is created.

- The same meeting room is used by the teacher and student.

- After starting, the teacher can complete the lesson or report student absence.

#
