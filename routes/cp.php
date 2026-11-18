<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JustinHoltWeb\Wingman\Http\Controllers\BackfillController;

// The cp group already applies auth + can:accessCp. Backfill writes elements, so it's limited to
// people who can use the Wingman utility.
Route::post('wingman/backfill', BackfillController::class)->middleware('can:utility:wingman');
