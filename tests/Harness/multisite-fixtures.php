<?php

// Wingman harness: a second site of our own, `wingmanfr` (French). Idempotent.
// Add/remove it under bin/harness-lock; check.sh removes it again at the end.
// Run with: ddev artisan tinker --execute "require 'storage/wm-multisite-fixtures.php';" < /dev/null

use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Sites;

$fr = Sites::getSiteByHandle('wingmanfr');

if (! $fr) {
    $fr = new Site;
    $fr->groupId = Sites::getPrimarySite()->groupId;
    $fr->name = 'Wingman FR';
    $fr->handle = 'wingmanfr';
    $fr->language = 'fr-FR';
    $fr->hasUrls = true;
    // Absolute: the harness's `@web` alias doesn't resolve in a site's base URL, so `@web/…` sites never match a request.
    $fr->baseUrl = rtrim((string) config('app.url'), '/').'/wingmanfr/';
    $fr->enabled = true;
    Sites::saveSite($fr) || throw new RuntimeException('site: '.json_encode($fr->errors()->all()));
}

echo $fr->id;
