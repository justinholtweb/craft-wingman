# Release Notes for Wingman

## 6.0.0-beta.1 - Unreleased

### Added
- Register app Eloquent models in Settings → Wingman; each gets a CP index, a relation field type and template queries.
- Model discovery (`modelPaths`, default `app/Models`).
- Utilities → Wingman: per-model health (rows, linked, missing, orphaned) with Backfill.
- `craft:wingman:status` and `craft:wingman:backfill [handle] [--dry-run]`.
