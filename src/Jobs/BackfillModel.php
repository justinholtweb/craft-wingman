<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Jobs;

use CraftCms\Cms\Queue\Job;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use JustinHoltWeb\Wingman\Sync\Backfill;

use function CraftCms\Cms\t;

/**
 * Backfills one model from the queue, for tables too big for a request.
 * One at a time per model; progress shows in the CP's queue manager.
 */
class BackfillModel extends Job implements ShouldBeUnique
{
    public int $timeout = 3600;

    /**
     * Unique locks are released when the job finishes; without an expiry a cleared
     * job or a crashed worker would block this model's backfill for good.
     */
    public int $uniqueFor = 3600;

    public function __construct(public string $handle)
    {
        parent::__construct();
    }

    public function uniqueId(): string
    {
        return $this->handle;
    }

    protected function defaultDescription(): string
    {
        return t('Creating {handle} elements', ['handle' => $this->handle], category: 'wingman');
    }

    public function handle(ModelRegistry $registry, Backfill $backfill): void
    {
        // The model may have been unregistered since the job was queued.
        if (! $registry->has($this->handle)) {
            return;
        }

        $result = $backfill->run($this->handle, progress: function (int $done, int $total): void {
            $this->setProgress($total > 0 ? (int) min(100, floor($done / $total * 100)) : 100, "{$done} / {$total}");
        });

        Log::info(sprintf('Wingman backfilled %s: %d created, %d skipped, %d failed.', $this->handle, $result['created'], $result['skipped'], $result['failed']));
    }
}
