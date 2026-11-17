<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Elements;

use CraftCms\Cms\Element\Eloquent\EloquentElement;
use Illuminate\Database\Eloquent\Model;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use Override;
use RuntimeException;

/**
 * Base for the generated per-model element classes. Everything core asks of an
 * Eloquent element type is answered from the registry by `static::class`.
 *
 * @extends EloquentElement<Model>
 */
abstract class WingmanElement extends EloquentElement
{
    #[Override]
    public static function modelClass(): string
    {
        return app(ModelRegistry::class)->modelClass(static::wingmanHandle());
    }

    #[Override]
    public static function relationFieldType(): ?string
    {
        return ModelRegistry::fieldClass(static::wingmanHandle());
    }

    public static function wingmanHandle(): string
    {
        return ModelRegistry::handleFromElementClass(static::class)
            ?? throw new RuntimeException(static::class.' is not a generated Wingman element.');
    }
}
