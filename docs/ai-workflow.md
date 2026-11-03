# Working with an AI assistant

The 2026 upgrade of this project (Symfony 7.0 to 8.1, API Platform 3 to 5, plus Docker, CI, voters and Messenger) was done together with Claude Code (Sonnet). This page describes how the work was organised, so a reviewer can judge the commits.

## Rules

- **Verify, do not assume.** Before a change the assistant reads the code, `composer.json`, the installed versions and the source in `vendor/`, or reproduces the problem. What it could not check is marked as an assumption.
- **Plan first for larger steps.** The assistant proposes a plan; changes start after approval.
- **Small, traceable steps.** One topic per commit.
- **Checks after every change:** PHPUnit, PHPStan and PHP CS Fixer. A deprecation triggered by the project fails the test run (`failOnDeprecation` in `phpunit.xml.dist`), so deprecations are fixed, not ignored.
- **Tests are agreed before they are written:** which kind (unit, kernel, functional), which cases, and why.
- **The assistant never commits or pushes.** `.claude/settings.json` denies `git commit*` and `git push*`. The assistant suggests a commit message; the developer reviews the diff and commits.
- **Commit messages** from the assistant end with a `Co-Authored-By: Claude Sonnet 5.5` line.
- **No secrets in committed files.** Reading `.env.local` is denied in `.claude/settings.json`. Local, private notes stay in `CLAUDE.local.md`, which is git-ignored.

Project facts for the assistant (commands, architecture, decisions) are in `CLAUDE.md`.

## Examples from this project

- **User API hardening.** A throwaway test showed that `GET /api/users` returned the password hash and that `POST`/`PATCH` stored passwords in plain text. The fix added serialization groups and a test.
- **Deleting a country or city.** First a test pinned the old behaviour (deleting a country removed its cities and addresses). Then the cascade was removed on purpose. A test then showed that API Platform answers `500` with the raw SQL message, so a small processor turns the foreign key error into `409`.
- **Soft delete for customers.** Gedmo `SoftDeleteable` was tried first in a throwaway script (it works with ORM 3). The decision for a small own `deletedAt` field and processor was made by the developer, to avoid a package for one feature.
- **Slow tests.** Reading the Liip source showed that every fixture load dropped and rebuilt the schema from the entities. The tests never ran against the migrations, and the suite took about two minutes. With `keep_database_and_schema` and tests that do not depend on fixed ids, it takes about ten seconds and `schema:validate` runs in CI.

## What went wrong on the way

The assistant's first attempt was wrong several times, and the checks caught it: a new config file was not seen because the test cache was old, a test expected an `id` field that the API does not return, and a first measurement of the test speed did not use the new setting. In each case the result was checked again before anything was kept.

## Decisions stay with the developer

Examples: no cascade delete, no hard delete of customers, tokens in plain text as a known limitation, a README that lists the limitations. The assistant names doubtful decisions briefly and precisely; the developer decides.
