# **Teacher and Platform Wallets**

## **1. Teacher Wallet**

# The teacher has one wallet for lesson earnings.┌─────────────────────────────┐│       Teacher Wallet        │├─────────────────────────────┤│ Current Balance             ││ Total Withdrawn             │└─────────────────────────────┘* **Current Balance:** Earnings available for withdrawal.

* **Total Withdrawn:** Money already paid to the teacher.When the teacher requests a withdrawal, the amount is deducted from the current balance.* If the request is rejected, the amount returns to the wallet.

* If the request is paid, it is added to the total withdrawn.***

## **2. Platform Wallet**

# The platform wallet shows all money currently held by the platform and how it is divided.┌──────────────────────────────────┐│         Platform Wallet          │├──────────────────────────────────┤│ Current Money                    ││                                  ││ ├── Undistributed Package Money  ││ ├── Teachers' Money              ││ └── Platform Earnings            │└──────────────────────────────────┘* **Current Money:** All actual money currently held by the platform.

* **Undistributed Package Money:** Value of Flexes not consumed yet.

* **Teachers' Money:** Earnings belonging to teachers but not paid yet.

* **Platform Earnings:** The platform's share from consumed Flexes.***

## **Example**

# A student purchases `Flex20` for `20 EGP`.

### **After Package Purchase**

# Student Flex Balance            = 20 FlexPlatform Current Money          = 20 EGPUndistributed Package Money     = 20 EGPTeachers' Money                 = 0 EGPPlatform Earnings               = 0 EGPThe student completes one lesson using one Flex worth `1 EGP`.

### **After Lesson Completion**

# Student Flex Balance            = 19 FlexTeacher Current Balance         = 0.40 EGPPlatform Current Money          = 20 EGPUndistributed Package Money     = 19 EGPTeachers' Money                 = 0.40 EGPPlatform Earnings               = 0.60 EGPThe platform still holds the full `20 EGP`, but part of this money now belongs to the teacher and part belongs to the platform.***

## **After Teacher Withdrawal**

# The teacher withdraws `0.40 EGP`, and the admin pays the request.Teacher Current Balance         = 0 EGPTeacher Total Withdrawn         = 0.40 EGPPlatform Current Money          = 19.60 EGPTeachers' Money                 = 0 EGPPlatform Earnings               = 0.60 EGPUndistributed Package Money     = 19 EGPThe platform's current money decreases only when money is actually paid outside the platform.***

## **Simple Flow**

# Student purchases package          │          ▼Money enters Platform Wallet          │          ▼Student consumes one Flex          │          ├── Teacher share → Teacher Wallet          │          └── Platform share → Platform Earnings                                   Teacher requests withdrawal          │          ▼Amount deducted from Teacher Wallet          │     ┌────┴────┐     ▼         ▼ Rejected     Paid     │         │     ▼         ▼Returned     Money leavesto wallet    the platform

## **Main Rules**

# - The teacher has one wallet.

- The platform holds the actual money until it is paid to the teacher.

- Teacher earnings are included in both the teacher wallet and the platform's teachers' money.

- Teacher money is not considered platform profit.

- Only consumed Flexes generate teacher and platform earnings.

- Paying a withdrawal reduces the platform's current money.

#
