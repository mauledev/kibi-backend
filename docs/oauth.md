# OAuth

How Google and Microsoft login works in KIBI.

---

## Core concept: the backend never redirects

In a SPA (React), the OAuth flow is handled by the **frontend**. The backend only validates the token the frontend already obtained from the provider. This is called the **stateless** or *token exchange* flow.

The backend never sees the Google/Microsoft login screen, never generates a `redirect_uri`, and never handles OAuth callbacks.

---

## Full flow

```
┌──────────┐        ┌──────────────────┐        ┌──────────────────┐        ┌──────────────┐
│   User   │        │  React Frontend  │        │   KIBI Backend   │        │ Google/MSFT  │
└────┬─────┘        └────────┬─────────┘        └────────┬─────────┘        └──────┬───────┘
     │                       │                           │                          │
     │  Click "Login Google" │                           │                          │
     │──────────────────────>│                           │                          │
     │                       │                           │                          │
     │                       │  Opens popup / redirects  │                          │
     │                       │──────────────────────────────────────────────────────>
     │                       │                           │                          │
     │  Logs in with account │                           │                          │
     │──────────────────────────────────────────────────────────────────────────────>
     │                       │                           │                          │
     │                       │      provider access_token                           │
     │                       │<──────────────────────────────────────────────────────
     │                       │                           │                          │
     │                       │  POST /auth/oauth/google  │                          │
     │                       │  { "access_token": "..." }│                          │
     │                       │──────────────────────────>│                          │
     │                       │                           │                          │
     │                       │                           │  Validate token w/Google │
     │                       │                           │─────────────────────────>│
     │                       │                           │                          │
     │                       │                           │  { id, email, name }     │
     │                       │                           │<─────────────────────────│
     │                       │                           │                          │
     │                       │                           │  Look up user in DB      │
     │                       │                           │  (by google_id → email)  │
     │                       │                           │──────────┐               │
     │                       │                           │          │               │
     │                       │                           │<─────────┘               │
     │                       │                           │                          │
     │                       │                           │  Create user if missing  │
     │                       │                           │  (password_hash = null)  │
     │                       │                           │──────────┐               │
     │                       │                           │          │               │
     │                       │                           │<─────────┘               │
     │                       │                           │                          │
     │                       │   { token, user, roles }  │                          │
     │                       │<──────────────────────────│                          │
     │                       │                           │                          │
     │  User authenticated   │                           │                          │
     │<──────────────────────│                           │                          │
```

---

## Lookup logic in OAuthLoginUseCase

The use case follows this order to find or create the user:

```
1. Look up by provider ID (google_id or microsoft_id)
   └─ Found → direct login

2. If not found, look up by email
   └─ Found → link provider ID to existing account (account linking)
              → save google_id / microsoft_id on the record

3. If not found → create new user
   └─ email and name from provider
   └─ password_hash = null (pure OAuth user)
   └─ google_id or microsoft_id saved
```

**Why look up by email as fallback?**
A user may have registered with email/password and later tries to sign in with Google using the same email. In that case the account is linked automatically instead of creating a duplicate.

---

## Code structure

```
app/Modules/Auth/
├── Domain/
│   └── Contracts/
│       └── OAuthProviderInterface.php     ← gateway contract
├── Application/
│   ├── DTOs/
│   │   ├── OAuthLoginInput.php            ← provider + access_token + tenantId
│   │   └── OAuthUserData.php              ← id, email, name (from provider)
│   └── UseCases/
│       └── OAuthLogin/
│           └── OAuthLoginUseCase.php      ← orchestrates lookup + create + token
└── Infrastructure/
    └── Gateways/
        ├── StubOAuthProvider.php          ← temporary stub (throws exception)
        └── (SocialiteGoogleGateway.php)   ← pending — real Socialite implementation
```

`OAuthLoginUseCase` returns `LoginOutput` — the same DTO as password login. The frontend receives exactly the same response structure.

---

## Implementing the real gateway (pending — Socialite)

When Socialite is integrated, `SocialiteGoogleGateway` (and `SocialiteMicrosoftGateway`) will be created in `Infrastructure/Gateways/` implementing `OAuthProviderInterface`:

```php
public function getUserFromToken(string $accessToken): OAuthUserData
{
    $social = Socialite::driver('google')->stateless()->userFromToken($accessToken);

    return new OAuthUserData(
        providerId: $social->getId(),
        email:      $social->getEmail(),
        name:       $social->getName(),
    );
}
```

The binding in `AppServiceProvider` changes from `StubOAuthProvider` to the real implementation. No other file changes — the use case has no knowledge of which provider is behind the interface.

**Rules about OAuth tokens:**
- Never store the provider `access_token` in the database
- Only store the provider user ID (`google_id` / `microsoft_id`)
- The session token for KIBI is the internally generated Sanctum token

---

## Endpoint

```
POST /auth/oauth/{provider}
```

| Field | Type | Description |
|---|---|---|
| `provider` | route param | `google` or `microsoft` |
| `access_token` | body (string, required) | Token obtained by the frontend from the provider |

**Response:** same format as `POST /auth/login`.

The endpoint lives in the **tenant** group (with `TenantMiddleware`). Staff does not have OAuth.
