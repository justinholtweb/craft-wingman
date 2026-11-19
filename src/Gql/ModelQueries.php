<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Gql;

use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\Cms\Gql\Queries\Query;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Str;
use JustinHoltWeb\Wingman\Plugin;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;

/**
 * Pro: three read-only queries per registered model the schema may read
 * (“Wingman → Query Customers”): `wingmanCustomers`, `wingmanCustomer` and
 * `wingmanCustomersCount`. Trashed elements are never returned.
 *
 * @phpstan-import-type UnnamedFieldDefinitionConfig from FieldDefinition
 */
class ModelQueries extends Query
{
    /** @return array<string, UnnamedFieldDefinitionConfig> */
    public static function getQueries(bool $checkToken = true): array
    {
        if (! Plugin::isPro()) {
            return [];
        }

        $queries = [];

        foreach (app(ModelRegistry::class)->handles() as $handle) {
            if ($checkToken && ! GqlHelper::canSchema(ModelResolver::component($handle))) {
                continue;
            }

            $type = ModelTypes::type($handle);
            $plural = self::plural($handle);
            $label = ModelRegistry::elementClass($handle)::pluralLowerDisplayName();
            $singular = ModelRegistry::elementClass($handle)::lowerDisplayName();

            $queries["wingman{$plural}"] = [
                'type' => Type::nonNull(Type::listOf(Type::nonNull($type))),
                'args' => self::args(true),
                'resolve' => fn ($source, array $args) => ModelResolver::all($handle, $args),
                'description' => "{$label}, not including trashed ones.",
                'complexity' => GqlHelper::relatedArgumentComplexity(),
            ];
            $queries["wingman{$handle}"] = [
                'type' => $type,
                'args' => self::args(false),
                'resolve' => fn ($source, array $args) => ModelResolver::one($handle, $args),
                'description' => "One {$singular}, by `id` or `where`.",
            ];
            $queries["wingman{$plural}Count"] = [
                'type' => Type::nonNull(Type::int()),
                'args' => self::args(false),
                'resolve' => fn ($source, array $args) => ModelResolver::count($handle, $args),
                'description' => "How many {$label} match.",
            ];
        }

        return $queries;
    }

    /** `Customer` → `Customers`; a handle whose plural is itself gets `List` (`Sheep` → `SheepList`). */
    public static function plural(string $handle): string
    {
        $plural = Str::pluralStudly($handle);

        return $plural === $handle ? "{$handle}List" : $plural;
    }

    /** @return array<string, array<string, mixed>> */
    private static function args(bool $paged): array
    {
        return [
            'id' => ['name' => 'id', 'type' => Type::listOf(Type::nonNull(Type::id())), 'description' => 'Element IDs.'],
            'where' => ['name' => 'where', 'type' => Type::listOf(Type::nonNull(ModelTypes::whereType())), 'description' => 'Exact matches on exposed attributes, e.g. `{attribute: "company", value: "Acme"}`.'],
            ...($paged ? [
                'orderBy' => ['name' => 'orderBy', 'type' => Type::string(), 'description' => 'An exposed attribute, `id`, `dateCreated` or `dateUpdated`, optionally followed by `asc` or `desc`.'],
                'limit' => ['name' => 'limit', 'type' => Type::int(), 'description' => 'At most 100 (the default).'],
                'offset' => ['name' => 'offset', 'type' => Type::int()],
            ] : []),
        ];
    }
}
