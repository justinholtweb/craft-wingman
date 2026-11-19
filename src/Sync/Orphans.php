<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Sync;

use CraftCms\Cms\Database\Table;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Eloquent\EloquentElementQuery;
use CraftCms\Cms\Support\Facades\Elements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JustinHoltWeb\Wingman\Elements\WingmanElement;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use Throwable;

/**
 * Elements whose model row is gone — the row was deleted outside Eloquent (a raw
 * query, a truncate, another app), so core's ModelSync never heard about it — and
 * elements that were never linked to a row at all.
 *
 * Links to another model type (a morph map that changed, say) are never counted:
 * their rows may well exist, so deleting them could destroy content.
 * Rows hidden by global scopes or soft-deleted still exist, so their elements aren't orphans.
 */
class Orphans
{
    private const int CHUNK = 1000;

    public function __construct(private readonly ModelRegistry $registry) {}

    /**
     * @return list<int>
     */
    public function elementIds(string $handle): array
    {
        $modelClass = $this->registry->modelClass($handle);
        $model = new $modelClass;
        $morph = $model->getMorphClass();
        $orphans = [];

        $links = DB::table(Table::ELEMENTS.' as e')
            ->leftJoin(Table::ELEMENTS_MODELS.' as em', 'em.elementId', '=', 'e.id')
            ->where('e.type', ModelRegistry::elementClass($handle))
            ->whereNull('e.draftId')
            ->whereNull('e.revisionId')
            ->select(['e.id', 'em.modelType', 'em.modelKey'])
            ->orderBy('e.id');

        $links->chunk(self::CHUNK, function ($rows) use ($model, $morph, &$orphans): void {
            $byKey = [];

            foreach ($rows as $row) {
                if ($row->modelKey === null) {
                    $orphans[] = (int) $row->id;
                } elseif ($row->modelType === $morph) {
                    $byKey[(string) $row->modelKey] = (int) $row->id;
                }
            }

            if ($byKey === []) {
                return;
            }

            $existing = $model->getConnection()->table($model->getTable())
                ->whereIn($model->getKeyName(), array_map(strval(...), array_keys($byKey)))
                ->pluck($model->getKeyName())
                ->map(strval(...))
                ->all();

            foreach (array_diff_key($byKey, array_flip($existing)) as $elementId) {
                $orphans[] = $elementId;
            }
        });

        sort($orphans);

        return $orphans;
    }

    /**
     * Deletes the orphaned elements for good (there's no row to restore them from).
     *
     * @return array{deleted: int, failed: int}
     */
    public function clean(string $handle, bool $dryRun = false): array
    {
        $ids = $this->elementIds($handle);

        if ($dryRun || $ids === []) {
            return ['deleted' => count($ids), 'failed' => 0];
        }

        /** @var class-string<WingmanElement> $elementType */
        $elementType = ModelRegistry::elementClass($handle);
        $result = ['deleted' => 0, 'failed' => 0];

        foreach (array_chunk($ids, 100) as $chunk) {
            // Without the model's scopes: with them, the query leaves out exactly these elements.
            $elements = new EloquentElementQuery($elementType, modelScopes: false)->id($chunk)->status(null)->trashed(null)->all();

            foreach ($elements as $element) {
                /** @var ElementInterface $element */
                try {
                    Elements::deleteElement($element, hardDelete: true) ? $result['deleted']++ : $result['failed']++;
                } catch (Throwable $e) {
                    Log::warning("Wingman couldn’t delete orphaned {$handle} element {$element->id}: {$e->getMessage()}");
                    $result['failed']++;
                }
            }
        }

        return $result;
    }
}
