<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman;

use Closure;
use CraftCms\Cms\Element\ElementTypes;
use CraftCms\Cms\Plugin\PluginSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Schema;
use JustinHoltWeb\Wingman\Elements\WingmanElement;
use JustinHoltWeb\Wingman\Registry\ModelDiscovery;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use JustinHoltWeb\Wingman\Sync\ModelHealth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

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
     * @return list<array{handle: string, modelClass: string, label: ?string, labelColumn: ?string, trash: ?string, nav: bool}>
     */
    public function modelDefinitions(): array
    {
        $definitions = [];

        foreach ($this->models ?? [] as $key => $row) {
            if (! is_array($row)) {
                continue;
            }

            $handle = is_string($key) ? $key : ($row['handle'] ?? null);

            $definitions[] = [
                'handle' => is_string($handle) ? trim($handle) : '',
                'modelClass' => is_string($row['modelClass'] ?? null) ? ltrim(trim($row['modelClass']), '\\') : '',
                'label' => self::blankToNull($row['label'] ?? null),
                'labelColumn' => self::blankToNull($row['labelColumn'] ?? null),
                'trash' => self::blankToNull($row['trash'] ?? null),
                // Shown unless hidden: the CP select posts '' or 'hide'; config files may use a bool.
                'nav' => ! in_array($row['nav'] ?? null, [false, 0, '0', 'hide'], true),
            ];
        }

        return $definitions;
    }

    private static function blankToNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
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
        $seenClasses = [];

        foreach ($this->modelDefinitions() as $i => $definition) {
            ['handle' => $handle, 'modelClass' => $class] = $definition;
            $row = $i + 1;
            $validHandle = ModelRegistry::isValidHandle($handle);

            if (! $validHandle) {
                $fail(t('Row {row}: the handle must start with a capital letter and contain only letters and numbers (e.g. Customer).', ['row' => $row], category: 'wingman'));
            } elseif (isset($seen[$handle])) {
                $fail(t('Row {row}: the handle {handle} is used more than once.', ['row' => $row, 'handle' => $handle], category: 'wingman'));
            }
            $seen[$handle] = true;

            if ($validHandle && ($conflict = self::identityConflict($handle)) !== null) {
                $fail(t('Row {row}: {conflict} Choose another handle.', ['row' => $row, 'conflict' => $conflict], category: 'wingman'));
            }

            if (class_exists($class) && ModelDiscovery::isCraftModel($class)) {
                $fail(t('Row {row}: {class} is one of Craft’s own models; its rows are already Craft elements.', ['row' => $row, 'class' => $class], category: 'wingman'));

                continue;
            }

            if (! isset($discovered[$class])) {
                $fail(t('Row {row}: {class} isn’t an Eloquent model in the model directories.', ['row' => $row, 'class' => $class], category: 'wingman'));

                continue;
            }

            // Core keeps one element type per model class (ModelSync), so a second row for it would fail at boot.
            if (isset($seenClasses[$class])) {
                $fail(t('Row {row}: {class} is already registered as {handle}.', ['row' => $row, 'class' => $class, 'handle' => $seenClasses[$class]], category: 'wingman'));
            }
            $seenClasses[$class] = $handle;

            /** @var Model $model */
            $model = new $class;

            if ($validHandle && ($linked = ModelHealth::linkedModelType($handle)) !== null && $linked !== $model->getMorphClass()) {
                $fail(t('Row {row}: {handle} already has elements linked to {linked}; it can’t be pointed at another model.', ['row' => $row, 'handle' => $handle, 'linked' => $linked], category: 'wingman'));
            }

            if (($column = $definition['labelColumn']) !== null && ! self::hasColumn($model, $column)) {
                $fail(t('Row {row}: {table} has no “{column}” column to use as the label.', ['row' => $row, 'table' => $model->getTable(), 'column' => $column], category: 'wingman'));
            } elseif ($column !== null && in_array($column, $model->getHidden(), true)) {
                $fail(t('Row {row}: “{column}” is hidden on the model, so it can’t be the label.', ['row' => $row, 'column' => $column], category: 'wingman'));
            }

            $trash = $definition['trash'];

            if ($trash !== null && ! in_array($trash, [ModelRegistry::TRASH_KEEP, ModelRegistry::TRASH_SOFT_DELETE], true)) {
                $fail(t('Row {row}: choose what trashing an element does to its row.', ['row' => $row], category: 'wingman'));
            } elseif ($trash === ModelRegistry::TRASH_SOFT_DELETE && ! in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
                $fail(t('Row {row}: {class} doesn’t use SoftDeletes, so trashing can’t soft-delete its row.', ['row' => $row, 'class' => $class], category: 'wingman'));
            }
        }

        foreach ($this->previouslyRegisteredHandles() as $handle) {
            if (! isset($seen[$handle]) && ModelHealth::hasElements($handle)) {
                $fail(t('{handle} can’t be removed while it has elements. Delete them first, or keep the model registered.', ['handle' => $handle], category: 'wingman'));
            }
        }
    }

    private static function hasColumn(Model $model, string $column): bool
    {
        try {
            return in_array($column, Schema::connection($model->getConnectionName())->getColumnListing($model->getTable()), true);
        } catch (Throwable) {
            return false;
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
            return t('{handle} would share the reference handle “{refHandle}” with {existing}.', ['handle' => $handle, 'refHandle' => $refHandle, 'existing' => $existing], category: 'wingman');
        }

        $uri = WingmanElement::cpIndexUriFor($handle);

        foreach ([$uri, "{$uri}/new"] as $path) {
            try {
                $route = app(Router::class)->getRoutes()->match(Request::create(cp_url($path)));
            } catch (HttpException) {
                continue;
            }

            if (! $route->isFallback && ($route->defaults['elementType'] ?? null) !== $ours) {
                return t('{handle}’s control panel page “{uri}” is already taken.', ['handle' => $handle, 'uri' => $uri], category: 'wingman');
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
