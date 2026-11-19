<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use JustinHoltWeb\Wingman\Jobs\BackfillModel;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use JustinHoltWeb\Wingman\Sync\Backfill;
use JustinHoltWeb\Wingman\Sync\ModelHealth;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

/**
 * POST admin/wingman/backfill — for people who can use the Wingman utility (see
 * routes/cp.php). The handle must be a registered one; anything else 404s. Tables
 * with more missing elements than {@see self::INLINE_LIMIT} are backfilled from the queue.
 */
class BackfillController
{
    public const int INLINE_LIMIT = 500;

    public function __invoke(Request $request, ModelRegistry $registry, Backfill $backfill, ModelHealth $health): RedirectResponse
    {
        $handle = (string) $request->input('handle');
        abort_unless($registry->has($handle), 404);

        $redirect = redirect(cp_url('utilities/wingman'));

        if ($health->report($handle)['unlinked'] > self::INLINE_LIMIT) {
            BackfillModel::dispatch($handle);

            return $redirect->with('success', t('{handle}: backfill queued. Progress shows in Utilities → Queue Manager.', ['handle' => $handle], category: 'wingman'));
        }

        $result = $backfill->run($handle);

        return $redirect->with('success', t('{handle}: {created} created, {skipped} already had elements, {failed} failed.', [
            'handle' => $handle, 'created' => $result['created'], 'skipped' => $result['skipped'], 'failed' => $result['failed'],
        ], category: 'wingman'));
    }
}
