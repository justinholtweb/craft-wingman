<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Commands;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use JustinHoltWeb\Wingman\Sync\Orphans;

class CleanupCommand extends Command
{
    use CraftCommand;

    protected $signature = 'craft:wingman:cleanup
        {handle? : A registered model handle (default: all)}
        {--dry-run : Count orphaned elements without deleting them}';

    protected $description = 'Deletes elements whose model row no longer exists';

    public function handle(ModelRegistry $registry, Orphans $orphans): int
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

            $r = $orphans->clean($h, $dry);
            $failed += $r['failed'];
            $this->components->info(sprintf('%s: %d orphaned elements %s, %d failed', $h, $r['deleted'], $dry ? 'would be deleted' : 'deleted', $r['failed']));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
