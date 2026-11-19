---
title: Installation
slug: installation
order: 10
summary: Requirements, the Craft 6 beta caution, install, and your first model.
---

> **Craft 6 only, and still in beta.** Wingman is a native **Craft CMS 6** plugin. Craft 6 is
> still in beta, and so is Wingman (`6.0.0-beta.1`). Try it on development and staging sites
> first, and **use caution before running it on a production site**: keep backups, and expect
> things to change between betas. It does not run on Craft 5.

## Requirements

- Craft CMS **6.0.0-beta.1** or later (native Craft 6, no Yii 2 adapter)
- PHP 8.5
- Your own Eloquent models, in your app (`app/Models` by default)

Wingman builds on Craft 6's own Eloquent elements (`CraftCms\Cms\Element\Eloquent`). Core can
already turn a model into an element, but only if you write an element class, a field class and a
registration call for each one. Wingman does that part for you, from the control panel.

## Install

```bash
composer require justinholtweb/craft-wingman
php artisan craft:plugin:install wingman
```

Or install it from the Plugin Store in the control panel.

## Your first model

1. **Settings → Plugins → Wingman → Add a model.** Pick the model class from the list, give it
   a handle such as `Customer`, and a label such as `Customers`.
2. Save. A **Customers** item appears in the sidebar, with an index, an editor and Craft
   permissions. A **Customers** relation field type appears under Settings → Fields.
3. Your existing rows don't have elements yet. Open **Utilities → Wingman** and click
   **Backfill**, or run `php artisan craft:wingman:backfill`.

From then on, core keeps elements in step with your model: an Eloquent `create()`, `save()`,
soft delete or restore updates the element, and saving the element in the control panel writes
the row.

**Handles are permanent once a model has elements.** Craft stores the generated class names
(`JustinHoltWeb\Wingman\Generated\Elements\Customer`) in its own tables and in project config.
