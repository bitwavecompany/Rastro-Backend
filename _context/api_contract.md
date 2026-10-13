# Rastro Backend — API Contract Specification

> **Version:** 2.1 (Canonical, Strict SSOT, and Aligned with PRD v1.3)  
> **Date:** October 2026  
> **Protocol:** JSON over HTTPS / UTF-8  
> **Naming Convention:** Strict `snake_case` across all resources and payloads.  
> **Architecture:** Offline-First with Outbox-based bulk synchronization and Zero Chatty CRUD.

---

## 1. Global Communication Standard

All responses issued by the API strictly implement a uniform envelope structure:

### 1.1 Successful Response (200 OK / 201 Created)
```json
{
  "success": true,
  "message": "Successful operation.",
  "data": { ... },
  "meta": null
}

```

For paginated lists, the `meta` object includes pagination metadata controllable by the client:

```json
{
  "success": true,
  "message": "Transactions successfully retrieved.",
  "data": [ ... ],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 142
  }
}

```

### 1.2 Validation Error Response (422 Unprocessable Entity)

```json
{
  "success": false,
  "message": "The submitted data is invalid.",
  "data": null,
  "meta": {
    "errors": {
      "name": ["An account with this name already exists."]
    }
  }
}

```

### 1.3 Conflict and Session Security Responses

For concurrency control and remote session revocation:

* **Concurrent Session Limit (409 Conflict):**

```json
{
  "success": false,
  "code": "SESSION_LIMIT_REACHED",
  "message": "You already have an active session on another device.",
  "data": null
}

```

* **Remotely Revoked Session (401 Unauthorized):**

```json
{
  "success": false,
  "code": "SESSION_REVOKED_REMOTE",
  "message": "Session closed from another device.",
  "data": null
}

```

* **Invalid or Expired Refresh Token (401 Unauthorized):**

```json
{
  "success": false,
  "code": "INVALID_REFRESH_TOKEN",
  "message": "The refresh token is invalid or has expired.",
  "data": null
}

```

> ⚠️ **Architectural Rule (AGENTS.md):** Hardcoding strings or error codes in controllers or responses is strictly forbidden. Every message must originate from the central dictionary `App\Constants\ApiMessage`, and every HTTP code from `App\Constants\HttpCode`.

---

## 2. JSON Models and Resources (API Resources) — Single Source of Truth (SSOT)

The system implements **`snake_case`** as the sole canonical source of truth. Input endpoints (Requests) accept aliases for backward compatibility, but API responses strictly output the unified representation.

### 2.1 `UserResource`

Represents the system user.

```json
{
  "id": "01a1284d-6834-703e-b004-60dbb29fdbb8",
  "name": "Juan Perez",
  "email": "juan@example.com",
  "plan": "free",
  "role": "client",
  "payment_method": null,
  "payment_bank": null,
  "payment_receipt": null,
  "created_at": "2026-10-10T19:00:00.000000Z",
  "updated_at": "2026-10-10T19:00:00.000000Z"
}

```

| Field | Type | Possible Values / Description |
| --- | --- | --- |
| `id` | `uuid` | Unique user identifier. |
| `name` | `string` | Full name of the user. |
| `email` | `string` | Unique email address of the user. |
| `plan` | `string` | `'free'` | `'premium'` |
| `role` | `string` | `'client'` | `'admin'` |
| `payment_method` | `string|null` | Informational snapshot of the last registered method (`'transfer'` | `'cash'` | `null`). The source of truth for the payment lifecycle is `PremiumRequestResource`. |
| `payment_bank` | `string|null` | Bank of the last registered receipt (informational snapshot). |
| `payment_receipt` | `string|null` | Code of the last registered receipt (informational snapshot). |
| `created_at` | `string` | Registration date in ISO 8601 format. |
| `updated_at` | `string` | Last update date in ISO 8601 format. |
| `subscription_expires_at` | — | Reserved field for future billing. It is not exposed in this API version. |

> **Note:** The `subscription_expires_at` field exists in the model and database but is not serialized in `UserResource` for the MVP. It will be exposed once the automated billing cycle is implemented.

---

### 2.2 `AccountResource`

Represents a financial account with a dynamic balance calculated in real-time.

```json
{
  "id": "01a1284d-6837-72a8-ad05-81403649f104",
  "user_id": "01a1284d-6834-703e-b004-60dbb29fdbb8",
  "name": "Banco Pichincha",
  "type": "bank",
  "amount": 750.50,
  "initial_balance": 500.00,
  "description": "Main savings account",
  "currency": "USD",
  "is_default": false,
  "deactivation_reason": null,
  "deleted_at": null,
  "created_at": "2026-10-10T19:00:00.000000Z",
  "updated_at": "2026-10-10T19:00:00.000000Z"
}

```

