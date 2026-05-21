# Laravel Multi-Tenant Backend Skill (COMPLETO)

## Overview

This skill is designed to assist with **comprehensive backend development** for a multi-tenant Laravel platform (school administration system).

**Key Features:**
- ✅ Multi-tenancy architecture (schema-per-tenant PostgreSQL)
- ✅ OAuth authentication with multiple roles (admin, teacher, student, doctor)
- ✅ Service Layer + Repository Pattern architecture
- ✅ PSR-12 Code Style Compliance
- ✅ OWASP Top 10 Security Integration
- ✅ API design for React frontend
- ✅ Complete security and data isolation
- ✅ Testing patterns and strategies
- ✅ Database design for multi-tenant systems
- ✅ Complete project structure

## When to Trigger This Skill

Use this skill when:
- Creating or modifying Laravel controllers, services, or repositories
- Setting up multi-tenancy logic and tenant resolution
- Implementing OAuth authentication flows with multiple roles
- Designing API endpoints for the school platform
- Writing migrations for multi-tenant schemas
- Implementing Service Layer or Repository Pattern
- Implementing security measures (input validation, XSS prevention, CSRF protection)
- Testing multi-tenant functionality
- Debugging tenant isolation or security issues
- Reviewing code for security vulnerabilities
- Ensuring PSR-12 compliance
- Implementing OWASP Top 10 protections
- Optimizing queries across tenants
- Designing database schemas for multi-tenancy

---

# SECTION 1: MULTI-TENANCY ARCHITECTURE

## 1.1 Schema-Per-Tenant Pattern (PostgreSQL)

### Database Structure

```sql
-- CENTRAL DATABASE (postgres)
CREATE DATABASE schools_central;

-- Central schema (public)
CREATE TABLE public.tenants (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    schema_name VARCHAR(100) NOT NULL UNIQUE,
    domain VARCHAR(255),
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE public.users (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES public.tenants(id),
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(tenant_id, email)
);

-- TENANT SCHEMAS (one per school)
CREATE SCHEMA escuela1;
CREATE SCHEMA escuela2;

-- Tables per tenant
CREATE TABLE escuela1.users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'user',
    department VARCHAR(100),
    tenant_id INTEGER,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE escuela1.students (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES escuela1.users(id),
    classroom VARCHAR(50),
    enrollment_number VARCHAR(20) UNIQUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE escuela1.grades (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL REFERENCES escuela1.students(id),
    subject VARCHAR(100),
    grade DECIMAL(5, 2),
    quarter INTEGER,
    academic_year INTEGER,
    created_at TIMESTAMP DEFAULT NOW()
);
```

