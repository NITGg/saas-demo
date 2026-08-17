# **US-AD-9-3: Deactivate or Delete a Program**

As an admin,\
I want to deactivate or delete a program,\
So that I can stop new purchases or remove unused programs from the platform.
-----------------------------------------------------------------------------

## **Flow**

## 🔧 Admin → Opens program management🔧 Admin → Selects a program🔧 Admin → Chooses "Deactivate Program" or "Delete Program"

### **If Deactivate Program is selected**

## ⚙️ System → Displays a confirmation message🔧 Admin → Confirms the action⚙️ System → Changes the program status to "Inactive"⚙️ System → Removes the program from the available programs list⚙️ System → Prevents new users from purchasing the program

### **If Delete Program is selected**

## ⚙️ System → Checks whether the program has ever been purchased

#### **If the program has never been purchased**

## ⚙️ System → Displays a deletion confirmation message🔧 Admin → Confirms the deletion⚙️ System → Permanently deletes the program⚙️ System → Removes the program from program management

#### **If the program has been purchased before**

## ⚙️ System → Prevents the deletion⚙️ System → Displays a message that the program can only be deactivated

## **Notes**

## - Deactivated programs cannot be purchased by new users.

- Existing purchased programs remain active.

- Users retain access to courses already granted through the program.

- A program can only be deleted if it has never been purchased.

- Programs with purchase records cannot be deleted and must be deactivated instead.

- Deletion is permanent and cannot be undone.