| Field | Type | Possible Values / Description |
| --- | --- | --- |
| `id` | `uuid` | Unique account identifier. |
| `user_id` | `uuid` | ID of the owning user. |
| `name` | `string` | Account name (unique per user among active accounts). |
| `type` | `string` | `'cash'` | `'bank'`. Strict PRD Rule 1. |
| `amount` | `float` | Current calculated balance: `initial_balance + incomes - expenses` (active transactions). |
| `initial_balance` | `float` | Initial balance recorded upon account creation. |
| `description` | `string|null` | Optional account note. |
| `currency` | `string` | Account currency (defaults to `'USD'`). |
| `is_default` | `boolean` | `true` only for the immutable "Cash" account. |
| `deactivation_reason` | `string|null` | `'user_action'` | `'plan_downgrade'` | `null` (Rule 17). |
| `deleted_at` | `string|null` | ISO 8601 timestamp if deactivated (Soft Delete); `null` if active. |
| `created_at` | `string` | Creation ISO 8601 timestamp. |
| `updated_at` | `string` | Last update ISO 8601 timestamp. |

---

### 2.3 `TransactionResource`

Represents a financial transaction. The resource **exclusively outputs `snake_case**`.

```json
{
  "id": "01a1284d-80dc-7377-87e5-f684cfd4fe53",
  "account_id": "01a1284d-6837-72a8-ad05-81403649f104",
  "user_id": "01a1284d-6834-703e-b004-60dbb29fdbb8",
  "type": "expense",
  "amount": 45.50,
  "reason": "Business lunch",
  "description": "La Esquina Restaurant",
  "payment_method": "cash",
  "date": "2026-10-10",
  "category": null,
  "tags": [],
  "transfer_id": null,
  "deletion_reason": null,
  "deleted_at": null,
  "created_at": "2026-10-10T13:30:00.000000Z",
  "updated_at": "2026-10-10T13:30:00.000000Z",
  "account": null
}

```

| Field | Type | Possible Values / Description |
| --- | --- | --- |
| `id` | `uuid` | Unique transaction identifier. |
| `account_id` | `uuid` | ID of the associated account. |
| `user_id` | `uuid` | ID of the owning user. |
| `type` | `string` | `'income'` | `'expense'`. Strict PRD. |
| `amount` | `float` | Transaction amount (`> 0.00`). |
| `reason` | `string` | Main concept or reason for the transaction. |
| `description` | `string|null` | Extended details and relocation audit notes (Rule 16). |
| `payment_method` | `string` | `'cash'` | `'transfer'`. Consistent with `account.type` (Rule 6). |
| `date` | `string` | Transaction date in `YYYY-MM-DD` format. No time component. |
| `category` | `string|null` | Mandatory for `type = 'expense'`: `'comida'`, `'transporte'`, `'entretenimiento'`, `'estudio'`, `'trabajo'`, `'otros'`. Null for `type = 'income'`. |
| `tags` | `array<string>` | Descriptive tags for the transaction. |
| `transfer_id` | `uuid|null` | Link identifier for transfers between own accounts. |
| `deletion_reason` | `string|null` | Deactivation reason (`cascade_account_deactivation` or relocation note). |
| `deleted_at` | `string|null` | ISO 8601 timestamp if deactivated; `null` if active. |
| `created_at` | `string` | Creation ISO 8601 timestamp. |
| `updated_at` | `string` | Update ISO 8601 timestamp. |
| `account` | `AccountResource|null` | `AccountResource` object loaded in eager-loaded queries (`GET /api/transactions`). In other flows, it is `null`. |

---

### 2.4 `PremiumRequestResource`

Single source of truth (SSOT) for Premium Plan upgrade requests and their approval or rejection audit.

```json
{
  "id": "01a1284d-9999-7377-87e5-f684cfd4fe53",
  "user_id": "01a1284d-6834-703e-b004-60dbb29fdbb8",
  "status": "pending_verification",
  "payment_method": "transfer",
  "origin_bank": "Banco de Guayaquil",
  "transfer_receipt": "TRX-10928374",
  "payment_notes": null,
  "rejection_reason": null,
  "payment_verified_by": null,
  "verified_at": null,
  "created_at": "2026-10-10T19:00:00.000000Z",
  "updated_at": "2026-10-10T19:00:00.000000Z",
  "user": null
}

```

| Field | Type | Possible Values / Description |
| --- | --- | --- |
| `id` | `uuid` | Unique request identifier. |
| `user_id` | `uuid` | ID of the requesting user. |
| `status` | `string` | `'pending_verification'` | `'approved'` | `'rejected'`. |
| `payment_method` | `string` | `'transfer'` (Bank transfer) | `'cash'` (Manual cash by admin). |
| `origin_bank` | `string|null` | Originating bank specified by the user. |
| `transfer_receipt` | `string|null` | Bank transfer receipt or reference number. |
| `payment_notes` | `string|null` | Administrative notes (used for in-person cash payments). |
| `rejection_reason` | `string|null` | Rejection reason detailed by the administrator. |
| `payment_verified_by` | `uuid|null` | ID of the administrator who audited and resolved the request. |
| `verified_at` | `string|null` | ISO 8601 timestamp of when it was approved or rejected. |
| `created_at` | `string` | Creation ISO 8601 timestamp. |
| `updated_at` | `string` | Update ISO 8601 timestamp. |
| `user` | `UserResource|null` | `UserResource` object automatically loaded in the administrative panel (`GET /api/admin/premium-requests`). |