### Tenant Identification

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class TenantResolver
{
    /**
     * Get tenant by domain.
     *
     * @param string $domain The domain (e.g., escuela1.app.com)
     * @return Tenant|null The tenant instance
     */
    public static function getTenantByDomain(string $domain): ?Tenant
    {
        return Tenant::where('domain', $domain)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Get tenant by ID.
     *
     * @param int $tenantId The tenant ID
     * @return Tenant|null
     */
    public static function getTenantById(int $tenantId): ?Tenant
    {
        return Tenant::find($tenantId);
    }

    /**
     * Activate tenant schema.
     * Call this after identifying tenant.
     *
     * @param Tenant $tenant The tenant instance
     * @return void
     */
    public static function activateTenant(Tenant $tenant): void
    {
        // PostgreSQL: SET search_path
        DB::statement("SET search_path TO {$tenant->schema_name}, public");
        
        // Store in cache
        cache()->put('current_tenant', $tenant, now()->addHours(1));
    }

    /**
     * Get current tenant from request context.
     *
     * @return Tenant|null
     */
    public static function getCurrentTenant(): ?Tenant
    {
        // From cache (after login)
        if (cache()->has('current_tenant')) {
            return cache()->get('current_tenant');
        }

        // From domain
        $domain = request()->getHost();
        return self::getTenantByDomain($domain);
    }
}
```

### Tenant Model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $connection = 'central';
    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'domain',
        'schema_name',
        'status',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
```

---

# SECTION 2: OAUTH AUTHENTICATION WITH MULTIPLE ROLES

## 2.1 Authentication Flow

### Super Admin Flow
```
1. Accede: admin.escuelas.com
2. Login con credenciales
3. Busca en BD central
4. Genera token (is_super_admin: true)
5. Accede a /admin/dashboard
```

### School User Flow
```
1. Accede: escuela1.app.com
2. Sistema detecta domain → busca tenant
3. Login con credenciales
4. Activa schema de escuela1
5. Busca usuario en schema escuela1
6. Genera token (tenant_id: 1, role: teacher)
7. Accede a /dashboard (según role)
```

### OAuth Controller

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\TenantUser;
use App\Services\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OAuthController extends Controller
{
    /**
     * Login user (Super Admin or School User).
     *
     * @param Request $request The login request
     * @return array Token and user data
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request): array
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $domain = $request->getHost();

        // Super Admin
        if ($domain === 'admin.escuelas.com') {
            return $this->loginSuperAdmin($validated);
        }

        // School User
        return $this->loginSchoolUser($validated, $domain);
    }

    /**
     * Login super admin from central BD.
     *
     * @param array $credentials Email and password
     * @return array
     * @throws \Illuminate\Auth\AuthenticationException
     */
    private function loginSuperAdmin(array $credentials): array
    {
        $user = SuperAdmin::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            Log::warning('Failed super admin login', ['email' => $credentials['email']]);
            throw new \Illuminate\Auth\AuthenticationException('Invalid credentials');
        }

        $token = $user->createToken('admin')->plainTextToken;

        Log::info('Super admin logged in', ['user_id' => $user->id]);

        return [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'is_super_admin' => true,
            ],
        ];
    }

    /**
     * Login school user from tenant schema.
     *
     * @param array $credentials
     * @param string $domain
     * @return array
     * @throws \Exception
     */
    private function loginSchoolUser(array $credentials, string $domain): array
    {
        // 1. Get tenant by domain
        $tenant = TenantResolver::getTenantByDomain($domain);
        if (!$tenant) {
            throw new \Exception('School not found');
        }

        // 2. Activate tenant schema
        TenantResolver::activateTenant($tenant);

        // 3. Find user in tenant schema
        $user = TenantUser::where('email', $credentials['email'])
            ->where('status', 'active')
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            Log::warning('Failed school user login', [
                'email' => $credentials['email'],
                'tenant_id' => $tenant->id,
            ]);

            throw new \Illuminate\Auth\AuthenticationException('Invalid credentials');
        }

        // 4. Generate token with tenant context
        $token = $user->createToken('school')->plainTextToken;

        Log::info('School user logged in', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => $user->role,
        ]);

        return [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => $user->role,
                'tenant_id' => $tenant->id,
            ],
        ];
    }
}
```

---

# SECTION 3: SERVICE LAYER + REPOSITORY PATTERN

## 3.1 Repository Pattern

### Interface (Contract)

```php
<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function create(array $data): User;
    public function update(int $id, array $data): User;
    public function find(int $id): ?User;
    public function getById(int $id): User;
    public function getByEmail(string $email): ?User;
    public function getByRole(string $role): Collection;
    public function delete(int $id): bool;
}
```

### Repository Implementation

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Collection;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Create user.
     *
     * @param array $data User data
     * @return User
     */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /**
     * Update user.
     *
     * @param int $id User ID
     * @param array $data Data to update
     * @return User
     */
    public function update(int $id, array $data): User
    {
        $user = $this->getById($id);
        $user->update($data);
        return $user;
    }

    /**
     * Find user (null if not found).
     *
     * @param int $id User ID
     * @return User|null
     */
    public function find(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Get user by ID (throw if not found).
     *
     * @param int $id User ID
     * @return User
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getById(int $id): User
    {
        return User::findOrFail($id);
    }

    /**
     * Get user by email.
     *
     * @param string $email User email
     * @return User|null
     */
    public function getByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Get all users by role.
     *
     * @param string $role User role
     * @return Collection
     */
    public function getByRole(string $role): Collection
    {
        return User::where('role', $role)->get();
    }

    /**
     * Delete user.
     *
     * @param int $id User ID
     * @return bool
     */
    public function delete(int $id): bool
    {
        return User::destroy($id) > 0;
    }
}
```

