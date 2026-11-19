---
title: Pro features
slug: pro
order: 50
summary: Reverse lookups from your models, a condition rule per attribute, and GraphQL.
---

## Reverse lookups from your models

Add the trait to a registered model:

```php
use JustinHoltWeb\Wingman\Concerns\HasCraftElements;

class Customer extends Model
{
    use HasCraftElements;
}
```

```php
$customer->craftElement();                        // the customer's element, or null
$customer->craftEntries('customers')->all();      // entries relating to it through the `customers` field
$customer->craftEntries()->count();               // … through any Wingman field
$customer->craftRelated('asset', 'photos')->all(); // other element types, by reference handle
```

Lists without N+1 queries:

```php
$customers = Customer::all();
Customer::loadCraftEntries($customers, 'customers');   // a fixed number of queries for the lot
$customers->first()->getRelation('craftEntries');      // list of entries
```

In templates, the same lookups are `wingman.relatedEntries()` and `wingman.related()` (see
[Templates](/plugins/craft-wingman/docs/templates/)). On Lite, these methods throw
`LogicException: Reverse lookups need Wingman Pro.`

## Condition rules for model attributes

Element indexes, custom sources and relation field selection conditions get a rule per listed
model attribute, under **Model**: *Company is NASA*, *Name begins with Gr*. Hidden attributes
get no rule.

“Related to a customer” needs nothing from Wingman: Craft's own **Related To** rule already offers
every registered model.

## GraphQL

See [GraphQL](/plugins/craft-wingman/docs/graphql/).
