<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Gql;

use BackedEnum;
use CraftCms\Cms\Gql\GqlEntityRegistry;
use CraftCms\Cms\Gql\Types\DateTime as DateTimeType;
use DateTimeInterface;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use JsonSerializable;
use JustinHoltWeb\Wingman\Elements\WingmanElement;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use Stringable;
use Throwable;
use UnitEnum;

/**
 * One GraphQL object type per registered model (`WingmanCustomer`): the element's
 * id, uid, label and dates, plus the model's attributes — only those its own
 * serialization would show (never `$hidden`, and only `$visible` when it sets one).
 */
class ModelTypes
{
    /** @var array<string, list<string>> */
    private static array $attributes = [];

    public static function typeName(string $handle): string
    {
        return "Wingman{$handle}";
    }

    /**
     * The model attributes GraphQL may expose for a handle.
     *
     * @return list<string>
     */
    public static function exposedAttributes(string $handle): array
    {
        return self::$attributes[$handle] ??= (function () use ($handle): array {
            /** @var class-string<WingmanElement> $type */
            $type = ModelRegistry::elementClass($handle);
            $model = $type::newModel();
            $visible = $model->getVisible();

            return array_values(array_filter(
                $type::listedModelAttributes(),
                fn (string $attribute) => self::isSafeName($attribute)
                    && ! in_array($attribute, $model->getHidden(), true)
                    && ($visible === [] || in_array($attribute, $visible, true)),
            ));
        })();
    }

    /** GraphQL names, and not one of the element fields every type has. */
    private static function isSafeName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)
            && ! in_array($name, ['id', 'uid', 'label', 'dateCreated', 'dateUpdated', '__typename'], true);
    }

    public static function type(string $handle): ObjectType
    {
        return GqlEntityRegistry::getOrCreate(self::typeName($handle), fn () => new ObjectType([
            'name' => self::typeName($handle),
            'description' => sprintf('A %s (Wingman).', ModelRegistry::elementClass($handle)::displayName()),
            'fields' => fn () => self::fields($handle),
        ]));
    }

    /** @return array<string, array<string, mixed>> */
    private static function fields(string $handle): array
    {
        /** @var class-string<WingmanElement> $elementType */
        $elementType = ModelRegistry::elementClass($handle);
        $model = $elementType::newModel();
        $columnTypes = self::columnTypes($model);
        $labelAttribute = $elementType::labelAttribute();
        $visible = $model->getVisible();
        $labelHidden = $labelAttribute === null
            || in_array($labelAttribute, $model->getHidden(), true)
            || ($visible !== [] && ! in_array($labelAttribute, $visible, true));

        $fields = [
            'id' => ['type' => Type::nonNull(Type::id()), 'resolve' => fn (WingmanElement $e) => $e->id],
            'uid' => ['type' => Type::nonNull(Type::string()), 'resolve' => fn (WingmanElement $e) => $e->uid],
            'label' => [
                'type' => Type::string(),
                'description' => 'The element’s label (its label column).',
                'resolve' => fn (WingmanElement $e) => $labelHidden ? null : self::scalar($e->getModel()->getAttribute((string) $labelAttribute)),
            ],
            'dateCreated' => ['type' => DateTimeType::getType(), 'resolve' => fn (WingmanElement $e) => $e->dateCreated],
            'dateUpdated' => ['type' => DateTimeType::getType(), 'resolve' => fn (WingmanElement $e) => $e->dateUpdated],
        ];

        foreach (self::exposedAttributes($handle) as $attribute) {
            $gqlType = self::gqlType($model, $attribute, $columnTypes[$attribute] ?? '');
            $fields[$attribute] = [
                'type' => $gqlType,
                'resolve' => function (WingmanElement $e) use ($attribute, $gqlType): mixed {
                    $value = self::scalar($e->getModel()->getAttribute($attribute));

                    return $gqlType === Type::string() && $value !== null && ! is_string($value) ? (string) json_encode($value) : $value;
                },
            ];
        }

        return $fields;
    }

    public static function whereType(): InputObjectType
    {
        return GqlEntityRegistry::getOrCreate('WingmanWhereInput', fn () => new InputObjectType([
            'name' => 'WingmanWhereInput',
            'fields' => [
                'attribute' => ['name' => 'attribute', 'type' => Type::nonNull(Type::string())],
                'value' => ['name' => 'value', 'type' => Type::string(), 'description' => 'Matched exactly; null matches empty.'],
            ],
        ]));
    }

    /** @return array<string, string> column → lower-case type name */
    private static function columnTypes(Model $model): array
    {
        try {
            $columns = Schema::connection($model->getConnectionName())->getColumns($model->getTable());
        } catch (Throwable) {
            return [];
        }

        $types = [];
        foreach ($columns as $column) {
            $types[(string) $column['name']] = strtolower($column['type_name']);
        }

        return $types;
    }

    private static function gqlType(Model $model, string $attribute, string $columnType): ScalarType
    {
        $cast = strtolower((string) ($model->getCasts()[$attribute] ?? ''));

        return match (true) {
            in_array($cast, ['int', 'integer'], true) => Type::int(),
            in_array($cast, ['bool', 'boolean'], true) => Type::boolean(),
            in_array($cast, ['float', 'double', 'real'], true) => Type::float(),
            $cast !== '' => Type::string(),
            in_array($columnType, ['int', 'integer', 'bigint', 'smallint', 'mediumint', 'int2', 'int4', 'int8'], true) => Type::int(),
            in_array($columnType, ['bool', 'boolean'], true) => Type::boolean(),
            in_array($columnType, ['float', 'double', 'real', 'float4', 'float8'], true) => Type::float(),
            default => Type::string(),
        };
    }

    /** Model values as GraphQL scalars. */
    public static function scalar(mixed $value): mixed
    {
        return match (true) {
            $value === null, is_scalar($value) => $value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof JsonSerializable, is_array($value) => json_encode($value),
            $value instanceof Stringable => (string) $value,
            default => null,
        };
    }
}
