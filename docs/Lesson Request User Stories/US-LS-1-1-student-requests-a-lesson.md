# **US-LS-1-1: Student Requests a Lesson**

**As a** student,\
**I want to** request a lesson from a teacher,\
**So that** I can study the selected subject.
=============================================

## **Flow**

# 1. **🎓 Student** → Opens a teacher profile

2. **🎓 Student** → Selects an available date and time

3. **🎓 Student** → Adds an required note

4. **⚙️ System** → Checks the active package, available Flex, and selected time

5. **⚙️ System** → Checks that the lesson is at least one hour from now

6. **🎓 Student** → Confirms the request

7. **⚙️ System** → Creates the request with "Pending" status

8. **⚙️ System** → Notifies the teacher

## **Notes**

# - The student must have an active package and available Flex.

- No Flex is permanently used while the request is pending.

- The selected subject must be supported by the teacher.

#
