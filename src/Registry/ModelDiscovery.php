<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Registry;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * Finds the app's concrete Eloquent models by scanning PHP files for their
 * declared namespace + class (tokens, not includes), then checking each class
 * through the autoloader. Only models found here can be registered, which is
 * what keeps arbitrary class names out of the registry.
 */
class ModelDiscovery
{
    /**
     * @param  list<string>  $paths  Directories relative to the app root (or absolute).
     * @return array<class-string<Model>, string> Model class → suggested handle
     */
    public function discover(array $paths): array
    {
        $models = [];

        foreach ($paths as $path) {
            $dir = str_starts_with($path, '/') ? $path : base_path($path);

            if (! is_dir($dir)) {
                continue;
            }

            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if ($file->getExtension() !== 'php' || ($class = self::declaredClass((string) file_get_contents($file->getPathname()))) === null) {
                    continue;
                }

                if (self::isConcreteModel($class)) {
                    $models[$class] = self::suggestHandle($class);
                }
            }
        }

        ksort($models);

        return $models;
    }

    /**
     * The fully qualified name of the first class a PHP file declares, if any.
     */
    public static function declaredClass(string $code): ?string
    {
        $namespace = '';
        $tokens = token_get_all($code);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (! is_array($tokens[$i])) {
                continue;
            }

            if ($tokens[$i][0] === T_NAMESPACE) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_NAME_QUALIFIED, T_STRING], true)) {
                        $namespace = $tokens[$j][1];
                        break;
                    }
                }
            }

            if ($tokens[$i][0] === T_CLASS) {
                // Skip `Foo::class` and `new class`.
                $prev = $tokens[$i - 1] ?? null;
                if (is_array($prev) && $prev[0] === T_DOUBLE_COLON) {
                    continue;
                }

                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        return ltrim($namespace.'\\'.$tokens[$j][1], '\\');
                    }
                    if (! is_array($tokens[$j]) || $tokens[$j][0] !== T_WHITESPACE) {
                        break;
                    }
                }
            }
        }

        return null;
    }

    public static function isConcreteModel(string $class): bool
    {
        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return false;
        }

        $reflection = new ReflectionClass($class);

        return $reflection->isInstantiable() && ! $reflection->isAnonymous();
    }

    /** `App\Models\Customer` → `Customer`, `App\Models\Shop\OrderLine` → `ShopOrderLine` */
    public static function suggestHandle(string $class): string
    {
        $relative = Str::after($class, 'Models\\');
        $handle = Str::studly(str_replace('\\', ' ', $relative));
        $handle = preg_replace('/[^A-Za-z0-9]/', '', $handle) ?? '';

        return ModelRegistry::isValidHandle($handle) ? $handle : 'Model'.substr(md5($class), 0, 8);
    }
}