## 3.2 Service Layer

### User Service

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * Create user with business logic validation.
     *
     * @param array $data User data (email, name, password, role, tenant_id)
     * @return User The created user
     * @throws ValidationException
     */
    public function createUser(array $data): User
    {
        // 1. Validate email unique (per tenant)
        $existing = User::where('email', $data['email'])
            ->where('tenant_id', $data['tenant_id'])
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'email' => 'Email already exists in this school',
            ]);
        }

        // 2. Validate role
        $validRoles = ['admin', 'teacher', 'student', 'doctor'];
        if (!in_array($data['role'], $validRoles)) {
            throw ValidationException::withMessages([
                'role' => 'Invalid role',
            ]);
        }

        // 3. Hash password
        $data['password'] = Hash::make($data['password']);

        // 4. Create user
        $user = $this->userRepository->create($data);

        // 5. Log creation
        Log::info('User created', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'role' => $user->role,
        ]);

        return $user;
    }

    /**
     * Update user with validation.
     *
     * @param int $userId User ID
     * @param array $data Data to update
     * @return User
     * @throws ValidationException
     */
    public function updateUser(int $userId, array $data): User
    {
        $user = $this->userRepository->getById($userId);

        // Validate allowed fields
        $allowedFields = ['name', 'email', 'role', 'status'];
        $filtered = collect($data)->only($allowedFields)->toArray();

        $user = $this->userRepository->update($userId, $filtered);

        Log::info('User updated', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        return $user;
    }

    /**
     * Delete user.
     *
     * @param int $userId User ID
     * @return bool
     * @throws ValidationException
     */
    public function deleteUser(int $userId): bool
    {
        $user = $this->userRepository->getById($userId);

        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            throw ValidationException::withMessages([
                'user_id' => 'Cannot delete your own account',
            ]);
        }

        Log::info('User deleted', [
            'deleted_user_id' => $user->id,
            'deleted_by' => auth()->id(),
            'tenant_id' => $user->tenant_id,
        ]);

        return $this->userRepository->delete($userId);
    }
}
```

## 3.3 Dependency Injection

### Service Provider

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register repository bindings.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
        );
    }
}
```

### Using in Controller

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {
    }

    /**
     * Create user via service layer.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'name' => 'required|string',
                'password' => 'required|string|min:12',
                'role' => 'required|in:admin,teacher,student,doctor',
            ]);

            $validated['tenant_id'] = auth()->user()->tenant_id;

            $user = $this->userService->createUser($validated);

            return response()->json([
                'success' => true,
                'data' => $user,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
```

---

# SECTION 4: PSR-12 CODE STYLE STANDARDS

## 4.1 File Structure

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

// Code here
```

**Rules:**
- ✅ `<?php` on first line
- ✅ `declare(strict_types=1);` after opening tag
- ✅ Blank line after namespace
- ✅ Use statements grouped and alphabetically sorted
- ✅ No closing `?>` tag

## 4.2 Classes & Methods

```php
<?php

declare(strict_types=1);

namespace App\Services;

class UserService
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Create user with validation.
     *
     * @param array $data User data
     * @return User The created user
     */
    public function createUser(array $data): User
    {
        return $this->userRepository->create($data);
    }

    private function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
```

**Rules:**
- ✅ Type declarations on properties
- ✅ Return types on methods
- ✅ 4 spaces indentation
- ✅ Opening brace on same line as class/method
- ✅ Public/Private/Protected keywords
- ✅ One blank line between methods
- ✅ Docblocks for public methods

## 4.3 Naming Conventions

```php
// Classes: PascalCase
class UserService { }
interface UserRepositoryInterface { }

// Methods/Functions: camelCase
public function createUser(): User { }
private function validateEmail(string $email): bool { }

// Constants: UPPERCASE_SNAKE_CASE
private const DEFAULT_ROLE = 'user';
public const MAX_LOGIN_ATTEMPTS = 5;

