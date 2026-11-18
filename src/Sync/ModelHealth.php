<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Sync;

use CraftCms\Cms\Database\Table;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;

/**
 * Counts how well a registered model's rows and Craft's elements line up.
 *
 * - rows: rows in the model's table
 * - linked: rows with a live (non-trashed) element
 * - unlinked: rows without one (backfill fixes these)
 * - orphaned: element links whose model row no longer exists
 */
class ModelHealth
{
    public function __construct(private readonly ModelRegistry $registry) {}

    /**
     * @return array{handle: string, modelClass: string, table: string, rows: int, linked: int, unlinked: int, orphaned: int}
     */
    public function report(string $handle): array
    {
        $modelClass = $this->registry->modelClass($handle);
        $model = new $modelClass;
        $table = $model->getTable();
        $keyName = $model->getKeyName();
        $connection = $model->getConnection();

        $rows = (int) $connection->table($table)->count();
        $linkedKeys = $this->links($handle, $model->getMorphClass())->pluck('em.modelKey')->map(strval(...));
        $existingKeys = $linkedKeys->isEmpty()
            ? collect()
            : $connection->table($table)->whereIn($keyName, $linkedKeys->all())->pluck($keyName)->map(strval(...));

        $linked = $existingKeys->count();

        return [
            'handle' => $handle,
            'modelClass' => $modelClass,
            'table' => $table,
            'rows' => $rows,
            'linked' => $linked,
            'unlinked' => max(0, $rows - $linked),
            'orphaned' => $linkedKeys->diff($existingKeys)->count(),
        ];
    }

    /**
     * Whether any element (trashed or not) of this handle's type exists.
     */
    public static function hasElements(string $handle): bool
    {
        return DB::table(Table::ELEMENTS)->where('type', ModelRegistry::elementClass($handle))->exists();
    }

    /**
     * The model type the handle's existing elements are linked to, if any.
     */
    public static function linkedModelType(string $handle): ?string
    {
        $type = DB::table(Table::ELEMENTS_MODELS.' as em')
            ->join(Table::ELEMENTS.' as e', 'e.id', '=', 'em.elementId')
            ->where('e.type', ModelRegistry::elementClass($handle))
            ->value('em.modelType');

        return is_string($type) ? $type : null;
    }

    private function links(string $handle, string $morphClass): Builder
    {
        return DB::table(Table::ELEMENTS_MODELS.' as em')
            ->join(Table::ELEMENTS.' as e', 'e.id', '=', 'em.elementId')
            ->where('e.type', ModelRegistry::elementClass($handle))
            ->where('em.modelType', $morphClass)
            ->whereNull('e.dateDeleted');
    }
}
