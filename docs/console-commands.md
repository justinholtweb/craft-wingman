---
title: Console commands
slug: console-commands
order: 70
summary: Status, backfill and orphan cleanup from the command line.
---

```bash
php artisan craft:wingman:status
```

The health table from Utilities → Wingman: rows, rows with elements, missing elements and
orphans per model.

```bash
php artisan craft:wingman:backfill [handle] [--dry-run] [--queue]
```

Creates elements for rows without one. No handle: every model. `--queue` pushes one queue job per
model. Exits non-zero if any element couldn't be created.

```bash
php artisan craft:wingman:cleanup [handle] [--dry-run]
```

Deletes elements whose row no longer exists. Exits non-zero if any couldn't be deleted.

Core's `php artisan craft:eloquent-elements:sync [type]` also creates missing elements (by
reference handle, `customer`), without the dry run or queue.
