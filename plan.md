# Tangible Populator — Refactor Plan

Handoff document for a new agent session. Work phases **in order**. Do not skip ahead unless a phase is explicitly marked complete and its acceptance criteria are met.

## Project context

**Repository:** WordPress plugin `tangible-populater` (Composer package `tangible/populater`).

**Purpose:** Seed LMS content (courses, lessons, quizzes, users, etc.) for LearnDash, LifterLMS, and Tangible LMS during development and testing.

**Stack:** PHP 8.1+, `deliciousbrains/wp-background-processing`, PHPUnit + Brain Monkey, Docker Compose.

### Docker services (important)

| Service | Port | Use |
|---------|------|-----|
| `wp` | 8888 | **Development** — manual testing, Playwright, admin UI |
| `wp-test` | 8889 | **PHPUnit** — DB reset before each test run |
| `db` | internal | MariaDB shared by both |

- Admin login (dev): `admin` / `password` (see container `/var/www/html/README.md`).
- Run tests: `composer docker:test` (executes in `wp-test`).
- Plugin path in container: `/var/www/html/src/wp-content/plugins/tangible-populater`.

### Key paths

```
tangible-populater.php          # Bootstrap
src/Plugin.php                  # Hooks, CLI, REST, admin
src/Seeding/                    # AbstractSeeding, SeedingManager, SeedingIdMap, SeedingStatus
src/Seeders/AbstractSeeder.php  # Queue building + default seed* contract
src/lms/{LearnDash,LifterLMS,TangibleLMS}/  # Per-LMS seeder, process, steps
src/REST/SeedController.php     # REST API
src/Admin/SettingsPage.php      # Admin UI
assets/admin.js                 # Polls REST for progress
tests/Unit/                     # Mostly mocked; no real background processing
docs/LMS-EXTENSION.md           # Target architecture for LMS overrides
docs/archive/legacy-populater.md # What legacy did; porting checklist
```

### Related docs

- [docs/LMS-EXTENSION.md](docs/LMS-EXTENSION.md) — How LMS plugins should extend abstracts (posts vs tables vs options).
- [docs/archive/legacy-populater.md](docs/archive/legacy-populater.md) — Removed legacy capabilities to port selectively.

---

## Already completed (do not redo)

### Background seeding fix (pre-plan)

The admin UI “stuck at 0%” bug was fixed:

1. **Per-UUID background actions** truncated batch keys past 64 chars → queue never processed. Fixed by **fixed actions per LMS**: `seed_learndash`, `seed_lifterlms`, `seed_tangible_lms`, with `process_id` on each queue item.
2. **Hooks not registered** on normal requests → `SeedingManager::registerBackgroundProcesses()` called from `Plugin::init()`.
3. **Index vs ID** in queue → `SeedingIdMap` + `course_index` on quiz items.

Files touched: `src/Seeding/AbstractSeeding.php`, `SeedingManager.php`, `SeedingIdMap.php`, LMS `*SeedingProcess.php`, `AbstractSeeder.php`, `AbstractSeedingStep.php`, all step classes, tests.

### Phase 5 — Legacy removal ✅

- Deleted entire `legacy/` (`lgenerators/`, old docs).
- Added [docs/archive/legacy-populater.md](docs/archive/legacy-populater.md).
- Added [docs/LMS-EXTENSION.md](docs/LMS-EXTENSION.md).
- Updated `AbstractSeeder` docblock.
- **73** unit tests pass.

---

## Phase 3 — Plugin registry (single source of truth)

**Goal:** One registry drives plugin detection, seeding manager, and admin/REST metadata. Remove duplicated slug/name/file maps.

### Current duplication

- `src/PluginDetector.php` — `PLUGIN_FILES`, `PLUGIN_NAMES`
- `src/Seeding/SeedingManager.php` — hardcoded seeder/process instances
- Each `*Seeder.php` — `PLUGIN_FILE`, `getName()`, `getSlug()`

### Tasks

1. Create `src/Registry/LmsPluginRegistry.php` (or `src/LMS/LmsRegistry.php`) defining per slug:
   - `slug`, `name`, `plugin_file` (bootstrap file)
   - `seeder_class`, `process_class` (FQCN)
   - optional: `is_available` callback
