# Academy Platform — Feature Specs (User Stories)

Single source of truth for the **Flex tutoring platform** requirements. These describe a
forward-looking system (student↔teacher lesson marketplace). They are **not** documentation of
the current Moodle 3.11 install — none of these tables/features exist in the live DB yet.

> How to add or update a story: see the `academy-specs` skill (`.claude/skills/academy-specs/`).
> Paste an updated story to Claude and it routes it to the right file and refreshes the table below.

## Layout

**One file per user story**, grouped in area subfolders. Filenames are `US-<ID>-<slug>.md`.

```
docs/specs/
  README.md            ← this index
  00-overview.md       ← roles, package catalog, Flex concept, status models, glossary
  admin/               US-AD-*
  teacher/             US-TR-* + teacher financial (US-FN-1-3, US-FN-2-1)
  student/             US-ST-*, US-PK-*
  lessons/             US-LS-*
  financial/           00-wallet-model.md + US-FN-*
  B2B Administrator/   US-B2B-*
```

## ID convention

`US-<AREA>-<GROUP>-<SEQ>` — e.g. `US-AD-1-2` = Admin, group 1 (packages), story 2.

| AREA | Meaning | Folder |
|------|---------|--------|
| AD | Admin | `admin/` |
| TR | Teacher | `teacher/` |
| ST | Student | `student/` |
| PK | Packages (student-facing) | `student/` |
| SB | Subscriptions (student-facing) | `student/` |
| LS | Lessons | `lessons/` |
| FN | Financial | `financial/` (or `teacher/` for teacher-facing financial) |
| B2B | B2B Administrator | `B2B Administrator/` |

### ID collisions — resolved
The two teacher-facing financial stories were renamed so every ID is now unique:
- *View Teacher Earnings and Withdrawals*: `US-FN-1-3` → **`US-TR-1-3`** (teacher/). `US-FN-1-3` = *Return a Reserved Flex* (financial/).
- *Export Teacher Reports*: `US-FN-2-1` → **`US-TR-2-1`** (teacher/). `US-FN-2-1` = *Teacher Earnings Withdrawal* (financial/).

## Status legend
`Spec` = written, not built · `In progress` = being implemented · `Built` = implemented & verified.

## Story index

### Admin — `admin/`
| ID | Title | Status |
|----|-------|--------|
| US-AD-1-1 | [Create a Lesson Package](admin/US-AD-1-1-create-lesson-package.md) | In progress |
| US-AD-1-2 | [Update a Lesson Package](admin/US-AD-1-2-update-lesson-package.md) | In progress |
| US-AD-1-3 | [Deactivate a Lesson Package](admin/US-AD-1-3-deactivate-lesson-package.md) | Built |
| US-AD-1-4 | [Delete an Unused Lesson Package](admin/US-AD-1-4-delete-unused-lesson-package.md) | Built |
| US-AD-2-1 | [Update Settings (3 tabs)](admin/US-AD-2-1-update-lesson-settings.md) | In progress |
| US-AD-3-1 | [View Lessons and Attendance Reports](admin/US-AD-3-1-view-lessons-and-attendance-reports.md) | Spec |
| US-AD-3-2 | [View Platform Earnings](admin/US-AD-3-2-view-platform-earnings.md) | Spec |
| US-AD-3-3 | [View Package and Flex Reports](admin/US-AD-3-3-view-package-and-flex-reports.md) | Spec |
| US-AD-3-4 | [View Student Flex Balance and History](admin/US-AD-3-4-view-student-flex-balance-and-history.md) | Spec |
| US-AD-4-1 | [Assign a Lesson Package to a Student](admin/US-AD-4-1-assign-lesson-package-to-student.md) | Spec |
| US-AD-5-1 | [Create a Subscription Plan](admin/US-AD-5-1-create-subscription-plan.md) | In progress |
| US-AD-5-2 | [Update a Subscription Plan](admin/US-AD-5-2-update-subscription-plan.md) | In progress |
| US-AD-5-3 | [Deactivate a Subscription Plan](admin/US-AD-5-3-deactivate-subscription-plan.md) | In progress |
| US-AD-5-4 | [Delete an Unused Subscription Plan](admin/US-AD-5-4-delete-unused-subscription-plan.md) | In progress |
| US-AD-6-1 | [Set Course Subscription Availability](admin/US-AD-6-1-set-course-subscription-availability.md) | In progress |
| US-AD-7-1 | [Create a Coupon](admin/US-AD-7-1-create-coupon.md) | Spec |
| US-AD-7-2 | [Update a Coupon](admin/US-AD-7-2-update-coupon.md) | Spec |
| US-AD-7-3 | [Deactivate or Delete a Coupon](admin/US-AD-7-3-deactivate-or-delete-coupon.md) | Spec |
| US-AD-8-1 | [Create an Offer](admin/US-AD-8-1-create-offer.md) | Spec |
| US-AD-8-2 | [Update an Offer](admin/US-AD-8-2-update-offer.md) | Spec |
| US-AD-8-3 | [Deactivate or Delete an Offer](admin/US-AD-8-3-deactivate-or-delete-offer.md) | Spec |
| US-AD-9-1 | [Create a Program](admin/US-AD-9-1-create-program.md) | Spec |
| US-AD-9-2 | [Update a Program](admin/US-AD-9-2-update-program.md) | Spec |
| US-AD-9-3 | [Deactivate or Delete a Program](admin/US-AD-9-3-deactivate-or-delete-program.md) | Spec |
| US-AD-10-1 | [Assign Certificate to Course](admin/US-AD-10-1-assign-certificate-to-course.md) | Spec |
| US-AD-10-2 | [Assign Certificate to Program](admin/US-AD-10-2-assign-certificate-to-program.md) | Spec |
| US-AD-10-3 | [Remove Certificate from Course](admin/US-AD-10-3-remove-certificate-from-course.md) | Spec |

