# CCA – Customer Contact API

A prototype REST API for managing customer contacts, built with Symfony 8.1, API Platform 5, Doctrine ORM 3 and MySQL 8, tested with PHPUnit 12. Token authentication, voters, soft delete and an asynchronous Messenger flow with RabbitMQ are part of it.

**History:** The project started in April 2024 as a Symfony 7.0 / API Platform 3 / PHP 8.3 implementation. In 2026 it was upgraded step by step to the current stack, together with Claude Code (Sonnet). The upgrade added Docker, a CI pipeline, voters and an asynchronous Messenger flow with RabbitMQ.

**Why it exists:** It is a practice project for upgrading a real API without losing test coverage, and for working with an AI assistant in small, reviewable steps (see `docs/ai-workflow.md`). Along the way it became a small showcase for:

- **Voters:** one rule for who may delete a customer
- **DTO resources:** the API is separate from the Doctrine entities
- **Messenger with RabbitMQ and a worker:** `POST /api/customers` sends a `CustomerCreated` message that a separate worker handles
- Soft delete, hashed API tokens, tests and CI

**Scope:** This is a backend API only. There is no frontend, no login and no registration. Authentication is a token in the `x-api-key` header: an admin creates a token (`app:api-token:create`), and the two demo tokens make it easy to try locally (see [First request](#first-request)). Tokens are stored as hashes and do not expire.

## Quick start (Docker)

PHP 8.4 with FrankenPHP, MySQL 8.0.36 and RabbitMQ 4. Project name `cca`.

```bash
docker compose up -d --build
```

The one-off service `init` installs the Composer dependencies, migrates the dev database and loads the demo data (users, countries, cities, customers). It runs on **every** `docker compose up`, and loading the demo data **replaces all rows** of the dev database. The first start takes a while because of `composer install`.

| Service | Address |
| --- | --- |
| API and API docs | http://localhost:8080/api (HTTPS on 8443) |
| RabbitMQ management UI | http://localhost:15672 (user and password `guest`) |
| MySQL from the host | `127.0.0.1:3307` (user, password and database `cca`) |

The `worker` service runs `php bin/console messenger:consume async` and handles the messages (see [Messenger](#messenger)).

### First request

From the token to the Messenger result, step by step:

1. Open http://localhost:8080/api (Swagger UI).
2. Click **Authorize**, enter one of the two demo tokens and confirm. Use the admin token first; the user token shows what the voter does in step 7.

   | Demo user | Role | Token |
   | --- | --- | --- |
   | `admin@example.com` | `ROLE_ADMIN` | `9a1f0c5e7b3d4826a0e1c7f35d8b2a64e91c0d7f3b5a8e2146c9d0f7a3b5e8c1d2f4a6b8c0e2d4f6a8b0c2e4d6f8a0b2c4e6d8f0a2b4c6e8d0f2a4b6c8e0d2f4` |
   | `test@example.com` | `ROLE_USER` | `12f3600378cbb476613d4cec52b56730bc0bcf77d5925538eeffd8dde2d3c4d71e8bb741f0b8cd6f090c328a7535340bf0e7121786566f1f60501d29` |

   ![Swagger UI: the Authorize dialog with the x-api-key field](docs/img/authorize.png)

3. Open `GET /api/customers`, click **Try it out** and **Execute**. The demo customers are listed. Without a token the API answers `401`.
4. Open `POST /api/customers`, click **Try it out**, use this body and click **Execute** (`201`):

   ```json
   {"name": "Jane Doe", "email": "jane@example.com", "phone": "0301234567"}
   ```

   Swagger pre-fills the body with an example; replace all of it with the line above.
   ![Swagger UI: POST /api/customers answers 201](docs/img/post-customer.png)

5. The API has saved the customer and put a `CustomerCreated` message into RabbitMQ. The `worker` takes it and the handler writes a log line:

   ```bash
   docker compose logs -f worker
   ```

   ![Worker log: CustomerCreated received, handled and acknowledged](docs/img/worker-log.png)

6. Optional: the queue `messages` in the RabbitMQ UI (http://localhost:15672, `guest` / `guest`) shows the messages. The worker takes them within milliseconds, so the queue is mostly empty. To see a message in the queue, stop the worker first (`docker compose stop worker`), send the `POST`, and look at the queue; `docker compose start worker` processes it.
7. Voter: click **Authorize**, **Logout**, and enter the user token. `DELETE /api/customers/{id}` now answers `403`. With the admin token it answers `204` (a soft delete, see [Behaviour of the endpoints](#behaviour-of-the-endpoints)).

The same with `curl`:

```bash
curl -H "x-api-key: 9a1f0c5e7b3d4826a0e1c7f35d8b2a64e91c0d7f3b5a8e2146c9d0f7a3b5e8c1d2f4a6b8c0e2d4f6a8b0c2e4d6f8a0b2c4e6d8f0a2b4c6e8d0f2a4b6c8e0d2f4" -H "Accept: application/ld+json" http://localhost:8080/api/customers
```

The demo tokens come from `src/DataFixtures/UserFixtures.php` and are public. Use them only on a local machine. If a token stops working (for example after it was deleted through `DELETE /api/api_tokens/{id}`), run `docker compose up -d`: the demo data and both tokens are loaded again. Further tokens are created with `docker compose exec web php bin/console app:api-token:create <email>`; the token is printed once, only its SHA-256 hash is stored. The password column is not used (authentication is token-only); `!` is not a valid hash, so no password can match it.

### Docker notes

- The container runs as user ID 1000, so `vendor/` and `var/` belong to the host user. For another host user ID: `UID=$(id -u) GID=$(id -g) docker compose up -d --build`.
- `.env` and `.env.test` are committed with safe defaults only. Put secrets and local overrides in `.env.local`.
- The MySQL init script (`docker/mysql/init.sql`) runs only on the first start. For an existing volume use `docker compose down -v`.

## Authentication and authorisation

Every request needs the header `x-api-key: <token>`. The `ApiTokenAuthenticator` (`src/Security`) looks up the user of the token. Without a valid token the API answers `401`.

Headers for a REST client:

| Header | Value |
| --- | --- |
| `x-api-key` | the token |
| `Accept` | `application/ld+json` |
| `Content-Type` | `application/ld+json` (for `PATCH`: `application/merge-patch+json`) |

| Resource | Path | Operations | Role |
| --- | --- | --- | --- |
| Customer | `/api/customers` | list, get, post, put, patch, delete | `ROLE_USER`; `DELETE` only `ROLE_ADMIN` (voter `CUSTOMER_DELETE`) |
| Address | `/api/addresses` | list, get, post, put, patch, delete | `ROLE_USER` |
| City | `/api/cities` | list, get, post, put, patch (never deleted; the country is always Germany) | `ROLE_USER` |
| Country | `/api/countries` | list, get (read only) | `ROLE_USER` |
| User | `/api/users` | list, get, post, patch, delete | `ROLE_ADMIN` |
| ApiToken | `/api/api_tokens` | list, get, delete (never shows a token or hash) | `ROLE_ADMIN` |

Every user also has `ROLE_USER`. The voter (`src/Security/Voter/CustomerVoter.php`) keeps the delete rule in one place instead of in the entity.

## Behaviour of the endpoints

- **Customer and Address are DTO resources** (`src/ApiResource`); the Doctrine entities only store data. `symfony/object-mapper` maps between them, and API Platform's Doctrine providers still read the entities, so the filters and the soft-delete query extension apply as before.
- **Customers** can be filtered and ordered: `?name=`, `?email=` (partial, case-insensitive), `?address.city.name=` (exact) and `?order[name]=asc|desc`.
- **Addresses can be sent with `POST /api/customers`** in the `address` list (`street`, `streetnumber`, `zipcode` and `city` as IRI, for example `{"name": "Jane Doe", "email": "jane@example.com", "phone": "0301234567", "address": [{"street": "Hauptstr", "streetnumber": "1", "zipcode": "10115", "city": "/api/cities/13"}]}`). The customer and its addresses are saved together; an invalid address answers `422` and nothing is saved. `PUT` and `PATCH /api/customers/{id}` change only the customer fields and never touch its addresses. Change or add addresses of an existing customer through `/api/addresses` (`"customer": "/api/customers/41"`). Relations are always IRIs (the whole path), not plain ids; `"customer": "41"` answers `400`.
- **Deleting a customer is a soft delete.** `DELETE` sets `deletedAt`; the row and its addresses stay in the database for traceability. Deleted customers and their addresses are not listed and answer `404`. The email of a deleted customer stays taken.
- **The API handles only Germany.** Countries are read only (the migration adds "Germany"), and every city gets Germany automatically: a `country` in the body of `POST`, `PUT` or `PATCH /api/cities` is ignored. Cities and countries are never deleted (`DELETE` answers `405`).
- Invalid input answers `422` (validation) with a Hydra error body.
- The unique `email` of a customer is checked by the validator and by a database index.

## Messenger

`POST /api/customers` saves the customer and then sends a `CustomerCreated` message to RabbitMQ. The `worker` service takes it from the queue and calls `CustomerCreatedHandler`, which writes a log line. The request does not wait for that; the handler is a small example for the flow.

```mermaid
flowchart TB
    subgraph web["web container (request)"]
        direction TB
        A["Client<br/>POST /api/customers"] --> B["Auth and roles<br/>x-api-key"]
        B --> C["Deserialise and validate"]
        C --> D["CustomerCreatedProcessor<br/>persist and flush"]
        D --> E["dispatch<br/>CustomerCreated(id)"]
        E --> F["Response 201"]
    end
    subgraph worker["worker container (separate)"]
        direction TB
        Q["RabbitMQ queue"] --> W["messenger:consume async"]
        W --> H["CustomerCreatedHandler<br/>logs, then ack"]
    end
    E -- "async" --> Q
```

- The message is routed with `#[AsMessage('async')]` on the class; the transport `async` is defined in `config/packages/messenger.yaml` and uses `MESSENGER_TRANSPORT_DSN`.
- `src/State/CustomerCreatedProcessor.php` wraps API Platform's persist processor and dispatches the message only after the customer is saved.
- In the `test` environment the transport is `in-memory://`, so tests and CI need no RabbitMQ (the `amqp` PHP extension is still required by `symfony/amqp-messenger`).

## Tests and quality

```bash
docker compose exec web php bin/console --env=test doctrine:database:create       # once
docker compose exec web php bin/console --env=test doctrine:migrations:migrate -n # test database
docker compose exec web php bin/phpunit                                  # all tests
docker compose exec web php bin/phpunit tests/CityTest.php               # one file
docker compose exec web vendor/bin/phpstan analyse --memory-limit=1G     # level 6
docker compose exec web vendor/bin/php-cs-fixer fix --dry-run --diff     # @Symfony code style
docker compose exec web php bin/console doctrine:schema:validate         # entities and migrations match
```

- The functional tests use a real MySQL test database (`cca_test`) and load Liip fixtures. The tests run against the **migrated** schema, so run `--env=test doctrine:migrations:migrate -n` after every new migration.
- A deprecation triggered by this project fails the test run.
- The GitHub Actions workflow (`.github/workflows/ci.yml`) runs PHP CS Fixer, PHPStan, the migrations, `schema:validate` and PHPUnit.

## Known limitations

- **No login and no registration.** An admin creates users, and tokens are created on the server with `app:api-token:create`. The two demo tokens are public (local use only).
- **Tokens do not expire.** They are stored as SHA-256 hashes and shown once; an admin revokes a token with `DELETE /api/api_tokens/{id}`.
- **Addresses inside `PUT` or `PATCH /api/customers` are ignored** (no error, and they are not part of the request schema in Swagger). This is a design decision for now: a rule is missing for what a list should mean (replace or add), and a replace deletes the existing addresses.
- **No anonymisation yet.** The email of a soft-deleted customer cannot be used again, and its addresses stay in the database (hidden from the API).
- **Messenger has no failure transport.** A message that keeps failing is dropped after the retries.
- **Request logging (dev only).** `LogRequestAndResponseEventListener` writes all request headers (including `x-api-key`) and the full response body to `var/log/request/` (kept for 90 days). Do not use it with real data.

## License

The code of this repository is licensed under the [MIT License](LICENSE).

The third-party packages installed with Composer (not part of this repository) keep their own licenses. At the time of writing these are MIT (for example Symfony, Copyright (c) Fabien Potencier) and BSD-3-Clause.
