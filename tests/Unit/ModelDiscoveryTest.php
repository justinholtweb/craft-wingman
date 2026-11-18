<?php

declare(strict_types=1);

use JustinHoltWeb\Wingman\Registry\ModelDiscovery;
use JustinHoltWeb\Wingman\Settings;

it('reads the declared class from PHP source', function (string $code, ?string $class) {
    expect(ModelDiscovery::declaredClass($code))->toBe($class);
})->with([
    ['<?php namespace App\Models; class Customer extends Model {}', 'App\Models\Customer'],
    ["<?php\nnamespace App\\Models\\Shop;\n\nfinal class OrderLine extends Model {}", 'App\Models\Shop\OrderLine'],
    ['<?php class Bare {}', 'Bare'],
    ['<?php namespace App; $x = Foo::class; class Real {}', 'App\Real'],
    ['<?php namespace App; $x = new class {}; ', null],
    ['<?php namespace App; interface Nope {}', null],
]);

it('suggests identifier-safe handles', function (string $class, string $handle) {
    expect(ModelDiscovery::suggestHandle($class))->toBe($handle);
})->with([
    ['App\Models\Customer', 'Customer'],
    ['App\Models\Shop\OrderLine', 'ShopOrderLine'],
    ['Domain\Billing\Invoice', 'DomainBillingInvoice'],
]);

it('normalises CP table rows and config-file maps', function () {
    $settings = new Settings;
    $settings->models = [
        ['handle' => ' Customer ', 'modelClass' => '\App\Models\Customer', 'label' => ' Customers '],
        'Invoice' => ['modelClass' => 'App\Models\Invoice'],
        'junk',
    ];

    expect($settings->modelDefinitions())->toBe([
        ['handle' => 'Customer', 'modelClass' => 'App\Models\Customer', 'label' => 'Customers'],
        ['handle' => 'Invoice', 'modelClass' => 'App\Models\Invoice', 'label' => null],
    ]);
});

it('defaults model paths to app/Models', function () {
    $settings = new Settings;
    expect($settings->modelPathList())->toBe(['app/Models']);

    $settings->modelPaths = "app/Models\n domain/Models ";
    expect($settings->modelPathList())->toBe(['app/Models', 'domain/Models']);
});

it('confines model directories to the project root, outside vendor', function () {
    $root = sys_get_temp_dir().'/wingman-root-'.getmypid();
    @mkdir("$root/app/Models", 0777, true);
    @mkdir("$root/vendor/acme", 0777, true);

    expect(ModelDiscovery::resolveDirectory("$root/app/Models", $root))->toBe(realpath("$root/app/Models"))
        ->and(ModelDiscovery::resolveDirectory('/', $root))->toBeNull()
        ->and(ModelDiscovery::resolveDirectory("$root/app/Models/../../..", $root))->toBeNull()
        ->and(ModelDiscovery::resolveDirectory("$root/vendor/acme", $root))->toBeNull()
        ->and(ModelDiscovery::resolveDirectory("$root/missing", $root))->toBeNull();
});
