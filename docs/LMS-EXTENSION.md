# LMS extension model

Each LMS plugin stores content differently: some use custom post types only,
others use custom tables, `wp_options`, or vendor APIs (e.g. LearnDash Pro Quiz
in separate tables). The architecture shares **orchestration** in abstract
classes and pushes **storage specifics** into LMS overrides.

## Registering an LMS

LMS integrations register via the WordPress filter
`tangible-populator-lms-plugins`:

```php
$lmsPlugins = apply_filters( 'tangible-populator-lms-plugins', array() );
```

Extend `AbstractLmsPlugin`, set the protected properties, and instantiate your
class (the constructor hooks the filter automatically):

```php
class MyLmsPlugin extends AbstractLmsPlugin
{
    protected string $slug = 'my-lms';
    protected string $name = 'My LMS';
    protected string $pluginFile = 'my-lms/my-lms.php';
    protected string $seederClass = MyLmsSeeder::class;
    protected string $backgroundAction = 'seed_my_lms';
}

// In your plugin bootstrap:
new MyLmsPlugin();
```

Built-in LMS classes live under `src/lms/*/` (e.g. `LearnDashLmsPlugin`).
`LmsPlugins::registerBuiltIn()` boots them from `Plugin` construction.

`LmsPluginRegistry` only reads from the filter — no central hardcoded list.

## Layers

```
AbstractLmsPlugin     → filter registration + createSeeder/createProcess
LmsPluginRegistry     → apply_filters( … )
SeedingManager        → seeders/runners from registry
AbstractSeeding       → queue, background process, ProcessRepository
LmsSeedingProcess     → DelegateSeedingStep per queue type
AbstractSeeder        → default seed* implementations + overridable hooks
```

## `AbstractSeeder` — shared defaults, LMS overrides

**Identity** comes from the injected `AbstractLmsPlugin`: `getName()`, `getSlug()`,
`isActive()`.

**Override per LMS when storage differs** — see previous table in this doc for
`seedCourses()`, `getPostType()`, hooks, etc.

## Steps

`DelegateSeedingStep` + `LmsSeedingProcess` handle queue dispatch. Add custom
steps only for extra queue types (topics, questions).

## Testing

- Unit tests: `LmsPlugins::registerBuiltIn()` or a `TestLmsPlugin` stub
- Integration: registry + seeder + queue via `tests/Integration/`
