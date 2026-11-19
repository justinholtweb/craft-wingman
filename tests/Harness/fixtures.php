<?php

// Wingman harness fixtures (idempotent): the `wingman_invoices` table behind App\Models\WingmanInvoice.
// Run with: ddev artisan tinker --execute "require 'storage/wm-fixtures.php';" < /dev/null

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

if (! Schema::hasTable('wingman_invoices')) {
    Schema::create('wingman_invoices', function (Blueprint $t) {
        $t->id();
        $t->string('number')->unique();
        $t->integer('amount')->default(0);
        $t->boolean('paid')->default(false);
        $t->date('issued_on')->nullable();
        $t->string('token')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
}

echo 'ok';
