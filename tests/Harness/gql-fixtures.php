<?php

// Wingman harness: GraphQL schemas + tokens (idempotent). Token = "wm{name}" padded to 32 chars.
//   full   the site, the articles section, Customer and WingmanInvoice
//   cust   the site, the articles section, Customer only
//   none   the site and the articles section, no Wingman model
// Run with: ddev artisan tinker --execute "require 'storage/wm-gql-fixtures.php';" < /dev/null

use CraftCms\Cms\Gql\Data\GqlSchema;
use CraftCms\Cms\Gql\Data\GqlToken;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Sites;

$site = 'sites.'.Sites::getPrimarySite()->uid.':read';
$articles = 'sections.'.Sections::getSectionByHandle('articles')->uid.':read';

$schemas = [
    'full' => [$site, $articles, 'wingman.Customer:read', 'wingman.WingmanInvoice:read'],
    'cust' => [$site, $articles, 'wingman.Customer:read'],
    'none' => [$site, $articles],
];

foreach ($schemas as $name => $scope) {
    $schema = collect(Gql::getSchemas())->first(fn ($s) => $s->name === "wm_{$name}") ?? new GqlSchema;
    $schema->name = "wm_{$name}";
    $schema->scope = $scope;
    Gql::saveSchema($schema) || throw new RuntimeException(json_encode($schema->errors()->all()));

    $token = collect(Gql::getTokens())->first(fn ($t) => $t->name === "wm_{$name}") ?? new GqlToken;
    $token->name = "wm_{$name}";
    $token->schemaId = $schema->id;
    $token->accessToken = str_pad("wm{$name}", 32, '0');
    Gql::saveToken($token) || throw new RuntimeException(json_encode($token->errors()->all()));
}

Gql::invalidateCaches();
echo 'ok';
