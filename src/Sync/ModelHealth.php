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
 * - orphaned: elements whose model row no longer exists, or that were never linked ({@see Orphans})
 */
class ModelHealth
{
    public function __construct(
        private readonly ModelRegistry $registry,
        private readonly Orphans $orphans,
    ) {}

    /**
     * @return array{handle: string, modelClass: string, table: string, rows: int, linked: int, unlinked: int, orphaned: int}
     */
    public function report(string $handle): array
    {
        $modelClass = $this->registry->modelClass($handle);
        $model = new $modelClass;
        $table = $model->getTable();
        $keyName = $model->getKeyName();

        // Soft-deleted rows don't get elements (their element, if any, is in the trash).
        // As the backfill sees them: without global scopes, but with any constraint the model's own query builds in.
        $rowsQuery = $model->newQueryWithoutScopes()->toBase();
        if (method_exists($model, 'getDeletedAtColumn')) {
            $rowsQuery->whereNull($model->getDeletedAtColumn());
        }

        $rows = (int) (clone $rowsQuery)->count();
        $linked = 0;

        $this->links($handle, $model->getMorphClass())->orderBy('em.elementId')->chunk(1000, function ($links) use ($rowsQuery, $keyName, &$linked): void {
            $keys = $links->pluck('modelKey')->map(strval(...))->all();
            $linked += (int) (clone $rowsQuery)->whereIn($keyName, $keys)->count();
        });

        return [
            'handle' => $handle,
            'modelClass' => $modelClass,
            'table' => $table,
            'rows' => $rows,
            'linked' => $linked,
            'unlinked' => max(0, $rows - $linked),
            'orphaned' => count($this->orphans->elementIds($handle)),
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

    /** Links of the handle's live (non-trashed) elements to this model type. */
    private function links(string $handle, string $morphClass): Builder
    {
        return DB::table(Table::ELEMENTS_MODELS.' as em')
            ->join(Table::ELEMENTS.' as e', 'e.id', '=', 'em.elementId')
            ->where('e.type', ModelRegistry::elementClass($handle))
            ->where('em.modelType', $morphClass)
            ->whereNull('e.dateDeleted')
            ->select(['em.elementId', 'em.modelKey']);
    }
}
