# Tangible Populator

WordPress plugin to seed LMS content (courses, lessons, quizzes, users) for
LearnDash, LifterLMS, and Tangible LMS during development and testing.

## Quickstart

```bash
docker compose up -d
composer install
composer docker:test
```

| Service | Port | Purpose |
|---------|------|---------|
| `wp` | 8888 | Dev — admin UI, Playwright, manual testing |
| `wp-test` | 8889 | PHPUnit + integration tests (DB reset per run) |

- Admin: http://localhost:8888/wp-admin (login `admin` / `password` in container README)
- Plugin path in container: `/var/www/html/src/wp-content/plugins/tangible-populater`

See [docs/testing.md](docs/testing.md) and [plan.md](plan.md) for architecture and phases.

## k6 stress tests (LifterLMS & LearnDash)

Load-test seeded student journeys against local WordPress or a bench host.
Configure targets in `.env` (`cp .env.example .env`). Load shape comes from
JSON profiles under `k6/profiles/`; set `K6_PROFILE` to switch profiles.

```bash
composer k6:lifter:smoke      # LifterLMS — one VU
composer k6:lifter              # LifterLMS — full ramping profile
composer k6:learndash:smoke     # LearnDash — one VU, 1 lesson × 3 topics
composer k6:learndash           # LearnDash — full profile
K6_PROFILE=bench-100 composer k6:lifter
K6_PROFILE=clean-race-100 composer k6:lifter
K6_PROFILE=breaking-point-arrival composer k6:learndash
K6_PROFILE=stress-knee K6_VUS=180 composer k6:lifter
```

Set `K6_EXECUTION=cloud` to run the same commands with `k6 cloud run` inside the
Docker k6 image. Cloud auth works with `K6_CLOUD_TOKEN` or a host `k6 cloud login`,
which is mounted into the container.

### Web dashboard and HTML report

`composer k6:lifter*` enables k6’s **built-in web dashboard** by default. While a run
is in progress, open **http://127.0.0.1:5665** for live charts (Overview, Timings,
Summary). The terminal prints the URL when the container starts.

**Autosave** is on by default: each run writes timestamped files under **`k6/reports/`**:

- `k6-report-<timestamp>.html` — visual HTML report (full runs; very short smoke may skip HTML)
- `k6-summary-<timestamp>.json` — end-of-test metric aggregates (small). Set `K6_JSON_STREAM=1` for full per-sample NDJSON instead (multi-GB on long runs).

You can also use **Report** in the live dashboard UI while the test runs.
The stress scripts use one k6 `course` group per course flow. Login runs before
that group, and lesson/quiz steps run inside it without nested groups, so
`group_duration` represents one student passing one course.

| Variable | Default | Purpose |
|----------|---------|---------|
| `K6_PROFILE` | `default` | JSON profile under `k6/profiles/` (`smoke`, `clean-race-100`, `breaking-point-arrival`, `stress-knee`, etc.) |
| `K6_EXECUTION` | `local` | Set `cloud` to use `k6 cloud run` |
| `K6_REPORT_AUTOSAVE` | `1` | Set `0` to disable HTML + JSON autosave |
| `K6_WEB_DASHBOARD` | `1` | Set `0` to disable live dashboard on port `5665` |
| `K6_WEB_DASHBOARD_PORT` | `5665` | Host port mapped to the dashboard in Docker |
| `K6_WEB_DASHBOARD_EXPORT` | *(timestamped)* | Override HTML path in container (under `k6/reports/`) |
| `K6_STEP_THINK_TIME` | `1` | Seconds between student actions within a journey |

Seed LifterLMS content first (Populater admin UI or WP-CLI), align `K6_MAX_USERS` /
`K6_VUS` with seeded students (`lifterstudent1`, …), and match `K6_USER_PASSWORD` to the Populater setting.
See [docs/testing.md](docs/testing.md#k6-lifterlms-stress-test) for Cloudflare bypass,
course-per-user mode, and troubleshooting.

## Deploying to WordPress

Build a clean installable package (plugin PHP, assets, production `vendor/` only):

```bash
composer zip
```

Upload `tangible-populater.zip` via **Plugins → Add New → Upload Plugin**.