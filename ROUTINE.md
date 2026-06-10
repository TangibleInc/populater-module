# k6 Benchmark Routine

Copy-paste the k6 commands from the repository root.

The routine assumes:

- `.env` points `K6_BASE_URL` at the target host.
- For cloud runs, `.env` has `K6_EXECUTION=cloud`.
- `K6_USER_PASSWORD` matches the password used when seeding students.

Important: reset and reseed between every run. Each k6 journey expects fresh
students and uncompleted courses.

## Grafana Cloud Token

For cloud runs, put your Grafana k6 token in `.env`:

```bash
K6_EXECUTION=cloud
K6_CLOUD_TOKEN=your_grafana_k6_token_here
```

Do not commit `.env`. It is already ignored by git.

Alternative: run `k6 cloud login` on this machine. The Docker k6 container mounts
your host k6 config from `${K6_HOST_CONFIG_DIR:-$HOME/.config/k6}` and uses that
login when `K6_CLOUD_TOKEN` is not set.

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
K6_PROFILE=clean-race-100 K6_MAX_USERS=100 K6_COURSE_COUNT=1 composer k6:lifter
```

### LearnDash

Activate LearnDash, reset the DB, and seed fresh LearnDash content with at least
100 students.

```bash
K6_PROFILE=clean-race-100 K6_MAX_USERS=100 K6_COURSE_COUNT=1 composer k6:learndash
```

## 4. Find The Breaking Point

Ramping arrival-rate test. This steps 20 -> 50 -> 100 -> 200 -> 400 starts/min,
about 1 minute per step. Watch where p95 first breaks the SLA or errors appear.

### LifterLMS

Activate LifterLMS, reset the DB, and seed fresh LifterLMS content with at least
1000 students.

```bash
K6_PROFILE=breaking-point-arrival K6_MAX_USERS=1000 K6_COURSE_COUNT=1 composer k6:lifter
```

### LearnDash

Activate LearnDash, reset the DB, and seed fresh LearnDash content with at least
1000 students.

```bash
K6_PROFILE=breaking-point-arrival K6_MAX_USERS=1000 K6_COURSE_COUNT=1 composer k6:learndash
```

Optional custom step sequence:

```bash
K6_PROFILE=breaking-point-arrival K6_RATE_TARGETS=50,100,200,300,500 K6_MAX_VUS=650 K6_MAX_USERS=1200 K6_COURSE_COUNT=1 composer k6:lifter
```

Before running a custom sequence, reset the DB and reseed enough fresh students
for the chosen `K6_MAX_USERS`.

## 5. Stress Past The Knee

After the breaking-point run, set `KNEE_VUS` to roughly 150% of the weaker
platform's knee and hold that load for 5 minutes. Set `KNEE_USERS` to the number
of fresh students seeded for each run.

```bash
export KNEE_VUS=180
export KNEE_USERS=600
```

### LifterLMS

Activate LifterLMS, reset the DB, and seed fresh LifterLMS content with at least
`KNEE_USERS` students.

```bash
K6_PROFILE=stress-knee K6_VUS="$KNEE_VUS" K6_MAX_USERS="$KNEE_USERS" K6_COURSE_COUNT=1 composer k6:lifter
```

### LearnDash

Activate LearnDash, reset the DB, and seed fresh LearnDash content with at least
`KNEE_USERS` students.

```bash
K6_PROFILE=stress-knee K6_VUS="$KNEE_VUS" K6_MAX_USERS="$KNEE_USERS" K6_COURSE_COUNT=1 composer k6:learndash
```

## Reset Reminder

Before every single k6 run:

1. Reset the DB on the target site.
2. Activate only the LMS being tested.
3. Reseed fresh courses and users for that LMS.
4. Run the matching `composer k6:*` command.

