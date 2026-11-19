<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Listeners;

use CraftCms\Cms\View\Events\TemplateGlobalsResolving;
use JustinHoltWeb\Wingman\Templates\WingmanVariable;

/**
 * `wingman` global for Twig and Blade alike (Craft feeds template globals to both).
 */
class RegisterTemplateApi
{
    public function handle(TemplateGlobalsResolving $event): void
    {
        $event->globals['wingman'] = app(WingmanVariable::class);
    }
}
