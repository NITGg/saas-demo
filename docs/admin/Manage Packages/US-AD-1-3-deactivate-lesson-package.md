### **US-AD-1-3: Deactivate or Delete a Lesson Package**

**As an admin,** I want to deactivate or delete a lesson package,\
&#x20;So that I can manage package availability while preserving purchase history.
----------------------------------------------------------------------------------

#### **Flow**

🔧 Admin → Opens package management\
&#x20;🔧 Admin → Selects a package\
&#x20;🔧 Admin → Chooses **"Deactivate Package"** or **"Delete Package"****If Deactivate is selected:*** ⚙️ System → Displays a confirmation message

* 🔧 Admin → Confirms the deactivation

* ⚙️ System → Changes the package status to **"Inactive"**

* ⚙️ System → Removes the package from the available packages list

* ⚙️ System → Prevents new students from purchasing the package**If Delete is selected:*** ⚙️ System → Checks whether the package has ever been purchased or used

* **If the package has never been purchased:**

  - ⚙️ System → Displays a deletion confirmation message

  - 🔧 Admin → Confirms the deletion

  - ⚙️ System → Permanently deletes the package

  - ⚙️ System → Removes the package from package management

* **If the package has purchase or usage records:**

  - ⚙️ System → Prevents deletion

  - ⚙️ System → Displays a message that the package can only be deactivated
---------------------------------------------------------------------------

#### **Notes**

## - Deactivating a package does not delete it.

- Inactive packages cannot be purchased by new students.

- Packages already purchased remain active until they are fully used or expired.

- Existing Flex balances are not affected.

- The admin can reactivate a deactivated package at any time.

- A package can be permanently deleted only if it has never been purchased or used.

- Packages with payment, purchase, or usage history cannot be deleted.

- Deletion is permanent and cannot be undone.

###
