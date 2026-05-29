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
- LifterLMS (empty DB only): checkout page, free access plans, `section` posts with `_llms_order`, course blocks for pricing/syllabus; true/false choices labeled `correct answer` / `incorrect answer` (odd index → marker A, even → B) with `_populater_correct_choice_marker` meta
- Tangible LMS: `_tgl_course_id` / `_tgl_lesson_id` meta on lessons/quizzes

Reset integration tests seed content via REST, then verify `POST /reset` clears it.

## Manual dev testing

- Admin UI: http://localhost:8888/wp-admin → Tangible Populator
- WP-CLI:

```bash
docker compose exec wp bash -c 'cd /var/www/html/src && wp --allow-root tangible-populater seed learndash --courses=1 --lessons=1 --quizzes=1 --users=1 --wait'
```
