<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman;

use CraftCms\Cms\Plugin\PluginSettings;

/**
 * Blank-able properties are nullable: Laravel's ConvertEmptyStringsToNull middleware
 * turns an empty field into null before Craft assigns it.
 */
class Settings extends PluginSettings
{
    /** Model directories. Directories scanned for Eloquent models, one per line (default: app/Models). */
    public string|array|null $modelPaths = null;

    /** Backfill on register. Create elements for existing model rows as soon as a model is registered. */
    public bool $autoBackfill = false;
}
