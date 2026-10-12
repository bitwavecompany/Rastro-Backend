# Rastro — Product Requirements Document

> **Date:** October 2026
> **Status:** Approved / In Implementation

---

## Product

**Rastro** is a personal finance management mobile application built on an Offline-First paradigm.

---

## Problem

People need to record incomes and expenses from their phones immediately, without relying on internet access. Existing solutions fail under intermittent connectivity or lose data when there is no network.

---

## Solution

Rastro stores all data locally on the device first (Dexie/IndexedDB). When a connection is available, it silently synchronizes with the cloud in the background using the Outbox pattern. The app is fully functional without internet access at all times.

---

## Users and Plans

The system has three types of users. Plan differentiation is driven **solely by the number of active accounts** — there are no transaction limits at any tier.

| | Guest | Free | Premium |
|---|---|---|---|
| **Registration** | Not required | Required | Required |
| **Active accounts** | 1 (Cash) | 3 | Unlimited |
| **Visible history** | Local only | Last 6 months | Complete |
| **Cloud sync** | ❌ | ✅ | ✅ |
| **Export CSV** | ❌ | ❌ | ✅ |
| **Concurrent devices** | 1 (local) | 1 | Unlimited |

The upgrade to Premium is done via bank transfer or in-person cash payment, with manual verification by an administrator (maximum 24-hour SLA). There are no automated payment gateways in the MVP.

---

## MVP Features

### Mobile App

- **Accounts:** creation, editing, and deactivation of `cash` and `bank` type accounts. "Cash" account is automatically created upon first use; it is immutable and permanent.
- **Transactions:** logging, editing, and deactivating incomes and expenses associated with an account. Relocation of transactions between accounts as a historical correction operation.
- **Dashboard:** total net worth, monthly incomes, monthly expenses, monthly net balance, and last 8 active transactions — calculated locally without server requests.
- **Offline Synchronization:** Outbox pattern with a user-visible Dead Letter Queue (DLQ) for operations rejected by the server.
- **Sessions:** dual-token lifecycle (60 min `access_token` / 30-day `refresh_token`). Concurrent session control based on the active plan.
- **Limit Management:** blocking excess accounts modal upon plan downgrade. Controlled merge when logging in from a device with pre-existing local data.
- **Premium Upgrade:** request flow with transfer receipt upload. Waiting panel while verification is pending.

### Administrative Panel (Web)

Separate portal, inaccessible from the mobile app, for manual plan management and payment verification.

- List of users with current plan
- Queue of pending Premium requests
- Approve / reject requests (rejection requires a reason)
- In-person cash payment logging
- Manual plan toggle per user

---

## Technical Documentation

| Document | Content |
|---|---|
| [`business-rules.md`](./business-rules.md) | Detailed business rules: plans, accounts, transactions, dashboard, data lifecycle, sync, sessions, limits, upgrade/downgrade, admin |
| [`data-model.md`](./data-model.md) | TypeScript interfaces, canonical types, and naming uniqueness rule |
| [`backlog.md`](./backlog.md) | Out of scope features for the MVP and backlog of actual transfers between accounts |

---

## Out of Scope for the MVP

Payment gateways, push notifications, multi-currency support, categories, tags, budgets, recurring transactions, advanced charts, sync conflict resolution, and shared/collaborative accounts.

See details in [`backlog.md`](./backlog.md).