### **US-B2B-1-8: View B2B Subscription Capacity**

**As a B2B administrator,**I want to view my subscription capacity and members,\
So that I know how many additional users I can approve.
-------------------------------------------------------

#### **Flow**

🏢 B2B Administrator → Opens the B2B subscription dashboard\
⚙️ System → Displays:* Purchased user capacity

* Consumed seats

* Available seats

* Pending users 

* Approved users 

* Rejected users 

* Removed users 

* Removed users whose seats were returned

* Removed users whose seats remain consumed

* Subscription expiration date
------------------------------

#### **Capacity Calculation**

## Consumed Seats =Number of membership records where consumes\_seat = trueAvailable Seats =Purchased user Capacity - Consumed Seats

#### **Seat Consumption Rules**

## When a user is approved:status = Approvedconsumes\_seat = trueWhen an approved user is removed while the seat-return setting is enabled:status = Removedconsumes\_seat = falseWhen an approved user is removed while the seat-return setting is disabled:status = Removedconsumes\_seat = truePending and rejected users :consumes\_seat = false

#### **Mixed Policy Example**

## A B2B subscription has a capacity of 10 users.* 5 users are currently approved.

* 2 users were removed while seat return was enabled.

* 1 users was removed while seat return was disabled.The calculation is:Consumed Seats = 5 approved + 1 removed seat still consumedConsumed Seats = 6Available Seats = 10 - 6Available Seats = 4The two users removed while seat return was enabled do not consume seats.

#### **Notes**

## - The current global seat-return setting must not be used directly to calculate historical capacity.

- The system must store the seat-consumption result on each membership record.

- Changing the seat-return setting affects future removals only.

- Previously removed users keep their recorded `consumes_seat` value.

- A removed user may still consume a seat even though the user no longer has subscription access.

- Consumed seats must never exceed the purchased capacity.

- Approving the same membership twice must not consume multiple seats.

- Removing the same user multiple times must not change the seat count multiple times.

- The dashboard must distinguish between:

  - Membership status

  - Access status

  - Seat-consumption status

###