// Variables: camelCase
$currentUser = auth()->user();
$isAuthenticated = true;

// Database: snake_case
Schema::create('users', function (Blueprint $table) {
    $table->string('first_name');
    $table->integer('tenant_id');
});
```

---

# SECTION 5: OWASP TOP 10 SECURITY

## 5.1 A01: Broken Access Control (Tenant Isolation)

```php
/**
 * Get user with tenant isolation.
 *
 * @param int $userId User ID
 * @return User
 * @throws \Illuminate\Auth\Access\AuthorizationException
 */
public function getUser(int $userId): User
{
    // 1. Verify user belongs to same tenant
    $user = User::where('id', $userId)
        ->where('tenant_id', auth()->user()->tenant_id)
        ->firstOrFail();

    // 2. Verify permission
    if (!$this->userHasPermission('view_users')) {
        throw new \Illuminate\Auth\Access\AuthorizationException();
    }

    return $user;
}
```

## 5.2 A02: Cryptographic Failures (Password Hashing)

```php
/**
 * Hash password securely.
 *
 * @param string $password Plaintext password
 * @return string Bcrypt hash
 */
private function hashPassword(string $password): string
{
    return Hash::make($password, [
        'rounds' => 12,  // More secure
    ]);
}

/**
 * Verify password.
 *
 * @param string $password Plaintext password
 * @param string $hash Bcrypt hash
 * @return bool
 */
public function verifyPassword(string $password, string $hash): bool
{
    return Hash::check($password, $hash);
}
```

## 5.3 A03: Injection (Parameterized Queries)

```php
/**
 * Get users by role (safe).
 *
 * @param string $role User role
 * @return Collection
 */
public function getUsersByRole(string $role): Collection
{
    // Eloquent uses parameterized queries (safe)
    return User::where('role', $role)
        ->where('tenant_id', auth()->user()->tenant_id)
        ->get();
}

/**
 * Search users (safe).
     *
     * @param string $email Email to search
     * @return Collection
     */
    public function searchByEmail(string $email): Collection
    {
        // Parameterized with LIKE
        return User::where('email', 'LIKE', '%' . $email . '%')
            ->where('tenant_id', auth()->user()->tenant_id)
            ->get();
    }
```

## 5.4 A04: Insecure Design (Rate Limiting)

```php
/**
 * Login with rate limiting.
     *
     * @param Request $request Login request
     * @return array Token
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request): array
    {
        // Rate limiting: 5 attempts per 15 minutes
        if ($this->rateLimiter->tooManyAttempts('login:' . $request->ip(), 5)) {
            throw new \Illuminate\Validation\ValidationException(
                'Too many login attempts'
            );
        }

        $this->rateLimiter->hit('login:' . $request->ip(), 15 * 60);

        // Authentication logic...

        $this->rateLimiter->clear('login:' . $request->ip());

        return ['token' => $token];
    }
```

## 5.5 A05: Broken Authentication (2FA)

```php
/**
 * Enable two-factor authentication.
     *
     * @return string QR code for scanning
     */
    public function enableTwoFactor(): string
    {
        $user = auth()->user();

        // Generate TOTP secret
        $generator = new Google2FAQRCode();
        $secret = $user->createSecret();

        return $generator->getQRCodeInline(
            config('app.name'),
            $user->email,
            $secret
        );
    }

    /**
     * Verify 2FA code.
     *
     * @param string $code User's 2FA code
     * @return bool
     */
    public function verifyTwoFactor(string $code): bool
    {
        $google2fa = app('pragmarx.google2fa');
        return $google2fa->verifyKey(
            auth()->user()->two_factor_secret,
            $code
        );
    }
```

## 5.6 A06: Vulnerable Components (Dependency Updates)

```bash
# Check for vulnerabilities
composer audit

# Update dependencies
composer update

