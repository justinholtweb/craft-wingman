# Release Notes for Wingman

## 6.0.0-beta.1 - Unreleased

> Wingman is for **Craft CMS 6**, which is in beta. Use caution on production.

### Added
- Register app Eloquent models in Settings → Wingman; each gets a CP index, editor, permissions, a relation field type and template queries.
- Per-model label, label column, trash behaviour (keep or soft-delete the row) and sidebar visibility.
- Model discovery (`modelPaths`, default `app/Models`), confined to the project root.
- Validation against handle/identity/CP URI collisions, Craft's own models, duplicate classes, missing or hidden label columns, and removing models that have elements.
- Utilities → Wingman: per-model health (rows, linked, missing, orphaned), Backfill (queued past 500 rows) and Delete orphans.
- `craft:wingman:status`, `craft:wingman:backfill [handle] [--dry-run] [--queue]`, `craft:wingman:cleanup [handle] [--dry-run]`.
- `wingman.element(model)` in Twig and Blade; elements print their label column.
- Every control panel string translatable (`wingman` category); model labels through `site`.
- Pro: reverse lookups (`HasCraftElements` trait, `wingman.relatedEntries()`, `wingman.related()`, eager `loadCraftEntries()`).
- Pro: a condition rule per model attribute.
- Pro: GraphQL queries per model (`wingman<Plural>`, `wingman<Handle>`, `wingman<Plural>Count`) and typed relation fields, with a schema permission per model; `$hidden`/`$visible` honoured.

### Security
- Backfill and cleanup are POST-only, CSRF-protected and need the Wingman utility permission; handles are checked against registered models.
- Generated classes come from a validated identifier only; model classes only from discovery.
