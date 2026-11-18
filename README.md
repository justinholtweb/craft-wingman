# Wingman

Introduce your app's Eloquent models to Craft — elements, relation fields and reverse lookups, configured in the CP instead of in PHP.

> **Status:** phase 1 (CP registration, health, backfill) working in the Craft 6 harness. See [PLAN.md](PLAN.md).

## Requirements

- Craft CMS 6.0.0-beta.1 or later (native Craft 6 plugin, no Yii 2 adapter)
- PHP 8.5
- Laravel integration: Eloquent (core). Builds on Craft 6's own `CraftCms\Cms\Element\Eloquent` layer.

## Editions and pricing

Lite / Pro — Lite free / Pro $49 (provisional).

## Development

```bash
composer install
composer test        # Pest
composer phpstan     # Larastan level 6
composer check-cs    # Pint
```

Tested in the Craft 6 harness at `~/Sites/plugin-testing-craft6` (`bin/plugin-wire craft-wingman --install`).