# Keep Laravel updated
php artisan update
```

## 5.7 A07: Identification Failures (Sessions)

```php
/**
 * Login with secure session.
     *
     * @param Request $request
     * @return array Token
     */
    public function login(Request $request): array
    {
        // ... authentication ...

        // 1. Regenerate session
        session()->regenerate();

        // 2. Use token with expiration
        $token = $user->createToken('auth', ['*'], now()->addHours(24))
            ->plainTextToken;

        return ['token' => $token];
    }
```

## 5.8 A08: Data Integrity (No Unserialize)

```php
/**
 * Deserialize data safely.
     *
     * @param string $data JSON data
     * @return array
     * @throws \Exception
     */
    private function deserializeData(string $data): array
    {
        // Never use unserialize() with untrusted data
        // Use JSON instead
        return json_decode($data, true, flags: JSON_THROW_ON_ERROR);
    }
```

## 5.9 A09: Logging (Audit Trails)

```php
/**
 * Log security events.
     *
     * @param string $action Action taken
     * @param array $data Event data
     * @return void
     */
    private function logSecurityEvent(string $action, array $data = []): void
    {
        Log::info("Security Event: $action", [
            'action' => $action,
            'user_id' => auth()->id(),
            'tenant_id' => auth()->user()->tenant_id,
            'ip' => request()->ip(),
            'timestamp' => now(),
            'data' => $data,
        ]);
    }
