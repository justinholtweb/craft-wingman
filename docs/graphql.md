---
title: GraphQL
slug: graphql
order: 35
summary: Per-model queries and typed relation fields (Pro), scoped per schema, never exposing $hidden.
---

Core Craft 6 leaves Eloquent elements out of GraphQL. **Wingman Pro** adds them.

## Permissions

Each model adds a schema permission under **Wingman**: *Query Customers*. Nothing is readable
until it's ticked. A token tied to a user also needs that user to be allowed to view the model's
elements (its *View* permission, or the model policy's `viewAny`).

## Queries

For a model with the handle `Customer`:

```graphql
{
  wingmanCustomers(where: [{attribute: "company", value: "NASA"}], orderBy: "name desc", limit: 20, offset: 0) {
    id uid label dateCreated dateUpdated
    name company
  }
  wingmanCustomer(id: [27]) { name }
  wingmanCustomersCount(where: [{attribute: "company", value: "NASA"}])
}
```

- Field types follow the model's casts (`integer` → `Int`, `boolean` → `Boolean`, `float` →
  `Float`), then its column types. Dates and JSON come back as strings.
- `where` matches exactly and only on exposed attributes. An unknown attribute is an error,
  never a wider query.
- `orderBy` takes one exposed attribute (or `id`, `dateCreated`, `dateUpdated`) and `asc`/`desc`.
- `limit` is capped at 100.
- Trashed elements are never returned.

A handle whose plural is itself gets `List`: `Sheep` → `wingmanSheepList`.

## What's never exposed

Attributes in the model's `$hidden` (and, when it sets `$visible`, everything outside it) aren't
fields, can't be filtered on and can't be sorted on. That includes the label: if the label
column is hidden, `label` is `null`.

## Relation fields

With the permission ticked, a Wingman relation field on an entry type returns its model's type:

```graphql
{
  entries(section: "articles") {
    ... on article_Entry { title customers { name company } }
  }
}
```

For a schema without the permission, the field isn't in the schema at all.
