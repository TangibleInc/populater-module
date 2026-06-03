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

## k6 LifterLMS stress test

```bash
cp .env.example .env   # K6_BASE_URL, K6_USER_PASSWORD, load profile
composer k6:lifter:smoke
composer k6:lifter     # COMPOSER_PROCESS_TIMEOUT=0 (default Composer limit is 300s)
```

| Variable | Role |
|----------|------|
| `K6_VUS` | Concurrent virtual users (load) |
| `K6_MAX_USERS` | Pool of seeded logins (`student1` … `studentN`) |
| `K6_COURSE_INDEX` | Course slug index when **not** using per-user courses (`lifterlms-course-1`) |
| `K6_COURSE_PER_USER=1` | `studentN` → `lifterlms-course-N` (use with N courses and N users) |
| `K6_CF_USER_AGENT` | User-Agent on every request (default contains `bench2.com`) |
| `K6_CF_BYPASS_HEADER` / `K6_CF_BYPASS_VALUE` | Extra header (default `x-reviewsignal: 1`) |
| `K6_CF_BYPASS=0` | Disable UA + header bypass (local runs) |

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
4. **Login failures** — Rate limits or wrong `K6_USER_PASSWORD` / usernames (`student1` …) show up as failed `login succeeded` checks.
5. **Align counts** — `K6_MAX_USERS` and `K6_VUS` should match seeded user count; re-seed after changing counts so `student1`…`student100` exist with the shared password from Populater settings.
