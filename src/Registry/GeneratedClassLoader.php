<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Registry;

use JustinHoltWeb\Wingman\Elements\WingmanElement;
use JustinHoltWeb\Wingman\Fields\WingmanField;

/**
 * Autoloads the per-model element and field classes for registered models.
 *
 * Each generated class is an empty, final subclass of WingmanElement or
 * WingmanField; all behaviour lives in those bases and reads the registry by
 * `static::class`. The only variable part of the generated code is the handle,
 * which ModelRegistry restricts to `[A-Z][A-Za-z0-9]*` — so nothing a user types
 * can become code.
 */
class GeneratedClassLoader
{
    public function __construct(private readonly ModelRegistry $registry) {}

    public function register(): void
    {
        spl_autoload_register($this->load(...));
    }

    public function load(string $class): void
    {
        if (($handle = ModelRegistry::handleFromElementClass($class)) !== null && $this->registry->has($handle)) {
            $this->define(ModelRegistry::ELEMENT_NAMESPACE, $handle, WingmanElement::class);
        } elseif (($handle = ModelRegistry::handleFromFieldClass($class)) !== null && $this->registry->has($handle)) {
            $this->define(ModelRegistry::FIELD_NAMESPACE, $handle.'Field', WingmanField::class);
        }
    }

    private function define(string $namespace, string $name, string $parent): void
    {
        if (! ModelRegistry::isValidHandle($name)) {
            return;
        }

        // phpcs:ignore -- generated from a validated identifier only (see class docblock)
        eval(sprintf('namespace %s; final class %s extends \\%s {}', rtrim($namespace, '\\'), $name, $parent));
    }
}