---

## 3. Public Authentication and Token Lifecycle Endpoints

### 3.1 Health Check (Ping)

* **Method & Route:** `GET /api/ping`
* **Authentication:** Not required
* **Rate Limit:** No specific application restriction
* **Successful Response (200 OK):**
```json
{
  "status": "Rastro API V1 Operational"
}

```



---

### 3.2 Login and Concurrency Control

* **Method & Route:** `POST /api/auth/login`
* **Authentication:** Not required
* **Rate Limit:** `10 requests / minute`
* **Request Body:**
```json
{
  "email": "user@example.com",
  "password": "mySecurePassword",
  "device_name": "Pixel 8 Pro",
  "force": false
}

```


* `email`: `string`, required, valid email format.
* `password`: `string`, required.
* `device_name`: `string`, optional (client device identifier).
* `force`: `boolean`, optional (defaults to `false`).


* **Session Concurrency Rules (PRD Rules 12 and 13):**
* **Premium Plan:** Allows unlimited concurrent devices.
* **Free Plan:** Strictly **1 active device only**.
* If an active session exists and `force = false`, it responds with **`409 Conflict`**:
```json
{
  "success": false,
  "code": "SESSION_LIMIT_REACHED",
  "message": "You already have an active session on another device.",
  "data": null
}

```


* If the user decides to take control on the client (`force = true`), the previous session is remotely revoked and tokens are issued for the new device.




* **Successful Response (200 OK):**
```json
{
  "success": true,
  "message": "Successful operation.",
  "data": {
    "access_token": "1|sanctum_access_token_string...",
    "refresh_token": "a1b2c3d4e5f6g7h8...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": { ... }
  },
  "meta": null
}

```


> `access_token` is the canonical OAuth2/Bearer standard. The backend sends the `'token'` field in parallel with the same value for clients that have not yet migrated to the RFC 6750 standard.



---

### 3.3 Transparent Token Renewal (Refresh Token)

* **Method & Route:** `POST /api/auth/refresh`
* **Authentication:** Not required (uses the cryptographic `refresh_token`)
* **Rate Limit:** `30 requests / minute`
* **Token Lifespan (PRD Rule 14):**
* `access_token`: **60 minutes** (3600 seconds).
* `refresh_token`: **30 days** with automatic rotation on each request.


* **Request Body:**
```json
{
  "refresh_token": "a1b2c3d4e5f6g7h8..."
}

```


* **Successful Response (200 OK):**
```json
{
  "success": true,
  "message": "Successful operation.",
  "data": {
    "access_token": "2|new_access_token_string...",
    "refresh_token": "9z8y7x6w5v4u3t2s...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": { ... }
  },
  "meta": null
}

```



---

### 3.4 Registration and Initial Synchronization (Register & Sync)

* **Method & Route:** `POST /api/auth/register-sync`
* **Authentication:** Not required
* **Rate Limit:** `10 requests / minute`
* **Description:** Registers a new user and atomically synchronizes, in a single database transaction (`DB::transaction`), the data locally generated by the user during Guest Mode.
* **Initial Balance Semantics:** Upon account creation, the canonical field for the initial balance is `initial_balance`. The endpoint also accepts `amount` as a fallback for backward compatibility with data generated by older client versions in offline mode — in that case, `initial_balance` takes precedence. New clients must only send `initial_balance`.
* **Request Body:**
```json
{
  "name": "Juan Perez",
  "email": "juan@example.com",
  "password": "password123",
  "device_name": "Pixel 8 Pro",
  "localData": {
    "accounts": [
      {
        "id": "01a1284d-6837-72a8-ad05-81403649f104",
        "name": "Cash",
        "type": "cash",
        "initial_balance": 150.00,
        "description": "Main account",
        "currency": "USD",
        "is_default": true
      }
    ],
    "transactions": [
      {
        "id": "01a1284d-80dc-7377-87e5-f684cfd4fe53",
        "account_id": "01a1284d-6837-72a8-ad05-81403649f104",
        "type": "income",
        "amount": 150.00,
        "reason": "Initial balance",
        "description": null,
        "payment_method": "cash",
        "date": "2026-10-10",
        "category": null,
        "tags": []
      }
    ]
  }
}

```


* **Successful Response (201 Created):**
```json
{
  "success": true,
  "message": "Successful operation.",
  "data": {
    "access_token": "1|sanctum_access_token...",
    "refresh_token": "a1b2c3d4...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": { ... },
    "syncedData": {
      "accounts": [ ... ],
      "transactions": [ ... ]
    }
  },
  "meta": null
}

```



---

## 4. Private Endpoints (Require Bearer Token Sanctum + Active Session Middleware)

