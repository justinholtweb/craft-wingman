<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Elements;

use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface;
use CraftCms\Cms\Element\Eloquent\EloquentElement;
use CraftCms\Cms\Element\Eloquent\ModelTrashBehavior;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use JustinHoltWeb\Wingman\Conditions\WingmanCondition;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use Override;
use RuntimeException;

use function CraftCms\Cms\t;

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

    /**
     * The label is site content, written by an admin, so it's translated like other
     * user-defined labels: through the `site` category (`lang/fr/site.php`).
     */
    #[Override]
    public static function displayName(): string
    {
        $label = app(ModelRegistry::class)->label(static::wingmanHandle());

        return t($label !== null ? Str::singular($label) : Str::headline(static::wingmanHandle()), category: 'site');
    }

    #[Override]
    public static function pluralDisplayName(): string
    {
        $label = app(ModelRegistry::class)->label(static::wingmanHandle());

        return t($label ?? Str::plural(Str::headline(static::wingmanHandle())), category: 'site');
    }

    /** The column chosen in the CP, else core's default (`name`, then `title`). */
    #[Override]
    public static function labelAttribute(): ?string
    {
        $column = app(ModelRegistry::class)->labelColumn(static::wingmanHandle());

        return $column !== null && in_array($column, static::modelColumns(), true) ? $column : parent::labelAttribute();
    }

    #[Override]
    public static function trashBehavior(): ModelTrashBehavior
    {
        return match (app(ModelRegistry::class)->trash(static::wingmanHandle())) {
            ModelRegistry::TRASH_SOFT_DELETE => ModelTrashBehavior::SoftDelete,
            ModelRegistry::TRASH_KEEP => ModelTrashBehavior::Keep,
            default => parent::trashBehavior(),
        };
    }

    /** Left out of the sidebar when “Show in the sidebar” is off; the index still works. */
    #[Override]
    public static function cpNavItem(): ?NavItem
    {
        return app(ModelRegistry::class)->showInNav(static::wingmanHandle()) ? parent::cpNavItem() : null;
    }

    #[Override]
    public static function relationFieldType(): ?string
    {
        return ModelRegistry::fieldClass(static::wingmanHandle());
    }

    /**
     * The label column's value, so `{{ customer }}` prints “Grace Hopper”, not
     * “Customer 27” (Eloquent elements have no title of their own).
     */
    #[Override]
    public function __toString(): string
    {
        return $this->uiLabel() ?? parent::__toString();
    }

    /** Craft's element condition, plus (Pro) a rule per listed model attribute. */
    #[Override]
    public static function createCondition(): ElementConditionInterface
    {
        return new WingmanCondition(static::class);
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
