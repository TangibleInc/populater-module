# LMS extension model

Each LMS plugin stores content differently: some use custom post types only,
others use custom tables, `wp_options`, or vendor APIs (e.g. LearnDash Pro Quiz
in separate tables). The architecture shares **orchestration** in abstract
classes and pushes **storage specifics** into LMS overrides.

## Layers

```
LmsPluginRegistry     → slug, name, plugin file, seeder/process FQCNs
SeedingManager        → builds seeders/runners from registry
AbstractSeeding       → queue, background process, ProcessRepository
LmsSeedingProcess     → DelegateSeedingStep per queue type
AbstractSeeder        → default seed* implementations + overridable hooks
DelegateSeedingStep   → one generic step → seeder->seed*()
```

## Registry

`Tangible\Populater\Registry\LmsPluginRegistry` is the single source of truth.
Adding a fourth LMS requires **one registry entry** plus seeder/process classes.

`PluginDetector` and `SeedingManager::getSupportedPlugins()` read from the registry.

## `AbstractSeeder` — shared defaults, LMS overrides

**Identity** comes from `LmsPluginDefinition` injected at construct time:
`getName()`, `getSlug()`, `isActive()`.

**Shared helpers:**

- `insertPost(array $args): int`
- `createWpUser(array $options): int`
- `defaultTitle(string $prefix, int $index): string`
- `applyMeta(int $postId, array $meta): void`

**Override hooks:**

| Hook | Use |
|------|-----|
| `getPostType(string $entity)` | Required — CPT slug per entity |
| `getMetaFor(string $entity, array $context)` | Post meta after insert |
| `afterCourseCreated()` | e.g. LearnDash `ld_course_steps` |
| `afterLessonCreated()` | e.g. add lesson to course steps |
| `afterQuizCreated()` | e.g. `quiz_pro_id`, course step tree |
| Full `seed*()` override | Custom tables, APIs, Lifter sections |

**Queue:** `buildSeedQueue(SeedConfig|array)` returns `list<SeedQueueItem>`.

## Steps

Per-LMS step classes were replaced by `DelegateSeedingStep` in
`src/Steps/DelegateSeedingStep.php`, wired from `LmsSeedingProcess`.

Add a custom step only when an LMS needs extra queue types (topic, question) or
non-delegate side effects.

## Persistence

`ProcessRepository` owns option prefixes for status, logs, and ID maps.
`SeedingIdMap` delegates to the repository.

## Job runner

`SeedingJobRunner` + `WpBackgroundSeedingRunner` wrap `AbstractSeeding` for
testability. Per-process cancel sets status only (does not cancel the whole LMS
background action).

## Testing

- Unit tests: mock `wp_insert_post` / meta in seeder overrides
- Integration tests (`tests/Integration/`, `wp-test` container): assert real DB
  state per LMS — see `docs/testing.md`
