### **US-AD-2-1: Update Settings**

**As an admin,**I want to update lesson deadlines and financial settings,\
So that the system applies the correct lesson and revenue rules.
================================================================

## **Flow**

🔧 Admin → Opens lesson settings\
⚙️ System → Displays the settings as tabs\
🔧 Admin → Selects the required settings tab\
🔧 Admin → Updates the settings\
🔧 Admin → Saves changes\
⚙️ System → Validates and applies the new values***
===================================================

## **Tab 1: Lesson Deadlines**

🔧 Admin → Updates lesson deadlines\
🔧 Admin → Selects minutes or hours
===================================

### **Fields**

# - Minimum lesson booking time

- Student cancellation deadline

- Lesson time-update deadline

- Lesson start allowed time

- Absence reporting time

- Lesson completion allowed time***

## **Tab 2: Financial Settings**

🔧 Admin → Updates the teacher earning percentage\
🔧 Admin → Updates the platform earning percentage
==================================================

### **Fields**

# - Teacher earning percentage

- Platform earning percentage***

## **Tab 3: B2B Subscription Settings**

### **Automatically Approve Invited Users**

# Determines whether users who register or log in through a B2B invitation link are approved automatically.Available values:* Enabled

* DisabledWhen enabled:* The user is automatically approved if the B2B subscription has an available seat.

* The user is counted under the subscription capacity.

* The user receives access to the B2B subscription.When disabled:* The user remains pending.

* The B2B administrator must approve the user .

* The user is not counted until approved.

* The user cannot access the B2B subscription before approval.

### **Return Seat When User Is Removed**

# Determines whether the consumed user seat becomes available again when the B2B administrator removes an approved user .Available values:* Enabled

* DisabledWhen enabled:* The removed user loses access.

* The user is no longer counted under the subscription capacity.

* The available user count increases by one.When disabled:* The removed user loses access.

* The user remains counted under the subscription capacity.

* The available user count does not increase.***

## **Settings Variables**

# b2b\_auto\_approve\_invited\_usersb2b\_return\_seat\_after\_user\_removal***

## **Notes**

# - Deadline values must be zero or greater.

- Teacher and platform percentages must total 100%.

- New values apply to future lesson actions and revenue transactions.

- Existing lesson times and completed financial transactions are not changed.

- Admin-configured values are used across the platform.

- B2B settings apply to future user approval and removal actions.

- Automatic approval cannot exceed the purchased user capacity.

- Changing the automatic-approval setting does not affect existing pending users.

- Changing the seat-return setting does not affect user who were already removed.
