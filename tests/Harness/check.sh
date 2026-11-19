#!/bin/bash
# Live checks against the Craft 6 harness (~/Sites/plugin-testing-craft6).
# Installs its own fixtures (shared-state changes go through bin/harness-lock):
#   app/Models/Customer.php        ← Customer.php.fixture (the `customers` table predates these checks)
#   app/Models/WingmanInvoice.php  ← WingmanInvoice.php.fixture, table `wingman_invoices` (fixtures.php)
#   storage/wm-*.php               ← helpers.php, fixtures.php, gql-fixtures.php, multisite-fixtures.php
#   resources/views/wm/            ← templates/*
# Uses the harness's `articles` section and its `customers` relation field (entry 8 relates to two customers).
# Leaves Wingman on Lite with Customer + WingmanInvoice registered; removes its users, schemas and the wingmanfr site.
set -uo pipefail
exec < /dev/null
H=/Users/jholt/Sites/plugin-testing-craft6
U=https://plugin-testing-craft6.ddev.site
SRC=$(cd "$(dirname "$0")" && pwd)
cd "$H" || exit 2
pass=0 fail=0
ok() { if [ "$2" = "$3" ]; then echo "✓ $1"; pass=$((pass+1)); else echo "✗ $1 (got '$2', want '$3')"; fail=$((fail+1)); fi; }
tk() { ddev artisan tinker --execute "require 'storage/wm-helpers.php'; $1" 2>/dev/null | grep -v WARNING | tail -1; }
sql() { ddev exec "mysql -N -e \"$1\" db" 2>/dev/null; }
# Project config is shared with other sessions: a write can hit a busy/stale lock, so retry in a fresh process.
edition() { local e i; for i in 1 2 3 4 5 6; do e=$(tk "echo wm_edition('$1');"); [ "$e" = "$1" ] && return; sleep 2; done; echo "! couldn't switch Wingman to $1 (got '$e')"; }
status_row() { ddev artisan craft:wingman:status 2>/dev/null | grep "| $1 " | awk -F'|' '{gsub(/ /,""); print $5"/"$6"/"$7"/"$8}'; }
xsrf() { awk '$6=="XSRF-TOKEN"{print $7}' "$1" | python3 -I -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))'; }
# login <user> <password> → cookie jar (or empty on failure). Logins are rate-limited per IP and every
# session on this machine shares the harness, so a 429 waits and tries again.
login() {
  local j c i; j=$(mktemp)
  for i in 1 2 3 4 5 6 7 8 9 10 11 12; do
    curl -sk -c "$j" -o /dev/null "$U/admin/login"
    c=$(curl -sk -b "$j" -c "$j" -o /dev/null -w '%{http_code}' -H 'Accept: application/json' -H "X-XSRF-TOKEN: $(xsrf "$j")" \
      -X POST "$U/actions/users/login" --data-urlencode "loginName=$1" --data-urlencode "password=$2")
    [ "$c" = 200 ] && { echo "$j"; return; }
    [ "$c" = 429 ] || return
    sleep 10
  done
}
code() { curl -sk -b "$1" -o /dev/null -w '%{http_code}' "$U/admin/$2"; }
# cpost <jar> <path> <handle> → HTTP status of a CP form POST (with the XSRF header)
cpost() { curl -sk -b "$1" -c "$1" -o /dev/null -w '%{http_code}' -H "X-XSRF-TOKEN: $(xsrf "$1")" -X POST "$U/admin/$2" --data-urlencode "handle=$3"; }
names() { curl -sk "$U/${2:-}wm/$1" | tr -d '\n' | sed 's/<[^>]*>/|/g' | tr -s '|'; }
lis() { curl -sk "$U/wm/$1${2:-}" | grep -oE '<li data-id="[^"]*">[^<]*' | sed 's/<li data-id="//; s/">/=/' | tr '\n' ' '; }

