<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Sync;

use Closure;
use CraftCms\Cms\Support\Facades\Elements;
use Illuminate\Database\Eloquent\Model;
use JustinHoltWeb\Wingman\Elements\WingmanElement;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;

/**
 * Creates elements for model rows that don't have one yet, in chunks.
 * Rows that already have an element (live or trashed) are left alone.
 */
class Backfill
{
    public function __construct(private readonly ModelRegistry $registry) {}

    /**
     * @param  (Closure(int $done): void)|null  $progress
     * @return array{created: int, skipped: int, failed: int}
     */
    public function run(string $handle, bool $dryRun = false, ?Closure $progress = null, int $chunk = 200): array
    {
        $modelClass = $this->registry->modelClass($handle);
        /** @var class-string<WingmanElement> $elementType */
        $elementType = ModelRegistry::elementClass($handle);
        $result = ['created' => 0, 'skipped' => 0, 'failed' => 0];

        $modelClass::query()->chunkById($chunk, function ($models) use ($elementType, $dryRun, $progress, &$result): void {
            /** @var Model $model */
            foreach ($models as $model) {
                if ($elementType::findForModel($model) !== null) {
                    $result['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $result['created']++;

                    continue;
                }

                $element = $elementType::fromModel($model);
                Elements::saveElement($element) ? $result['created']++ : $result['failed']++;
            }

            if ($progress) {
                $progress(array_sum($result));
            }
        });

        return $result;
    }
}
