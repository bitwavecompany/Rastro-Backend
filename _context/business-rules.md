# Rastro — Business Rules

> **Date:** October 2026
> **Status:** Approved / In Implementation

This document is the source of truth for Rastro's business logic. It dictates the expected behavior of the system across all layers — frontend, backend, and synchronization. All implementations must adhere to these rules without exception.

**References:**
- Types and interfaces → [`data-model.md`](./data-model.md)
- Out of scope features → [`backlog.md`](./backlog.md)

---

## Table of Contents

1. [Plans and Entitlements](#1-plans-and-entitlements)
2. [Accounts](#2-accounts)
3. [Transactions](#3-transactions)
4. [Dashboard](#4-dashboard)
5. [Data Lifecycle](#5-data-lifecycle)
6. [Offline Synchronization](#6-offline-synchronization)
7. [Authentication and Sessions](#7-authentication-and-sessions)
8. [Limits and Collisions Management](#8-limits-and-collisions-management)
9. [Upgrade and Downgrade](#9-upgrade-and-downgrade)
10. [Administrative Panel](#10-administrative-panel)

---

## 1. Plans and Entitlements

### Plans Table

|  | Guest | Free | Premium |
|---|---|---|---|
| **Registration required** | No | Yes | Yes |
| **Active accounts** | 1 (Cash only) | 3 | Unlimited |
| **Transactions** | Unlimited | Unlimited | Unlimited |
| **Visible history** | Local device only | Last 6 months | Complete history |
| **Cloud sync** | ❌ | ✅ | ✅ |
| **Export CSV** | ❌ | ❌ | ✅ |
| **Concurrent devices** | 1 (local, no remote session) | 1 | Unlimited |

### Model Rules

**Unlimited transactions across all plans.** There is no transaction counter in the system. Never display limits or transaction counters to the user.

**Archived history is never deleted from the server.** The 6-month limit for the Free plan is a visibility filter applied at the API level. Upgrading to Premium immediately restores the complete history on the next synchronization.

**Guest data lives exclusively on the device.** Uninstalling the app permanently destroys all their data with no possibility of recovery.

**The Guest is cash-only as a consequence of the slots model, not by direct restriction.** Its single account slot is permanently occupied by the default "Cash" account (`cash` type). It cannot create a `bank` type account because it has no available slots. When selecting `transfer` in the transaction form, the account list will always be empty, and the system will display the conversion message to sign up for a free account. Backend validation of payment methods by plan is not required — the slots system implicitly resolves it.

**An active device is exactly equivalent to a valid `refresh_token` registered on the server.** The Guest operates without a remote session. The Free user is allowed exactly 1 active device. The Premium user has unlimited concurrent devices.

---

## 2. Accounts

### Default Account ("Cash")

Upon launching the app for the first time, the system automatically creates an account named "Cash" for every new user, whether Guest or Registered. This account has three absolute properties with no exceptions:

- **Type-locked & Named:** the name ("Cash") and type (`cash`) cannot be edited under any circumstance.
- **Permanent:** it cannot be deactivated or deleted.
- **Editable initial balance:** its `initial_balance` (and optional description) can be edited to record and adjust opening capital without distorting monthly income metrics.
- **Occupies 1 slot** of the user's plan at all times.

### Fields

| Field | Type | Rules |
|---|---|---|
| `name` | `string` | Required. Unique per user — duplicate names are not allowed. Immutable for "Cash". |
| `type` | `'cash' \| 'bank'` | Required. Editable under conditions (see Account Type Editing). Immutable (`cash`) for the "Cash" account. |
| `initial_balance` | `number` | Required. Must be ≥ 0. Editable on all accounts (including the default "Cash" account) to set or correct opening balance — not as a subsequent accounting adjustment. |
| `amount` | `number` | Read-only. Calculated and returned by the server in every response. The client never calculates or sends it. |
| `description` | `string \| null` | Optional. |
| `is_default` | `boolean` | `true` only for the auto-created "Cash" account. |
| `deactivation_reason` | `'user_action' \| 'plan_downgrade' \| null` | `null` when the account is active. Controls the visible message in the UI (see message table below). |
| `deleted_at` | `string \| null` | `null` = active account. ISO8601 Timestamp = deactivated account. |

**UI Messages based on `deactivation_reason`:**

| Value | Visible message |
|---|---|
| `null` | The account is active — no message is shown. |
| `'user_action'` | *"Account deactivated"* |
| `'plan_downgrade'` | *"Account deactivated upon changing your plan to Free"* |

### Balance Calculation (`amount`)

```
amount = initial_balance
       + SUM(active transactions of type income)
       − SUM(active transactions of type expense)
```

"Active" means `deleted_at = null`. Deactivated transactions do not affect the balance. The backend calculates `amount` at query time and returns it in every account response. The client displays the value directly without recalculating it locally.

### Slots

A **slot** is a unit of account capacity within the plan. The rules are:

- Deactivating an account immediately frees up its slot.
- Reactivating an account immediately occupies a slot.
- If there is no available slot when attempting to reactivate, the operation is blocked until the user frees a slot or upgrades.

### Account Type Editing

> This section does not apply to the "Cash" account, whose type is always immutable.

The behavior when changing the type of an account depends on whether it has associated transactions:

| State | Allows change? | Behavior |
|---|---|---|
| Without transactions (active or deactivated) | ✅ Yes | Free and immediate change. No confirmation dialog. |
| With at least one transaction (active or deactivated) | ✅ Yes, with confirmation | Requires a warning dialog before applying. |

When there are transactions, the confirmation modal must display the exact following text and cannot be bypassed:

> *"Changing the type of this account will update the payment method of all its transactions. This action cannot be undone."*

The modal has two buttons: **Cancel** with default focus, and **Confirm change** in a warning style.

**Cascading upon change confirmation:** the system updates the `payment_method` field of all transactions in that account — active and deactivated — atomically:

```
cash → bank   :  all transactions update payment_method = 'transfer'
bank → cash   :  all transactions update payment_method = 'cash'
```

This change does not alter amounts or balances. It only aligns the `payment_method` to maintain the relational consistency of the system.

---

## 3. Transactions

### Fields

| Field | Type | Rules |
|---|---|---|
| `amount` | `number` | Required. Must be > 0. Always positive — the `type` field determines whether it adds or subtracts from the balance. |
| `reason` | `string` | Required. Short title describing the transaction. |
| `description` | `string \| null` | Optional. Relocations from deactivated accounts automatically append an audit note. |
| `payment_method` | `'cash' \| 'transfer'` | Required. Filters the list of available accounts and must match the assigned account's type. |
| `type` | `'income' \| 'expense'` | Required. Determines the transaction's direction in the balance. |
| `account_id` | `string` | Required. UUID of an active account whose type matches the `payment_method`. |
| `date` | `string` | Required. `YYYY-MM-DD` format. Actual date of the transaction, visible and editable by the user. Default: current day. |
| `created_at` | `string` | ISO8601. DB creation timestamp. Immutable. Assigned by the server. Not visible or editable. |
| `updated_at` | `string` | ISO8601. Last modification timestamp. Automatically updated. Not visible or editable. |
| `transfer_id` | `string \| null` | Reserved for transfers between own accounts (Backlog). Store as `null`. |
| `category` | `'comida' \| 'transporte' \| 'entretenimiento' \| 'estudio' \| 'trabajo' \| 'otros' \| null` | Mandatory for `expense` transactions. Must be selected from the backend catalog (`GET /api/categories`). Null for `income` transactions. |
| `tags` | `string[]` | Reserved for future use. Store as `[]`. |
| `deleted_at` | `string \| null` | `null` = active. ISO8601 Timestamp = deactivated. |
| `deletion_reason` | `string \| null` | Optional note when manually deactivating. Mandatory reason for relocations. Value is `'cascade_account_deactivation'` when the transaction is deactivated due to its account cascading. |

### Payment Method Selection and Account Filtering

When creating or editing a transaction, the `payment_method` field reactively filters the available accounts in the selector:

| `payment_method` | Accounts displayed |
|---|---|
| `'cash'` | Only active accounts of type `cash` |
| `'transfer'` | Only active accounts of type `bank` |

If the resulting list is empty, the user cannot proceed or log the transaction with that method. An empty selector must always display a contextual message and a suggested action — never just an empty list with no explanation:

| Situation | Message | Action |
|---|---|---|
| Guest selects `'transfer'` | *"You need a bank account to log transfers. Sign up for free to create one."* | Button linking to the registration screen |
| Free or Premium user without active bank accounts selects `'transfer'` | *"Create a bank-type account to log transfers."* | Button to create an account (subject to available slots) |
| Any user without active cash accounts selects `'cash'` | *"You have no active cash accounts available for this payment method."* | — |

### Account-Transaction Association Restriction

When creating or editing a transaction, two conditions are validated on the frontend and backend:

1. The account referenced in `account_id` must be active (`deleted_at = null`).
2. The account's type must match the `payment_method`:
   - `'cash'` ↔ `cash` type account
   - `'transfer'` ↔ `bank` type account

If either condition fails — whether due to payload manipulation or out-of-order synchronization — the backend responds with **HTTP 403 Forbidden**.

### Dates

Every transaction has three date fields with distinct and independent roles:

| Field | Visible to user | Editable | Behavior |
|---|---|---|---|
| `date` | ✅ Yes | ✅ Yes | Actual transaction date. Default: current day. Inherited without changes during relocations. |
| `created_at` | ❌ No | ❌ No | DB creation timestamp. Immutable. Assigned exclusively by the server. |
| `updated_at` | ❌ No | ❌ No | Record's last modification timestamp. Automatically updated by the system. |

The frontend captures `date` with a standard selector in `YYYY-MM-DD` format. It does not request or transmit a time component. Upon receiving the record, the backend assigns the server time (`now()`) to guarantee a uniform and consistent chronological ordering across devices.

**Date format in UI:** any date visible to the user — on any screen, modal, message, transactional email, or administrative panel — strictly uses the `dd mmm yyyy` format (e.g., `10 Oct 2026`). Pure numeric formats (`10/10/2026` or `2026-10-10`) are strictly prohibited.

### Transaction Creation

The user first selects the `payment_method`. That value filters the list of available accounts (see previous section). Once a valid account is selected, they fill in the remaining fields and save. The association restriction validation is applied before persisting.

### Transaction Editing

The editing form is identical to the creation form, pre-filled with current values. The user can modify any field directly. The affected account's balance recalculates in real-time.

When the user modifies `payment_method` or `account_id`, the following dependency rules apply:

**Case A — the user changes `payment_method`:**
The `account_id` field is automatically cleared. The account selector is refiltered based on the new method. The user must select a valid account from the new list before they can save.

**Case B — the user directly changes `account_id`:**
- If the new account is of the same type as the current `payment_method`: `payment_method` does not change.
- If the new account is of a different type than the current `payment_method`: `payment_method` automatically updates to the corresponding type.

In both cases, the association restriction is validated before allowing the save.

### Transaction Relocation Between Accounts

A relocation is a historical correction operation: it moves a transaction from one account to another with complete traceability. It does not edit or delete the original record.

**Blocking condition.** Relocation is only available when at least one valid destination account exists:

```
active_accounts_of_the_required_type_excluding_source_account.length > 0
```

If this condition is not met, the relocation modal does not open. Instead, a banner is shown:

| Plan | Message | Action |
|---|---|---|
| Guest | *"You need at least one destination account available to perform relocations. Sign up for free to create more accounts."* | Button → `/register` |
| Free / Premium | *"You need at least one destination account available to perform relocations. Create a new account first."* | Button → `/accounts` |

**Flow when relocation is available:**

1. The user selects the destination account.
2. The system displays a confirmation modal: *"Will be relocated from [Source Account] to [Destination Account]"*, with a mandatory field for the relocation reason.
3. Upon confirmation, within an **atomic transaction**, two operations occur:
   - The original record is **deactivated** in the source account. The relocation reason is logged in `deletion_reason`.
   - A **new active record is created** in the destination account.

**New record fields:**

| Field | Value |
|---|---|
| `amount`, `reason`, `description`, `type` | Inherited from the original record |
| `date` | Inherited — preserves the original actual transaction date |
| `account_id` | UUID of the destination account |
| `payment_method` | Derived from the destination account's type: `cash` if `cash` type, `transfer` if `bank` type |
| `created_at` | Timestamp of the moment the relocation is executed |
| `updated_at` | Equal to `created_at` at the moment of creation |

**Special Case — relocation from a deactivated account:**

When the source record belongs to a deactivated account (its `deletion_reason` is `'cascade_account_deactivation'`):

- The new record in the destination account spawns active (`deleted_at = null`) and immediately affects that account's balance.
- The original record **preserves** `deletion_reason = 'cascade_account_deactivation'` intact so as not to destroy the cascade audit trail. The relocation traceability is appended to its `description` field:
  ```
  [Relocated to [Destination Account] on dd mmm yyyy: [user's reason]]
  ```
- The balance of the deactivated source account remains unchanged.

---

## 4. Dashboard

### Main View

The dashboard presents four calculated metrics and a list of recent transactions:

| Element | Definition |
|---|---|
| **Total Net Worth** | Sum of `amount` across all the user's active accounts. |
| **Monthly incomes** | Sum of `amount` of all active `income` transactions within the current calendar month. |
| **Monthly expenses** | Sum of `amount` of all active `expense` transactions within the current calendar month. |
| **Monthly net balance** | Monthly incomes − Monthly expenses. |
| **Recent transactions** | The last 8 active transactions combined from all accounts, ordered by `date DESC`. |

Recent transactions are retrieved via a local query on Dexie/IndexedDB — without a server request:

```sql
- SELECT
FROM transactions
WHERE deleted_at IS NULL
ORDER BY date DESC
LIMIT 8
```

The number 8 is fixed and not configurable in the MVP. If the user has fewer than 8 total active transactions, the available ones are shown without a special empty state.

### Account Detail View

Displays the current `amount` of the account and lists its transactions across three tabs: **All**, **Incomes**, and **Expenses**. By default, only active transactions are shown. A toggle allows the user to also view deactivated transactions, which are displayed in a visually dimmed and clearly distinct style.

**Batch reactivation banner.** When the user reactivates an account, and that account has transactions with `deletion_reason = 'cascade_account_deactivation'`, the detail view displays an informational banner:

> *"This account has X deactivated transactions. You can reactivate them all or review them one by one."*

The banner provides two actions:

- **Reactivate all:** shows a confirmation modal and, upon confirming, batch-reactivates all transactions holding `deletion_reason = 'cascade_account_deactivation'`.
- **Review individually:** enables the deactivated transactions toggle so the user can act on each one separately.

This banner only appears for transactions deactivated by account cascading. Transactions manually deactivated for other reasons are not included and always require individual action.

### History Cutoff for the Free Plan

The API applies a visibility filter for Free users: it only returns transactions from the last 6 months. Older data exists on the server but is not returned. Upon upgrading to Premium, the complete history becomes available on the next sync without data loss.

---

## 5. Data Lifecycle

The system distinguishes three operations on existing records: deactivation, permanent deletion, and reactivation. These operations apply to both accounts and transactions, with cascading rules between them.

### Deactivation (Soft Delete)

Deactivation is the **primary deletion action** in the app. It does not delete the record — it marks it as inactive.

When the user presses "delete" on any account or transaction, the system executes a deactivation: it sets `deleted_at` to the current timestamp. The record remains in the database, is excluded from all balance calculations, and is hidden from all app selectors. Deactivated accounts immediately free up their slot. The operation is reversible.

**Cascading upon account deactivation.** Deactivating an account automatically deactivates all its transactions. Each affected transaction receives:

```
deletion_reason = 'cascade_account_deactivation'
```

This value makes it possible to identify at any time that the transaction was not individually deactivated by the user, but as a consequence of its account's deactivation.

**No cascade upon account reactivation.** Reactivating an account does not automatically reactivate its transactions. Each transaction must be reactivated individually or via the batch reactivation option available in the account detail view (see Section 4).

**Behavior of a deactivated account.** The behavior is identical regardless of whether the account was deactivated by a user action or a plan downgrade. There are no deactivation states with differing rules.

| Behavior | Always Applies |
|---|---|
| Does not appear in any app selector | ✅ |
| Its transactions are cascade-deactivated | ✅ |
| Its individual transactions are not editable | ✅ |
| Its individual transactions are not reactivatable while the account remains deactivated | ✅ |
| Its transactions CAN be relocated to an active account | ✅ |
| Can be permanently deleted | ✅ |
| Can be reactivated if a slot is available in the plan | ✅ |

### Permanent Deletion (Hard Delete)

Permanent deletion physically erases the record from the database. It is irreversible.

This operation is **never the primary action**. The mandatory regular flow is: deactivate first, then permanently delete. An active transaction cannot be hard-deleted in a single step.

The only authorized exception to this flow is the excess accounts resolution modal during a downgrade (see Section 8), where a direct hard delete is permitted to avoid a double step within an already blocking flow.

Every hard delete must display a confirmation modal before executing:

> *"This action is permanent and cannot be undone. The record will be deleted from the database."*

The modal has two buttons: **Cancel** with default focus, and **Permanently delete** in a destructive style.

Access points for hard delete are:

| Scope | Where it is available |
|---|---|
| An individual transaction | Action menu on a **deactivated** transaction |
| All transactions in an account | Account settings (the account itself is not deleted) |
| All user data | Profile / General settings |
| An excess account during downgrade | Excess resolution modal — authorized exception |

### Reactivation

| Entity | Condition to reactivate |
|---|---|
| Transaction | The account it belongs to must be active (`deleted_at = null`). If it is deactivated, the account must be reactivated first. |
| Account | The user must have at least one free slot in their plan. Upon reactivation: `deleted_at → null`, `deactivation_reason → null`. Its transactions remain deactivated. |
| "Cash" Account | Not applicable — cannot be deactivated. |

**Warning when reactivating a relocated transaction.** When the user attempts to reactivate a transaction that was the source of a relocation, the system displays a mandatory warning before confirming:

> *"This record was relocated to [Destination Account] on [dd mmm yyyy]. Reactivating it will account for it again in this account, duplicating the transaction."*

The system does not block the reactivation — it only informs. The user decides whether to cancel or proceed.

---

## 6. Offline Synchronization

### Principle

All reads and writes operate first on the local device database (Dexie/IndexedDB). The app is fully functional without internet. Only registered users (Free and Premium) sync with the cloud — Guest data is never sent to the server.

### Outbox Pattern (Push)

Changes made offline are queued in an Outbox in the exact order they occurred. When connectivity is detected, the sync engine sends each operation to the server sequentially via `POST /api/sync/push` and marks it as synced upon receiving confirmation.

### Hydration and Multi-device Sync (Pull)

To synchronize updates made across multiple devices, during page reloads, or upon mobile app resume (`visibilitychange`), the client executes a pull request to `GET /api/sync/pull` (optionally specifying `?since=<ISO_TIMESTAMP>`). 
- **Clock Skew Safety Buffer:** For incremental queries (`?since=`), the server subtracts a 2-second safety buffer (`since - 2s`) to prevent race conditions from concurrent operations in flight.
- **Soft Deletes:** Entities with `deleted_at != null` retain their deactivation timestamp; the client uses `bulkPut` to reflect deactivated/soft-deleted records reactively in Dexie.
- **Physical Deletions (Tombstones):** Irreversible physical deletions (`hard-delete`, `purge-account`, `purge-all`) register deleted entity IDs in `sync_tombstones`. In incremental pulls, the server returns `deleted_account_ids` and `deleted_transaction_ids`. The client uses `bulkDelete` to purge these IDs directly from IndexedDB without requiring a forced full re-download (`forceFull`).

### Dead Letter Queue (DLQ)

If the server permanently rejects an operation (HTTP 422 or HTTP 403), the operation is not silently discarded. It is moved to the Dead Letter Queue — an error queue visible in the app where the user can review the failed operation and its reason, correct the data and retry it, or manually discard it. No operation fails without the user being informed.

**Hierarchical dependency management in the DLQ:**

When an account creation is rejected, the account and all its dependent transactions go to the DLQ together. If the user discards the rejected account, the system automatically cascade-discards all its dependent transactions — not silently, but subject to confirmation:

> *"Discarding the [Account] will also delete the X associated transactions pending synchronization."*

If the user regains slots (for example, by upgrading to Premium) and retries the account, the engine ensures the account is confirmed on the server before retrying its transactions. If the account fails, the transactions are not retried.

Operations in the DLQ persist indefinitely without automatic purging until the user acts on them.

---

## 7. Authentication and Sessions

### Dual-Token Lifecycle

The system manages two tokens per active session:

| Token | Duration | Purpose |
|---|---|---|
| `access_token` | 60 minutes | Authorizes each request via the header `Authorization: Bearer <access_token>`. |
| `refresh_token` | 30 days | Transparently obtains a new `access_token` without requiring re-login. |

The frontend automatically renews the `access_token` before it expires or reactively upon a `401` with `code: "TOKEN_EXPIRED"`. The user never perceives the `access_token` expiration. The only time the user experiences a forced re-login is when the `refresh_token` expires (30 days of inactivity) or is invalidated by the server.

### Events that Invalidate the Refresh Token

The server invalidates the `refresh_token` under the following events:

| Event |
|---|
| Admin approves or rejects a Premium upgrade request |
| Admin revokes a user's Premium plan (downgrade) |
| User manually logs out |
| A Free user confirms a session on a second device (the first device's token is invalidated) |
| User deletes their account |

### Session Concurrency

**Free User — login attempt on a second device.** If a Free user has an active session on one device and attempts to log in on another, the login is immediately rejected — the previous session is not silently displaced. The server responds with:

```
HTTP 409 Conflict
{ "code": "SESSION_LIMIT_REACHED" }
```

The frontend displays the following dialog:

> *"You already have an active session on another device. Close that session to continue here, or upgrade to Premium to use Rastro on all your devices."*

The user has two actions available:

- **Close session on the other device:** the server invalidates the previous device's `refresh_token` and issues a new session on the current device. Before executing, the frontend warns: *"Logging in here will close your session on the other active device."*
- **Upgrade to Premium:** redirects to the upgrade flow.

**Device with remotely revoked session.** When the server invalidates a device's `refresh_token` because the user confirmed login on another, subsequent requests from that device receive:

```
HTTP 401
{ "code": "SESSION_REVOKED_REMOTE", "message": "Session closed from another device." }
```

The frontend intercepts this specific code and shows a modal:

> *"Your session was closed because you logged in on another device. Log in again to continue here."*

With two available actions: **Log in** (with a warning that it will close the other device's session if the plan is Free) and **Delete account** (always available per privacy regulations).

Receiving a `SESSION_REVOKED_REMOTE` **must never purge the local database or execute `clearAllLocalData`**. Synchronization is immediately frozen, but all local data — transactions, accounts, and pending operations in the Outbox — remain fully intact. Upon logging back in, the Merge flow triggers (see Section 8).

### Account Management

**Guest User:**

| Action | Result |
|---|---|
| Delete local data | Destroys the local temporary ID and the entire device database. Irreversible. Requires the standard confirmation modal. |

**Registered User (Free or Premium):**

| Action | Result |
|---|---|
| Log out | Invalidates the `refresh_token` on the server and clears the local device session. Cloud data remains intact. |
| Delete account | Physically deletes all user data on the device and the server, and invalidates all their tokens. Requires two-step confirmation: confirming intent, then typing a confirmation phrase (e.g., "DELETE"). Complies with GDPR regulations. |

---

## 8. Limits and Collisions Management

### Merge: Guest Logs into Existing Account

When a Guest logs into a registered account, the system evaluates compatibility before completing the login:

```
total_active_accounts = active_accounts_on_device + active_accounts_in_cloud
```

If `total_active_accounts ≤ plan_limit`, the merge occurs automatically: local data is associated with the cloud account, and the user is authenticated. The local "Cash" account maps to the `is_default: true` account in the cloud, reassigning its transactions.

If `total_active_accounts > plan_limit`, the merge is blocked, and the system displays the collision modal with three options:

| Option | Result |
|---|---|
| **Discard local data** | The Guest data on the device is permanently deleted. The user logs in solely with the cloud data. |
| **Upgrade to Premium** | The user submits a payment receipt. They remain a Guest with their local data intact while verification is pending. |
| **Cancel** | The modal closes. Nothing changes. The user remains a Guest. |

If the user closes the app without selecting any option, the local data is preserved and the login does not occur.

### Guest Signs Up (New Account)

The Guest always has exactly one active account (Cash), so migration when creating a new account is always automatic and never causes a collision. If the Guest limit changes in the future, the same merge flow from the previous point applies.

### Attempting to Exceed the Account Limit

| Trigger | Behavior |
|---|---|
| Free user attempts to create a 4th active account | Blocked. *"You reached the limit of 3 accounts. Deactivate an account or upgrade to Premium."* |
| Free user attempts to reactivate an account with no available slots | Blocked. Same message. |
| Guest attempts to create any additional account | Blocked. *"Create a free account to add more accounts and back up your data to the cloud."* Directs to registration, not directly to Premium. |

### Excess Accounts Modal (Downgrade)

When a user's plan is downgraded to Free and the user has more than 3 active accounts, a mandatory resolution modal appears upon opening the app. This modal has the following traits:

- **No close:** has no X button, cannot be dismissed by clicking outside, does not offer "remind me later".
- **Persistent:** if the user backgrounds the app and returns, the modal reappears.
- **Impassable:** there is no navigation path within the app that bypasses this modal.
- **Reactive:** the excess accounts counter updates in real-time as the user acts.
- **Auto-closing:** the modal automatically closes as soon as `active_accounts.length ≤ 3`.

The modal displays the message:

> *"You have X active accounts. Your Free plan allows 3. You must deactivate or delete Y accounts to continue."*

It lists all eligible active accounts, excluding the "Cash" account (permanent). For each excess account, the user can:

| Action | Immediate Result |
|---|---|
| **Deactivate** | Frees the slot. The account receives `deactivation_reason = 'plan_downgrade'`. Its transactions receive `deletion_reason = 'cascade_account_deactivation'`. |
| **Permanently delete** | Frees the slot. Irreversibly deletes the account and all its records. Authorized exception to the regular prior-deactivation flow. Requires the standard hard delete confirmation modal. |

While the excess modal is active, the following actions are blocked: viewing the dashboard, creating or editing transactions, creating accounts, and any general navigation within the app.

The following actions always remain accessible from the modal, regardless of the blockade:

| Action | Reason |
|---|---|
| **Log out** | Basic user right. Clears device tokens. |
| **Permanently delete account** | Privacy regulations (GDPR). |
| **Upgrade to Premium** | Resolves the excess without data loss. The user fills in bank and receipt details. |
| **Delete local data** (Guest only) | Equivalent to full local account deletion. |

If the user submits an upgrade request from the modal, the system does not grant provisional access. The modal adopts an informative waiting state:

> *"Your request is under review (max 24h). You can wait or deactivate accounts to continue on the Free plan now."*

### Excess Detected Upon Reconnection (Offline Scenario)

If the user operated offline on a Premium plan, created extra accounts in the Outbox, and the admin downgraded them while disconnected: upon reconnecting, the server rejects the excess creations and routes them to the DLQ.

The excess modal has **absolute priority** over the DLQ. All DLQ notifications and warnings are suppressed while the modal is active. Once the user resolves the excess and the app unlocks, the DLQ presents the context:

> *"These operations were rejected because your plan changed to Free while you were offline."*

---

## 9. Upgrade and Downgrade

### Upgrade Request (Transfer Payment)

1. The user opens the upgrade screen and taps **"Upgrade to Premium"**.
2. Fills in `origin_bank` (name of their bank) and `transfer_receipt` (receipt number).
3. The request is persisted with `status = 'pending_verification'`.
4. The app confirms: *"Your request has been received. We will review it within a maximum of 24 hours."*

While the request is in `pending_verification`: the user keeps their current plan limits, no automatic action occurs regardless of time passed, no provisional benefit is granted, and the request stays open until an admin acts.

### Admin: Approve

1. The user's plan changes to `'premium'` immediately.
2. The server invalidates the user's active `refresh_token` to force a renewal holding the new entitlements.
3. The system sends an activation email to the user (dates in `dd mmm yyyy` format).
4. Logs `payment_method = 'transfer'`, `payment_verified_by`, `verified_at`.

### Admin: Reject

1. The `rejection_reason` field is mandatory in order to reject.
2. The server invalidates the user's active `refresh_token`.
3. The system sends a rejection email with the reason and instructions to resubmit the receipt.
4. The plan remains `'free'`.
5. Logs `rejection_reason`, `payment_verified_by`, `verified_at`.

### Admin: Cash Payment (In-Person)

1. The admin manually creates the upgrade from the panel.
2. Fills in `payment_notes` with free text (e.g., *"Paid at office on 10 Oct 2026"*).
3. The plan changes to `'premium'` immediately.
4. The server invalidates the user's active `refresh_token`.
5. The system sends the same activation email to the user.
6. Logs `payment_method = 'cash'`, `payment_notes`, `payment_verified_by`, `verified_at`.

### Admin: Revoke Premium Plan (Downgrade)

1. The plan is set to `'free'` on the server immediately.
2. The server invalidates **all** of the user's active `refresh_tokens` across all their devices.
3. Connected devices receive an HTTP 401. Upon re-authenticating, the user is bound to the 1-device limit.
4. The system sends an email notifying the plan change (dates in `dd mmm yyyy` format).
5. The user's accounts **are not automatically deactivated** — the user decides what to do with them via the excess modal if applicable.

---

## 10. Administrative Panel

Separate web portal with exclusive authentication for administrators. Not accessible from the mobile app.

| Feature | Description |
|---|---|
| **User list** | All registered users showing email, registration date, and current plan. |
| **Pending Premium requests queue** | Requests in `pending_verification` sorted by date. Displays bank, receipt number, and elapsed time. |
| **Approve request** | Activates Premium plan, triggers activation email. Logs `payment_verified_by` and `verified_at`. |
| **Reject request** | Requires `rejection_reason`. Triggers rejection email. The user remains on Free. |
| **Manual plan toggle** | Upgrade or downgrade of any user's plan (corrections or internal policy). |
| **Cash payment logging** | Creates a Premium upgrade for a user who paid in person, including a free-notes field. |