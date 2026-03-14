# Stalwart Adaptive Rate Limiter

Event-driven Symfony service that dynamically adjusts Stalwart outbound rate limits based on provider rejection feedback.

## Stack

- **PHP 8.4** on FrankenPHP (Caddy)
- **Symfony 7.4** with API Platform 4
- **Doctrine ORM 3** / PostgreSQL
- **Messenger** for async event processing
- **OpenTelemetry** for observability (SigNoz)
- **PHPUnit 12** / PHPStan level 10 / PHP-CS-Fixer (@PER-CS + @Symfony)
- **GrumPHP** pre-commit hooks (phpstan + php-cs-fixer)
- Docker Compose for local dev

## PHP Standards

- **Modern PHP 8.4**: readonly classes, enums, named arguments, match expressions, typed properties, constructor promotion, first-class callables, fibers where appropriate.
- **PSR compliance**: PSR-1, PSR-4 (autoloading), PSR-12 (code style via @PER-CS + @Symfony rules).
- **Strict types**: every PHP file must declare `declare(strict_types=1);`.
- **No magic**: avoid `__get`/`__set`, prefer explicit typed accessors.
- **Final by default**: mark classes `final` unless designed for extension.
- **Value objects**: use readonly classes for DTOs and value objects.

## Service Design (IMPORTANT)

**Always program to interfaces.** Every service that talks to an external system (APIs, mail servers, etc.) must follow this pattern:

1. **Interface** in `src/Contract/` — defines the contract (e.g., `StalwartApiClientInterface`).
2. **Real implementation** in `src/Service/` — the production class that makes actual HTTP calls, marked `final readonly`.
3. **In-memory fake** in `tests/Fake/` — a test double that stores state in arrays, used for integration tests (e.g., `InMemoryStalwartApiClient`). This is NOT a mock — it's a working implementation that behaves like the real thing but without external dependencies.

Wire the interface to the real implementation in `config/services.yaml`. In test environment, override with the fake in `config/services_test.yaml` (or via `when@test` in services.yaml).

This enables **end-to-end integration tests** where you can simulate external interactions (e.g., "Stalwart sends webhook → service processes it → verify what was written to the Stalwart API") without mocking.

## API Documentation (IMPORTANT)

**Every endpoint MUST be documented in the OpenAPI spec and visible in Swagger UI at `/api/docs`.**

- **API Platform resources** (`#[ApiResource]`, `#[Get]`, `#[Post]`, etc.) for entity/DTO-based endpoints — these are automatically documented.
- **Plain Symfony controllers** (using `#[Route]`) that are NOT API Platform resources must be documented via an **OpenAPI decorator** — a service implementing `OpenApiFactoryInterface` decorated with `#[AsDecorator(decorates: 'api_platform.openapi.factory')]`. See `src/OpenApi/HealthCheckOpenApiDecorator.php` for the pattern.
- **Do NOT use `OpenApi\Attributes` (`zircote/swagger-php`)** — this package is not installed. Use API Platform's own `ApiPlatform\OpenApi\Model\*` classes.
- No undocumented endpoints — if it exists, it must be in Swagger UI.

## Testing (IMPORTANT)

- **100% code coverage** is required on all new code. Every new class and method must have corresponding tests.
- **Unit tests** for pure logic — use `PHPUnit\Framework\TestCase` with mocks/stubs.
- **Integration tests** for end-to-end flows — use `WebTestCase` with in-memory fakes from `tests/Fake/` swapped in via test service config. These test real behavior without external dependencies.
- **Functional tests** for controllers/endpoints use `Symfony\Bundle\FrameworkBundle\Test\WebTestCase`.
- **OpenAPI decorators** must also be tested — verify they add the expected paths to the OpenAPI spec.
- Tests run inside Docker: `docker exec app vendor/bin/phpunit --coverage-text`.
- The test environment uses `APP_ENV=test` — set in `.env.test` and synced in `tests/bootstrap.php`.
- **Do NOT set `APP_ENV` as a baked `ENV` in the Dockerfile dev stage** — it conflicts with PHPUnit's test env override. Let `compose.yaml` and `.env` handle it. The Dockerfile `ENV APP_ENV=...` is only for `prod` and `test` stages.
- The `tests/bootstrap.php` syncs `$_SERVER['APP_ENV']` to `$_ENV['APP_ENV']` before `Dotenv::bootEnv()` — this is required because `KernelTestCase::createKernel()` reads `$_ENV` before `$_SERVER`.

## Interface / Fake Pattern

- External service integrations are defined as **interfaces** in `src/Contract/`.
- Production implementations live in `src/Service/` as `final readonly` classes.
- In-memory **fakes** live in `tests/Fake/` and implement the same interface. They are `final` (not readonly, since they hold mutable state).
- The test environment swaps the real implementation for the fake via `when@test` in `config/services.yaml`.
- This allows integration tests to run without real HTTP calls while still exercising the full service container.

