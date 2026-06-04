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
(`ld_adv_quiz_pro_ajax` + `wp_pro_quiz_completed_quiz`) per lesson.

| Variable | Role |
|----------|------|
| `K6_MAX_USERS` | Pool of LearnDash logins (`ldstudent1` … `ldstudentN`) |
| `K6_COURSE_INDEX` | Course slug `learndash-course-N` when not using per-user courses |
| `K6_COURSE_PER_USER=1` | `ldstudentN` → `learndash-course-N` |
| `K6_LESSON_COUNT` | Lessons per course (`learndash-lesson-c{C}-l{L}`) |
| `K6_TOPIC_COUNT` | Topics per lesson to mark complete (`learndash-topic-c{C}-l{L}-t{T}`) |
| `K6_QUIZ_TOPIC_INDEX` | Topic that hosts the quiz (Populater: last topic in the lesson, often `10`) |
| `K6_QUIZ_INDEX` | Quiz index on that topic (`learndash-quiz-c{C}-l{L}-q{Q}`) |

Counters: `enroll_skipped_no_form` / `enroll_attempted`, `topic_skipped_no_form` /
`topic_marked_complete`. Group-seeded students are usually already enrolled (skip enroll).

## k6 LifterLMS stress test

```bash
cp .env.example .env   # K6_BASE_URL, K6_USER_PASSWORD, load profile
composer k6:lifter:smoke
composer k6:lifter     # COMPOSER_PROCESS_TIMEOUT=0 (default Composer limit is 300s)
```

While a run is active, open **http://127.0.0.1:5665** for k6’s built-in web dashboard
(live metrics). **Autosave** (`K6_REPORT_AUTOSAVE=1`, default) writes timestamped
**`k6/reports/k6-report-<timestamp>.html`** and **`k6-results-<timestamp>.json`** after
each run. Set `K6_REPORT_AUTOSAVE=0` to disable file output; `K6_WEB_DASHBOARD=0` only
disables the live UI.

| Variable | Role |
|----------|------|
| `K6_VUS` | Concurrent virtual users (load) |
| `K6_THINK_TIME` | Seconds between full journey repeats (end of each iteration) |
| `K6_ACTION_DELAY` | Seconds between HTTP steps within a journey (login, lessons, quiz); `0` = no pause |
| `K6_MAX_USERS` | Pool of Lifter seeded logins (`lifterstudent1` … `lifterstudentN`; fixed by Populater + `k6/lifterlms-stress.js`) |
| `K6_COURSE_INDEX` | Course slug index when **not** using per-user courses (`lifterlms-course-1`) |
| `K6_COURSE_PER_USER=1` | `lifterstudentN` → `lifterlms-course-N` (use with N courses and N users) |
| `K6_CF_USER_AGENT` | User-Agent on every request (default contains `bench2.com`) |
| `K6_CF_BYPASS_HEADER` / `K6_CF_BYPASS_VALUE` | Extra header (default `x-reviewsignal: 1`) |
| `K6_CF_BYPASS=0` | Disable UA + header bypass (local runs) |
| `K6_REPORT_AUTOSAVE` | `1` = save HTML + JSON under `k6/reports/` each run; `0` = no files |
| `K6_WEB_DASHBOARD` | `1` = live dashboard on port 5665; `0` = no live UI (HTML autosave still runs if autosave on) |
| `K6_WEB_DASHBOARD_PORT` | Host port for http://127.0.0.1:PORT (default `5665`) |
| `K6_WEB_DASHBOARD_EXPORT` | Optional fixed HTML path in container (default: timestamped under `k6/reports/`) |

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

1. **Same course for everyone** — With `K6_COURSE_INDEX=1` (default), every VU hits one course. Set `K6_COURSE_PER_USER=1` to spread across 100 courses.
2. **Composer timeout** — Full run is ~5.5m+; without `COMPOSER_PROCESS_TIMEOUT=0` Composer may kill the run early.
3. **Already enrolled** — If seeding used **groups**, Populater auto-enrolls group members; k6 skips when no free-enroll form. Check k6 summary counters `enroll_skipped_no_form` vs `enroll_attempted`.
4. **Login failures** — Rate limits or wrong `K6_USER_PASSWORD` / usernames (`lifterstudent1` …) show up as failed `login succeeded` checks.
5. **Align counts** — `K6_MAX_USERS` and `K6_VUS` should match seeded user count; re-seed after changing counts so `lifterstudent1`…`lifterstudent100` exist with the shared password from Populater settings.
