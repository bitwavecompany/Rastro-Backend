# Rastro — Backlog and Out of Scope

> **Date:** October 2026
> **Status:** MVP Reference

---

## Backlog: Actual Transfers Between Own Accounts

The `transfer_id` field has been reserved in the MVP to avoid requiring database migrations once this feature is implemented.

An actual transfer represents money that was physically moved from one account to another (e.g., withdrawing money from the bank to keep as cash). This is strictly different from a relocation, which is meant to be a historical correction:

| | Relocation (Available) | Actual Transfer (Backlog) |
|---|---|---|
| What it represents | A record that was saved in the wrong account | Actual physical movement of money between accounts |
| Source record | Deactivated with a `deletion_reason` | Remains active as an `expense` |
| Destination record | New active record inheriting fields | New active record as an `income` |
| Resulting active records | 1 active + 1 deactivated | 2 active records linked by `transfer_id` |
| Net worth impact | Neutral | Neutral |

---

## Out of Scope Features for the MVP

- Payment gateways (Stripe, PayPhone, PlacetoPay, etc.)
- Push notifications — only email notifications are in scope
- Multi-currency support — `currency` field reserved in `Account`
- Tags and tag filtering — `tags` field reserved in `Transaction`
- Budgets and spending limit alerts
- Recurring transactions
- Charts and advanced analytics
- Sync conflict resolution beyond sequential Outbox ordering
- Shared or collaborative accounts