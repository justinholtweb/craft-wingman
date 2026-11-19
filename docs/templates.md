---
title: Templates (Twig and Blade)
slug: templates
order: 30
summary: Query a model's elements, print them, and get from a model row to its element.
---

Craft 6 renders `.twig` and `.blade.php` templates alike, and everything here works in both.

## Querying

Each model gets a template query named after the plural of its handle, camel-cased:
`Customer` → `craft.customers()`, `WingmanInvoice` → `craft.wingmanInvoices()`.

```twig
{% for customer in craft.customers().orderBy('name').all() %}
  <li>{{ customer }} — {{ customer.company }}</li>
{% endfor %}
```

```blade
@foreach ($craft->customers()->orderBy('name')->all() as $customer)
  <li>{{ $customer }} — {{ $customer->company }}</li>
@endforeach
```

Model columns read as element properties (`customer.company`). Printing the element prints its
label column. `customer.getModel()` returns the Eloquent model itself.

## From a model to its element

```twig
{% set element = wingman.element(order.customer) %}
{{ element ? element.url }}
```

```blade
@php($element = $wingman->element($order->customer))
```

`wingman.element()` returns `null` for anything that isn't a registered model's row with an element.

## Related entries (Pro)

```twig
{# Entries relating to this customer through the `customers` field #}
{% for entry in wingman.relatedEntries(customer, 'customers').all() %}…{% endfor %}

{# Through any Wingman field, from a model or its element #}
{{ wingman.relatedEntries(customer.getModel()).count() }}

{# Other element types, by reference handle #}
{% set photos = wingman.related(customer, 'asset', 'photos').all() %}
```

```blade
@foreach ($wingman->relatedEntries($customer, 'customers')->all() as $entry) … @endforeach
```

These return ordinary element queries for the current site, with Craft's normal status rules.
A field handle that isn't a relation field to that model is an error, not an empty result.