> 🔒 **Revoked Session Detection (PRD Rule 10.4):** All authenticated routes are intercepted by `CheckSessionRevocationMiddleware`. If the device's session was remotely invalidated (due to concurrent login on another device or an administrative plan change), the API immediately responds with **`401 Unauthorized`**:
> ```json
> {
>   "success": false,
>   "code": "SESSION_REVOKED_REMOTE",
>   "message": "Session closed from another device.",
>   "data": null
> }
> 
> ```
> 
> 
> ⚠️ **Rule for Mobile Clients:** The mobile client must **NEVER** delete its local database (Dexie/IndexedDB) upon receiving this error. It must suspend synchronization, preserve pending data in the Outbox, and prompt the user to log in again to merge their changes.

---

### 4.1 Profile and Account Management

#### `GET /api/auth/me`

* **Description:** Retrieves the profile and subscription data of the authenticated user.
* **Rate Limit:** Infrastructure standard (60 req/min).
* **Response (200 OK):** `UserResource`.

#### `PATCH /api/auth/me`

* **Description:** Updates the profile name or alias.
* **Rate Limit:** Infrastructure standard (60 req/min).
* **Request Body:**
```json
{
  "name": "Juan Carlos Perez"
}

```


* **Response (200 OK):** `UserResource`.

#### `POST /api/auth/logout`

* **Description:** Closes the active session on the current device, invalidating the token and the registered session (PRD Rule 15).
* **Rate Limit:** Infrastructure standard (60 req/min).
* **Response (200 OK):**
```json
{
  "success": true,
  "message": "Successful operation.",
  "data": null,
  "meta": null
}

```



#### `DELETE /api/auth/me`

* **Description:** **Total User Purge (Global Hard Delete - PRD Section 6.2).** Physically removes the user, all active sessions, tokens, financial accounts, and all historical transactions from the database.
* **Rate Limit:** Infrastructure standard (60 req/min).
* **Response (200 OK):**
```json
{
  "success": true,
  "message": "Successful operation.",
  "data": null,
  "meta": null
}

```



#### `POST /api/auth/upgrade-request`

* **Description:** Submits a Premium Plan upgrade request attaching the bank transfer receipt details.
* **Rate Limit:** Infrastructure standard (60 req/min).
* **Request Body:**
```json
{
  "origin_bank": "Banco Pichincha",
  "transfer_receipt": "REC-987654321"
}

```


* **Response (200 OK):**
* Message: `"Your request has been received. We will review it within a maximum of 24 hours."` (`PREMIUM_REQUEST_SUBMITTED`).
* Data: `UserResource` (The plan remains `free` until administrator approval).



---

### 4.2 Offline Bulk Synchronization (Outbox Pattern)

#### `POST /api/sync/push`

* **Description:** Central and exclusive endpoint for Account and Transaction mutations. It batch-processes the operations queued in the device's local Outbox within an atomic database transaction (`DB::transaction`).
* **Rate Limit:** `60 requests / minute`.
* **Request Format:**
```json
{
  "operations": [
    {
      "opId": "01a1284d-op1-7377-87e5-f684cfd4fe53",
      "entity": "accounts",
      "op": "create",
      "payload": { ... }
    },
    {
      "opId": "01a1284d-op2-7377-87e5-f684cfd4fe53",
      "entity": "transactions",
      "op": "relocate",
      "payload": { ... }
    }
  ]
}

```



#### Canonical Operations Catalog:

