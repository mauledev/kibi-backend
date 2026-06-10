# Two-Factor Authentication (2FA)

TOTP-based two-factor auth for Softlinkia **staff**. Built as a reusable **base**
(enroll / confirm / verify / disable) plus a thin **wiring** into the staff login,
so the same primitives can gate any sensitive action — not just login.

---

## Library

- **`pragmarx/google2fa`** — the pure TOTP engine (secret generation, provisioning
  URI, code verification).
- **`pragmarx/google2fa-laravel`** is declared as a dependency (per the Jira ticket)
  but its session middleware / facade are intentionally **not** used: staff auth is a
  token-based API, so only the stateless engine is consumed, behind
  `Google2faService` so the rest of the app never touches the library directly.

Defaults are standard and **must not be changed** (already-enrolled authenticator
entries depend on them): `SHA1`, 6 digits, 30s period.

---

## Two layers

### 1. Base — reusable, login-agnostic

`app/Modules/Auth/Application/UseCases/TwoFactor/`

| Use case | Signature | Purpose |
|---|---|---|
| `EnrollTwoFactorUseCase` | `execute(userId, accountLabel, issuer): TwoFactorEnrollment` | generate secret + otpauth URI (for the QR); **idempotent while pending** |
| `ConfirmTwoFactorUseCase` | `execute(userId, code): string[]` | validate the code against the pending secret, activate 2FA, return recovery codes |
| `VerifyTwoFactorUseCase` | `execute(userId, code): bool` | validate a TOTP **or** recovery code (recovery codes are single-use) |
| `DisableTwoFactorUseCase` | `execute(userId): void` | clear the 2FA fields |

Backed by domain contracts:

- `TwoFactorServiceInterface` → `Google2faService` (engine).
- `TwoFactorRepositoryInterface` → `EloquentTwoFactorRepository` (persistence on `users`).

This layer knows nothing about login. It can be injected anywhere a second factor
is needed (see **Reuse beyond login**).

### 2. Login wiring — consumes the base

`app/Modules/Auth/Application/UseCases/{StaffLogin,TwoFactorLogin}/`

- `StaffLoginUseCase` — returns `LoginOutput | TwoFactorChallenge` (a challenge when the role enforces 2FA).
- `StartTwoFactorSetupUseCase` / `ConfirmTwoFactorLoginUseCase` / `VerifyTwoFactorLoginUseCase` — resolve the login challenge and drive enroll/confirm/verify.
- `IssueStaffSessionUseCase` — single place that mints the staff `LoginOutput` (token + roles + permissions).
- `CacheTwoFactorChallengeRepository` (`TwoFactorChallengeRepositoryInterface`) — the short-lived challenge-token store.

---

## Storage (`users` table)

| Column | Type | Notes |
|---|---|---|
| `two_factor_secret` | `text` | TOTP secret — **encrypted** at rest (`encrypted` cast) |
| `two_factor_confirmed_at` | `timestamptz` | `NULL` until the user confirms enrollment; non-null ⇒ 2FA active |
| `two_factor_recovery_codes` | `text` | **encrypted** JSON array (`encrypted:array`); each code is **hashed** and burned on use |

`User::hasTwoFactorEnabled()` returns `two_factor_confirmed_at !== null`. The secret
and recovery codes are in `$hidden` and never serialized.

---

## Who needs 2FA

Whether a role mandates 2FA is **stored per-role in the database**:
`roles.requires_2fa` (boolean) — the **single source of truth**. The seeder sets it
`true` for `leader` and `support`. To require 2FA for any other role (staff or
tenant), flip that flag — no code or config change. The domain `Role` entity
exposes it via `Role::requiresTwoFactor()`.

A login needs a second factor when **either**:

- any of the user's active roles has `requires_2fa = true` (mandate), **or**
- the user already has 2FA enabled (`User::hasTwoFactorEnabled()` / the repository's
  `isConfirmed()`) — i.e. they enrolled voluntarily (opt-in).

Activation uses only the role mandate (a freshly-activated user is never enrolled yet).

## Config (`config/twofactor.php`)

| Key | Default | Purpose |
|---|---|---|
| `issuer` | `APP_NAME` | issuer label shown in the authenticator app |
| `challenge_ttl` | `600` | challenge-token lifetime, in seconds |

There is intentionally **no role list** in config — that lives in `roles.requires_2fa`.

---

## Enrollment & verification semantics

- **Enroll** generates a secret + otpauth URI. It is **idempotent while pending**: if
  an unconfirmed secret already exists it is reused instead of minting a new one. This
  makes repeated setup calls safe (client retries, React StrictMode double-invoke) —
  otherwise the QR shown could race the stored pending secret.
- **Confirm** validates the TOTP against the pending secret, sets
  `two_factor_confirmed_at`, and returns **8 one-time recovery codes** (hashed at rest,
  shown to the user exactly once).
