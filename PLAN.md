# Wingman — plan

Introduce your app's Eloquent models to Craft — elements, relation fields and reverse lookups, configured in the CP instead of in PHP.

- Package: `justinholtweb/craft-wingman` · handle `wingman` · namespace `JustinHoltWeb\Wingman`
- Editions: Lite / Pro · pricing: Lite free / Pro $49 (provisional)
- Laravel integration: Eloquent (core) — Builds on Craft 6's own `CraftCms\Cms\Element\Eloquent` layer.
- Craft 6 only, native Laravel (no `craftcms/yii2-adapter`, ever). Follow
  `~/Sites/plugin-testing-craft6/porting/COOKBOOK.md` for Craft 6 idioms.

## Rescoped against Craft 6 core (2026-11-17)
Core already ships the hard part: `EloquentElement` (an element proxying an app model's row, linked through
`elements_models`), `EloquentElementsField` (relation field to those elements), `ModelSync` (model writes →
element) and `EloquentElements::register()`. **But every model needs a PHP subclass, a field subclass and a
registration call.** Wingman's job is everything around that.

## Spike result (2026-11-17): runtime registration WORKS — full product is viable
Tested in the Craft 6 harness against an ordinary `App\Models\Customer` (`tests/Harness/`).

**Mechanism.** Core's API is static per class, so Wingman mints one tiny `final` subclass per model on
demand: `Generated\Elements\Customer extends WingmanElement`, `Generated\Fields\CustomerField extends
WingmanField`. An `spl_autoload_register` loader (registered in the plugin's `register()`) `eval`s a fixed
one-line template; the only variable is the handle, held to `^[A-Z][A-Za-z0-9]{0,63}$`. The bases answer
everything (`modelClass()`, `relationFieldType()`, `elementType()`) from `ModelRegistry` by `static::class`.

**Verified:** registration via `EloquentElements::register()`; columns proxied from the schema with `$hidden`
kept out of listings; backfill via `fromModel()` + save; `ModelSync` creates elements for new model rows;
relation field of the generated type saved to project config and attached to a layout; related elements
load in a *fresh process* (`elements.type` resolved by the autoloader) and by `getElementById`; CP index
route `admin/customers` 200; fields settings 200.

**Disabling Wingman degrades cleanly:** the field becomes `MissingField`, the front end still 200s, element
lookups return null, and `admin/customers` 404s. Re-enabling restores it all. The class names must never
change once data exists (they're in `elements.type` and project config) — handles are immutable after
creation; renaming means a migration.

**Open items found:**
- `eval` vs writing generated files to `storage/` + `require`: eval chosen (no filesystem writes, opcache
  irrelevant at this size). Revisit only if a host disables eval (rare; Suhosin-era).
- CP index rows are client-rendered; not visually checked yet (needs a CP browser session).
- Elements have no title (core uses `labelAttribute()`); Momentum serialises them as `{id,title,url}` →
  add Eloquent-element support to Momentum (non-`$hidden` model attributes).
- Model discovery, the CP UI and project-config storage of `models` are next (phase 1); the spike reads
  `config/craft/wingman.php`.

## Phases
1. **CP registration (Lite).** Discover Eloquent models under `modelPaths`; pick one, choose title column,
   index columns, searchable columns, trash behaviour. Stored in project config; at boot Wingman registers a
   generic `EloquentElement`/`EloquentElementsField` pair per entry (anonymous-class or generated-class
   strategy — spike which core tolerates). Allowlist only: never resolve a class name from a request.
2. **Backfill + health (Lite).** `craft:wingman:backfill <model>`; health page showing unlinked rows, orphaned
   links, last sync errors.
3. **Reverse lookups (Pro).** `HasCraftElements` trait for app models: `$customer->craftEntries('field')`
   (which entries relate to this model), eager-loadable; Twig helpers.
4. **Query + conditions (Pro).** Element condition rules and query params for "relates to model X"; GraphQL
   types for registered models.

## Risks / open questions
- Can core's registration accept a runtime-configured element class, or must each be a real class? (Spike first.)
- Edition gating in core for Eloquent elements, if any.
- If the spike shows core needs real classes, fall back to a `craft:wingman:make` generator — smaller product.

## Definition of done (every phase)
`bin/port-check craft-wingman` green · Pest suite green · Pint + Larastan level 6 clean · installs, uninstalls and
reinstalls cleanly in the harness · CP pages smoke-tested with `bin/cp-session`.
