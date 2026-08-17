# **US-FN-2-2: Admin Process Withdrawal**

**As an** admin,\
**I want to** review and process teacher withdrawal requests,\
**So that** teachers receive their available earnings.
======================================================

## **Flow**

# 1. **Admin** → Opens withdrawal requests

2. **Admin** → Reviews the teacher and payment details

3. **Admin** → Approves or rejects the request

4. **Admin** → Records the payment reference when paid

5. **⚙️ System** → Updates the withdrawal status

6. **⚙️ System** → Notifies the teacher

## **Results**

# - Approve → Request becomes `Approved`

- Confirm payment → Request becomes `Paid`

- Reject → Request becomes `Rejected` and the amount returns to the teacher balance

## **Notes**

# - The admin must provide a reason when rejecting a request.

- Only approved requests can be marked as paid.

- A paid withdrawal cannot be processed again.

- The system records the admin who processed the request.

#