## Code Quality Gates

All code must pass before commit (enforced by GrumPHP):

```bash
# Static analysis (level 10 — strictest)
docker exec app vendor/bin/phpstan analyse

# Code style (auto-fix)
docker exec app vendor/bin/php-cs-fixer fix

# Tests with coverage (target: 100%)
docker exec app vendor/bin/phpunit --coverage-text
```

- **PHPStan level 10** — no baseline exceptions for new code.
- Run all three checks before considering any task done.

## Git Workflow (IMPORTANT)

- **Branch per ticket**: `feat/BOR-{number}-short-description` or `fix/BOR-{number}-short-description`
- Create the branch from `main` before starting work.
- Commit messages: `feat(BOR-{number}): description` or `fix(BOR-{number}): description`.
- Keep commits atomic — one logical change per commit.
- **NEVER add `Co-Authored-By` lines to commit messages.** This applies to ALL commits and PR descriptions — no exceptions.
- **NEVER add `Generated with` attribution lines** to PR descriptions or commit messages.

## Project Structure

```
src/
├── Contract/       # Interfaces for external services
├── Controller/     # Symfony controllers (plain endpoints with #[Route])
├── Entity/         # Doctrine entities
├── Messenger/      # Message handlers & messages
├── OpenApi/        # OpenAPI decorators for documenting non-ApiResource endpoints
├── Repository/     # Doctrine repositories
├── Service/        # Real implementations of Contract interfaces
└── Kernel.php
tests/
├── Controller/     # Functional tests (WebTestCase)
├── Fake/           # In-memory fake implementations for integration tests
├── Service/        # Unit tests for services
└── ...             # Mirrors src/ structure
migrations/         # Doctrine migrations
config/             # Symfony configuration
docker/
├── Caddyfile       # Production Caddyfile (worker mode, no file watching)
├── Caddyfile.dev   # Dev Caddyfile (worker mode + file watching for auto-reload)
└── php/            # PHP ini overrides
```

## Development Environment

- **FrankenPHP worker mode** with file watching in dev — code changes in `src/`, `config/`, and `templates/` auto-reload workers (no manual restart needed).
- Dev Caddyfile is mounted via `compose.yaml` volume: `./docker/Caddyfile.dev:/etc/frankenphp/Caddyfile`.
- Assets are installed on container startup via the compose `command`.
- **Never bake `ENV APP_ENV=dev` into the Dockerfile dev stage** — use `compose.yaml` environment instead.

## Agent Workflow

Every Linear ticket is implemented using three coordinated agents working in sequence:

### 1. Architect Agent (Plan)
- Analyzes the ticket requirements.
- Reads relevant existing code to understand current patterns and conventions.
- Produces a step-by-step implementation plan: which files to create/modify, class designs, interface contracts, and Doctrine mappings.
- Identifies edge cases and integration points.
- **Does NOT write code** — only plans.

### 2. Developer Agent (Code)
- Follows the architect's plan exactly.
- Implements production code in `src/` and any migrations.
- Follows all PHP standards and project conventions above.
- Creates feature branch, makes atomic commits.

### 3. Tester Agent (Test)
- Writes comprehensive tests in `tests/` for everything the developer built.
- Unit tests for individual classes, integration tests for API endpoints and message handlers.
- Tests for OpenAPI decorators verifying endpoints appear in the spec.
- Achieves 100% coverage on all new code.
- Runs the full quality gate (phpstan + php-cs-fixer + phpunit).
- If any check fails, fixes the issues (in both test and production code if needed).

### Coordination Rules
- **Architect -> USER REVIEW -> Developer -> Tester.**
- After the Architect produces the plan, **present it to the user for approval** before launching the Developer. The user may adjust the design, reject parts, or add requirements.
- Do NOT start coding until the user explicitly approves the plan.
- Each agent has access to the output/work of the previous agent.
- The tester agent is the final gatekeeper — nothing is done until all checks pass.
- After all checks pass: **push the branch and create a PR** using `gh pr create`.

## Linear Integration

- Project: **Stalwart Adaptive Rate Limiter**
- Team key: **BOR**
- Fetch tickets with `list_issues` filtered by project.
- Do NOT update Linear ticket status manually — it syncs automatically via PR open/close/merge.

## Commands Reference

```bash
# Local dev
docker compose up -d

# Run inside container
docker exec app vendor/bin/phpstan analyse
docker exec app vendor/bin/php-cs-fixer fix
docker exec app vendor/bin/phpunit
docker exec app vendor/bin/phpunit --coverage-text
docker exec app bin/console cache:clear
docker exec app bin/console doctrine:migrations:migrate

# Composer
docker exec app composer require <package>
docker exec app composer require --dev <package>
```
