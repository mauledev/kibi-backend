# Testing

Pest conventions, test organization by layer, factories and tenant context in tests.

---

## Stack

- **Pest** for all tests (Unit and Feature)
- **RefreshDatabase** on Feature tests — each test runs in a transaction that rolls back
- No Mockery — use interface substitution for external services

---

## Directory structure

```
tests/
├── Unit/
│   └── Modules/
│       └── {Module}/
│           ├── Domain/Entities/
│           └── Application/UseCases/
└── Feature/
    └── Modules/
        └── {Module}/
```

Unit tests mirror the `app/` structure: module code under `app/Modules/{Module}/` maps to `tests/Unit/Modules/{Module}/`, and cross-cutting code under `app/Common/` maps to `tests/Unit/Common/`. Feature tests map to HTTP flows and DB integration; cross-cutting infrastructure (e.g. `app/Common/Audit/AuditLogger`) is integration-tested under `tests/Feature/Common/` (e.g. `tests/Feature/Common/Audit/`).

---

## Pest conventions

```php
describe('LoginUseCase', function () {
    beforeEach(function () {
        // shared setup
    });

    it('returns a token on valid credentials', function () {
        // ...
        expect($output->token)->toBeString()->not->toBeEmpty();
    });

    it('throws on invalid password', function () {
        expect(fn () => $this->useCase->execute($input))
            ->toThrow(InvalidCredentialsException::class);
    });
});
```

Use `describe()` to group by class. Use `it()` for every case. `beforeEach()` for shared setup inside a `describe()`.

---

## What to test per layer

### Domain Entities (Unit)

Pure PHP — no framework, no DB. Test every behavior method.

```php
it('deactivates an active user', function () {
    $user = new User(id: 1, status: 'active', ...);
    $user->deactivate();
    expect($user->status)->toBe('inactive');
});
```

No factories, no database. Instantiate entities directly.

### UseCases (Unit)

Mock the repository interface. Test orchestration and business rules only.

```php
beforeEach(function () {
    $this->repo = Mockery::mock(UserRepositoryInterface::class);
    $this->useCase = new LoginUseCase($this->repo);
});

it('calls findByEmail with the provided email', function () {
    $this->repo->shouldReceive('findByEmail')
        ->once()
        ->with('user@test.com')
        ->andReturn(null);

    expect(fn () => $this->useCase->execute(new LoginInput('user@test.com', 'pass')))
        ->toThrow(UserNotFoundException::class);
});
```

Never hit the database in UseCase unit tests.

### Repositories (Feature)

Integration tests — hit the real database. Verify tenant scoping works correctly.

```php
uses(RefreshDatabase::class);

it('only returns users belonging to the tenant', function () {
    $tenant = Tenant::factory()->create();
    $other  = Tenant::factory()->create();

    User::factory()->for($tenant)->create(['email' => 'mine@test.com']);
    User::factory()->for($other)->create(['email' => 'theirs@test.com']);

    app()->instance(TenantContext::class, new TenantContext(tenantId: $tenant->id));

    $repo = app(UserRepositoryInterface::class);
    $user = $repo->findByEmail('mine@test.com');

    expect($user)->not->toBeNull();
    expect($repo->findByEmail('theirs@test.com'))->toBeNull();
});
```

### Controllers / HTTP (Feature)

Test the full HTTP flow. Assert status codes and response structure.

```php
uses(RefreshDatabase::class);

it('returns 200 with user data on valid login', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    User::factory()->for($tenant)->create([
        'email'         => 'admin@test.com',
        'password_hash' => bcrypt('secret'),
    ]);

    $response = $this->withServerVariables(['HTTP_HOST' => 'acme.kibi.test'])
        ->postJson('/api/auth/login', [
            'email'    => 'admin@test.com',
            'password' => 'secret',
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success', 'status', 'data' => ['id', 'email', 'token'],
        ]);
});
```

Never assert internal IDs — assert `uuid` fields only.

---

## Tenant context in tests

`TenantContext` now requires both `tenantId` and `ownerId`. Bind it manually before any UseCase or Repository call:

```php
use App\Common\Tenant\TenantContext;

app()->instance(TenantContext::class, new TenantContext(
    tenantId: $tenant->id,
    ownerId: $tenant->owner_id ?? 0,
));
```

For HTTP Feature tests, use `withHeader('X-Tenant-Slug', $tenant->slug)` so `TenantMiddleware` resolves `TenantContext` automatically (including `ownerId` from `tenants.owner_id`).

---

## Authentication in tests

Users no longer have a `tenant_id` column. To associate a user with a tenant for repository scoping, either:

1. Make them the **tenant owner** (their `id` equals `TenantContext::ownerId`)
2. Give them an **active role assignment** for a role owned by that tenant

```php
// Tenant-scoped user via role assignment
$user = User::factory()->create();
$role = Role::factory()->forTenant($tenant)->atLevel(5)->create(['slug' => 'some_role']);
UserRoleAssignment::factory()->forUser($user)->forRole($role)->active()->create();

// Use the tenant owner directly (TenantFactory auto-creates an owner)
$tenant = Tenant::factory()->create();
$owner = User::find($tenant->owner_id);
```

For staff endpoints:

```php
$staff = User::factory()->staff()->create(); // is_staff = true

actingAs($staff)
    ->getJson('/api/staff/tenants')
    ->assertStatus(200);
```

### Owner bypass in tests

`Gate::before` grants all abilities to the user whose `id` matches `TenantContext::ownerId`. For tests that verify owner bypass:

```php
$tenant = Tenant::factory()->create();
$owner = User::find($tenant->owner_id);

// Give the owner a low-level role so UseCase hierarchy checks also pass
$ownerRole = Role::factory()->forTenant($tenant)->atLevel(1)->create(['slug' => 'owner_fixture']);
UserRoleAssignment::factory()->forUser($owner)->forRole($ownerRole)->active()->create();

actingAs($owner)
    ->withHeader('X-Tenant-Slug', $tenant->slug)
    ->postJson('/api/roles', [...])
    ->assertStatus(201);
```

Note: `Gate::before` only bypasses Laravel Gate checks (`authorize()`). UseCase domain logic (hierarchy checks) still applies, so the owner must have a low-level role for those checks to pass.

---

## External services

Never mock repositories in Feature tests — use the real database.

Mock external service interfaces (mailers, payment gateways, storage) when they would make network calls:

```php
$this->mock(MailerInterface::class)
    ->shouldReceive('send')
    ->once();
```

Bind the mock before the action that triggers it.

---

## Factories

Factories live in `database/factories/`. Each factory maps to an Eloquent model.

```php
// Standalone user (not scoped to any tenant by default)
User::factory()->create();

// Staff user (is_staff = true)
User::factory()->staff()->create();

// Inactive user
User::factory()->inactive()->create();

// Tenant with auto-created owner
$tenant = Tenant::factory()->create(); // owner_id is auto-set via User::factory()
$owner = User::find($tenant->owner_id);
```

`User::factory()->for($tenant)` is no longer supported — `users` no longer has a `tenant_id` column. Associate users to a tenant via role assignments or by making them the tenant owner.

Define states (`staff`, `inactive`, etc.) in the factory class — never pass raw attribute arrays in tests to override behavior.
