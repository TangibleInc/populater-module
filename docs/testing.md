# Testing

## Unit tests (default)

Runs in the **`wp-test`** container (port 8889) so the dev database on `wp` (8888)
is untouched.

```bash
composer docker:test
# or locally:
composer test
```

Uses Brain Monkey mocks — no real background processing.

## Integration tests

WordPress REST integration tests load the real `wp-test` site, call REST
endpoints, process the seeding queue synchronously, and verify entity counts
and LMS-specific structure in the database.

```bash
composer docker:tests:integration
```

Each LMS seed test uses fixed counts (`2` courses, `3` lessons/course,
`2` quizzes/lesson, `4` users) and asserts:

- REST status/logs report `completed` with the expected queue total
- DB deltas match expected course/lesson/quiz/user counts
- LearnDash: `ld_course_steps` with `sfwd-topic` slots and lesson-attached quizzes
- LifterLMS (empty DB only): checkout page, free access plans, `section` posts with `_llms_order`, course blocks for pricing/syllabus; true/false choices labeled `correct answer` / `incorrect answer` (odd index → marker A, even → B) with `_populater_correct_choice_marker` meta; seeded students have `first_name`, `last_name`, and billing address meta for checkout / free enrollment
- Tangible LMS: `_tgl_course_id` / `_tgl_lesson_id` meta on lessons/quizzes

Reset integration tests seed content via REST, then verify `POST /reset` clears it.

## Manual dev testing

- Admin UI: http://localhost:8888/wp-admin → Tangible Populator
- WP-CLI:

```bash
docker compose exec wp bash -c 'cd /var/www/html/src && wp --allow-root tangible-populater seed learndash --courses=1 --lessons=1 --quizzes=1 --users=1 --wait'
```

## k6 LearnDash stress test

```bash
cp .env.example .env   # K6_BASE_URL, K6_USER_PASSWORD, load profile
composer k6:learndash:smoke
composer k6:learndash
```

Same dashboard/autosave behavior as the Lifter test (see below). Journey: login →
free enroll when `course_join` form is present → mark each topic complete → wpProQuiz
(one `checkAnswers` admin-ajax call per question, then `wp_pro_quiz_completed_quiz`)
per lesson.

Load profiles are k6 JSON config files under `k6/profiles/`. Set `K6_PROFILE`
to select one; `.env` values like `K6_VUS`, `K6_HOLD`, or `K6_SCENARIO` override
the selected profile when present. One VU iteration is one seeded student's journey.
Each iteration walks every seeded course (`K6_COURSE_COUNT`, default 5) starting
at `K6_COURSE_INDEX`. Student logins are consumed sequentially by journey start
(`ldstudent1`, `ldstudent2`, `ldstudent3`, ...), so no user hits the same course
twice. Set `K6_MAX_USERS` to cover the number of student journeys the run can start.

| Variable | Role |
|----------|------|
| `K6_MAX_USERS` | Pool of LearnDash logins (`ldstudent1` … `ldstudentN`) |
| `K6_COURSE_INDEX` | First course slug index (`learndash-course-N`) |
| `K6_COURSE_COUNT` | Courses per iteration (`N` … `N+count-1`; default 5) |
| `K6_COURSE_PER_USER=1` | Each student runs only `learndash-course-N` matching their login |
| `K6_LESSON_COUNT` | Lessons per course (`learndash-lesson-c{C}-l{L}`) |
| `K6_TOPIC_COUNT` | Topics per lesson to mark complete (`learndash-topic-c{C}-l{L}-t{T}`) |
| `K6_QUIZ_TOPIC_INDEX` | Topic that hosts the quiz (Populater: last topic in the lesson, often `10`) |
| `K6_QUIZ_INDEX` | Quiz index on that topic (`learndash-quiz-c{C}-l{L}-q{Q}`) |

The journey is strict: the seeded student must be fresh, the enrollment form must
exist, topics must expose mark-complete forms, and quizzes must be reachable. If
any expected seeded state is missing or already completed, k6 fails the iteration.

## k6 LifterLMS stress test

```bash
cp .env.example .env   # K6_BASE_URL, K6_USER_PASSWORD, load profile
composer k6:lifter:smoke
composer k6:lifter     # full ramping profile; COMPOSER_PROCESS_TIMEOUT=0 avoids Composer's 300s limit
```

