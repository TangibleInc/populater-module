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

k6 runs **directly on the host machine** (not in Docker), so the dashboard, network
access to the local WordPress containers, and all k6 CLI features work without
workarounds.

### Install k6

```bash
# macOS
brew install k6

# Debian / Ubuntu (including WSL2)
sudo gpg -k
sudo gpg --no-default-keyring \
  --keyring /usr/share/keyrings/k6-archive-keyring.gpg \
  --keyserver hkp://keyserver.ubuntu.com:80 \
  --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" \
  | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update && sudo apt-get install k6

# Windows (winget)
winget install k6 --source winget
```

Verify: `k6 version`

### Run load tests

Load-test seeded student journeys against local WordPress or a bench host.
Configure targets in `.env` (`cp .env.example .env`). Load shape comes from
JSON profiles under `k6/profiles/`; set `K6_PROFILE` to switch profiles.

```bash
composer k6:lifter:smoke      # LifterLMS — one VU
composer k6:lifter              # LifterLMS — full ramping profile
composer k6:learndash:smoke     # LearnDash — one VU, 1 lesson × 3 topics
composer k6:learndash           # LearnDash — full profile
K6_MAX_USERS=100 K6_COURSE_COUNT=1 composer k6:lifter:clean-race
K6_MAX_USERS=1000 K6_COURSE_COUNT=1 composer k6:lifter:breakpoint
K6_MAX_USERS=600 K6_COURSE_COUNT=1 composer k6:lifter:knee
K6_MAX_USERS=1000 K6_COURSE_COUNT=1 composer k6:learndash:breakpoint
```

Set `K6_EXECUTION=cloud` to run the same commands with `k6 cloud run`. Cloud auth
works with `K6_CLOUD_TOKEN` or a prior `k6 cloud login` on the host.

### Web dashboard and HTML report

`composer k6:lifter*` enables k6's **built-in web dashboard** by default. While a run
is in progress, open **http://127.0.0.1:5665** for live charts (Overview, Timings,
Summary). The terminal prints the URL when the run starts.

**WSL2 note:** Windows Firewall sometimes blocks the `localhost` port forwarding from
WSL2. If `http://127.0.0.1:PORT` is unreachable, use the WSL2 VM IP printed by the
run script instead (e.g. `http://172.x.x.x:PORT`). You can also add a permanent
Windows Firewall inbound rule for the dashboard port, or enable WSL2 mirrored
networking in `%USERPROFILE%\.wslconfig`:
```ini
[wsl2]
networkingMode=mirrored
```
Then restart WSL (`wsl --shutdown`) — after that `localhost` always works.

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
| `K6_WEB_DASHBOARD_PORT` | `5665` | Dashboard port |
| `K6_WEB_DASHBOARD_EXPORT` | *(timestamped)* | Override HTML path (under `k6/reports/`) |
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