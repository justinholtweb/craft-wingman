---
title: Health, backfill and orphans
slug: health
order: 40
summary: See how each model's rows line up with Craft's elements, and fix what doesn't.
---

**Utilities → Wingman** (and `php artisan craft:wingman:status`) shows, per model:

| Column | Meaning |
|---|---|
| **Rows** | Rows in the model's table, without global scopes, not counting soft-deleted rows |
| **With elements** | Rows that have a live (not trashed) element |
| **Missing elements** | Rows without one. Backfill fixes these |
| **Orphaned links** | Elements whose row is gone, or that were never linked to a row |

## Backfill

Rows written before the model was registered, or written with raw queries that skip Eloquent's
events, have no element. **Backfill** creates them:

- in chunks, looking links up a chunk at a time;
- without writing anything back to your rows (no `updated_at` bumps, no observers);
- skipping rows that already have an element (live or trashed) and soft-deleted rows.

More than 500 missing elements, and the button says **Queue backfill**: the work goes to a queue
job (one per model at a time) that reports progress in the Queue Manager. From the command line:

```bash
php artisan craft:wingman:backfill Customer --dry-run
php artisan craft:wingman:backfill Customer
php artisan craft:wingman:backfill --queue      # every model, as queue jobs
```

## Orphans

Core keeps elements in step with Eloquent's events. A row deleted with a raw query, a truncate,
or by another application never fires them, and its element is left behind. **Delete orphans**
deletes those elements for good (there's no row to restore them from), along with their relations.

```bash
php artisan craft:wingman:cleanup Customer --dry-run
php artisan craft:wingman:cleanup Customer
```

Never counted as orphans:

- elements of a soft-deleted row (the row still exists);
- rows hidden by a global scope;
- links to a *different* model type, such as after a morph map change. Their rows may well
  exist, so Wingman won't touch them.
