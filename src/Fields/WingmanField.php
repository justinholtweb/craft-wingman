<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Fields;

use CraftCms\Cms\Element\Eloquent\EloquentElementsField;
use JustinHoltWeb\Wingman\Elements\WingmanElement;
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
}
