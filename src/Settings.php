<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman;

use Closure;
use CraftCms\Cms\Element\ElementTypes;
use CraftCms\Cms\Plugin\PluginSettings;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use JustinHoltWeb\Wingman\Elements\WingmanElement;
use JustinHoltWeb\Wingman\Registry\ModelDiscovery;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use JustinHoltWeb\Wingman\Sync\ModelHealth;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function CraftCms\Cms\cp_url;

/**
 * Blank-able properties are nullable: Laravel's ConvertEmptyStringsToNull middleware
 * turns an empty field into null before Craft assigns it.
 */
class Settings extends PluginSettings
{
    /**
     * Directories scanned for Eloquent models, one per line (default: app/Models).
     *
     * @var string|list<string>|null
     */
    public string|array|null $modelPaths = null;

    /**
     * Registered models. The CP stores table rows
     * (`[['handle' => 'Customer', 'modelClass' => '…', 'label' => '…'], …]`);
     * `config/craft/wingman.php` may also use a map (`'Customer' => ['modelClass' => '…']`).
     *
     * @var array<array-key, mixed>|null
     */
    public ?array $models = [];

    /**
     * @return list<string>
     */
    public function modelPathList(): array
    {
        $items = is_array($this->modelPaths) ? $this->modelPaths : preg_split('/[\r\n,]+/', (string) $this->modelPaths);
        $items = array_values(array_filter(array_map(trim(...), $items ?: []), fn (string $v) => $v !== ''));

        return $items ?: ['app/Models'];
    }

    /**
     * Both storage shapes, normalised.
     *
     * @return list<array{handle: string, modelClass: string, label: ?string}>
     */
    public function modelDefinitions(): array
    {
        $definitions = [];

        foreach ($this->models ?? [] as $key => $row) {
            if (! is_array($row)) {
                continue;
            }

            $handle = is_string($key) ? $key : ($row['handle'] ?? null);
            $label = $row['label'] ?? null;

            $definitions[] = [
                'handle' => is_string($handle) ? trim($handle) : '',
                'modelClass' => is_string($row['modelClass'] ?? null) ? ltrim(trim($row['modelClass']), '\\') : '',
                'label' => is_string($label) && trim($label) !== '' ? trim($label) : null,
            ];
        }

        return $definitions;
    }

    /**
     * @return array<string, list<string|Closure>>
     */
    public function getRules(): array
    {
        return [
            'models' => ['nullable', 'array', $this->validateModels(...)],
        ];
    }

    private function validateModels(string $attribute, mixed $value, Closure $fail): void
    {
        $discovered = app(ModelDiscovery::class)->discover($this->modelPathList());
        $seen = [];

        foreach ($this->modelDefinitions() as $i => ['handle' => $handle, 'modelClass' => $class]) {
            $row = $i + 1;

            if (! ModelRegistry::isValidHandle($handle)) {
                $fail("Row {$row}: the handle must start with a capital letter and contain only letters and numbers (e.g. Customer).");
            } elseif (isset($seen[$handle])) {
                $fail("Row {$row}: the handle {$handle} is used more than once.");
            }
            $seen[$handle] = true;

            if (ModelRegistry::isValidHandle($handle) && ($conflict = self::identityConflict($handle)) !== null) {
                $fail("Row {$row}: {$conflict} Choose another handle.");
            }

            if (! isset($discovered[$class])) {
                $fail("Row {$row}: {$class} isn't an Eloquent model in the model directories.");
            } elseif (ModelRegistry::isValidHandle($handle) && ($linked = ModelHealth::linkedModelType($handle)) !== null && $linked !== new $class()->getMorphClass()) {
                $fail("Row {$row}: {$handle} already has elements linked to {$linked}; it can't be pointed at another model.");
            }
        }

        foreach ($this->previouslyRegisteredHandles() as $handle) {
            if (! isset($seen[$handle]) && ModelHealth::hasElements($handle)) {
                $fail("{$handle} can't be removed while it has elements. Delete them first, or keep the model registered.");
            }
        }
    }

    /**
     * Why a handle's ref handle or CP index URI can't be used, if it can't. Core
     * would otherwise throw while registering it, at boot, on every request.
     */
    public static function identityConflict(string $handle): ?string
    {
        $ours = ModelRegistry::elementClass($handle);
        $refHandle = WingmanElement::refHandleFor($handle);
        $existing = app(ElementTypes::class)->typeByRefHandle($refHandle);

        if ($existing !== null && $existing !== $ours) {
            return "{$handle} would share the reference handle “{$refHandle}” with {$existing}.";
        }

        $uri = WingmanElement::cpIndexUriFor($handle);

        foreach ([$uri, "{$uri}/new"] as $path) {
            try {
                $route = app(Router::class)->getRoutes()->match(Request::create(cp_url($path)));
            } catch (HttpException) {
                continue;
            }

            if (! $route->isFallback && ($route->defaults['elementType'] ?? null) !== $ours) {
                return "{$handle}’s control panel page “{$uri}” is already taken.";
            }
        }

        return null;
    }

    /**
     * Handles with elements in the database — removing one would orphan them.
     *
     * @return list<string>
     */
    private function previouslyRegisteredHandles(): array
    {
        return app(ModelRegistry::class)->handles();
    }
}
