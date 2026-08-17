## **Overview**

## ***

## **Application Overview**

The platform connects students with teachers.

The platform has three main user roles:

- Student

- Teacher

- Admin

Teachers register on the platform and wait for lesson requests from students.

Students purchase lesson packages and use their available lessons to request sessions from teachers.

# **User Roles Overview**

## **Student**

The student can:

- Create an account.

- Browse available teachers.

- View teacher profiles.

- Purchase a lesson package.

- Send a lesson request to a teacher.

- Select a preferred lesson date and time.

- Discuss another suitable time with the teacher.

- View previous lessons.

- Cancel a lesson according to the cancellation rules.


## **Teacher**

The teacher can:

- Create an account.

- Add personal information.

- Add subjects and specializations.

- Receive lesson requests.

- Accept a lesson request.

- Reject a lesson request.

- Suggest another lesson time.

- View previous lessons.


## **Admin**

The admin can:

- Approve or reject teacher accounts.

- Create and update lesson packages.

- Set package prices.

- Set package expiration periods.

- View payments and platform reports.

- Suspend or activate user accounts.

***

\
\



# **Lesson Packages Overview**

## **Shared Package Information**

- One Flex represents one lesson.

- One lesson = 1 hour (10 break)

- The student must have an active package to request a lesson.

- The student can request a lesson from any available teacher.

- The student cannot use more lessons than the remaining Flex balance.

- Each package has a predefined expiration period by days (0 for unlimited).

- Unused Flexes expire when the package expiration date is reached.

- The admin defines the package flex, package price and expiration period.


## **Available Packages**

### **Flex10**

- Provides 10 Flexes

- Price 1000 EG


### **Flex20**

- Provides 20 Flexes

- Price 1900 EG


### **Flex30**

- Provides 30 Flexes

- Price 2700 EG

***

\
\



# **Subscriptions Overview**

## **Shared Subscription Information**

- A subscription gives access to courses, not lessons.

- Each subscription has:

  - Name

  - Price

  - Duration (in days)

- The student must have an active subscription to access courses.

- The subscription starts after successful payment.

- The subscription expires after its duration ends.

- Expired subscriptions cannot be used to access courses.

- The full subscription price is recorded as platform revenue.

- The admin defines the subscription price and duration.


## **Course Access Rules**

- Each course defines which subscriptions can access it.

- A student can access a course only if:

  - The subscription is active

  - The subscription is allowed for that course


## **Examples**

- 30 Days Subscription

  - Duration: 30 days

  - Access: Limited courses

- 365 Days Subscription

  - Duration: 365 days

  - Access: All courses (e.g. English course)


## **Notes**

- A course can be available for all subscriptions or specific ones.

- Course access changes apply immediately.

- Students can only access courses during active subscription period.

***

\
\



# **B2B Subscription Overview**

## **Shared B2B Information**

- B2B subscription is purchased by one user (B2B Admin).

- The B2B Admin manages access for multiple students.

- The B2B Admin selects the number of seats (students).

- The price is based on:

  - Base subscription price

  - Number of seats

  - Discount (if applied)


## **Roles**

- B2B Admin:

  - Purchases the subscription

  - Manages students

  - Generates invitation links

- Students:

  - Join using the B2B invitation link

  - Require approval (optional)


## **Student Management**

- The B2B Admin can:

  - Approve students

  - Reject students

  - Remove students

- Each approved student:

  - Occupies one seat

  - Gets access to courses


## **Capacity Rules**

- Each B2B subscription has a maximum number of students.

- A student cannot join if capacity is full.

- Removing a student:

  - May return the seat (based on system setting)


## **Subscription Expiration**

- When B2B subscription expires:

  - All students lose access

  - Membership becomes inactive

  - Roles may revert to normal user


## **Notes**

- B2B students do not purchase subscriptions individually.

- Access is controlled by the B2B Admin.

- Course access follows the same subscription rules.

\
\
\
\
\
\
\


when user register at system his role is user by default

admin can make user role teacher or admin

current subscription

the one subscription will has 2 types

normal type (current)

B2B type (new)

the b2b subscription specifications is

when user buy subscription b2b type, his role become b2b\_adminstrator

before buy it, user select number of student there will be under this subscription (10-20-50)

the subscription price will be (normal price \* number of student under this subscription \* discount ratio)

the b2b admin after buy this subscription can generate link and send it to target users to register or login with this link, and the system will link this accounts with b2b admin and with this subscription

but students can’t access this subscriptions role until b2b admin approve there accounts or they can depend on var in settings

when one student use link and be approved (by system or admin) the system will count that

and if the b2b admin remove this account the system will count back or not depend on var in settings