| Entity | Canonical Operation (`op`) | Required Payload | Business Rules and Validations |
| --- | --- | --- | --- |
| `accounts` | `create` | `{"id": "uuid", "name": "string", "type": "cash|bank", "initial_balance": number, "description": "string|null", "currency": "USD"}` | `name` must be unique per user among active accounts (`422 ACCOUNT_NAME_DUPLICATE`). `initial_balance >= 0` (`amount` is also accepted). Free Plan blocks this if there are already $\ge 3$ active accounts (`403 LIMIT_ACCOUNTS`). |
| `accounts` | `update` | `{"id": "uuid", "name": "string|null", "type": "cash|bank|null", "amount": number|null", "description": "string|null"}` | If `is_default = true`, modifying `type` or `name` is blocked (`403 ACCOUNT_DEFAULT_PROTECTED`), but updating `initial_balance` (or `amount`) and `description` is allowed to record or correct opening capital. **Rules 4 and 5:** Changing the type executes a cascading mutation over all transactions (see section 4.3). The `amount` field in this payload represents the editable initial balance of the account. An internal model mutator persists it in the physical `initial_balance` column. Do not confuse it with the `amount` field in `AccountResource`, which is the dynamic calculated balance. |
| `accounts` | `delete` | `{"id": "uuid", "deactivation_reason": "user_action|plan_downgrade|null"}` | Blocked if `is_default = true` (`403 ACCOUNT_DEFAULT_PROTECTED`). Soft Delete of the account; **cascades deactivation to all its transactions** with `deletion_reason = 'cascade_account_deactivation'` (Rule 18). Immediately frees up an account slot. |
| `accounts` | `restore` | `{"id": "uuid"}` | Reactivates the account (`deleted_at = null`, `deactivation_reason = null`). Validates available slot in Free Plan (`403 LIMIT_ACCOUNTS`). **No cascade:** Does not reactivate the associated transactions (Rule 6.3). |
| `accounts` | `hard-delete` | `{"id": "uuid"}` | **Irreversible permanent deletion of the account (Rules 6.2 and 20).** Authorized exception in the downgrade resolution modal. Physically deletes the account and all its records. Blocked for the default account (`403 ACCOUNT_DEFAULT_PROTECTED`). |
| `transactions` | `create` | `{"id": "uuid", "account_id": "uuid", "type": "income|expense", "amount": number, "reason": "string", "payment_method": "cash|transfer", "description": "string|null", "date": "YYYY-MM-DD|ISO8601|null", "category": string|null, "tags": [], "transfer_id": "uuid|null"}` | Unlimited across all plans. `amount > 0`. **Category requirement:** Mandatory for `type = 'expense'` from backend catalog (`'comida'`, `'transporte'`, `'entretenimiento'`, `'estudio'`, `'trabajo'`, `'otros'`). Null for `type = 'income'`. **Rule 6:** Account must be active (`403 ACCOUNT_INACTIVE`) and its type must match the method (`403 ACCOUNT_TYPE_MISMATCH`). Date without time automatically receives `now()` from the server (PRD 4.4). |
| `transactions` | `update` | `{"id": "uuid", "account_id": "uuid|null", "type": "income|expense|null", "amount": number|null", "reason": "string|null", "payment_method": "cash|transfer|null", "category": string|null, ...}` | **Rule 6:** Requires an active destination account (`403 ACCOUNT_INACTIVE`) and payment method consistency (`403 ACCOUNT_TYPE_MISMATCH`). If updated transaction is `expense`, valid `category` is mandatory. |
| `transactions` | `delete` | `{"id": "uuid", "deletion_reason": "string|null"}` | Deactivates the transaction (Soft Delete) and logs an optional reason. |
| `transactions` | `restore` | `{"id": "uuid"}` | Reactivates the transaction (`deleted_at = null`). **Rule 6:** Requires the associated account to be active (`403 ACCOUNT_INACTIVE`). If it was the source of a prior relocation, reactivation is allowed while notifying the user (Rule 9). |
| `transactions` | `hard-delete` | `{"id": "uuid"}` | **Irreversible permanent deletion of a transaction.** Only permitted if the transaction was already previously deactivated (`422 HARD_DELETE_REQUIRES_SOFT_DELETE`). |
| `transactions` | `purge-account` | `{"account_id": "uuid"}` | **Account transactions purge (PRD 6.2):** Physically deletes all transactions of that specific account from the database, leaving the account intact. |
| `transactions` | `purge-all` | `{}` | **Global user transactions purge:** Physically deletes all transactions across all user accounts, preserving all configured accounts intact. Requires no fields in the payload. The affected user is determined exclusively from the authenticated token (Sanctum). The payload must be sent as an empty object `{}`. |
| `transactions` | `relocate` | `{"id": "uuid", "new_id": "uuid", "destination_account_id": "uuid", "reason": "string"}` | **Transaction relocation between accounts (Rules 7, 10, and 16):** Correction with audit. See rules matrix below. |

---

### 4.3 Synchronization Engine Special Rules

#### A. Cascading Mutation on Account Type Change (Rule 5)

When an account with transactions updates its `type` attribute:

* **`cash → bank`:** All transactions within the account (both active and deactivated) automatically update their `payment_method` to `'transfer'`.
* **`bank → cash`:** All transactions within the account (both active and deactivated) automatically update their `payment_method` to `'cash'`.

#### B. Canonical Relocations Matrix (`transactions/relocate`, Rules 7, 10, and 16)

Relocating between accounts is a historical correction operation that preserves traceability:

```text
                          ┌────────────────────────────────┐
                          │     Source == Destination?     │ ── Yes ──> 422 RELOCATE_SAME_ACCOUNT
                          └───────────────┬────────────────┘
                                          │ No
                          ┌───────────────▼────────────────┐
                          │  Destination Account Active?   │ ── No ──> 403 ACCOUNT_INACTIVE
                          └───────────────┬────────────────┘
                                          │ Yes
                          ┌───────────────▼────────────────┐
                          │       Reason specified?        │ ── No ──> 422 RELOCATE_REASON_REQUIRED
                          └───────────────┬────────────────┘
                                          │ Yes
          ┌───────────────────────────────┴───────────────────────────────┐
          │                                                               │
┌─────────▼────────────────┐                                    ┌─────────▼────────────────┐
│    Active Transaction    │                                    │ Deactivated Transaction  │
│  (deleted_at == null)    │                                    │  (deleted_at != null)    │
└─────────┬────────────────┘                                    └─────────┬────────────────┘
          │                                                               │
          │                                           ┌───────────────────┴───────────────────┐
          │                                           │                                       │
          │                                 ┌─────────▼────────────────┐            ┌─────────▼────────────────┐
          │                                 │  Parent Account Active   │            │ Parent Account Inactive  │
          │                                 └─────────┬────────────────┘            └─────────┬────────────────┘
          │                                           │                                       │
          │                                           ▼                                       ▼
          │                            422 TRANSACTION_ALREADY_INACTIVE         RULE 16 PERMITTED:
          │                                                                     - Original keeps cascade.
          │                                                                     - Note in description.
          ▼                                                                     - New one spawns active in dest.
RULE 7 PERMITTED:
- Original deactivated with deletion_reason: "Relocated to [Destination]: [reason]".
- New record spawns active in destination account with new ID and derived payment_method.

```

