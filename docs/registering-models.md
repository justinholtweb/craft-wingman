---
title: Registering models
slug: registering-models
order: 20
summary: Handles, labels, label columns, trash behaviour, the sidebar, and config files.
---

Each row under **Settings → Plugins → Wingman → Models** registers one Eloquent model.

| Column | What it does |
|---|---|
| **Handle** | `Customer`. A capital letter, then letters and numbers. It becomes a class name, the element type's reference handle (`customer`), its control panel URI (`customers`), its template query (`craft.customers()`) and its GraphQL type (`WingmanCustomer`). |
| **Eloquent model** | Only concrete Eloquent models found in the model directories are offered. |
| **Label** | The plural name in the sidebar and field type list (`Customers`). Translatable through the `site` category. |
| **Label column** | The column shown as each element's name. Defaults to `name`, then `title`. Can't be a `$hidden` column. |
| **Trashing an element** | *Default*: soft-deletes the row if the model uses `SoftDeletes`, otherwise keeps it. *Keeps the row* or *Soft-deletes the row* override that. Deleting an element from the trash for good always deletes the row. |
| **Sidebar** | *Hide* leaves the model out of the sidebar. Its index still works at its URL. |

**Model directories** lists where to look for models, one per line, relative to the project root
(default `app/Models`). Directories outside the project, and `vendor/`, are never scanned.

## What Wingman refuses

Saving is refused, with a message per row, when:

- a handle isn't a valid identifier, or is used twice;
- a handle's reference handle or control panel URI is already taken (a handle of `Entry`, `User`
  or `Asset` would collide with Craft's own element types);
- the class isn't an Eloquent model in the model directories, or is registered twice;
- the class is one of **Craft's own models**, or extends one. In a Craft 6 project,
  `App\Models\User` extends Craft's user model, and its rows are already Craft users;
- the label column doesn't exist or is hidden;
- *Soft-deletes the row* is chosen for a model without `SoftDeletes`;
- a model that has elements is removed, or its handle is pointed at another model.

At boot each model is registered on its own. If one fails (its class was deleted, say), it's
skipped and logged, and the rest of the site keeps working. Its elements fall back to Craft's
missing-element handling until it's back.

## Config file

`config/craft/wingman.php` overrides the control panel, like any plugin setting:

```php
return [
    'modelPaths' => ['app/Models', 'app/Domain/Billing/Models'],
    'models' => [
        'Customer' => ['modelClass' => App\Models\Customer::class, 'label' => 'Customers'],
        'Invoice' => [
            'modelClass' => App\Models\Invoice::class,
            'labelColumn' => 'number',
            'trash' => 'softDelete',   // or 'keep'
            'nav' => false,
        ],
    ],
];
```

## What the element shows

Core exposes every column except the primary key, the timestamps, the soft-delete column and
any named like an element property. Columns in the model's `$hidden` are kept out of index
columns, sort options, search keywords, sandboxed templates and GraphQL.
