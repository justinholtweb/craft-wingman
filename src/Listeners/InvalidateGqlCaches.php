<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Listeners;

use CraftCms\Cms\Plugin\Events\PluginSettingsSaved;
use CraftCms\Cms\Support\Facades\Gql;

/**
 * Registered models are GraphQL types and schema permissions, so a change to them
 * has to reach cached GraphQL results.
 */
class InvalidateGqlCaches
{
    public function handle(PluginSettingsSaved $event): void
    {
        if ($event->plugin->handle === 'wingman') {
            Gql::invalidateCaches();
        }
    }
}