2. Refactor `PluginDetector` to read from registry (or merge detector into registry).
3. Refactor `SeedingManager` to build seeders/processes from registry (constructor or `registerBackgroundProcesses()`).
4. Slim LMS seeders: `getName()` / `getSlug()` / `isActive()` delegate to registry or a small `LmsPluginDefinition` injected at construct time.
5. Update unit tests: `PluginDetectorTest`, any `SeedingManager` tests, add `LmsPluginRegistryTest`.

### Acceptance criteria

- [x] Adding a fourth LMS requires **one registry entry** + seeder/process classes (no edits to `SeedingManager` switch/match).
- [x] `getSupportedPlugins()` for REST/admin uses registry only.
- [x] All existing unit tests pass.

### Files (expected)

- **New:** `src/Registry/LmsPluginRegistry.php`
- **Edit:** `PluginDetector.php`, `SeedingManager.php`, `Plugin.php`, tests

---

## Phase 1 — LMS deduplication (abstract defaults + overrides)

**Goal:** Shared post-based logic in `AbstractSeeder`; LMS classes only override where storage differs (custom tables, `wp_options`, LearnDash Pro Quiz, Lifter sections, etc.). Reduce 15 near-identical step classes where possible.

**Design rule:** See [docs/LMS-EXTENSION.md](docs/LMS-EXTENSION.md). Do **not** assume all LMS data lives in CPTs.

### Tasks

1. **AbstractSeeder helpers** (protected):
   - `insertPost(array $args): int` — wraps `wp_insert_post`, returns 0 on failure
   - `createWpUser(array $options): int` — wraps `wp_create_user` + default role/meta hooks
   - Optional: `defaultTitle(string $prefix, int $index): string`
2. **Default `seed*()` in abstract** using helpers + hooks:
   - Subclasses declare `getPostType(string $entity): string` and `getMetaFor(string $entity, array $context): array` OR override individual `seed*()` methods entirely.
3. **Collapse step classes** where they only call `$this->seeder->seedX(1, ...)`:
   - Option A: Generic `DelegateSeedingStep` in `src/Steps/` keyed by type
   - Option B: Keep per-LMS steps but inherit `AbstractLmsStep` with shared `run()` → seeder
   - Prefer reducing 5×3 duplicate step files to 1 generic + LMS seeder overrides
4. **LearnDash first overrides** (highest gap vs legacy — see archive doc):
   - `seedCourses()` → set `ld_course_steps` meta (was in legacy `course-generator.php`)
   - `seedQuizzes()` → Pro Quiz / `quiz_pro_id`, update `ld_course_steps` (legacy `quiz-generator.php`)
   - Consider topics/questions as new queue types later
5. **LifterLMS overrides:** sections (`llms_section`) between course and lessons if required for valid structure.
6. **Tangible LMS:** align with its APIs (may differ from CPT-only model).
7. Update / add unit tests per seeder; mock `wp_insert_post` etc.

### Acceptance criteria

- [x] LearnDash, LifterLMS, Tangible seeders use shared helpers for simple CPT creates.
- [ ] LearnDash quizzes/courses link correctly in LD meta/tables (verify on `wp` with 1/1/1/1 seed) — manual check on dev container.
- [x] Step class count materially reduced: 15 per-LMS step files removed; `DelegateSeedingStep` + `LmsSeedingProcess` used instead.
- [x] `docs/LMS-EXTENSION.md` updated with final class names and extension points.
- [x] Unit tests pass; no regression in queue item types.

### Files (expected)

- **Edit:** `AbstractSeeder.php`, `src/lms/*/*Seeder.php`, `*SeedingProcess.php`, `src/Steps/*`, tests under `tests/Unit/Seeders/`

### Porting reference (legacy → LearnDash)

| Legacy behaviour | Target |
|------------------|--------|
| `initialize_course_steps` | `LearnDashSeeder::seedCourses()` after insert |
| `add_quiz_to_step`, `quiz_pro_id` | `LearnDashSeeder::seedQuizzes()` |
| Topics, questions, groups | New queue types + steps (optional stretch) |

---

## Phase 6 + 7 — Queue model & state repository

