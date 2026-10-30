# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

"CCA – Customer Contact API": a prototype REST API built with Symfony 7.0, API Platform 3.2 (`api-platform/core ^3.2`), Doctrine ORM 3, MySQL 8 and PHPUnit 9.6. There is no frontend and no login; authentication is token-only. The branch `upgrade/docker-symfony-8` is meant for a Docker setup and an upgrade to Symfony 8; the Docker setup now exists.

## Goals and conventions

- **Goal:** upgrade to Symfony 8.1 and API Platform 5, then add Messenger with RabbitMQ, voters and a CI pipeline.
- **Upgrade path:** first API Platform 4.4 (fix all deprecations, e.g. `#[ApiFilter]` and `SearchFilter`), then API Platform 5. Symfony 7.0 → 7.4 → 8.1.
- **Authentication:** custom `ApiTokenAuthenticator` with the `x-api-key` request header (the README wrongly says `api`; fix the README later).
- **Schema changes** only via Doctrine migrations.
- **Configuration:** prefer PHP attributes over YAML.

## Setup and commands

The app runs in Docker (`Dockerfile`, `compose.yaml`: FrankenPHP + PHP 8.4, MySQL 8.0.36, project name `cca`); the host has PHP 7.4, so run all commands with `docker compose exec web ...`. Web: http://localhost:8080, MySQL on the host: `127.0.0.1:3307`. `.env` and `.env.test` are committed with safe defaults only (secrets go to `.env.local`, which is ignored). In test, Doctrine appends `_test` to the DB name (`config/packages/doctrine.yaml`); the `cca` DB user may use all `cca%` databases (`docker/mysql/init.sql`).

```bash
docker compose up -d --build                                # API docs: http://localhost:8080/api
docker compose exec web composer install
docker compose exec web php bin/console doctrine:migrations:migrate -n           # dev DB
docker compose exec web php bin/console --env=test doctrine:database:create
docker compose exec web php bin/console --env=test doctrine:migrations:migrate -n  # test DB

docker compose exec web php bin/phpunit                     # all tests
docker compose exec web php bin/phpunit tests/CityTest.php  # one file
docker compose exec web php bin/phpunit --filter testCreateCitySuccess  # one test
docker compose exec web vendor/bin/phpstan analyse          # level 6, config phpstan.dist.neon
```

`phpunit.xml.dist` sets `SYMFONY_DEPRECATIONS_HELPER=disabled`, so deprecations are hidden in test runs. No PHP-CS-Fixer is installed.

## Architecture

- **Resources = Doctrine entities.** All API resources are defined with `#[ApiResource]` directly on `src/Entity/*` (no DTOs, no custom state providers/processors, no controllers). `src/ApiResource/` and `src/Controller/` are empty.
- **Domain model:** `Country` 1–n `City` 1–n `Address` n–1 `Customer`; `User` 1–n `ApiToken`. Deletes cascade through `orphanRemoval`/`cascade: ['remove']` (deleting a Country removes its Cities and their Addresses). `Customer` persists Addresses via `cascade: ['persist']` and accepts nested address writes (denormalization groups `customer:write` + `address:write`). Serialization uses `<entity>:read` / `<entity>:write` groups.
- **Access control** lives in `#[ApiResource(security: ...)]`: `ROLE_USER` for customers, addresses, cities, countries; `ROLE_ADMIN` for `users` and `api_tokens`. `access_control` in `security.yaml` is empty.
- **Authentication:** `src/Security/ApiTokenAuthenticator.php` is the `custom_authenticator` of the `main` firewall. It reads the `x-api-key` header (the README wrongly says `api`), looks up the user via `UserRepository::findByApiToken()` (→ `ApiTokenRepository`), and returns a `SelfValidatingPassport`. Failure returns a JSON 401. Tokens are created manually: `php generate-token.php` prints a random token that is inserted into the `api_token` table.
- **API Platform config** (`config/packages/api_platform.yaml`): only JSON-LD (`application/ld+json`); PATCH needs `application/merge-patch+json`. `standard_put: false`, `rfc_7807_compliant_errors: true`, `event_listeners_backward_compatibility_layer: false`, `keep_legacy_inflector: false`.
- **Request logging:** `src/EventListener/LogRequestAndResponseEventListener.php` listens to `kernel.terminate` and logs to the `request_log` Monolog channel (tagged in `config/services.yaml`). The handler exists only in `when@dev` and writes to `var/log/request/`.
- **Timestamps:** `Customer` sets `createdAt`/`updatedAt` with `#[ORM\HasLifecycleCallbacks]` (`stof/doctrine-extensions-bundle` is required but not registered in `bundles.php`).

## Tests

- Functional API tests in `tests/` (`CityTest`, `CountryTest`) extend API Platform's `ApiTestCase` and need a real MySQL test database.
- Each test loads fixtures with Liip `DatabaseToolCollection` (`UserFixtures`, `CountryFixtures`, `CityFixtures` in `src/DataFixtures/`); this resets the DB per test. `dama/doctrine-test-bundle` is required but not enabled.
- `UserFixtures` creates `test@example.com` (`ROLE_USER`) with a fixed API token; tests read it back and send it as `x-api-key`.
- Tests assert Hydra keys (`hydra:member`, `hydra:totalItems`, `hydra:Error`) — these change when upgrading API Platform.
