# Archived: Legacy Tangible Fields Populater (`legacy/lgenerators`)

This directory was removed from the codebase. It was **never loaded** by the current
`Tangible Populator` WordPress plugin (`tangible-populater.php`). It targeted
**LearnDash + Tangible Fields Pro** via procedural `generate()` helpers.

## Replacement

| Legacy capability | Current location | Status |
|-------------------|------------------|--------|
| Courses, lessons, quizzes, users | `src/lms/*/ *Seeder.php` + seeding queue | Partial (post-based only) |
| Topics | — | Not ported |
| Questions | — | Not ported |
| Groups | — | Not ported |
| Assignments | — | Not ported |
| Certificates (with LD meta) | Step classes exist; not in default queue | Partial |
| `ld_course_steps` / `quiz_pro_id` | — | Not ported (LearnDash-specific) |
| `complete_quiz()` helpers | — | Not ported |
| Tangible Fields counters (`num_of_courses`, etc.) | REST / admin config | Replaced |
| Faker-rich post content | Simple placeholder content | Simplified |

## Why it was removed

- Hard dependency on `tangible_fields()` and global `$populater`
- No autoloading, no tests, SQL string concatenation
- Superseded by the multi-LMS architecture under `src/lms/`

## If you need old behaviour

Port targeted logic into the appropriate LMS seeder or step override (see
`docs/LMS-EXTENSION.md`). Do not restore the procedural includes.

The original user-facing guide lived in `legacy/docs/index.md` (LearnDash-only
`generate($n, $type, $parent)` API).
