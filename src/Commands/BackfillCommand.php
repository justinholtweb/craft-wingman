<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Commands;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use JustinHoltWeb\Wingman\Sync\Backfill;

class BackfillCommand extends Command
{
    use CraftCommand;

    protected $signature = 'craft:wingman:backfill {handle? : A registered model handle (default: all)} {--dry-run : Count what would be created without creating it}';

    protected $description = 'Creates elements for model rows that don’t have one yet';

    public function handle(ModelRegistry $registry, Backfill $backfill): int
    {
        $handle = $this->argument('handle');
        $handles = is_string($handle) ? [$handle] : $registry->handles();

        foreach ($handles as $h) {
            if (! $registry->has($h)) {
                $this->components->error("{$h} isn’t a registered model.");

                return self::FAILURE;
            }

            $dry = (bool) $this->option('dry-run');
            $r = $backfill->run($h, $dry);
            $this->components->info(sprintf('%s: %d %s, %d already had elements, %d failed', $h, $r['created'], $dry ? 'would be created' : 'created', $r['skipped'], $r['failed']));
        }

        return self::SUCCESS;
    }
}
