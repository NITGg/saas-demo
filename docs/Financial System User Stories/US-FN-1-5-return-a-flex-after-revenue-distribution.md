# **US-FN-1-5: Return a Flex After Revenue Distribution**

**As an** admin,\
**I want to** return a consumed Flex to a student,\
**So that** I can correct an approved lesson or financial issue.
----------------------------------------------------------------

## **Flow**

## 1. **🔧 Admin** → Opens the completed lesson

2. **🔧 Admin** → Selects `Return Flex`

3. **🔧 Admin** → Adds a return reason

4. **🔧 Admin** → Confirms the action

5. **⚙️ System** → Returns one Flex to the student

6. **⚙️ System** → Reverses the teacher earning

7. **⚙️ System** → Reverses the platform earning

8. **⚙️ System** → Records the reversal transaction

## **Example**

## - Flex value → 1 EGP

- Returned to student → 1 Flex

- Teacher earning reversed → 0.40 EGP

- Platform earning reversed → 0.60 EGP

## **Notes**

## - Only consumed and distributed Flexes can be returned.

- The admin must provide a return reason.

- The Flex cannot be returned more than once.

- The original financial transaction remains in history.

- A separate reversal transaction is created for auditing.

- If the teacher earning was already withdrawn, the reversed amount is deducted from the teacher's current or future balance.

#
