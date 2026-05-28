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

```bash
composer docker:test:integration
```

Loads WordPress from the `wp-test` environment and exercises seeding with real
options/posts. Requires active LMS plugins in that container.

## Manual dev testing

- Admin UI: http://localhost:8888/wp-admin → Tangible Populator
- WP-CLI:

```bash
docker compose exec wp bash -c 'cd /var/www/html/src && wp --allow-root tangible-populater seed learndash --courses=1 --lessons=1 --quizzes=1 --users=1 --wait'
```
