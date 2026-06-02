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

## Deploying to WordPress

Build a clean installable package (plugin PHP, assets, production `vendor/` only):

```bash
composer zip
```

Upload `tangible-populater.zip` via **Plugins → Add New → Upload Plugin**.

Do **not** copy the repository (or a zip of the repo) into `wp-content/plugins`. Dev files such as `.git/`, `tests/`, `node_modules/`, and `docker-compose.yml` are not part of the plugin and will break in-place WordPress updates.

If a site was installed from the repo and updates fail with permission/copy errors, delete the plugin folder once (FTP, SSH, or WP-CLI), then install `tangible-populater.zip` from `composer zip`. Future updates from clean zips will work normally.