```

## 5.10 A10: SSRF (URL Validation)

```php
/**
 * Fetch remote content safely.
     *
     * @param string $url URL to fetch
     * @return string Content
     * @throws \Exception
     */
    public function fetchRemoteContent(string $url): string
    {
        // 1. Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \Exception('Invalid URL');
        }

        // 2. Prevent private IP access
        $ip = gethostbyname(parse_url($url, PHP_URL_HOST));
        if ($this->isPrivateIP($ip)) {
            throw new \Exception('Access to private networks not allowed');
        }

        // 3. Use whitelist
        $allowedDomains = ['api.example.com', 'cdn.example.com'];
        if (!in_array(parse_url($url, PHP_URL_HOST), $allowedDomains)) {
            throw new \Exception('Domain not in whitelist');
        }

        // 4. Fetch with timeout
        return Http::timeout(5)
            ->get($url)
            ->body();
    }

    private function isPrivateIP(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
```

---

# SECTION 6: DIRECTORY STRUCTURE

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   ├── OAuthController.php
│   │   │   │   └── SuperAdminAuthController.php
│   │   │   ├── School/
│   │   │   │   ├── UserController.php
│   │   │   │   ├── StudentController.php
│   │   │   │   └── GradeController.php
│   │   │   └── Admin/
│   │   │       └── SchoolController.php
│   │   ├── Middleware/
│   │   │   ├── ResolveTenant.php
│   │   │   ├── AuthenticateTenant.php
│   │   │   ├── CheckPermission.php
│   │   │   └── SecurityHeaders.php
│   │   └── Requests/
│   │       ├── LoginRequest.php
│   │       └── CreateUserRequest.php
│   ├── Services/
│   │   ├── UserService.php
│   │   ├── StudentService.php
│   │   ├── GradeService.php
│   │   ├── AuthService.php
│   │   └── TenantResolver.php
│   ├── Repositories/
│   │   ├── Contracts/
│   │   │   ├── UserRepositoryInterface.php
│   │   │   └── StudentRepositoryInterface.php
│   │   ├── UserRepository.php
│   │   └── StudentRepository.php
│   ├── Models/
│   │   ├── Tenant.php
│   │   ├── User.php
│   │   └── Student.php
│   └── Providers/
│       ├── RepositoryServiceProvider.php
│       └── AppServiceProvider.php
├── database/
│   ├── migrations/
│   │   ├── 0001_create_central_tables.php
│   │   └── tenant/
│   │       ├── 0001_create_users_table.php
│   │       ├── 0002_create_students_table.php
│   │       └── 0003_create_grades_table.php
│   └── seeders/
├── routes/
│   ├── api.php
│   ├── web.php
│   └── tenant.php
├── tests/
│   ├── Unit/
│   │   └── Services/
│   │       ├── UserServiceTest.php
│   │       └── StudentServiceTest.php
│   └── Feature/
│       └── Api/
│           ├── AuthTest.php
│           └── UserTest.php
├── .env.example
├── composer.json
└── artisan
```

---

# SECTION 7: TESTING STRATEGY

## 7.1 Unit Tests (Services)

```php
<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $userService;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = new UserRepository();
        $this->userService = new UserService($this->userRepository);
    }

    /**
     * @test
     */
    public function can_create_user_with_validation(): void
    {
        $data = [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'password' => 'SecurePassword123!',
            'role' => 'teacher',
            'tenant_id' => 1,
        ];

        $user = $this->userService->createUser($data);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('test@example.com', $user->email);
    }

    /**
     * @test
     */
    public function throws_exception_on_duplicate_email(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        User::create([
            'email' => 'test@example.com',
            'name' => 'User 1',
            'password' => 'hashed',
            'role' => 'teacher',
            'tenant_id' => 1,
        ]);

        $this->userService->createUser([
            'email' => 'test@example.com',  // Duplicate
            'name' => 'User 2',
            'password' => 'SecurePassword123!',
            'role' => 'teacher',
            'tenant_id' => 1,
        ]);
    }
}
```

## 7.2 Feature Tests (API Endpoints)

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function can_create_user_via_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'tenant_id' => 1]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/users', [
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'password' => 'SecurePassword123!',
            'role' => 'teacher',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'tenant_id' => 1,
        ]);
    }

    /**
     * @test
     */
    public function tenant_isolation_prevents_cross_tenant_access(): void
    {
        $user1 = User::factory()->create(['tenant_id' => 1]);
        $user2 = User::factory()->create(['tenant_id' => 2]);

        Sanctum::actingAs($user1);

        // Try to access user from different tenant
        $response = $this->getJson("/api/users/{$user2->id}");

        $response->assertStatus(404);  // Should not find user2
    }
}
```

---

# SECTION 8: KEY PRINCIPLES & CHECKLIST

## ✅ DO
1. **Always validate input** (PSR-12 + OWASP)
2. **Use parameterized queries** (Eloquent default)
3. **Implement tenant isolation** on every query
4. **Hash passwords** with bcrypt (12+ rounds)
5. **Use HTTPS only** in production
6. **Set security headers** (CSP, X-Frame-Options)
7. **Log security events** (audit trails)
8. **Rate limit** sensitive endpoints
9. **Use environment variables** for secrets
10. **Test thoroughly** (unit + feature + integration)
11. **Follow PSR-12** style guide
12. **Update dependencies** regularly (composer audit)
13. **Use service layer** for business logic
14. **Use repository pattern** for data access
15. **Implement OAuth** for authentication
16. **Use role-based access control** (RBAC)

## ❌ DON'T
1. **Store passwords plaintext**
2. **Expose sensitive data** in responses
3. **Trust user input** without validation
4. **Ignore tenant isolation** (multi-tenant)
5. **Use weak cryptography** (MD5, SHA1)
6. **Hardcode secrets** in source code
7. **Skip CSRF protection**
8. **Leave debug mode** on in production
9. **Forget rate limiting** on login
10. **Violate PSR-12** standards
11. **Use outdated dependencies**
12. **Mix concerns** (controller, service, repository)
13. **Store tokens** in plaintext
14. **Use unsecure** session management
15. **Ignore OWASP Top 10**

---

# SECTION 9: RESOURCES

- **PSR-12:** https://www.php-fig.org/psr/psr-12/
- **OWASP Top 10:** https://owasp.org/www-project-top-ten/
- **Laravel Documentation:** https://laravel.com/docs
- **Laravel Security:** https://laravel.com/docs/security
- **PostgreSQL Schemas:** https://www.postgresql.org/docs/current/ddl-schemas.html
- **JWT Best Practices:** https://tools.ietf.org/html/rfc7519

---

**Created for:** School Administration Platform (Multi-Tenant Laravel)
**Components:** Multi-tenancy + OAuth + Service Layer + Repository Pattern
**Standards:** PSR-12 + OWASP Top 10 2021
**Version:** 1.0.0 (COMPLETE)
**Date:** 2024-05-21