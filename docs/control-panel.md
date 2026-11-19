---
title: Control panel and permissions
slug: control-panel
order: 25
summary: The index, the editor, the relation field type, and who can see what.
---

Every registered model gets, from Craft 6 core:

- an **index** at its URI (`admin/customers`) with sorting, searching, custom columns and
  custom sources;
- an **editor** at `admin/customers/{id}` and **New** at `admin/customers/new`;
- a **relation field type** (Settings → Fields → *Customers*) to relate entries, users and
  other elements to rows of the model;
- **permissions**: *View customers*, with *Create*, *Edit* and *Delete* beneath it.

When your app registers a policy for the model (`Gate::policy()`), core asks the policy instead of
these permissions.

## Wingman's own pages

**Utilities → Wingman** shows model health, with Backfill and Delete orphans buttons (see
[Health, backfill and orphans](/plugins/craft-wingman/docs/health/)). It, and the actions behind
its buttons, need the *Wingman* utility permission. People with permission to view a model's
elements but not the utility can use the index, and nothing else.

Every action is a POST with Craft's CSRF protection and a permission check. Model handles sent to
it are checked against the registered models, so nothing else can be named.

## Editions

| | Lite | Pro |
|---|---|---|
| Models registered from the control panel | ✓ | ✓ |
| Index, editor, relation field type, permissions | ✓ | ✓ |
| Health, backfill (queued for big tables), orphan cleanup | ✓ | ✓ |
| `craft.customers()` in Twig and Blade, `wingman.element(model)` | ✓ | ✓ |
| Reverse lookups (`$customer->craftEntries()`, `wingman.relatedEntries()`) | | ✓ |
| A condition rule per model attribute | | ✓ |
| GraphQL queries and typed relation fields | | ✓ |
