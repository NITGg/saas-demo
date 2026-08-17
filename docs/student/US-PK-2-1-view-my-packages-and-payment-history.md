# US-PK-2-1: View My Packages and Payment History

[← spec index](../README.md) · Area: Student (packages) · **Status:** Built · API: [student-packages](../../api/student-packages-mobile-guide.md)

As a student, I want to view my packages and payment history, so that I can track Flex balance, package validity, and previous payments.

## Flow
1. 🎓 Open "My Packages" → ⚙️ display active package + previous packages → 🎓 review
2. 🎓 Open "Payment History" → ⚙️ display previous transactions
3. 🎓 Select a transaction → ⚙️ display payment details

## Package info (per package)
Name · total Flexes · remaining Flexes · used Flexes · activation date · expiration date · status.

## Payment info (per payment)
Package name · amount · date · method · status · transaction number.

## Notes
- Student sees only their own packages/payments; active package appears first.
- Previous packages include fully used and expired (expired cannot be used; fully used = 0 remaining).
- Successful payments link to purchased packages; failed payments do not activate a package or add Flexes.
- Payment records remain available even after a package expires or is fully used.