While a run is active, open **http://127.0.0.1:5665** for k6’s built-in web dashboard
(live metrics; k6 runs on the host so the port is always directly reachable).
On WSL2, if `localhost` is blocked by Windows Firewall use the WSL2 VM IP printed
by the run script, or switch to mirrored networking (see README).
**Autosave** (`K6_REPORT_AUTOSAVE=1`, default) writes timestamped
**`k6/reports/k6-report-<timestamp>.html`** and **`k6-summary-<timestamp>.json`**
(end-of-test aggregates). The stress scripts use one k6 `course` group per
course flow. Login runs before the group, and lesson/quiz steps run inside it
without nested groups, so the unfiltered `group_duration` row represents one
student passing one course.

Set `K6_JSON_STREAM=1` only when you need full per-sample NDJSON (`--out json=…`);
on long 100-VU runs that stream can reach many GB and prevent the HTML export from
finishing. Set `K6_REPORT_AUTOSAVE=0` to disable file output; `K6_WEB_DASHBOARD=0`
only disables the live UI.

### Load profiles

| Profile | File | Purpose |
|---------|------|---------|
| `default` | `k6/profiles/default.json` | 20 VU ramping profile |
| `smoke` | `k6/profiles/smoke.json` | 1 VU, 1 iteration |
| `constant` | `k6/profiles/constant.json` | Steady `constant-vus` profile |
| `bench-100` | `k6/profiles/bench-100.json` | 100 VU bench profile |
| `soak` | `k6/profiles/soak.json` | 50 VU, 2 hour endurance profile |
| `clean-race-100` | `k6/profiles/clean-race-100.json` | Start exactly 100 journeys over 30 seconds, then drain |
| `breaking-point-arrival` | `k6/profiles/breaking-point-arrival.json` | Ramping arrival-rate steps: 20, 50, 100, 200, 400 starts/min |
| `stress-knee` | `k6/profiles/stress-knee.json` | 150 VU, 5 minute hold for stress just past the knee |

Examples:

```bash
K6_PROFILE=smoke composer k6:lifter
K6_PROFILE=bench-100 composer k6:learndash
K6_PROFILE=bench-100 K6_EXECUTION=cloud composer k6:lifter
```

The wrapper compiles the selected profile plus any `K6_*` load overrides into
`k6/.runtime/<profile>-effective.json` before running k6.

### Benchmark routine

Run each phase for both LMS platforms against the same hardware and seeded user
pool. For cloud runs, add `K6_EXECUTION=cloud`.

1. Clean race: exactly 100 student journeys, started over 30 seconds with no
   re-loop. The profile then waits for in-flight journeys to drain naturally.
   Run it three times per platform for stability.

```bash
K6_PROFILE=clean-race-100 K6_MAX_USERS=100 composer k6:lifter
K6_PROFILE=clean-race-100 K6_MAX_USERS=100 composer k6:learndash
```

2. Breaking point: ramping arrival-rate, not fixed VUs. This starts 20, 50, 100,
   200, then 400 journeys per minute for about one minute per step. Watch where
   p95 first exceeds the SLA (`http_req_duration` p95 threshold is 1s by default)
   or errors first appear.

```bash
K6_PROFILE=breaking-point-arrival K6_MAX_USERS=800 composer k6:lifter
K6_PROFILE=breaking-point-arrival K6_MAX_USERS=800 composer k6:learndash
```

Override the step list when needed:

```bash
K6_PROFILE=breaking-point-arrival K6_RATE_TARGETS=50,100,200,300,500 K6_MAX_VUS=650 composer k6:lifter
```

3. Stress past the knee: after the arrival-rate run shows the weaker platform's
   knee, hold just above it for five minutes. The default is 150 VUs; set `K6_VUS`
   to roughly 150% of the weaker knee.

```bash
K6_PROFILE=stress-knee K6_VUS=180 K6_MAX_USERS=500 composer k6:lifter
K6_PROFILE=stress-knee K6_VUS=180 K6_MAX_USERS=500 composer k6:learndash
```

### k6 Cloud

Set `K6_EXECUTION=cloud` to run the same Composer commands with `k6 cloud run`.
Cloud runs must target a public URL; the runner rejects `localhost` targets in cloud mode.

Cloud auth works in either of these ways:

1. Set `K6_CLOUD_TOKEN` in the shell, CI secret, or `.env`.
2. Run `k6 cloud login` on the host (`~/.config/k6/config.json` is used automatically).

Optionally set `K6_CLOUD_PROJECT_ID` when the account has multiple projects. Local
dashboard and autosaved report files are skipped for cloud runs.

