# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Environment

Docker Compose runs three services: `app` (PHP-FPM 8.2), `nginx` (port 8000), and `postgres` (port 5432).

```bash
# Initial setup
cp .env.example .env
docker-compose up -d
docker-compose exec app composer install
docker-compose exec app php artisan migrate

# Common commands
docker-compose exec app php artisan test                         # all tests
docker-compose exec app php artisan test --filter=Unit           # unit only
docker-compose exec app php artisan test --filter=Feature        # feature only
docker-compose exec app php artisan test tests/Unit/Modules/Auth # single file/dir
docker-compose exec app bash                                     # shell
docker-compose logs -f app                                       # logs
```

API is at `http://localhost:8000/api`. Health check: `GET /api/health`.

## Architecture

The project uses **Clean Architecture** with modules under `app/Modules/`. Each module has four layers:

```
app/Modules/{Module}/
├── Domain/          # Pure business logic — no Laravel dependencies
│   ├── Entities/    # Business objects with behavior
│   ├── ValueObjects/# Validated, immutable values (e.g., Email)
│   ├── Repositories/# Interfaces (contracts only)
│   └── Exceptions/  # Domain-specific exceptions
├── Application/     # Use case orchestration — no HTTP concerns
│   ├── UseCases/    # One class per use case with execute() method
│   ├── DTOs/        # Input/Output data transfer objects
│   └── Services/    # Application-level services
├── Infrastructure/  # Concrete implementations (Eloquent, external APIs)
│   └── Repositories/# Implements Domain repository interfaces
└── Presentation/    # (Routes moved to app/Http/ for now)
```

**Presentation layer** lives in `app/Http/`:
- `Controllers/` — thin, delegate to use cases
- `Requests/` — validation only
- `Resources/` — JSON serialization
- `Response/ApiResponse.php` — standardized response helper for all API responses

**Eloquent models** in `app/Models/` are data-only (no business logic). Domain entities in `app/Modules/*/Domain/Entities/` contain the business logic.

### Request flow

```
HTTP → routes/api.php → Controller → FormRequest (validation)
    → UseCase → Domain (entities, value objects) + Repository interface
    → Infrastructure/Repository (Eloquent) → DB
    → Resource → ApiResponse::success() → JSON
```

### Dependency injection

Repository interfaces are bound to Eloquent implementations in `app/Providers/`. Use constructor injection in use cases and services.

## Code style

PSR-12 compliance is required. Every PHP file must start with:

```php
<?php

declare(strict_types=1);
```

All public methods need docblocks. Naming: `PascalCase` classes, `camelCase` methods/variables, `UPPER_SNAKE_CASE` constants, `snake_case` DB columns.

## Multi-tenancy (planned)

The platform is being built for **schema-per-tenant PostgreSQL**: each school gets its own schema (`escuela1`, `escuela2`, etc.) alongside a central `public` schema for tenants/global users. Tenant resolution is via subdomain → `TenantResolver::activateTenant()` sets `search_path`. Always scope queries to `tenant_id` to enforce isolation.

## Security requirements

- Tenant isolation: every query must filter by `tenant_id`
- Passwords: `Hash::make()` with 12+ rounds (bcrypt)
- Tokens expire after 24 hours (`createToken(..., now()->addHours(24))`)
- Rate-limit sensitive endpoints (5 attempts / 15 min for login)
- Never use `unserialize()` on untrusted input; use `json_decode(..., flags: JSON_THROW_ON_ERROR)`
- Log security events with `user_id`, `tenant_id`, `ip`, and `timestamp`