**Decision Table for `relocate`:**

| Source Transaction Status | Source Account Status | Allowed? | Backend Behavior |
| --- | --- | --- | --- |
| Active (`deleted_at = null`) | Active | **Yes (Rule 7)** | Deactivates the original (`deleted_at = now()`) with `deletion_reason = "Relocated to [Destination]: [reason]"`. Creates the new active record in the destination account. |
| Deactivated (`deleted_at != null`) | Active | **No** | Responds with **`422 TRANSACTION_ALREADY_INACTIVE`**. Relocating records that the user intentionally deactivated individually is not allowed. |
| Deactivated by cascade (`deleted_at != null`) | Inactive / Trashed | **Yes (Rule 16)** | Keeps the original deactivated with `deletion_reason = 'cascade_account_deactivation'`, appending to its `description` the note: `"[Relocated to [Destination] on dd mmm yyyy: reason]"`. Creates the new active record in the destination account, adding to its balance. |

---

### 4.4 Bulk Synchronization Response Format

The `/api/sync/push` endpoint processes all operations submitted in the batch. If one operation fails, the others are evaluated individually, and the result is reported per `opId`:

```json
{
  "success": true,
  "message": "Batch processed.",
  "data": {
    "results": [
      {
        "opId": "01a1284d-op1-7377-87e5-f684cfd4fe53",
        "status": "success"
      },
      {
        "opId": "01a1284d-op2-7377-87e5-f684cfd4fe53",
        "status": "error",
        "code": 403,
        "message": "Account limit reached for your current plan."
      }
    ]
  },
  "meta": null
}

```

---

### 4.5 Data Query and Download Endpoints (Pull)

#### `GET /api/accounts`

* **Description:** Retrieves the user's accounts (by default includes active and deactivated accounts via `withTrashed()` for local state synchronization).
* **Rate Limit:** Infrastructure standard (60 req/min).
* **Query Parameters (Optional):**
* `type`: `'cash'` | `'bank'` (filters by account type, populating the selector from Rule 1).
* `active_only`: `'true'` | `'false'` (if `true`, returns only accounts with `deleted_at = null`).


* **Response (200 OK):**
```json
{
  "success": true,
  "message": "Accounts successfully retrieved.",
  "data": [ ... ],
  "meta": null
}

```



---

#### `GET /api/transactions`

* **Description:** Returns paginated user transactions ordered chronologically in descending order.
* **Rate Limit:** Infrastructure standard (60 req/min).
* **Query Parameters (Optional):**
* `type`: `'income'` | `'expense'`.
* `account_id`: `uuid` (filters transactions belonging to a specific account).
* `active_only`: `'true'` | `'false'` (if `true`, excludes soft-deleted records).
* `page`: `integer` (page number, defaults to `1`).
* `per_page`: `integer` (items per page, between `1` and `100`, defaults to `25`).


* **History Cutoff by Plan (PRD 5.3):**
* **Free Plan:** Exclusively returns transactions from the **last 6 months** (`date >= now()->subMonths(6)`).
* **Premium Plan:** Returns the **complete history with no time limit**.


* **Response (200 OK):**
```json
{
  "success": true,
  "message": "Transactions successfully retrieved.",
  "data": [ ... ],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 85
  }
}

```



---

#### `GET /api/transactions/export`

* **Description:** Exports the user's entire transaction history in **CSV** format with UTF-8 BOM encoding for native opening in Microsoft Excel and spreadsheets.
* **Rate Limit:** Infrastructure standard (60 req/min).
* **Authorization (Gate):** Exclusive to users with a **Premium** plan. If a Free user attempts to access it, it responds with:
```json
{
  "success": false,
  "message": "This feature requires an active Premium subscription.",
  "data": null,
  "meta": null
}

```


* **Successful Response (200 OK):** `StreamedResponse` file download with headers `Content-Type: text/csv; charset=UTF-8` and `Content-Disposition: attachment; filename="transacciones-rastro-...csv"`.

---

#### `GET /api/categories`

