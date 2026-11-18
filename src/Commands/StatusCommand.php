<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Commands;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use JustinHoltWeb\Wingman\Sync\ModelHealth;

class StatusCommand extends Command
{
    use CraftCommand;

    protected $signature = 'craft:wingman:status';

    protected $description = 'Shows how each registered model’s rows line up with Craft elements';

    public function handle(ModelRegistry $registry, ModelHealth $health): int
    {
        $rows = array_map(fn (string $handle) => array_values($health->report($handle)), $registry->handles());

        if ($rows === []) {
            $this->components->info('No models are registered.');

            return self::SUCCESS;
        }

        $this->table(['Handle', 'Model', 'Table', 'Rows', 'With elements', 'Missing', 'Orphaned'], $rows);

        return self::SUCCESS;
    }
}
