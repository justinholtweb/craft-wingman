<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Registry;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The app models Wingman exposes to Craft, keyed by handle (`Customer`).
 *
 * Craft's Eloquent element API is static per class (`modelClass()`, the relation
 * field type, field layouts and permissions are all keyed on `static::class`), so
 * every registered model gets its own small generated element and field class,
 * minted on demand by {@see GeneratedClassLoader}. Their names are stable, which
 * matters: Craft stores them in `elements.type` and in project config.
 */
class ModelRegistry
{
    public const string ELEMENT_NAMESPACE = 'JustinHoltWeb\\Wingman\\Generated\\Elements\\';

    public const string FIELD_NAMESPACE = 'JustinHoltWeb\\Wingman\\Generated\\Fields\\';

    public const string TRASH_KEEP = 'keep';

    public const string TRASH_SOFT_DELETE = 'softDelete';

    /** @var array<string, array{handle: string, modelClass: class-string<Model>, label: ?string, labelColumn: ?string, trash: ?string, nav: bool}> */
    private array $models = [];

    /**
     * @param  class-string<Model>|string  $modelClass
     * @param  array{labelColumn?: ?string, trash?: ?string, nav?: bool}  $options
     */
    public function add(string $handle, string $modelClass, ?string $label = null, array $options = []): void
    {
        if (! self::isValidHandle($handle)) {
            throw new InvalidArgumentException("Invalid Wingman model handle: $handle");
        }

        if (! is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException("$modelClass is not an Eloquent model.");
        }

        if (ModelDiscovery::isCraftModel($modelClass)) {
            throw new InvalidArgumentException("$modelClass is one of Craft’s own models.");
        }

        $trash = $options['trash'] ?? null;

        if ($trash !== null && ! in_array($trash, [self::TRASH_KEEP, self::TRASH_SOFT_DELETE], true)) {
            throw new InvalidArgumentException("Invalid trash behaviour: $trash");
        }

        $this->models[$handle] = [
            'handle' => $handle,
            'modelClass' => $modelClass,
            'label' => $label,
            'labelColumn' => $options['labelColumn'] ?? null,
            'trash' => $trash,
            'nav' => $options['nav'] ?? true,
        ];
    }

    public function remove(string $handle): void
    {
        unset($this->models[$handle]);
    }

    public function has(string $handle): bool
    {
        return isset($this->models[$handle]);
    }

    /** @return list<string> */
    public function handles(): array
    {
        return array_keys($this->models);
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(string $handle): string
    {
        return $this->models[$handle]['modelClass'] ?? throw new InvalidArgumentException("Unknown Wingman model: $handle");
    }

    public function label(string $handle): ?string
    {
        return $this->models[$handle]['label'] ?? null;
    }

    /** The column chosen in the CP as the element's label, if any. */
    public function labelColumn(string $handle): ?string
    {
        return $this->models[$handle]['labelColumn'] ?? null;
    }

    /** `keep`, `softDelete`, or null for core's default. */
    public function trash(string $handle): ?string
    {
        return $this->models[$handle]['trash'] ?? null;
    }

    public function showInNav(string $handle): bool
    {
        return $this->models[$handle]['nav'] ?? true;
    }

    /** The handle a model class is registered under, if it is. */
    public function handleForModel(string $modelClass): ?string
    {
        $modelClass = ltrim($modelClass, '\\');

        foreach ($this->models as $handle => $model) {
            if ($model['modelClass'] === $modelClass) {
                return $handle;
            }
        }

        return null;
    }

    public static function elementClass(string $handle): string
    {
        return self::ELEMENT_NAMESPACE.$handle;
    }

    public static function fieldClass(string $handle): string
    {
        return self::FIELD_NAMESPACE.$handle.'Field';
    }

    /** `JustinHoltWeb\Wingman\Generated\Elements\Customer` → `Customer` */
    public static function handleFromElementClass(string $class): ?string
    {
        return self::stripNamespace($class, self::ELEMENT_NAMESPACE, '');
    }

    /** `JustinHoltWeb\Wingman\Generated\Fields\CustomerField` → `Customer` */
    public static function handleFromFieldClass(string $class): ?string
    {
        return self::stripNamespace($class, self::FIELD_NAMESPACE, 'Field');
    }

    /**
     * Handles become PHP class names, so they're held to a strict shape:
     * nothing here can ever reach the generated code except a plain identifier.
     */
    public static function isValidHandle(string $handle): bool
    {
        return (bool) preg_match('/^[A-Z][A-Za-z0-9]{0,63}$/D', $handle);
    }

    private static function stripNamespace(string $class, string $namespace, string $suffix): ?string
    {
        $class = ltrim($class, '\\');

        if (! str_starts_with($class, $namespace)) {
            return null;
        }

        $short = substr($class, strlen($namespace));

        if ($suffix !== '') {
            if (! str_ends_with($short, $suffix)) {
                return null;
            }
            $short = substr($short, 0, -strlen($suffix));
        }

        return self::isValidHandle($short) ? $short : null;
    }
}
