<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use JustinHoltWeb\Wingman\Sync\Backfill;

use function CraftCms\Cms\cp_url;

/**
 * POST admin/wingman/backfill — admin-only (see routes/cp.php). The handle must be
 * a registered one; anything else 404s.
 */
class BackfillController
{
    public function __invoke(Request $request, ModelRegistry $registry, Backfill $backfill): RedirectResponse
    {
        $handle = (string) $request->input('handle');
        abort_unless($registry->has($handle), 404);

        $result = $backfill->run($handle);

        return redirect(cp_url('utilities/wingman'))->with('success', sprintf(
            '%s: %d created, %d already had elements, %d failed.',
            $handle, $result['created'], $result['skipped'], $result['failed'],
        ));
    }
}