**Goal:** Replace loose arrays with value objects; centralize option keys and cleanup.

### Phase 6 — Queue model

1. Add `src/Seeding/SeedConfig.php` — validated config (courses, lessons_per_course, quizzes_per_lesson, users, plugin slug).
2. Add `src/Seeding/SeedQueueItem.php` — `type`, `data`, optional `processId` when enqueued.
3. Refactor `AbstractSeeder::buildSeedQueue(SeedConfig $config): list<SeedQueueItem>`.
4. Refactor `AbstractSeeding::start()` / `task()` to use objects (serialize arrays at boundary for WP Background Process).

### Phase 7 — Process repository

1. Add `src/Seeding/ProcessRepository.php` (or `Persistence/`):
   - `getStatus(processId)`, `saveStatus(...)`, `getLogs`, `appendLog`, `getIdMap`, `saveIdMap`, `deleteProcess(processId)`
2. Move option prefixes from `AbstractSeeding`, `Logger`, `SeedingIdMap` into repository constants.
3. On complete/cancel: `deleteProcess()` clears status + id map + logs + (document batch cleanup interaction with background process).
4. Optional: TTL cleanup for orphaned options older than N days.

### Acceptance criteria

- [x] No raw `update_option('tangible_populater_status_' . $id)` scattered outside repository.
- [x] `SeedingStatus` can include `plugin` slug in `toArray()` for REST if needed.
- [x] Unit tests for `SeedConfig`, `SeedQueueItem`, `ProcessRepository`.
- [x] Full unit suite passes.

---

## Phase 2 — Job layer (decouple from WP_Background_Process)

**Goal:** `SeedingJobRunner` interface so dispatch, cancel, and status are testable and swappable.

### Known issues to address

- `cancelProcess()` calls `parent::cancel()` on **entire LMS queue** — cancels other concurrent runs for same slug.
- `STATUS_FAILED` defined but never set when steps log errors.
- Docker: `wp_remote_post` to `http://localhost:8888` from inside container may fail (`response.code: false`); cron/registered hooks may still process.
- Completion tracked in `task()` per item; `complete()` is mostly passthrough.

### Tasks

1. Define `src/Seeding/SeedingJobRunner.php` interface: `start`, `cancel`, `isRunning`, `dispatch`, (optional) `processNext`.
2. Implement `WpBackgroundSeedingRunner` wrapping current `AbstractSeeding` behaviour.
3. Wire `SeedingManager` to runner per LMS slug (from registry).
4. **Per-process cancel:** cancel only batches whose items match `process_id` (may require custom batch metadata or separate action per process — design doc in PR).
5. Set status to `failed` when step errors exceed threshold or on unrecoverable exception; store `error` on status.
6. Optional dev runner: `SynchronousSeedingRunner` for `wp-test` integration tests (no loopback).

### Acceptance criteria

- [x] Interface + WP implementation; manager uses interface.
- [x] Cancelling process A does not clear unrelated process B on same LMS (status-only cancel; no global `parent::cancel()`).
- [x] Failed seed surfaces `status: failed` in REST/CLI/admin poll (error threshold in `AbstractSeeding::task()`).
- [ ] Unit tests for runner with mocked background process (runner covered via `AbstractSeedingTest` / manager).

---

## Phase 4 — Integration tests

**Goal:** Catch real regressions (batch keys, hooks, ID map) that unit mocks miss.

### Tasks

1. Add `tests/Integration/` (or `tests/e2e-php/`) bootstrap using `wp-test` or in-container WP load.
2. Test: start seed via `SeedingManager` with small counts (1/1/1/1), trigger processing:
   - Prefer `SynchronousSeedingRunner` if implemented, else `wp cron event run` / direct `handle()` with registered hooks.
3. Assert: status `completed`, `processed === total`, posts exist, LearnDash meta links (if phase 1 LD work done).
4. Document in `CONTRIBUTING.md` or `docs/testing.md`: `composer docker:test` vs integration command.
5. Add CI step if applicable (same `docker compose exec wp-test`).

### Acceptance criteria

