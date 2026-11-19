# Wingman — plan

Introduce your app's Eloquent models to Craft — elements, relation fields and reverse lookups, configured in the CP instead of in PHP.

- Package: `justinholtweb/craft-wingman` · handle `wingman` · namespace `JustinHoltWeb\Wingman`
- Editions: Lite / Pro · pricing: Lite free / **Pro price TBD** (was pencilled at $49; see Questions)
- Laravel integration: Eloquent (core) — Builds on Craft 6's own `CraftCms\Cms\Element\Eloquent` layer.
- Craft 6 only, native Laravel (no `craftcms/yii2-adapter`, ever). Follow
  `~/Sites/plugin-testing-craft6/porting/COOKBOOK.md` for Craft 6 idioms.

## Rescoped against Craft 6 core (2026-11-17)
Core already ships the hard part: `EloquentElement` (an element proxying an app model's row, linked through
`elements_models`), `EloquentElementsField` (relation field to those elements), `ModelSync` (model writes →
element) and `EloquentElements::register()`. **But every model needs a PHP subclass, a field subclass and a
registration call.** Wingman's job is everything around that.

## Status
- **2026-11-17 — phase 1 done.** Models registered in the CP (settings Table → project config), discovered
  from `modelPaths` by token scan; validation rejects bad/duplicate handles, non-discovered classes,
  identity/CP-URI collisions, removing or repointing a model that has elements. Boot registers each model
  in its own try/catch (a bad one is logged and skipped, never fatal). Utilities → Wingman health table
  (rows / linked / missing / orphaned) with per-model Backfill; `craft:wingman:status`,
  `craft:wingman:backfill [handle] [--dry-run]`. 24 Pest tests; harness-verified.
- **Bug found + fixed:** core derives `refHandle()` (the element-type *identity*) from the model's class
  basename, so registering `App\Models\User` collided with Craft's users and crashed every request.
  Wingman now derives identity + CP URI from its handle (`AppUser` → `app-user` / `app-users`).
- **2026-11-18 — Lite and Pro done (overnight run).** See "Status by phase" below. 97 live harness checks
  (`tests/Harness/check.sh`), 41 Pest tests, Larastan 6 + Pint + port-check green.

## Status by phase (2026-11-18)

1. **CP registration (Lite) — done.** Plus per-model label column, trash behaviour (keep/soft-delete) and
   sidebar visibility. Craft's own models (and `App\Models\User`, which extends Craft's user model) are refused at
   validation *and* at boot. Index columns/searchable columns are left to core (CP column picker; listed attributes).
2. **Backfill + health (Lite) — done.** Backfill rewritten to match core's `craft:eloquent-elements:sync` (batched
   link lookups, sync-guarded saves with no model write-back, no validation, soft-deleted rows skipped) plus dry run,
   progress and per-row failures; >500 missing rows → unique `BackfillModel` queue job (CP + `--queue`). Orphan
   cleanup (CP button + `craft:wingman:cleanup [--dry-run]`): elements whose row is gone or never linked; links to a
   different morph type are never touched. Non-admin permission checks (view-only user vs utility user) live.
   CP index rows verified through `element-indexes/get-elements` (names/companies present, hidden email absent).
3. **Reverse lookups (Pro) — done.** `HasCraftElements` (`craftElement`, `craftEntries`, `craftRelated`,
   `loadCraftEntries` — constant query count), `wingman.element()` (Lite) / `wingman.relatedEntries()` /
   `wingman.related()` (Pro) in Twig + Blade with parity checks.
4. **Query + conditions (Pro) — done.** “Related to model X” is already core (`RelatedToConditionRule` lists every
   relational field type, Wingman's included), so Pro adds a condition rule per listed model attribute. GraphQL:
   per-model queries + typed relation fields, schema permission per model, `$hidden`/`$visible` honoured.

**Multisite decision:** core makes Eloquent elements non-localized (primary site only; queries for them are pinned
to the primary site from any site). Wingman keeps that: one element per row, identical on every site. Verified on a
temporary `wingmanfr` site. Labels translate via the `site` category; CP strings via `wingman`.

**Core facts found:** Eloquent elements have no statuses (`hasStatuses()` false — `enabled` is ignored by queries);
`EloquentElementsField::includeInGqlSchema()` is false in core (Wingman overrides it in Pro); elements' `__toString`
fell back to “Customer 27” (Wingman prints the label column).

## Questions for Justin

1. **Pro price?** Placeholder is TBD (was pencilled at $49, same as Bloodhound Pro). Lite stays free.
2. **GraphQL is Pro-only** (per this plan's phase 4). Bloodhound gives GraphQL to Lite. Keep it Pro, or move the
   read queries to Lite and keep relation-field typing / conditions in Pro?
3. **GraphQL naming:** queries are `wingman` + plural handle, so a model handled `WingmanInvoice` becomes
   `wingmanWingmanInvoices`. Fine, or drop the prefix (risking clashes with core/other plugins' query names)?
4. **Queue threshold** for CP backfill is a constant (500 missing rows). Make it a setting?
5. **Morphing:** links store `getMorphClass()`. If an app adds a morph map later, existing links stop matching
   (Wingman reports them as neither linked nor orphaned and never deletes them). Want a `craft:wingman:remap` command?

  in a browser.

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
