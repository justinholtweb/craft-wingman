<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use JustinHoltWeb\Wingman\Sync\Orphans;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

/**
 * POST admin/wingman/cleanup — deletes a model's orphaned elements (see {@see Orphans}).
 * Same access as backfill; unknown handles 404.
 */
class CleanupController
{
    public function __invoke(Request $request, ModelRegistry $registry, Orphans $orphans): RedirectResponse
    {
        $handle = (string) $request->input('handle');
        abort_unless($registry->has($handle), 404);

        $result = $orphans->clean($handle);

        return redirect(cp_url('utilities/wingman'))->with('success', t('{handle}: {deleted} orphaned elements deleted, {failed} failed.', [
            'handle' => $handle, 'deleted' => $result['deleted'], 'failed' => $result['failed'],
        ], category: 'wingman'));
    }
}