- **Verify** accepts a current TOTP code **or** an unused recovery code (recovery codes
  are single-use and burned on match). The TOTP acceptance window is **±60s**
  (`verifyKey(..., window: 2)`) to absorb device clock drift — the library default of 1
  (±30s) was too tight for real authenticator apps.
- **Disable** clears all three columns.

---

## Login flow (staff, two steps)

```
┌──────────┐     ┌──────────────────┐     ┌──────────────────┐
│  Staff   │     │  React Frontend  │     │   KIBI Backend   │
└────┬─────┘     └────────┬─────────┘     └────────┬─────────┘
     │  email + password  │                        │
     │───────────────────>│  POST /staff/auth/login│
     │                    │───────────────────────>│
     │                    │                         │ role enforces 2FA?
     │                    │   { two_factor: {       │   yes ↓ (no token issued)
     │                    │     status, token } }   │
     │                    │<────────────────────────│
     │                    │                         │
     │  (setup_required)  │ POST /staff/auth/2fa/setup ({challenge_token})
     │                    │───────────────────────>│  → { secret, provisioning_uri }
     │  scan QR, enter code                         │
     │                    │ POST /staff/auth/2fa/confirm ({challenge_token, code})
     │                    │───────────────────────>│  → { session, recovery_codes }
     │                    │                         │
     │  (required)        │ POST /staff/auth/2fa/challenge ({challenge_token, code})
     │                    │───────────────────────>│  → session (LoginResource)
```

- A successful credential check for a 2FA-enforced role returns a **challenge** instead
  of a session: `{ two_factor: { status, challenge_token } }`.
- `status`:
  - `setup_required` — role enforces 2FA and the user has not enrolled → run the QR
    enrollment (`/setup` → `/confirm`), which returns the session **and** the recovery codes.
  - `required` — user is enrolled → enter a code (`/challenge`), which returns the session.
- The **challenge token** is an opaque, cache-stored random string (NOT a Sanctum
  token). It resolves to a user id for `challenge_ttl` seconds and **cannot access any
  authenticated route**. It is invalidated once the login completes.

---

## Activation interplay

`POST /auth/activate` (the magic-link flow) sets the password. For a user whose role
mandates 2FA (`roles.requires_2fa`), activation **withholds the session** — it returns
`token: null` (the password is set, but no Sanctum token is issued). The SPA then
redirects to the staff login so 2FA is enforced on the next sign-in. Non-enforced roles
(e.g. tenant owners, `operator`) receive a session as before.

---

## Endpoints

All staff 2FA endpoints are **public** (no `auth:sanctum`) and guarded by the opaque
challenge token, throttled `5,15`.

```
POST /staff/auth/login           → LoginResource  OR  { two_factor: { status, challenge_token } }
POST /staff/auth/2fa/setup       { challenge_token }        → { secret, provisioning_uri }
POST /staff/auth/2fa/confirm     { challenge_token, code }  → { session: LoginResource, recovery_codes: string[] }
POST /staff/auth/2fa/challenge   { challenge_token, code }  → LoginResource
```

Error mapping: invalid/expired challenge → `401`; wrong code (or no pending secret on
confirm) → `422`.

---

## Reuse beyond login (step-up auth)

The base is deliberately login-agnostic so any sensitive action can require a second
factor. Example — **SCRUM-520** superadmin-approval: the approve use case injects
`VerifyTwoFactorUseCase` and gates the action on the actor's current code:

```php
if (! $this->verifyTwoFactor->execute($approverId, $code)) {
    // reject — invalid second factor
}
// …perform the approval…
```

No new 2FA code is required for such flows — only the wiring that calls `verify`
before the protected operation.

---

## Dependency injection (`AppServiceProvider`)

- `TwoFactorServiceInterface` → `Google2faService`
- `TwoFactorRepositoryInterface` → `EloquentTwoFactorRepository`
- `TwoFactorChallengeRepositoryInterface` → `CacheTwoFactorChallengeRepository` (TTL from config)
- The staff-scoped use cases bind the staff `User`/`Role` repositories (no `TenantContext`).
- No role-list binding is needed anymore — the gates read `Role::requiresTwoFactor()` (`roles.requires_2fa`).

---

## Tests

- `tests/Feature/Modules/Auth/TwoFactorBaseTest.php` — base: enroll/confirm/verify/disable, enroll idempotency, recovery-code burn, encryption at rest, exceptions.
- `tests/Feature/Modules/Auth/TwoFactorLoginTest.php` — login flow: `setup_required`/`required`, confirm + recovery codes, challenge, recovery code at challenge, invalid code (422), invalid challenge (401), operator-no-2FA.
- `tests/Feature/Modules/Auth/ActivateAccountTest.php` — activation withholds the token for a 2FA-enforced role; issues it otherwise.
