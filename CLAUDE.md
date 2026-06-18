# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Docs

Before any task, identify which docs are relevant and read them.

| File | When to read | Contains |
|---|---|---|
| `docs/architecture.md` | When creating or modifying modules, UseCases, repositories | Layer patterns, UseCase vs Service, contracts, data flow, how to add a module |
| `docs/database.md` | When creating migrations, factories, seeders or touching multi-tenancy | Table schema, relationships, tenancy strategy, DB conventions |
| `docs/api.md` | When creating endpoints, controllers, requests or responses | ApiResponse, HTTP codes, route conventions, authentication, Postman collection rule |
| `docs/testing.md` | When writing any test | Pest conventions, what to test per layer, factories in tests, tenant context |
| `docs/global-rules.md` | Always | Cross-cutting code rules that apply to every task |
| `docs/audit.md` | When emitting audit logs or adding audit events | Audit event catalog, `{model}.{verb}` convention, what is and isn't audited, how to add an event |
| `docs/oauth.md` | When working on OAuth, Socialite, or social login | Full OAuth flow diagram, lookup logic, gateway pattern, Socialite integration guide |
| `docs/post-mvp.md` | When making architectural trade-off decisions | Known limitations accepted for MVP with recommended solutions for the mature system |
| `docs/roles-and-permissions.md` | When working on roles, permissions, assignments, or anything related to authorization | Full roles and permissions model: role types, scopes, category bounds, denials, school context, worked examples, permission slug convention |

After completing any task, update the docs that are affected by the changes made. If a new pattern was introduced, a rule changed, or a structural decision was taken, reflect it in the corresponding doc. Do not document task-specific details — only rules, patterns and decisions that apply going forward.

All files in this repository (docs, comments, variable names, commit messages) must be written in **English**. The only exception is user-facing content (e.g. error messages returned to end users) which may be in Spanish.

Permission slugs, permission names, permission category names, and role names in seeders must always be in **English** — they are internal identifiers, not user-facing strings.

## Seeder structure

`RolesAndPermissionsSeeder` is the coordinator — it delegates to focused seeders in order:

- `StaffSeeder` — `staff` scope (Softlinkia internal roles)
- `TenantSchoolSeeder` — `tenant` and `school` scope

When adding a new scope or splitting an existing one (e.g. `TenantSeeder` and `SchoolSeeder`), create the new class and register it in `RolesAndPermissionsSeeder::run()`. Each seeder follows the sequence: categories → permissions → roles → role_permissions.

## Agents

Two agents are available in `.claude/agents/`:

| Agent | Role |
|---|---|
| `laravel-backend-engineer` | Implementation: modules, UseCases, repositories, controllers, migrations |
| `pest-testing` | Tests: Unit (Domain, UseCases) and Feature (repositories, HTTP) |

When a task involves implementing a module or feature, launch both agents in parallel — `laravel-backend-engineer` for the implementation and `pest-testing` for the tests. Only run them sequentially if the tests depend on implementation decisions not yet made.

## Environment

Docker Compose runs three services: `app` (PHP-FPM 8.2), `nginx` (port 8000), `postgres` (port 5432). Every `composer` or `php artisan` command must run inside the container.

```bash
# Start environment
docker-compose up -d

# Recommended aliases (~/.zshrc)
alias art='docker-compose exec app php artisan'
alias comp='docker-compose exec app composer'
```

## Commands

```bash
# Initial setup
cp .env.example .env
docker-compose up -d
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate

# Tests
docker-compose exec app php artisan test                          # all
docker-compose exec app php artisan test --filter=Unit            # unit
docker-compose exec app php artisan test --filter=Feature         # feature
docker-compose exec app php artisan test tests/Unit/Modules/Auth  # single directory

# Code quality
docker-compose exec app composer format        # pint (fix)
docker-compose exec app composer format:test   # pint (validate only)
docker-compose exec app composer analyse       # phpstan / Larastan
docker-compose exec app composer quality       # both in order

# Utilities
docker-compose exec app bash                   # shell
docker-compose logs -f app                     # logs
```

API available at `http://localhost:8000/api`. Health check: `GET /api/health`.

## Prerequisites (Composer)

Larastan bootstraps the full app via `bootstrap/app.php`. These packages must be present in `composer.json`:

| Package | Role |
|---|---|
| `laravel/framework` | Required by Larastan for bootstrap |
| `laravel/sanctum` | Token-based API authentication |
| `laravel/pint` (dev) | PSR-12 formatting |
| `larastan/larastan` + `phpstan/phpstan` (dev) | Static analysis |
| `pestphp/pest` + `pestphp/pest-plugin-laravel` (dev) | Testing |

Composer scripts call binaries explicitly: `@php vendor/bin/pint`, `@php vendor/bin/phpstan`.

## Troubleshooting

| Error | Fix |
|---|---|
| `sh: pint: command not found` | Run `composer install`; scripts use `vendor/bin/pint` |
| `Class "Illuminate\Foundation\Application" not found` | Add `laravel/framework` to `require` and run `composer update` |
| `bootstrap/cache directory must be present and writable` | Ensure `bootstrap/cache/` exists |
| `APP_KEY is already present in the environment` | Variable is hardcoded in `docker-compose.yml`; use `env_file: .env` instead |
| `Unable to set application key` | Same as above |

## Testing after changes

After completing any task that modifies code, run the tests for the affected classes and any tests that could be impacted:

```bash
# Run tests for a specific module
docker-compose exec app php artisan test tests/Unit/Modules/Auth
docker-compose exec app php artisan test tests/Feature/Modules/Schools

# Run all unit tests (fast — ~10s)
docker-compose exec app php artisan test --filter=Unit
```

Use the narrowest scope that covers the change — a single directory when only one module is touched, broader when the change crosses module boundaries (e.g. a shared model, a Gate rule, a middleware). If tests fail, fix them before reporting the task as done.

## Git hooks (Husky)

`npm install` registers Husky via the `prepare` script. Two hooks run automatically:

- **pre-commit** (`scripts/quality-check.sh`) — runs `composer quality` (Pint + PHPStan)
- **pre-push** (`scripts/unit-tests.sh`) — runs all unit tests

Both scripts detect whether Docker is available and run inside the container if so.

Emergency bypass only: `git commit --no-verify` / `git push --no-verify` (not recommended).
