# Wingman

Introduce your app's Eloquent models to Craft — elements, relation fields, reverse lookups and GraphQL, configured in the control panel instead of in PHP.

> [!WARNING]
> **Craft 6 only, and in beta.** Wingman is a native **Craft CMS 6** plugin. Craft 6 is still in beta,
> and so is Wingman (`6.0.0-beta.1`). Try it on development and staging sites first and **use caution on
> production**: keep backups, and expect changes between betas. It does not run on Craft 5.

Docs: [justinholt.com/plugins/craft-wingman](https://justinholt.com/plugins/craft-wingman/docs/). See [PLAN.md](PLAN.md).

## Requirements

- Craft CMS 6.0.0-beta.1 or later (native Craft 6 plugin, no Yii 2 adapter)
- PHP 8.5
- Your own Eloquent models. Wingman builds on Craft 6's `CraftCms\Cms\Element\Eloquent` layer.

## What it does

Craft 6 core can turn an Eloquent model into an element, but each model needs an element class, a
field class and a registration call in PHP. Wingman does that from **Settings → Plugins → Wingman**:
pick a model, give it a handle (`Customer`) and a label, and it gets

- a control panel index, editor and permissions (*View/Create/Edit/Delete customers*);
- a relation field type, so entries can relate to rows of your model;
- `craft.customers()` in Twig and Blade;
- a label column, trash behaviour (keep or soft-delete the row) and sidebar visibility per model.

Registration is guarded: handles that would collide with another element type or CP page, Craft's
own models (`App\Models\User` extends Craft's user model), unknown classes, missing label columns
and removing a model that has elements are all refused with a message, and a model that breaks at
boot is skipped and logged instead of taking the site down.

## Health, backfill and orphans

**Utilities → Wingman** shows rows, rows with elements, missing elements and orphans per model.
**Backfill** creates missing elements (queued past 500 rows) without writing back to your rows;
**Delete orphans** removes elements whose row was deleted outside Eloquent.

```bash
php artisan craft:wingman:status
php artisan craft:wingman:backfill [handle] [--dry-run] [--queue]
php artisan craft:wingman:cleanup [handle] [--dry-run]
```

## Templates

```twig
{% for customer in craft.customers().orderBy('name').all() %}{{ customer }} — {{ customer.company }}{% endfor %}
{% set element = wingman.element(order.customer) %}
```

```blade
@foreach ($craft->customers()->orderBy('name')->all() as $customer){{ $customer }} — {{ $customer->company }}@endforeach
@php($element = $wingman->element($order->customer))
```

## Pro

- **Reverse lookups:** `use HasCraftElements` on a model, then `$customer->craftEntries('customers')`,
  `craftRelated('asset', 'photos')`, and `Customer::loadCraftEntries($customers)` for lists without
  N+1. In templates: `wingman.relatedEntries(customer, 'customers')`.
- **Condition rules** per model attribute for indexes, custom sources and relation fields.
- **GraphQL:** `wingmanCustomers`, `wingmanCustomer`, `wingmanCustomersCount` and typed relation
  fields, each model behind its own schema permission. `$hidden` attributes are never exposed,
  filtered or sorted on.

## Multi-site

Model elements are non-localized, as in core: one element per row, the same on every site. Labels
translate through the `site` category; Wingman's own strings through `wingman`.

## Editions and pricing

Lite (free) / Pro (price TBD).

## Development

```bash
composer install
composer test        # Pest
composer phpstan     # Larastan level 6
composer check-cs    # Pint
tests/Harness/check.sh   # live checks against the Craft 6 harness
```
