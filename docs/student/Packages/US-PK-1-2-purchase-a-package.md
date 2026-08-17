# **US-PK-1-2: Purchase a Package**

**As a** student,\
**I want to** purchase a lesson package,\
**So that** I can request lessons from teachers.***
===================================================

## **Flow**

# 1. **🎓 Student** → Opens the packages screen

2. **🎓 Student** → Selects Flex10, Flex20, or Flex30

3. **🎓 Student** → Taps "Buy Package"

4. **⚙️ System** → Displays the payment screen

5. **🎓 Student** → Enters payment information

6. **🎓 Student** → Confirms the payment

7. **⚙️ System** → Processes the payment

8. **⚙️ System** → Activates the package

9. **⚙️ System** → Adds the Flex balance

10. **⚙️ System** → Calculates the expiration date

11. **⚙️ System** → Check user should not has active package

12. **🎓 Student** → Receives a purchase confirmation

## **Notes**

# * The package is activated only after successful payment.

* The expiration date is based on the package rules.

* Each Flex represents one lesson.

* User can not has more than active package

* **Package Statuses:**- Pending Payment

-       |

-       +---- Payment Failed

-       |

-       +---- Cancelled

-       |

-       v

-     Active

-       |

-       +---- Fully Used

-       |

-       +---- Expired
