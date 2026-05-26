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

Unit tests mirror the `app/Modules/` structure. Feature tests map to HTTP flows.

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

Never assert internal IDs — assert `public_id` fields only.

---

## Tenant context in tests

Bind `TenantContext` manually before any UseCase or Repository call:

```php
use App\Common\Tenant\TenantContext;

app()->instance(TenantContext::class, new TenantContext(tenantId: $tenant->id));
```

For HTTP Feature tests, use `withServerVariables` to simulate the subdomain so `TenantMiddleware` resolves correctly (as shown above). This requires the tenant to exist in the database.

---

## Authentication in tests

Use Sanctum's `actingAs` for authenticated endpoints:

```php
$user = User::factory()->for($tenant)->create();

actingAs($user)
    ->getJson('/api/roles')
    ->assertStatus(200);
```

For staff endpoints (no tenant):

```php
$staff = User::factory()->staff()->create(); // tenant_id IS NULL

actingAs($staff)
    ->getJson('/api/staff/tenants')
    ->assertStatus(200);
```

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
// Tenant-owned user
User::factory()->for($tenant)->create();

// Staff user (tenant_id IS NULL)
User::factory()->staff()->create();

// Inactive user
User::factory()->inactive()->create();
```

Define states (`staff`, `inactive`, etc.) in the factory class — never pass raw attribute arrays in tests to override behavior.