# ---- Setup ----
"$H/bin/harness-lock" bash -c "cp '$SRC/Customer.php.fixture' app/Models/Customer.php && cp '$SRC/WingmanInvoice.php.fixture' app/Models/WingmanInvoice.php && for f in helpers fixtures gql-fixtures multisite-fixtures; do cp '$SRC/'\$f.php storage/wm-\$f.php; done"
mkdir -p resources/views/wm && cp "$SRC"/templates/* resources/views/wm/
tk "require 'storage/wm-fixtures.php';" >/dev/null
edition lite
ok "settings: baseline saves" "$(tk 'echo wm_baseline();')" "ok"
# Fresh invoice rows each run, written without Eloquent so they have no elements yet.
tk "foreach (JustinHoltWeb\\Wingman\\Generated\\Elements\\WingmanInvoice::find()->status(null)->trashed(null)->all() as \$e) CraftCms\\Cms\\Support\\Facades\\Elements::deleteElement(\$e, true); echo 1;" >/dev/null
sql "DELETE FROM wingman_invoices"
ddev artisan craft:wingman:cleanup WingmanInvoice >/dev/null 2>&1   # elements left behind by an interrupted run
sql "INSERT INTO wingman_invoices (number, amount, paid, issued_on, token, created_at, updated_at) VALUES ('INV-001', 1200, 1, '2026-11-01', 'secret-1', NOW(), NOW()), ('INV-002', 300, 0, '2026-11-02', 'secret-2', NOW(), NOW()), ('INV-003', 50, 0, NULL, 'secret-3', NOW(), NOW());"

# ---- Settings validation ----
v=$(tk "echo wm_save([['Customer','App.Models.Customer','Customers'],['Entry','App.Models.WingmanInvoice'],['Bill','App.Models.User'],['Dupe','App.Models.Customer','','nope','softDelete'],['bad name','App.Nope']]);")
ok "validation: a handle colliding with a core element type" "$(echo "$v" | grep -c 'reference handle “entry”')" "1"
ok "validation: Craft's own User model is refused" "$(echo "$v" | grep -c 'one of Craft’s own models')" "1"
ok "validation: one model, one handle" "$(echo "$v" | grep -c 'already registered as Customer')" "1"
ok "validation: label column must exist" "$(echo "$v" | grep -c 'no “nope” column')" "1"
ok "validation: soft-delete trash needs SoftDeletes" "$(echo "$v" | grep -c 'doesn’t use SoftDeletes')" "1"
ok "validation: handles are identifiers" "$(echo "$v" | grep -c 'must start with a capital letter')" "1"
ok "validation: unknown classes are refused" "$(echo "$v" | grep -c 'App\\Nope isn’t an Eloquent model')" "1"
ok "validation: hidden column can't be the label" "$(tk "echo wm_save([['Customer','App.Models.Customer','Customers','email']]);" | grep -c 'hidden on the model')" "1"
ok "validation: a model with elements can't be removed" "$(tk "echo wm_save([['WingmanInvoice','App.Models.WingmanInvoice','Invoices','number','softDelete','hide']]);" | grep -c 'Customer can’t be removed')" "1"
ok "validation: nothing was saved" "$(ddev artisan craft:wingman:status 2>/dev/null | grep -c '| WingmanInvoice ')" "1"

# ---- Health + backfill ----
ok "health: invoices rows/linked/missing/orphaned" "$(status_row WingmanInvoice)" "3/0/3/0"
ok "backfill: dry run counts, creates nothing" "$(ddev artisan craft:wingman:backfill WingmanInvoice --dry-run 2>/dev/null | grep -oE '[0-9]+ would be created')/$(status_row WingmanInvoice)" "3 would be created/3/0/3/0"
ok "backfill: unknown handle fails" "$(ddev artisan craft:wingman:backfill Nope >/dev/null 2>&1; echo $?)" "1"
J=$(login admin claudepassword)
[ -n "$J" ] || echo "! admin login failed"
ok "backfill: CP POST without the XSRF token is refused" "$(curl -sk -b "$J" -o /dev/null -w '%{http_code}' -X POST "$U/admin/wingman/backfill" --data handle=WingmanInvoice)" "419"
ok "backfill: CP POST for an unknown handle 404s" "$(cpost "$J" wingman/backfill 'App\Models\Customer')" "404"
ok "backfill: CP POST creates the elements" "$(cpost "$J" wingman/backfill WingmanInvoice)/$(status_row WingmanInvoice)" "302/3/3/0/0"
ok "backfill: a second run creates nothing" "$(ddev artisan craft:wingman:backfill WingmanInvoice 2>/dev/null | grep -oE '[0-9]+ created, [0-9]+ skipped')" "0 created, 3 skipped"
ok "backfill: the model rows weren't written back" "$(sql "SELECT COUNT(*) FROM wingman_invoices WHERE updated_at > created_at + INTERVAL 1 SECOND")" "0"
ok "sync: an Eloquent create gets an element (core ModelSync)" "$(tk "App\\Models\\WingmanInvoice::create(['number' => 'INV-004', 'amount' => 75, 'token' => 'secret-4']); echo 1;" >/dev/null; status_row WingmanInvoice)" "4/4/0/0"

# ---- Per-model options ----
INV1=$(sql "SELECT id FROM wingman_invoices WHERE number='INV-001'")
ok "label column: the element is labelled by its number" "$(tk "echo (string) wm_element('WingmanInvoice', $INV1);")" "INV-001"
ok "trash → soft-deletes the row" "$(tk "CraftCms\\Cms\\Support\\Facades\\Elements::deleteElement(wm_element('WingmanInvoice', $INV1)); echo 1;" >/dev/null; sql "SELECT deleted_at IS NOT NULL FROM wingman_invoices WHERE id=$INV1")" "1"
ok "trash: soft-deleted rows count as neither rows nor missing" "$(status_row WingmanInvoice)" "3/3/0/0"
ok "restore → restores the row" "$(tk "CraftCms\\Cms\\Support\\Facades\\Elements::restoreElement(wm_element('WingmanInvoice', $INV1)); echo 1;" >/dev/null; sql "SELECT deleted_at IS NULL FROM wingman_invoices WHERE id=$INV1")" "1"
nav() { curl -sk -b "$J" "$U/admin/dashboard" | grep -oE "admin\\\\/$1[\"?]" | head -1 | wc -l | tr -d ' '; }
ok "sidebar: Customers shown, Invoices hidden" "$(nav customers)/$(nav wingman-invoices)" "1/0"
ok "sidebar: a hidden model's index still works" "$(code "$J" wingman-invoices)" "200"

# ---- Orphans ----
INV2=$(sql "SELECT id FROM wingman_invoices WHERE number='INV-002'")
EL2=$(tk "echo wm_element('WingmanInvoice', $INV2)->id;")
sql "DELETE FROM wingman_invoices WHERE id=$INV2"
ok "orphans: a row deleted outside Eloquent is reported" "$(status_row WingmanInvoice)" "3/3/0/1"
ok "orphans: dry run deletes nothing" "$(ddev artisan craft:wingman:cleanup WingmanInvoice --dry-run 2>/dev/null | grep -oE '[0-9]+ orphaned elements would be deleted')/$(status_row WingmanInvoice)" "1 orphaned elements would be deleted/3/3/0/1"
ok "orphans: Utilities shows Delete orphans" "$(curl -sk -b "$J" "$U/admin/utilities/wingman" | grep -c 'Delete orphans')" "1"
ok "orphans: CP POST deletes them for good" "$(cpost "$J" wingman/cleanup WingmanInvoice)/$(status_row WingmanInvoice)/$(sql "SELECT COUNT(*) FROM elements WHERE id=$EL2")" "302/3/3/0/0/0"
ok "orphans: the other elements survive" "$(sql "SELECT COUNT(*) FROM elements WHERE type LIKE '%Generated%Elements%WingmanInvoice'")" "3"
ok "orphans: a soft-deleted row's element isn't an orphan" "$(sql "UPDATE wingman_invoices SET deleted_at=NOW() WHERE id=$INV1"; status_row WingmanInvoice | cut -d/ -f4; sql "UPDATE wingman_invoices SET deleted_at=NULL WHERE id=$INV1")" "0"

# ---- Queued backfill (more than 500 missing → queue) ----
sql "INSERT INTO wingman_invoices (number, amount, created_at, updated_at) SELECT CONCAT('BULK-', LPAD(n, 4, '0')), n, NOW(), NOW() FROM (SELECT a.N + b.N * 10 + c.N * 100 + 1 AS n FROM (SELECT 0 N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a, (SELECT 0 N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b, (SELECT 0 N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) c) t"
ok "queue: 700 missing" "$(status_row WingmanInvoice)" "703/3/700/0"
ok "queue: the CP button says Queue backfill" "$(curl -sk -b "$J" "$U/admin/utilities/wingman" | grep -c 'Queue backfill')" "1"
ok "queue: CP POST queues instead of running" "$(cpost "$J" wingman/backfill WingmanInvoice)/$(status_row WingmanInvoice)" "302/703/3/700/0"
ok "queue: a second click doesn't queue a second job (unique)" "$(cpost "$J" wingman/backfill WingmanInvoice >/dev/null; sql "SELECT COUNT(*) FROM jobs WHERE payload LIKE '%BackfillModel%'")" "1"
ddev artisan queue:work --queue=default --stop-when-empty >/dev/null 2>&1
ok "queue: the worker backfills them all" "$(status_row WingmanInvoice)" "703/703/0/0"
tk "foreach (JustinHoltWeb\\Wingman\\Generated\\Elements\\WingmanInvoice::find()->where('wingman_invoices.number', 'like', 'BULK-%')->status(null)->all() as \$e) CraftCms\\Cms\\Support\\Facades\\Elements::deleteElement(\$e, true); echo 1;" >/dev/null
ok "queue: bulk fixtures removed (hard delete removes rows too)" "$(status_row WingmanInvoice)" "3/3/0/0"

# ---- Permissions (non-admin users) ----
tk "echo wm_user('wingman_viewer', ['accesscp', 'elements:customer:view']);" >/dev/null
tk "echo wm_user('wingman_tools', ['accesscp', 'utility:wingman']);" >/dev/null
V=$(login wingman_viewer wingman-pass-123); T=$(login wingman_tools wingman-pass-123)
ok "perm: both fixture users can log in" "$([ -n "$V" ] && [ -n "$T" ] && echo yes)" "yes"
ok "perm: viewer sees the Customers index" "$(code "$V" customers)" "200"
ok "perm: viewer can't see Invoices (no permission)" "$(code "$V" wingman-invoices)" "403"
ok "perm: viewer can't open Utilities → Wingman" "$(code "$V" utilities/wingman)" "403"
ok "perm: viewer can't backfill or clean up" "$(cpost "$V" wingman/backfill Customer)/$(cpost "$V" wingman/cleanup Customer)" "403/403"
ok "perm: tools user opens the utility" "$(code "$T" utilities/wingman)" "200"
ok "perm: tools user can backfill" "$(cpost "$T" wingman/backfill WingmanInvoice)" "302"
ok "perm: tools user can't see the Customers index" "$(code "$T" customers)" "403"
ok "perm: logged out → no backfill" "$(curl -sk -o /dev/null -w '%{http_code}' -X POST "$U/admin/wingman/backfill" --data handle=Customer | grep -cE '^(302|401|419)$')" "1"

# ---- CP pages ----
ok "CP: Customers index" "$(code "$J" customers)" "200"
CEL=$(tk "echo JustinHoltWeb\\Wingman\\Generated\\Elements\\Customer::find()->where('customers.name', 'Grace Hopper')->one()->id;")
ok "CP: a customer's edit page" "$(code "$J" "customers/$CEL")" "200"
ok "CP: Utilities → Wingman" "$(code "$J" utilities/wingman)" "200"
ok "CP: settings" "$(code "$J" settings/plugins/wingman)" "200"
rows=$(curl -sk -b "$J" -H "X-XSRF-TOKEN: $(xsrf "$J")" -H 'Accept: application/json' -H 'Content-Type: application/json' -X POST "$U/admin/actions/element-indexes/get-elements" \
  --data '{"elementType":"JustinHoltWeb\\Wingman\\Generated\\Elements\\Customer","source":"*","context":"index","viewState":{"mode":"table"}}' | python3 -c 'import json,sys; print(json.load(sys.stdin).get("html",""))')
ok "CP index rows: names and companies" "$(echo "$rows" | grep -q 'Grace Hopper' && echo "$rows" | grep -q 'Navy' && echo yes)" "yes"
ok "CP index rows: no hidden email" "$(echo "$rows" | grep -c '@example.com')" "0"

# ---- Templates: Twig + Blade (Lite) ----
ok "Twig template query lists customers" "$(names twig | grep -c 'Grace Hopper')" "1"
ok "Blade template query lists customers" "$(names blade | grep -c 'Grace Hopper')" "1"
ok "Twig and Blade return the same rows" "$(names twig)" "$(names blade)"
ok "wingman.element(model) in Twig" "$(lis lookup | grep -c "=Grace Hopper")" "1"
ok "Twig and Blade: element lookups match" "$(lis lookup)" "$(lis lookup-blade)"
ok "Twig and Blade: null → null, invoices query" "$(curl -sk "$U/wm/lookup" | grep -oE 'id="(none|invoice)">[^<]*' | tr '\n' ' ')" "$(curl -sk "$U/wm/lookup-blade" | grep -oE 'id="(none|invoice)">[^<]*' | tr '\n' ' ')"
ok "Lite: reverse lookups need Pro" "$(curl -sk -o /dev/null -w '%{http_code}' "$U/wm/related")/$(tk "try { App\\Models\\Customer::first()->craftEntries()->ids(); echo 'ran'; } catch (LogicException \$e) { echo \$e->getMessage(); }")" "500/Reverse lookups need Wingman Pro."

# ---- GraphQL ----
tk "require 'storage/wm-gql-fixtures.php';" >/dev/null
gqraw() { local o i; for i in 1 2 3 4 5 6 7 8 9 10 11 12; do
  o=$(curl -sk -w '\n%{http_code}' -H "Authorization: Bearer $(printf 'wm%s' "$1" | awk '{ s=$0; while (length(s) < 32) s=s "0"; print s }')" -H 'Content-Type: application/json' \
    --data "$(python3 -c 'import json,sys; print(json.dumps({"query": sys.argv[1]}))' "$2")" "$U/actions/graphql/api")
  [ "$(echo "$o" | tail -1)" = 429 ] || { echo "$o" | sed '$d'; return; }; sleep 10; done; }
gq() { gqraw "$1" "$2" \
  | python3 -c 'import json,sys
try: r=json.load(sys.stdin)
except Exception: print("not json"); sys.exit()
if r.get("errors"): print("ERR:" + "; ".join(e.get("message","") for e in r["errors"])); sys.exit()
d=r.get("data") or {}; j=lambda *v: "/".join(str(x) for x in v)
print(eval(sys.argv[1]))' "$3"; }
ok "gql Lite: no wingman queries even with the permission" "$(gq full '{ wingmanCustomers { name } }' '0')" "ERR:Cannot query field \"wingmanCustomers\" on type \"Query\"."
edition pro
ok "gql: list, ordered, with typed attributes" "$(gq full '{ wingmanCustomers(orderBy: "name desc", limit: 2) { name company } }' 'j(*[c["name"] for c in d["wingmanCustomers"]])')" "Mary Jackson/Linus Pauling"
ok "gql: count and where" "$(gq full '{ wingmanCustomersCount(where: [{attribute: "company", value: "NASA"}]) }' 'd["wingmanCustomersCount"]')" "3"
ok "gql: one by id, label" "$(gq full "{ wingmanCustomer(id: [$CEL]) { id label company } }" 'j(d["wingmanCustomer"]["label"], d["wingmanCustomer"]["company"])')" "Grace Hopper/Navy"
ok "gql: \$hidden attributes aren't fields" "$(gq full '{ wingmanCustomers { email } }' '0')" 'ERR:Cannot query field "email" on type "WingmanCustomer".'
ok "gql: \$hidden attributes can't be filtered on" "$(gq full '{ wingmanCustomersCount(where: [{attribute: "email", value: "grace@example.com"}]) }' '0')" "ERR:“email” isn’t an attribute of Customer."
ok "gql: \$hidden attributes can't be sorted on" "$(gq full '{ wingmanCustomers(orderBy: "email") { name } }' '0')" "ERR:“email” can’t be sorted on."
ok "gql: orderBy can't carry SQL" "$(gq full '{ wingmanCustomers(orderBy: "name; DROP TABLE customers") { name } }' '0')" "ERR:orderBy must be an attribute name, optionally followed by asc or desc."
ok "gql: casts → Int/Boolean/String types" "$(gq full '{ wingmanWingmanInvoices(orderBy: "number") { number amount paid issuedOn: issued_on } }' 'j(*[d["wingmanWingmanInvoices"][0][k] for k in ("number", "amount", "paid")], d["wingmanWingmanInvoices"][0]["issuedOn"][:10])')" "INV-001/1200/True/2026-11-01"
ok "gql: invoice token (hidden) isn't a field" "$(gq full '{ wingmanWingmanInvoices { token } }' '0' | grep -c 'Cannot query field "token"')" "1"
ok "gql: limit is capped at 100" "$(gq full '{ wingmanCustomers(limit: 100000) { id } }' 'len(d["wingmanCustomers"])')" "5"
ok "gql: trashed elements aren't returned" "$(tk "CraftCms\\Cms\\Support\\Facades\\Elements::deleteElement(wm_element('WingmanInvoice', $INV1)); echo 1;" >/dev/null; gq full '{ wingmanWingmanInvoicesCount }' 'd["wingmanWingmanInvoicesCount"]')" "2"
tk "CraftCms\\Cms\\Support\\Facades\\Elements::restoreElement(wm_element('WingmanInvoice', $INV1)); echo 1;" >/dev/null
ok "gql: schema without Invoices has no invoice queries" "$(gq cust '{ wingmanWingmanInvoicesCount }' '0' | grep -c 'Cannot query field "wingmanWingmanInvoicesCount"')" "1"
ok "gql: schema without any model has none" "$(gq none '{ wingmanCustomers { name } }' '0')" 'ERR:Cannot query field "wingmanCustomers" on type "Query".'
ETYPE=$(gq full '{ entries(section: "articles", id: 8) { __typename } }' 'd["entries"][0]["__typename"]')
ok "gql: entries' customers field resolves to WingmanCustomer" "$(gq full "{ entries(id: 8) { ... on $ETYPE { customers { name } } } }" 'j(*sorted(c["name"] for c in d["entries"][0]["customers"]))')" "Katherine Johnson/Linus Pauling"
ok "gql: the field is absent for a schema without Customer" "$(gq none "{ entries(id: 8) { ... on $ETYPE { customers { name } } } }" '0' | grep -c 'Cannot query field "customers"')" "1"
ok "gql: CP schema editor offers each model" "$(curl -sk -b "$J" "$U/admin/graphql/schemas/new" | grep -c 'wingman.WingmanInvoice:read' | awk '{print ($1>0)?"yes":"no"}')" "yes"

# ---- Pro: reverse lookups (Twig + Blade + the model trait) ----
ok "Pro Twig: relatedEntries(element, field) and (model)" "$(lis related | grep -oE '=Katherine Johnson:[0-9,]*/[0-9]+')" "=Katherine Johnson:8/1"
ok "Pro: Twig and Blade reverse lookups match" "$(lis related)" "$(lis related-blade)"
ok "Pro trait: craftEntries()" "$(tk "echo json_encode(App\\Models\\Customer::where('name', 'Linus Pauling')->first()->craftEntries('customers')->ids());")" "[8]"
eagerq() { tk "\$c = App\\Models\\Customer::orderBy('id')->limit($1)->get(); DB::enableQueryLog(); App\\Models\\Customer::loadCraftEntries(\$c, 'customers'); echo count(DB::getQueryLog());"; }
ok "Pro trait: loadCraftEntries" "$(tk "\$c = App\\Models\\Customer::orderBy('id')->get(); App\\Models\\Customer::loadCraftEntries(\$c, 'customers'); echo json_encode(\$c->mapWithKeys(fn (\$m) => [\$m->name => array_map(fn (\$e) => \$e->id, \$m->getRelation('craftEntries'))])->filter()->all());")" '{"Linus Pauling":[8],"Katherine Johnson":[8]}'
ok "Pro trait: eager loading costs the same for 2 models as for 5 (no N+1)" "$(eagerq 2)" "$(eagerq 5)"
ok "Pro: a field of another type is refused" "$(tk "try { App\\Models\\Customer::first()->craftEntries('summary'); } catch (InvalidArgumentException \$e) { echo \$e->getMessage(); }")" "“summary” isn’t a Customer relation field."
ok "Pro: an unknown element type is refused" "$(tk "try { App\\Models\\Customer::first()->craftRelated('nope'); } catch (InvalidArgumentException \$e) { echo \$e->getMessage(); }")" "“nope” isn’t an element type."
ok "Pro: craftElement() for a row without an element" "$(tk "echo json_encode(App\\Models\\Customer::make(['name' => 'x'])->craftElement());")" "null"

