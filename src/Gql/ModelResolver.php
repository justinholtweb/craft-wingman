<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Gql;

use CraftCms\Cms\Element\Eloquent\EloquentElementQuery;
use CraftCms\Cms\Gql\GqlHelper;
use CraftCms\Cms\User\Elements\User;
use GraphQL\Error\UserError;
use JustinHoltWeb\Wingman\Elements\WingmanElement;
use JustinHoltWeb\Wingman\Plugin;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;

/**
 * Builds the element queries behind the Wingman GraphQL queries (never trashed
 * elements; Eloquent elements have no statuses of their own). Everything a
 * request can name — attributes, sort columns — is checked against the model's
 * exposed attributes first, so `$hidden` columns can't be read, filtered or sorted on.
 */
class ModelResolver
{
    public const int MAX_LIMIT = 100;

    public static function component(string $handle): string
    {
        return "wingman.{$handle}";
    }

    /** Whether the active schema may read a handle's elements. */
    public static function canRead(string $handle): bool
    {
        if (! Plugin::isPro() || ! app(ModelRegistry::class)->has($handle) || ! GqlHelper::canSchema(self::component($handle))) {
            return false;
        }

        // A token tied to a user also needs that user's own permission (or the model policy's viewAny).
        $user = GqlHelper::getSchemaUser();

        return ! $user instanceof User || ModelRegistry::elementClass($handle)::canViewIndex($user);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return list<WingmanElement>
     */
    public static function all(string $handle, array $args): array
    {
        $query = self::query($handle, $args);
        $query->limit(min(self::MAX_LIMIT, max(1, (int) ($args['limit'] ?? self::MAX_LIMIT))));

        if (isset($args['offset'])) {
            $query->offset(max(0, (int) $args['offset']));
        }

        /** @var list<WingmanElement> */
        return $query->all();
    }

    /** @param array<string, mixed> $args */
    public static function one(string $handle, array $args): ?WingmanElement
    {
        $element = self::query($handle, $args)->one();

        return $element instanceof WingmanElement ? $element : null;
    }

    /** @param array<string, mixed> $args */
    public static function count(string $handle, array $args): int
    {
        return (int) self::query($handle, $args)->count();
    }

    /**
     * @param  array<string, mixed>  $args
     * @return EloquentElementQuery<WingmanElement>
     */
    private static function query(string $handle, array $args): EloquentElementQuery
    {
        if (! self::canRead($handle)) {
            throw new UserError("This schema can’t read {$handle} elements.");
        }

        /** @var class-string<WingmanElement> $type */
        $type = ModelRegistry::elementClass($handle);
        $table = $type::newModel()->getTable();
        $exposed = ModelTypes::exposedAttributes($handle);

        /** @var EloquentElementQuery<WingmanElement> $query */
        $query = $type::find();

        if (isset($args['id'])) {
            $query->id(array_map(intval(...), (array) $args['id']) ?: false);
        }

        foreach ($args['where'] ?? [] as $filter) {
            $attribute = (string) $filter['attribute'];

            // Ignoring an unknown attribute would widen the results, so it's an error instead.
            if (! in_array($attribute, $exposed, true)) {
                throw new UserError("“{$attribute}” isn’t an attribute of {$handle}.");
            }

            $value = $filter['value'] ?? null;
            $value === null ? $query->whereNull("{$table}.{$attribute}") : $query->where("{$table}.{$attribute}", (string) $value);
        }

        if (isset($args['orderBy'])) {
            if (! preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)(?:\s+(asc|desc))?\s*$/Di', (string) $args['orderBy'], $m)) {
                throw new UserError('orderBy must be an attribute name, optionally followed by asc or desc.');
            }

            $column = match (true) {
                in_array($m[1], $exposed, true) => "{$table}.{$m[1]}",
                in_array($m[1], ['id', 'dateCreated', 'dateUpdated'], true) => "elements.{$m[1]}",
                default => throw new UserError("“{$m[1]}” can’t be sorted on."),
            };
            $query->orderBy($column, strtolower($m[2] ?? 'asc'));
        }

        return $query;
    }
}
