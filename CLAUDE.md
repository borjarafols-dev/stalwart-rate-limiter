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

- **100% code coverage** is required. Every new class and method must have corresponding tests.
- **PHPStan level 10** — no baseline exceptions for new code.
- Run all three checks before considering any task done.

## Git Workflow

- **Branch per ticket**: `feat/BOR-{number}-short-description` or `fix/BOR-{number}-short-description`
- Create the branch from `main` before starting work.
- Commit messages: `feat(BOR-{number}): description` or `fix(BOR-{number}): description`.
- Keep commits atomic — one logical change per commit.
- **Never** add `Co-Authored-By` lines to commit messages.

## Project Structure

```
src/
├── Controller/     # API endpoints
├── Entity/         # Doctrine entities
├── Messenger/      # Message handlers & messages
├── Repository/     # Doctrine repositories
└── Kernel.php
tests/              # PHPUnit tests (mirrors src/ structure)
migrations/         # Doctrine migrations
config/             # Symfony configuration
docker/             # Docker-specific configs
```

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
- Achieves 100% coverage on all new code.
- Runs the full quality gate (phpstan + php-cs-fixer + phpunit).
- If any check fails, fixes the issues (in both test and production code if needed).

### Coordination Rules
- Agents work sequentially: Architect -> Developer -> Tester.
- Each agent has access to the output/work of the previous agent.
- The tester agent is the final gatekeeper — nothing is done until all checks pass.

## Linear Integration

- Project: **Stalwart Adaptive Rate Limiter**
- Team key: **BOR**
- Fetch tickets with `list_issues` filtered by project.
- Update ticket status as work progresses.

## Commands Reference

```bash
# Local dev
docker compose up -d

# Run inside container
docker exec app vendor/bin/phpstan analyse
docker exec app vendor/bin/php-cs-fixer fix
docker exec app vendor/bin/phpunit
docker exec app vendor/bin/phpunit --coverage-text
docker exec app bin/console doctrine:migrations:migrate

# Composer
docker exec app composer require <package>
docker exec app composer require --dev <package>
```
