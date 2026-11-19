<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Listeners;

use CraftCms\Cms\Gql\Events\GqlSchemaComponentsResolving;
use JustinHoltWeb\Wingman\Gql\ModelResolver;
use JustinHoltWeb\Wingman\Plugin;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;

use function CraftCms\Cms\t;

/**
 * Pro: one GraphQL schema permission per registered model — “Query Customers”.
 * Nothing is readable until it's ticked.
 */
class RegisterGqlComponents
{
    public function __construct(private readonly ModelRegistry $registry) {}

    public function handle(GqlSchemaComponentsResolving $event): void
    {
        if (! Plugin::isPro()) {
            return;
        }

        $components = [];

        foreach ($this->registry->handles() as $handle) {
            $components[ModelResolver::component($handle).':read'] = [
                'label' => t('Query {type}', ['type' => ModelRegistry::elementClass($handle)::pluralDisplayName()], category: 'wingman'),
                'info' => t('Adds the `wingman{handle}…` queries and relation fields to {handle} elements. Hidden model attributes are never exposed.', ['handle' => $handle], category: 'wingman'),
                // Tokens tied to a user also need that user's permission to view them.
                'authenticated' => true,
            ];
        }

        if ($components !== []) {
            $event->queries['Wingman'] = $components;
        }
    }
}
