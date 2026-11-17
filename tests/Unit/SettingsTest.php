<?php

declare(strict_types=1);

use JustinHoltWeb\Wingman\Settings;

it('has sensible defaults', function () {
    $settings = new Settings;

    expect($settings->modelPaths)->toBe(null);
});
