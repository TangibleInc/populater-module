# k6 Benchmark Routine

Copy-paste the k6 commands from the repository root.

The routine assumes:

- `.env` points `K6_BASE_URL` at the target host.
- For cloud runs, `.env` has `K6_EXECUTION=cloud`.
- `K6_USER_PASSWORD` matches the password used when seeding students.

Important: reset and reseed between every run. Each k6 journey expects fresh
students and uncompleted courses.

If Grafana Cloud shows "Aborted (by user)" but you did not abort it, check the
terminal output. k6 reports script-triggered aborts that way. Common causes are
exhausting `K6_MAX_USERS` or reusing students who are already enrolled/completed.

For LifterLMS, `course enrollment form available for fresh un-enrolled student`
means the course page did not show the free enrollment form. Reset and reseed
fresh LifterLMS students/courses before rerunning.

## Grafana Cloud Token

For cloud runs, put your Grafana k6 token in `.env`:

```bash
K6_EXECUTION=cloud
K6_CLOUD_TOKEN=your_grafana_k6_token_here
```

Do not commit `.env`. It is already ignored by git.

Alternative: run `k6 cloud login` on the host. The stored credentials in
`~/.config/k6/config.json` are used automatically when `K6_CLOUD_TOKEN` is not set.

## 1. Start Docker

```bash
docker compose up -d
```

If the target site is not local, do the reset/activation/seed steps on that server.

## 2. Before Each Run

Log in to the target site admin.

For LifterLMS runs:

1. Activate LifterLMS.
2. Reset the DB.
3. Seed fresh LifterLMS courses and students.

For LearnDash runs:

1. Activate LearnDash.
2. Reset the DB.
3. Seed fresh LearnDash courses and students.

## 3. Clean Race

Exactly 100 journeys started over 30 seconds, then natural drain. No re-loop.
Run 3 times per platform.

### LifterLMS

Activate LifterLMS, reset the DB, and seed fresh LifterLMS content with at least
100 students.

```bash
K6_MAX_USERS=100 K6_COURSE_COUNT=1 composer k6:lifter:clean-race
```

### LearnDash

Activate LearnDash, reset the DB, and seed fresh LearnDash content with at least
100 students.

```bash
K6_MAX_USERS=100 K6_COURSE_COUNT=1 composer k6:learndash:clean-race
```

## 4. Find The Breaking Point

Ramping arrival-rate test. This steps 20 → 50 → 100 → 200 → 400 starts/min,
about 1 minute per step. Watch where p95 first breaks the SLA or errors appear.

Rate targets and VU capacity live in `k6/profiles/breaking-point-arrival.json`.
Edit them there instead of passing env overrides.

### LifterLMS

Activate LifterLMS, reset the DB, and seed fresh LifterLMS content with at least
1000 students.

```bash
K6_MAX_USERS=1000 K6_COURSE_COUNT=1 composer k6:lifter:breakpoint
```

### LearnDash

Activate LearnDash, reset the DB, and seed fresh LearnDash content with at least
1000 students.

```bash
K6_MAX_USERS=1000 K6_COURSE_COUNT=1 composer k6:learndash:breakpoint
```

## 5. Stress Past The Knee

After the breaking-point run, set `"vus"` in `k6/profiles/stress-knee.json` to
roughly 150 % of the weaker platform's knee VU count. Default is `100`.

Seed at least as many fresh students as the VU count you chose.

### LifterLMS

```bash
K6_MAX_USERS=600 K6_COURSE_COUNT=1 composer k6:lifter:knee
```

### LearnDash

```bash
K6_MAX_USERS=600 K6_COURSE_COUNT=1 composer k6:learndash:knee
```

## Reset Reminder

Before every single k6 run:

1. Reset the DB on the target site.
2. Activate only the LMS being tested.
3. Reseed fresh courses and users for that LMS.
4. Run the matching `composer k6:*` command.
