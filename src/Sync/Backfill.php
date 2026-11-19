<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Sync;

use Closure;
use CraftCms\Cms\Element\Eloquent\EloquentElement;
use CraftCms\Cms\Element\Eloquent\ModelLinks;
use CraftCms\Cms\Support\Facades\Elements;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use JustinHoltWeb\Wingman\Elements\WingmanElement;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use Throwable;

/**
 * Creates elements for model rows that don't have one yet, in chunks.
 *
 * Works the way core's own `craft:eloquent-elements:sync` does — links looked up a
 * chunk at a time, the save marked as a sync so the model isn't written back, no
 * element validation (the row is the source of truth) — plus a dry run, progress
 * and a per-row failure count. Rows that already have an element (live or
 * trashed) and soft-deleted rows are left alone.
 */
class Backfill
{
    public function __construct(
        private readonly ModelRegistry $registry,
        private readonly ModelLinks $links,
    ) {}

    /**
     * @param  (Closure(int $done, int $total): void)|null  $progress
     * @return array{created: int, skipped: int, failed: int}
     */
    public function run(string $handle, bool $dryRun = false, ?Closure $progress = null, int $chunk = 200): array
    {
        $this->registry->modelClass($handle); // unknown handle → exception before any work
        /** @var class-string<WingmanElement> $elementType */
        $elementType = ModelRegistry::elementClass($handle);
        $result = ['created' => 0, 'skipped' => 0, 'failed' => 0];
        $query = $elementType::newModel()->newQueryWithoutScopes();
        $total = $progress ? (clone $query)->count() : 0;

        $query->chunkById($chunk, function (Collection $models) use ($elementType, $dryRun, $progress, $total, $handle, &$result): void {
            /** @var Model $first */
            $first = $models->first();
            $linked = array_flip($this->links->linkedKeys($first, $models->modelKeys()));

            /** @var Model $model */
            foreach ($models as $model) {
                if (isset($linked[(string) $model->getKey()]) || (method_exists($model, 'trashed') && $model->trashed())) {
                    $result['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $result['created']++;

                    continue;
                }

                try {
                    $saved = EloquentElement::syncing($model, fn () => Elements::saveElement($elementType::fromModel($model), runValidation: false));
                } catch (Throwable $e) {
                    Log::warning("Wingman couldn’t create an element for {$handle} #{$model->getKey()}: {$e->getMessage()}");
                    $saved = false;
                }

                $saved ? $result['created']++ : $result['failed']++;
            }

            if ($progress) {
                $progress(array_sum($result), $total);
            }
        });

        return $result;
    }
}