* **Description:** Returns the active catalog of expense categories defined by the backend.
* **Authentication:** `auth:sanctum` + active session.
* **Successful Response (200 OK):**
```json
{
  "success": true,
  "message": "Categorías obtenidas exitosamente.",
  "data": [
    { "id": "comida", "name": "Comida", "icon": "utensils" },
    { "id": "transporte", "name": "Transporte", "icon": "car" },
    { "id": "entretenimiento", "name": "Entretenimiento", "icon": "film" },
    { "id": "estudio", "name": "Estudio", "icon": "book-open" },
    { "id": "trabajo", "name": "Trabajo", "icon": "briefcase" },
    { "id": "otros", "name": "Otros", "icon": "more-horizontal" }
  ],
  "meta": null
}
```

---

## 5. Administrative Panel Endpoints (Require Admin Role)

All endpoints under `/api/admin/*` are protected by `AdminMiddleware` and `auth:sanctum`.

> ⚡ **Session Invalidation Rule (PRD Rule 15):** Whenever an administrator changes, approves, or toggles a user's plan, the backend **invalidates all active sessions and tokens for that user**. This forces the client to refresh its session or log in again to immediately acquire its new entitlements.

### 5.1 List Clients

* **Method & Route:** `GET /api/admin/users`
* **Rate Limit:** Infrastructure standard (60 req/min).
* **Query Parameters:**
* `page`: `integer` (defaults to `1`).
* `per_page`: `integer` (between `1` and `100`, defaults to `25`).


* **Response (200 OK):**
```json
{
  "success": true,
  "message": "Client list successfully retrieved.",
  "data": [ ... ],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 12
  }
}

```



---

### 5.2 Pending Premium Requests Queue

* **Method & Route:** `GET /api/admin/premium-requests`
* **Rate Limit:** Infrastructure standard (60 req/min).
* **Query Parameters:**
* `page`: `integer` (defaults to `1`).
* `per_page`: `integer` (between `1` and `100`, defaults to `25`).


* **Description:** Returns requests with a `'pending_verification'` status, with the `user` relation eager-loaded.
* **Response (200 OK):**
```json
{
  "success": true,
  "message": "Premium requests successfully retrieved.",
  "data": [ ... ],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 3
  }
}

```



---

### 5.3 Approve Premium Request

* **Method & Route:** `POST /api/admin/premium-requests/{id}/approve`
* **Rate Limit:** Infrastructure standard (60 req/min).
* **Executed Actions:**
1. Updates the user to `plan = 'premium'`.
2. Invalidates all user sessions and tokens (Rule 15).
3. Marks the request as `status = 'approved'`, records `payment_verified_by` and `verified_at = now()`.
4. Sends the `PremiumActivatedMail` transactional email with the date in standard `dd mmm yyyy` format (Rule 11).


* **Response (200 OK):** `PremiumRequestResource`.

---

### 5.4 Reject Premium Request

* **Method & Route:** `POST /api/admin/premium-requests/{id}/reject`
* **Rate Limit:** Infrastructure standard (60 req/min).
* **Request Body:**
```json
{
  "rejection_reason": "Illegible receipt or the amount does not match the annual fee."
}

```


* **Executed Actions:**
1. Keeps the user's plan as `free`.
2. Invalidates the user's sessions to force a UI update (Rule 15).
3. Marks the request as `status = 'rejected'`, saves `rejection_reason`, `payment_verified_by`, and `verified_at = now()`.
4. Sends the `PremiumRejectedMail` transactional email.


* **Response (200 OK):** `PremiumRequestResource`.

---

### 5.5 Register In-Person Cash Payment (Counter / Office)

* **Method & Route:** `POST /api/admin/users/{user_id}/manual-upgrade`
* **Rate Limit:** Infrastructure standard (60 req/min).
* **Purpose:** Formal billing flow at a physical counter/office. Creates an audited order in `PremiumRequest` with the `cash` method and collection notes.
* **Request Body:**
```json
{
  "payment_notes": "Annual payment received in cash at the central office on 10 Oct 2026."
}

```


* **Executed Actions:**
1. Updates the user to `plan = 'premium'`.
2. Invalidates the active user sessions (Rule 15).
3. Creates a `PremiumRequest` record with `status = 'approved'`, method `'cash'`, payment notes, and admin audit (`payment_verified_by`).
4. Sends the `PremiumActivatedMail` transactional email.


* **Response (200 OK):** `UserResource`.

---

### 5.6 Direct Administrative Plan Toggle

* **Method & Route:** `PATCH /api/admin/users/{user_id}/plan`
* **Rate Limit:** Infrastructure standard (60 req/min).
* **Purpose:** Direct administrative switch. Primarily used to force an immediate downgrade to `free` or for direct membership assignment without cash flow.
* **Request Body for Premium Upgrade:**
```json
{
  "plan": "premium",
  "payment_method": "transfer",
  "payment_bank": "Banco Pichincha",
  "payment_receipt": "REC-123456"
}

```


* **Request Body for Free Downgrade:**
```json
{
  "plan": "free"
}

```


* **Executed Actions:**
1. Updates the user's plan.
2. If the plan changes, it invalidates all active user sessions (Rule 15).
3. If upgraded to Premium: sends `PremiumActivatedMail` email.
4. If downgraded to Free: sends `PlanDowngradedMail` email notifying plan restrictions and grace period (Rules 11 and 20).