# ---- Pro: condition rules ----
cond() { tk "\$c = JustinHoltWeb\\Wingman\\Generated\\Elements\\Customer::createCondition(); \$c->addConditionRule(\$c->createConditionRule(['class' => JustinHoltWeb\\Wingman\\Conditions\\ModelAttributeConditionRule::class, 'attribute' => '$1', 'operator' => '$2', 'value' => '$3'])); \$q = JustinHoltWeb\\Wingman\\Generated\\Elements\\Customer::find(); \$c->modifyQuery(\$q); echo \$q->count();"; }
ok "condition: one rule per listed attribute (none for email)" "$(tk "echo implode(',', collect(JustinHoltWeb\\Wingman\\Generated\\Elements\\Customer::createCondition()->getSelectableConditionRules())->filter(fn (\$r) => \$r instanceof JustinHoltWeb\\Wingman\\Conditions\\ModelAttributeConditionRule)->map(fn (\$r) => \$r->attribute)->sort()->values()->all());")" "company,name"
ok "condition: company = NASA" "$(cond company '=' NASA)" "3"
ok "condition: name begins with" "$(cond name 'bw' Grace)" "1"
ok "condition: a hidden attribute matches nothing" "$(cond email '=' grace@example.com)" "0"
edition lite
ok "condition: Lite offers no model rules" "$(tk "echo collect(JustinHoltWeb\\Wingman\\Generated\\Elements\\Customer::createCondition()->getSelectableConditionRules())->filter(fn (\$r) => \$r instanceof JustinHoltWeb\\Wingman\\Conditions\\ModelAttributeConditionRule)->count();")" "0"

