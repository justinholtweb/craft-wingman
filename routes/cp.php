<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JustinHoltWeb\Wingman\Http\Controllers\BackfillController;
use JustinHoltWeb\Wingman\Http\Controllers\CleanupController;

// The cp group already applies auth + can:accessCp (and Laravel's CSRF check on POST). Backfill and
// cleanup write elements, so they're limited to people who can use the Wingman utility.
Route::post('wingman/backfill', BackfillController::class)->middleware('can:utility:wingman');
Route::post('wingman/cleanup', CleanupController::class)->middleware('can:utility:wingman');