* **Response (200 OK):** `UserResource`.

---

## 6. Central Message and HTTP Code Dictionary (`ApiMessage`)

All messages and HTTP codes issued by the backend immutably originate from `App\Constants\ApiMessage` and `App\Constants\HttpCode`:

| Constant | Text Message | HTTP Code | Use Case |
| --- | --- | --- | --- |
| `SUCCESS` | `"Successful operation."` | `200 OK` | Generic successful operations. |
| `CREATED` | `"Record successfully saved."` | `201 Created` | Resource creation via direct endpoints. |
| `BATCH_PROCESSED` | `"Batch processed."` | `200 OK` | Completion of `/api/sync/push` processing. |
| `UNAUTHORIZED` | `"Access denied. Invalid or missing token."` | `401 Unauthorized` | General Bearer authentication failure. |
| `SESSION_REVOKED_REMOTE` | `"Session closed from another device."` | `401 Unauthorized` | Session revoked due to concurrent login or admin action. |
| `INVALID_REFRESH_TOKEN` | `"The refresh token is invalid or has expired."` | `401 Unauthorized` | Corrupted or expired refresh token (30 days). |
| `FORBIDDEN` | `"You do not have permission to perform this action."` | `403 Forbidden` | Denial by middleware or unauthorized role. |
| `LIMIT_ACCOUNTS` | `"Account limit reached for your current plan."` | `403 Forbidden` | Free Plan attempting to have more than 3 active accounts. |
| `ACCOUNT_DEFAULT_PROTECTED` | `"The default main account cannot be modified or deleted."` | `403 Forbidden` | Attempt to modify or delete the "Cash" account. |
| `ACCOUNT_INACTIVE` | `"The selected account is deactivated."` | `403 Forbidden` | Attempt to operate on or towards a deactivated account (PRD Rule 6). |
| `ACCOUNT_TYPE_MISMATCH` | `"The payment method does not match the account type."` | `403 Forbidden` | Payment method incompatible with account type (PRD Rule 6). |
| `PREMIUM_REQUIRED` | `"This feature requires an active Premium subscription."` | `403 Forbidden` | Attempt to export CSV or use a premium feature from Free plan. |
| `NOT_FOUND` | `"The requested resource does not exist or was deleted."` | `404 Not Found` | Identifier not found in the database. |
| `SESSION_LIMIT_REACHED` | `"You already have an active session on another device."` | `409 Conflict` | Concurrent login on Free Plan with `force: false`. |
| `VALIDATION_ERROR` | `"The submitted data is invalid."` | `422 Unprocessable Entity` | Failure in FormRequest validation rules. |
| `ACCOUNT_NAME_DUPLICATE` | `"An account with this name already exists."` | `422 Unprocessable Entity` | Duplicate name in user's active accounts. |
| `HARD_DELETE_REQUIRES_SOFT_DELETE` | `"A transaction must be deactivated before being permanently deleted."` | `422 Unprocessable Entity` | Direct hard-delete attempt on an active transaction. |
| `RELOCATE_REASON_REQUIRED` | `"The relocation reason is mandatory."` | `422 Unprocessable Entity` | Relocation submitted without an explanatory reason. |
| `RELOCATE_SAME_ACCOUNT` | `"The destination account must be different from the source account."` | `422 Unprocessable Entity` | Relocation with identical source and destination accounts. |
| `TRANSACTION_ALREADY_INACTIVE` | `"Cannot relocate a deactivated transaction."` | `422 Unprocessable Entity` | Relocation of a deactivated transaction in an active account. |
| `MISSING_ID` | `"Missing record identifier."` | `400 Bad Request` | Synchronization payload is missing the `id` key. |
| `UNSUPPORTED_ENTITY` | `"The submitted entity is not supported for synchronization."` | `422 Unprocessable Entity` | Entity other than `accounts` or `transactions`. |
| `UNSUPPORTED_OPERATION` | `"The submitted operation is not supported for this entity."` | `422 Unprocessable Entity` | Operation not listed in the sync catalog. |
| `SERVER_ERROR` | `"An unexpected error occurred on the server."` | `500 Internal Error` | Unhandled critical exception in the backend. |

---

## 7. Transactional Email Notifications (PRD Rule 11)

All dates displayed in transactional emails and system notifications strictly implement the canonical format **`dd mmm yyyy`** (example: `10 Oct 2026`) formatted by `App\Support\DateFormatter`:

| Event | Mailable Class | Recipient | Purpose |
| --- | --- | --- | --- |
| **Receipt Uploaded** | `PaymentProofSubmittedMail` | Administrator | Notifies that a user uploaded a receipt for review. |
| **Premium Activation** | `PremiumActivatedMail` | User | Notifies the activation of the Premium account and start date. |
| **Request Rejection** | `PremiumRejectedMail` | User | Notifies the reason for rejection of the bank receipt. |
| **Downgrade to Free** | `PlanDowngradedMail` | User | Informs the change to the Free Plan and account limit policies. |

```