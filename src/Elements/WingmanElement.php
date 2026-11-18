<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Elements;

use CraftCms\Cms\Element\Eloquent\EloquentElement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use Override;
use RuntimeException;

/**
 * Base for the generated per-model element classes. Everything core asks of an
 * Eloquent element type is answered from the registry by `static::class`.
 *
 * Identity comes from the Wingman handle, not the model's class name: core's
 * default (`class_basename`) makes an app `User` model collide with Craft's own
 * users, which takes the whole app down. The handle is chosen in the CP and
 * validated against existing element types and CP routes.
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
    public static function refHandle(): ?string
    {
        return self::refHandleFor(static::wingmanHandle());
    }

    #[Override]
    public static function displayName(): string
    {
        $label = app(ModelRegistry::class)->label(static::wingmanHandle());

        return $label !== null ? Str::singular($label) : Str::headline(static::wingmanHandle());
    }

    #[Override]
    public static function pluralDisplayName(): string
    {
        return app(ModelRegistry::class)->label(static::wingmanHandle()) ?? Str::plural(static::displayName());
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

    /** `Customer` → `customer`, `AppUser` → `app-user` */
    public static function refHandleFor(string $handle): string
    {
        return Str::kebab($handle);
    }

    /** `Customer` → `customers` (core's default: the plural of the ref handle) */
    public static function cpIndexUriFor(string $handle): string
    {
        return Str::plural(self::refHandleFor($handle));
    }
}