### Teacher — `teacher/`
| ID | Title | Status |
|----|-------|--------|
| US-TR-1-1 | [Update Teacher Profile](teacher/US-TR-1-1-update-teacher-profile.md) | Built |
| US-TR-1-2 | [View Related Lessons](teacher/US-TR-1-2-view-related-lessons.md) | Spec |
| US-TR-1-3 | [View Teacher Earnings and Withdrawals](teacher/US-TR-1-3-view-teacher-earnings-and-withdrawals.md) | Spec |
| US-TR-2-1 | [Export Teacher Reports](teacher/US-TR-2-1-export-teacher-reports.md) | Spec |

### Student — `student/`
| ID | Title | Status |
|----|-------|--------|
| US-ST-1-1 | [Student Registration](student/US-ST-1-1-student-registration.md) | Spec |
| US-ST-2-1 | [Browse Teachers](student/US-ST-2-1-browse-teachers.md) | Built |
| US-ST-2-2 | [View Related Lessons](student/US-ST-2-2-view-related-lessons.md) | Spec |
| US-PK-1-1 | [View Available Packages](student/US-PK-1-1-view-available-packages.md) | Built |
| US-PK-1-2 | [Purchase a Package](student/US-PK-1-2-purchase-a-package.md) | Built |
| US-PK-2-1 | [View My Packages and Payment History](student/US-PK-2-1-view-my-packages-and-payment-history.md) | Built |
| US-SB-1-1 | [View Available Subscriptions](student/US-SB-1-1-view-available-subscriptions.md) | In progress |
| US-SB-1-2 | [Purchase a Subscription](student/US-SB-1-2-purchase-a-subscription.md) | In progress |
| US-SB-2-1 | [View My Subscriptions and Payment History](student/US-SB-2-1-view-my-subscriptions-and-payment-history.md) | In progress |
| US-US-CP-1-1 | [View Available Coupons](student/US-US-CP-1-1-view-available-coupons.md) | Spec |
| US-US-CP-1-2 | [Apply a Coupon](student/US-US-CP-1-2-apply-a-coupon.md) | Spec |
| US-US-CP-1-3 | [View My Coupons and Usage History](student/US-US-CP-1-3-view-my-coupons-and-usage-history.md) | Spec |
| US-US-OF-1-1 | [View Available Offers](student/US-US-OF-1-1-view-available-offers.md) | Spec |
| US-US-OF-1-2 | [Apply Offer Automatically](student/US-US-OF-1-2-apply-offer-automatically.md) | Spec |
| US-US-OF-1-3 | [View My Offer Usage History](student/US-US-OF-1-3-view-my-offer-usage-history.md) | Spec |
| US-US-10-1 | [View & Download My Certificates](student/US-US-10-1-view-and-download-my-certificates.md) | Spec |

