<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Commands;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use JustinHoltWeb\Wingman\Jobs\BackfillModel;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use JustinHoltWeb\Wingman\Sync\Backfill;

class BackfillCommand extends Command
{
    use CraftCommand;

    protected $signature = 'craft:wingman:backfill
        {handle? : A registered model handle (default: all)}
        {--dry-run : Count what would be created without creating it}
        {--queue : Push a queue job per model instead of running now}';

    protected $description = 'Creates elements for model rows that don’t have one yet';

    public function handle(ModelRegistry $registry, Backfill $backfill): int
    {
        $handle = $this->argument('handle');
        $handles = is_string($handle) ? [$handle] : $registry->handles();
        $dry = (bool) $this->option('dry-run');
        $failed = 0;

        foreach ($handles as $h) {
            if (! $registry->has($h)) {
                $this->components->error("{$h} isn’t a registered model.");

                return self::FAILURE;
            }

            if ($this->option('queue') && ! $dry) {
                BackfillModel::dispatch($h);
                $this->components->info("{$h}: queued.");

                continue;
            }

            $r = $backfill->run($h, $dry);
            $failed += $r['failed'];
            $this->components->info(sprintf('%s: %d %s, %d skipped (already linked or soft-deleted), %d failed', $h, $r['created'], $dry ? 'would be created' : 'created', $r['skipped'], $r['failed']));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
