---
title: Multi-site and translations
slug: multisite
order: 45
summary: Why model elements are the same on every site, and how to translate their labels.
---

## One element per row, on every site

A model row has one value per column, so its element has one too. Craft 6 core makes Eloquent
elements **non-localized**: each one lives on the primary site, and Craft answers queries for
them there whatever site the request is for. Wingman keeps that.

In practice:

- `craft.customers()` returns the same elements on every site, in Twig and Blade;
- the index has no site menu, and saving an element doesn't propagate it to other sites;
- relations to them belong to the element doing the relating (an entry's Customers field), so
  whether they differ per site is up to that field's translation setting. The customers
  themselves are shared.

If you need per-site values, keep them in your model (a `name_fr` column, a translations table)
and choose which to print in your templates.

## Translating labels

- A model's **label** (*Customers*) goes through Craft's `site` translation category. Add it to
  `lang/fr/site.php` in your project: `'Customers' => 'Clients'`.
- Wingman's own control panel strings use the `wingman` category. Copy the plugin's
  `lang/en/wingman.php` to `lang/<locale>/wingman.php` in your project to translate them.