# ---- Multisite: our own `wingmanfr` site, removed at the end ----
for i in 1 2 3 4 5; do
  "$H/bin/harness-lock" ddev artisan tinker --execute "require 'storage/wm-multisite-fixtures.php';" >/dev/null 2>&1
  [ "$(tk "echo CraftCms\\Cms\\Support\\Facades\\Sites::getSiteByHandle('wingmanfr') ? 'yes' : 'no';")" = yes ] && break; sleep 3
done
if [ "$(tk "echo CraftCms\\Cms\\Support\\Facades\\Sites::getSiteByHandle('wingmanfr') ? 'yes' : 'no';")" = yes ]; then
  ok "multisite: Twig on wingmanfr lists the same customers" "$(names twig wingmanfr/)" "$(names twig)"
  ok "multisite: Blade on wingmanfr lists the same customers" "$(names blade wingmanfr/)" "$(names blade)"
  ok "multisite: elements live on the primary site only (non-localized)" "$(tk "echo json_encode([JustinHoltWeb\\Wingman\\Generated\\Elements\\Customer::isLocalized(), JustinHoltWeb\\Wingman\\Generated\\Elements\\Customer::find()->site('wingmanfr')->count(), JustinHoltWeb\\Wingman\\Generated\\Elements\\Customer::find()->site('*')->count()]);")" "[false,5,5]"
    ok "multisite: a new row gets one element, not one per site" "$(tk "App\\Models\\WingmanInvoice::create(['number' => 'INV-FR', 'amount' => 1]); echo 1;" >/dev/null; sql "SELECT COUNT(*) FROM elements_sites es JOIN elements_models em ON em.elementId = es.elementId JOIN wingman_invoices i ON i.id = em.modelKey WHERE i.number='INV-FR'")" "1"
  for i in 1 2 3 4 5; do
    "$H/bin/harness-lock" ddev artisan tinker --execute "\$s = CraftCms\\Cms\\Support\\Facades\\Sites::getSiteByHandle('wingmanfr'); if (\$s) CraftCms\\Cms\\Support\\Facades\\Sites::deleteSite(\$s); echo 1;" >/dev/null 2>&1
    [ "$(tk "echo CraftCms\\Cms\\Support\\Facades\\Sites::getSiteByHandle('wingmanfr') ? 'x' : 'gone';")" = gone ] && break; sleep 2
  done
  ok "multisite: wingmanfr removed" "$(tk "echo CraftCms\\Cms\\Support\\Facades\\Sites::getSiteByHandle('wingmanfr') ? 'still there' : 'gone';")" "gone"
else
  echo "- multisite: skipped (couldn't create the wingmanfr site)"
fi

# ---- Cleanup ----
for i in 1 2 3 4 5; do r=$(tk 'echo wm_cleanup();'); [ "$r" = ok ] && break; sleep 2; done
ok "cleanup: fixtures removed" "$r" "ok"
tk "\$e = wm_element('WingmanInvoice', App\\Models\\WingmanInvoice::where('number', 'INV-FR')->value('id') ?? 0); if (\$e) CraftCms\\Cms\\Support\\Facades\\Elements::deleteElement(\$e, true); echo 1;" >/dev/null
for i in 1 2 3 4 5; do r=$(tk 'echo wm_baseline();'); [ "$r" = ok ] && break; sleep 2; done
ok "baseline restored" "$r" "ok"
echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