- [x] At least one integration test per active LMS (parameterized `SeedingQueueIntegrationTest`).
- [x] Tests run green via documented command (`composer test:integration`).
- [x] Integration tests do **not** run against `wp` dev DB (use `wp-test` via `composer docker:test:integration`).

---

## Phase 8 + 9 — Admin UX & WP-CLI

### Phase 8 — Admin UI (`assets/admin.js`)

1. Check `response.ok`; handle WordPress REST error shape (`code`, `message`, `data.status`).
2. Show `failed` / `cancelled` states; stop polling on terminal states (partially done).
3. Surface last log error in UI when `status === 'failed'`.
4. Optional: use `@wordpress/api-fetch` for nonce handling.

### Phase 9 — WP-CLI structure

**Current problem:** `wp tangible-populater seed seed learndash` (double `seed`) because `SeedCommand` is registered as subcommand `seed` with methods `seed`, `status`, `logs`, `cancel`.

**Fix options (pick one):**

- A) Register separate commands: `wp tangible-populater run`, `status`, `logs`, `cancel`
- B) Single `SeedCommand` as default with positional subactions via `WP_CLI::add_command( 'tangible-populater', ... )` and doc updates

### Acceptance criteria

- [x] Documented CLI examples match actual commands (`wp tangible-populater seed …`, not double `seed`).
- [x] REST error shows user-visible message in admin.
- [ ] Manual test on `wp:8888` admin page: start → progress → complete.

### Files

- `assets/admin.js`, `src/CLI/SeedCommand.php`, `src/Plugin.php` (registration), inline docs in `SeedCommand.php`

---

## Phase 10 — Dev tooling cleanup

### Tasks

1. **Remove or fix npm/Playwright setup:**
   - Delete `scripts/start-playwright-mcp.sh` if unused (Cursor uses `npx @playwright/mcp@latest` in `.cursor/mcp.json`).
   - Fix or remove `package.json` script `mcp:playwright` pointing at missing `.cursor/playwright-mcp.json`.
   - Decide: keep `package.json` only for `playwright install chromium` or remove Node entirely from plugin repo.
2. **`.gitignore`** — ensure coverage includes: `.phpunit.cache/`, `.playwright-mcp/`, `test-results/`, `.cursor/`, `node_modules/`. Do **not** ignore `package-lock.json` if npm stays.
3. Unstage/commit hygiene: no MCP snapshot YAML in commits.
4. Optional: add root `README.md` with quickstart (docker up, composer install, test, admin URL).

### Acceptance criteria

- [x] No broken npm scripts in repo (`mcp:playwright` removed; `playwright:install` only).
- [x] `.gitignore` matches actual dev artifacts.
- [x] README or docs point new devs to `wp` vs `wp-test`.

---

## Suggested session breakdown

| Session | Phases | Focus |
|---------|--------|--------|
| 1 | 3 | Registry only |
| 2 | 1a | AbstractSeeder helpers + defaults |
| 3 | 1b | LearnDash overrides + step dedup |
| 4 | 1c | Lifter + Tangible overrides |
| 5 | 6 + 7 | Value objects + ProcessRepository |
| 6 | 2 | Job runner + cancel/fail fixes |
| 7 | 4 | Integration tests |
| 8 | 8 + 9 + 10 | UX, CLI, tooling |

---

## Commands cheat sheet

```bash
# Dev environment
docker compose up -d
# http://localhost:8888/wp-admin — Tangible Populator menu

# Unit tests (wp-test)
composer docker:test

# WP-CLI on dev
docker compose exec wp bash -c 'cd /var/www/html/src && wp --allow-root tangible-populater seed learndash --courses=1 --lessons=1 --quizzes=1 --users=1 --wait'

# Active plugins on dev (typical)
docker compose exec wp bash -c 'cd /var/www/html/src && wp --allow-root plugin list --status=active'
```

---

## Out of scope (unless product asks)

- Full port of legacy topics/questions/groups/assignments
- Production database reset UX hardening
- Force-push / release automation
- Fixing Docker loopback for async dispatch (optional; document `define('ALTERNATE_WP_CRON', true)` or use sync runner in dev)

---

*Last updated: phases 3, 1, 6+7, 2, 4, 8+9, 10 implemented. 104 unit + 3 integration tests passing.*