### Lessons — `lessons/`
| ID | Title | Status |
|----|-------|--------|
| US-LS-1-1 | [Student Requests a Lesson](lessons/US-LS-1-1-student-requests-a-lesson.md) | Spec |
| US-LS-2-1 | [Teacher Accept/Reject/Suggest](lessons/US-LS-2-1-teacher-accept-reject-suggest.md) | Spec |
| US-LS-2-2 | [Student Accept/Reject/Suggest](lessons/US-LS-2-2-student-accept-reject-suggest.md) | Spec |
| US-LS-2-3 | [Teacher Accept/Reject (after response)](lessons/US-LS-2-3-teacher-accept-reject.md) | Spec |
| US-LS-3-1 | [Start and Join a Lesson](lessons/US-LS-3-1-start-a-lesson.md) | In progress |
| US-LS-3-2 | [Complete a Lesson](lessons/US-LS-3-2-complete-a-lesson.md) | In progress |
| US-LS-3-3 | [Report Student Absence](lessons/US-LS-3-3-report-student-absence.md) | Spec |
| US-LS-3-4 | [Report Teacher Absence](lessons/US-LS-3-4-report-teacher-absence.md) | Spec |
| US-LS-4-1 | [Cancel a Lesson as a Student](lessons/US-LS-4-1-cancel-lesson-as-student.md) | Spec |
| US-LS-4-2 | [Cancel a Lesson as a Teacher](lessons/US-LS-4-2-cancel-lesson-as-teacher.md) | Spec |
| US-LS-5-1 | [Update Lesson Time](lessons/US-LS-5-1-update-lesson-time.md) | Spec |
| US-LS-5-2 | [Respond to Update Request](lessons/US-LS-5-2-respond-to-update-request.md) | Spec |

### Financial — `financial/`
| ID | Title | Status |
|----|-------|--------|
| — | [Wallet model (reference)](financial/00-wallet-model.md) | — |
| US-FN-1-1 | [Purchase a Flex Package](financial/US-FN-1-1-purchase-a-flex-package.md) | Built |
| US-FN-1-2 | [Reserve a Flex for a Lesson](financial/US-FN-1-2-reserve-a-flex-for-a-lesson.md) | Spec |
| US-FN-1-3 | [Return a Reserved Flex](financial/US-FN-1-3-return-a-reserved-flex.md) | Spec |
| US-FN-1-4 | [Distribute Lesson Revenue](financial/US-FN-1-4-distribute-lesson-revenue.md) | Spec |
| US-FN-1-5 | [Return a Flex After Revenue Distribution](financial/US-FN-1-5-return-a-flex-after-revenue-distribution.md) | Spec |
| US-FN-2-1 | [Teacher Earnings Withdrawal](financial/US-FN-2-1-teacher-earnings-withdrawal.md) | Spec |
| US-FN-2-2 | [Admin Process Withdrawal](financial/US-FN-2-2-admin-process-withdrawal.md) | Spec |

### B2B Administrator — `B2B Administrator/`
| ID | Title | Status |
|----|-------|--------|
| US-B2B-1-1 | [Purchase a B2B Subscription](B2B%20Administrator/US-B2B-1-1-purchase-a-b2b-subscription.md) | Spec |
| US-B2B-1-2 | [Generate a B2B Invitation Link](B2B%20Administrator/US-B2B-1-2-generate-a-b2b-invitation-link.md) | Spec |
| US-B2B-1-3 | [Join Through a B2B Invitation Link](B2B%20Administrator/US-B2B-1-3-join-through-a-b2b-invitation-link.md) | Spec |
| US-B2B-1-4 | [Automatically Approve an Invited User](B2B%20Administrator/US-B2B-1-4-automatically-approve-invited-user.md) | Spec |
| US-B2B-1-5 | [Approve a User Membership](B2B%20Administrator/US-B2B-1-5-approve-a-user-membership.md) | Spec |
| US-B2B-1-6 | [Reject a User Membership](B2B%20Administrator/US-B2B-1-6-reject-a-user-membership.md) | Spec |
| US-B2B-1-7 | [Remove an Approved User](B2B%20Administrator/US-B2B-1-7-remove-an-approved-user.md) | Spec |
| US-B2B-1-8 | [View B2B Subscription Capacity](B2B%20Administrator/US-B2B-1-8-view-b2b-subscription-capacity.md) | Spec |
| US-B2B-1-9 | [View User Activity Report](B2B%20Administrator/US-B2B-1-9-view-user-activity-report.md) | Spec |