| Variable | Role |
|----------|------|
| `K6_PROFILE` | JSON profile name under `k6/profiles/` |
| `K6_EXECUTION` | `local` or `cloud` |
| `K6_SCENARIO` | Optional executor override for the selected profile |
| `K6_VUS` | Optional virtual user override for profile scenarios |
| `K6_RAMP_UP` / `K6_HOLD` / `K6_RAMP_DOWN` | Optional ramping profile overrides |
| `K6_RATE_TARGETS` | Comma-separated arrival-rate step targets for `ramping-arrival-rate` profiles |
| `K6_STAGE_DURATION` | Duration for each `K6_RATE_TARGETS` step |
| `K6_PRE_ALLOCATED_VUS` / `K6_MAX_VUS` | VU capacity for arrival-rate profiles |
| `K6_RATE_TIME_UNIT` | Time unit for arrival-rate targets, default from profile is `1m` |
| `K6_GRACEFUL_STOP` | Graceful stop for arrival-rate profiles |
| `K6_STEP_THINK_TIME` | Seconds between HTTP steps within a journey (login, lessons, quiz); `0` = no pause |
| `K6_THINK_TIME` | Seconds between courses inside a journey |
| `K6_MAX_USERS` | Pool of Lifter seeded logins (`lifterstudent1` … `lifterstudentN`; fixed by Populater + `k6/lifterlms-stress.js`) |
| `K6_COURSE_INDEX` | First course slug index (`lifterlms-course-N`) |
| `K6_COURSE_COUNT` | Courses per iteration (`N` … `N+count-1`; default 5) |
| `K6_COURSE_PER_USER=1` | Each student runs only `lifterlms-course-N` matching their login |
| `K6_CF_USER_AGENT` | User-Agent on every request (default contains `bench2.com`) |
| `K6_CF_BYPASS_HEADER` / `K6_CF_BYPASS_VALUE` | Extra header (default `x-reviewsignal: 1`) |
| `K6_CF_BYPASS=0` | Disable UA + header bypass (local runs) |
| `K6_REPORT_AUTOSAVE` | `1` = save HTML + summary JSON under `k6/reports/` each run; `0` = no files |
| `K6_JSON_STREAM` | `1` = full per-sample NDJSON via `--out json=…` (multi-GB on long runs); default off |
| `K6_WEB_DASHBOARD` | `1` = live dashboard on port 5665; `0` = no live UI (HTML autosave still runs if autosave on) |
| `K6_WEB_DASHBOARD_PORT` | Dashboard port (default `5665`) |
| `K6_WEB_DASHBOARD_EXPORT` | Optional fixed HTML path in container (default: timestamped under `k6/reports/`) |
| `K6_CLOUD_TOKEN` | Grafana Cloud token for `k6 cloud run` auth |
| `K6_HOST_CONFIG_DIR` | Override k6 config directory for cloud login (default: `~/.config/k6`) |

### Cloudflare Skip rule (bench / rate limits)

Create a **Custom rule** on the zone for your bench host. Place it **first** among
custom rules. Scope to the bench hostname when possible.

**When** (use **Or**):

1. User Agent → **contains** → `bench2.com`
2. Header → `x-reviewsignal` → **exists**

**And** (recommended): Hostname → **equals** → `tangible-bench.tangiblelaunchpad.com`

**Then:** **Skip** → **All rate limiting rules**, **All managed rules**, **All Super Bot Fight Mode Rules**

k6 sends both signals when `K6_CF_BYPASS=1` (default). Set `K6_CF_BYPASS=0` in `.env` for local Docker.

**Troubleshooting low enrollments**

1. **Same course for everyone** — Default is all `K6_COURSE_COUNT` courses per iteration. Set `K6_COURSE_PER_USER=1` so each VU only hits `course-N` matching their login.
2. **Composer timeout** — Full runs can exceed Composer's default 300s timeout; keep `COMPOSER_PROCESS_TIMEOUT=0`.
3. **Already enrolled / completed** — Use fresh seeded students for each run. The k6 scripts fail if the student is already enrolled, a lesson/topic is already complete, or an expected form is missing.
4. **Login failures** — Rate limits or wrong `K6_USER_PASSWORD` / usernames (`lifterstudent1` …) show up as failed `login succeeded` checks.
5. **Align counts** — `K6_MAX_USERS` must cover how many full journeys the scenario can start (`ldstudent1` … `ldstudentN` / `lifterstudent1` …). Each student runs all `K6_COURSE_COUNT` courses exactly once, then a new student takes over on the next iteration. If the pool is exhausted, k6 aborts with a configuration error instead of idling VUs.
