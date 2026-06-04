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
Configure targets and load in `.env` (`cp .env.example .env`).

```bash
composer k6:lifter:smoke      # LifterLMS — one VU
composer k6:lifter              # LifterLMS — full profile (~5.5m+)
composer k6:learndash:smoke     # LearnDash — one VU, 1 lesson × 3 topics
composer k6:learndash           # LearnDash — full profile
```

### Web dashboard and HTML report

`composer k6:lifter*` enables k6’s **built-in web dashboard** by default. While a run
is in progress, open **http://127.0.0.1:5665** for live charts (Overview, Timings,
Summary). The terminal prints the URL when the container starts.

**Autosave** is on by default: each run writes timestamped files under **`k6/reports/`**:

- `k6-report-<timestamp>.html` — visual HTML report (full runs; very short smoke may skip HTML)
- `k6-results-<timestamp>.json` — raw metrics (always saved when autosave is on)

You can also use **Report** in the live dashboard UI while the test runs.

| Variable | Default | Purpose |
|----------|---------|---------|
| `K6_REPORT_AUTOSAVE` | `1` | Set `0` to disable HTML + JSON autosave |
| `K6_WEB_DASHBOARD` | `1` | Set `0` to disable live dashboard on port `5665` |
| `K6_WEB_DASHBOARD_PORT` | `5665` | Host port mapped to the dashboard in Docker |
| `K6_WEB_DASHBOARD_EXPORT` | *(timestamped)* | Override HTML path in container (under `k6/reports/`) |

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