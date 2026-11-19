<?php

// Wingman harness helpers for check.sh, loaded with `require 'storage/wm-helpers.php';` in tinker.
// Class names are written with `.` instead of `\` (App.Models.Customer), which survives shell quoting.

use CraftCms\Cms\Element\Eloquent\EloquentElement;
use CraftCms\Cms\ProjectConfig\Exceptions\BusyResourceException;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Gql;
use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Cms\Support\Facades\UserPermissions;
use CraftCms\Cms\User\Elements\User;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;

function wm_class(string $dotted): string
{
    return str_replace('.', '\\', $dotted);
}

/** Saves Wingman's model settings; rows are [handle, dotted class, label?, labelColumn?, trash?, nav?]. */
function wm_save(array $rows, array $extra = []): string
{
    $models = array_map(fn (array $r) => [
        'handle' => $r[0],
        'modelClass' => wm_class($r[1]),
        'label' => $r[2] ?? '',
        'labelColumn' => $r[3] ?? '',
        'trash' => $r[4] ?? '',
        'nav' => $r[5] ?? '',
    ], $rows);
    $plugin = Plugins::getPlugin('wingman');

    return Plugins::savePluginSettings($plugin, ['models' => $models, ...$extra])
        ? 'ok'
        : implode(' | ', $plugin->getSettings()->errors()->all());
}

/** The baseline: Customer + WingmanInvoice (label column `number`, soft-deletes on trash, hidden from the sidebar). */
function wm_baseline(): string
{
    return wm_save_retry([
        ['Customer', 'App.Models.Customer', 'Customers'],
        ['WingmanInvoice', 'App.Models.WingmanInvoice', 'Invoices', 'number', 'softDelete', 'hide'],
    ]);
}

function wm_element(string $handle, int|string $key): ?EloquentElement
{
    $class = ModelRegistry::elementClass($handle);
    $model = app(ModelRegistry::class)->modelClass($handle)::withTrashed()->find($key);

    return $model ? $class::findForModel($model) : null;
}

/** A non-admin fixture user (wingman_*) with exactly these permissions. Returns the id. */
function wm_user(string $username, array $permissions): int
{
    $class = User::class;
    $u = $class::find()->username($username)->status(null)->one() ?? new $class;
    $u->username = $username;
    $u->email = "{$username}@wingman.test";
    $u->newPassword = 'wingman-pass-123';
    $u->admin = false;
    $u->active = true;
    $u->pending = false;
    Elements::saveElement($u, false) || throw new RuntimeException(json_encode($u->errors()->all()));
    UserPermissions::saveUserPermissions($u->id, $permissions);

    return $u->id;
}

/** Removes all wingman_* fixture users, GraphQL schemas/tokens and the wingmanfr site. */
function wm_cleanup(): string
{
    // Project config is shared with other sessions; wait for a lock rather than fail.
    for ($i = 0; $i < 10; $i++) {
        try {
            return wm_cleanup_once();
        } catch (BusyResourceException) {
            sleep(2);
        }
    }

    return 'busy';
}

function wm_cleanup_once(): string
{
    foreach (User::find()->username('wingman_*')->status(null)->all() as $u) {
        Elements::deleteElement($u, true);
    }
    foreach (Gql::getTokens() as $t) {
        if (str_starts_with((string) $t->name, 'wm_')) {
            Gql::deleteTokenById($t->id);
        }
    }
    foreach (Gql::getSchemas() as $s) {
        if (str_starts_with((string) $s->name, 'wm_')) {
            Gql::deleteSchemaById($s->id);
        }
    }

    return 'ok';
}

/** Switches Wingman's edition, waiting out other sessions' project config writes. Returns the edition now in effect. */
function wm_edition(string $edition): string
{
    for ($i = 0; $i < 15; $i++) {
        try {
            Plugins::switchEdition('wingman', $edition);
            break;
        } catch (BusyResourceException) {
            sleep(2);
        }
    }

    return (string) Plugins::getPlugin('wingman')?->edition;
}

/** wm_save(), retried while project config is busy. */
function wm_save_retry(array $rows, array $extra = []): string
{
    for ($i = 0; $i < 15; $i++) {
        try {
            return wm_save($rows, $extra);
        } catch (BusyResourceException) {
            sleep(2);
        }
    }

    return 'busy';
}
