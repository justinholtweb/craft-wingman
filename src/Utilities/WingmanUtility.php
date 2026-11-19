<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Utilities;

use CraftCms\Cms\Utility\Utility;
use Illuminate\Support\Facades\Log;
use JustinHoltWeb\Wingman\Http\Controllers\BackfillController;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use JustinHoltWeb\Wingman\Sync\ModelHealth;
use Override;
use Throwable;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

/**
 * Utilities → Wingman: how each registered model's rows line up with Craft's
 * elements, with a backfill button per model.
 */
class WingmanUtility extends Utility
{
    #[Override]
    public static function id(): string
    {
        return 'wingman';
    }

    public static function displayName(): string
    {
        return t('Wingman', category: 'wingman');
    }

    #[Override]
    public static function icon(): ?string
    {
        return 'link';
    }

    #[Override]
    public static function badgeCount(): int
    {
        return 0;
    }

    #[Override]
    public static function contentHtml(): string
    {
        $registry = app(ModelRegistry::class);
        $health = app(ModelHealth::class);
        $reports = [];

        foreach ($registry->handles() as $handle) {
            try {
                $reports[] = $health->report($handle) + ['label' => $registry->label($handle), 'error' => null];
            } catch (Throwable $e) {
                Log::warning("Wingman couldn’t check {$handle}: {$e->getMessage()}");
                $reports[] = ['handle' => $handle, 'label' => $registry->label($handle), 'error' => true];
            }
        }

        return template('wingman/_utility', [
            'reports' => $reports,
            'backfillUrl' => cp_url('wingman/backfill'),
            'cleanupUrl' => cp_url('wingman/cleanup'),
            'inlineLimit' => BackfillController::INLINE_LIMIT,
        ]);
    }
}
