<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Fields;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Eloquent\EloquentElementsField;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Gql\Data\GqlSchema;
use CraftCms\Cms\Gql\GqlHelper;
use GraphQL\Type\Definition\Type;
use JustinHoltWeb\Wingman\Elements\WingmanElement;
use JustinHoltWeb\Wingman\Gql\ModelResolver;
use JustinHoltWeb\Wingman\Gql\ModelTypes;
use JustinHoltWeb\Wingman\Plugin;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use Override;
use RuntimeException;

/**
 * Base for the generated per-model relation field classes.
 */
abstract class WingmanField extends EloquentElementsField
{
    #[Override]
    public static function elementType(): string
    {
        $handle = ModelRegistry::handleFromFieldClass(static::class)
            ?? throw new RuntimeException(static::class.' is not a generated Wingman field.');

        /** @var class-string<WingmanElement> */
        return ModelRegistry::elementClass($handle);
    }

    public static function wingmanHandle(): string
    {
        return ModelRegistry::handleFromFieldClass(static::class)
            ?? throw new RuntimeException(static::class.' is not a generated Wingman field.');
    }

    /**
     * Pro: in GraphQL, a field relating to a model's elements returns its `Wingman…`
     * type — when the schema may read that model. Core leaves Eloquent relation fields out.
     */
    #[Override]
    public function includeInGqlSchema(GqlSchema $schema): bool
    {
        return Plugin::isPro()
            && app(ModelRegistry::class)->has(static::wingmanHandle())
            && GqlHelper::canSchema(ModelResolver::component(static::wingmanHandle()), schema: $schema);
    }

    /** @return array<string, mixed> */
    #[Override]
    public function getContentGqlType(): array
    {
        $handle = static::wingmanHandle();

        return [
            'name' => $this->handle,
            'type' => Type::nonNull(Type::listOf(Type::nonNull(ModelTypes::type($handle)))),
            'resolve' => function (ElementInterface $source) use ($handle): array {
                if (! ModelResolver::canRead($handle)) {
                    return [];
                }

                $value = $source->getFieldValue((string) $this->handle);
                $elements = match (true) {
                    $value instanceof ElementQueryInterface => $value->all(),
                    is_iterable($value) => iterator_to_array($value, false),
                    default => [],
                };

                return array_values(array_filter($elements, fn ($e) => $e instanceof WingmanElement && ! $e->trashed));
            },
        ];
    }

    /** @return array<string, mixed>|null */
    #[Override]
    public function getEagerLoadingGqlConditions(): ?array
    {
        return ModelResolver::canRead(static::wingmanHandle()) ? [] : null;
    }
}
