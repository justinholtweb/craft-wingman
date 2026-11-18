#!/bin/bash
# Live checks against the Craft 6 harness. Prereqs: App\Models\Customer (tests/Harness/Customer.php.fixture)
# registered as `Customer`; tests/Harness/templates/* copied to the harness's resources/views/wm/.
set -uo pipefail
exec < /dev/null
H=/Users/jholt/Sites/plugin-testing-craft6
U=https://plugin-testing-craft6.ddev.site
cd "$H" || exit 2
pass=0 fail=0
ok() { if [ "$2" = "$3" ]; then echo "✓ $1"; pass=$((pass+1)); else echo "✗ $1 (got '$2', want '$3')"; fail=$((fail+1)); fi; }
names() { curl -sk "$U/wm/$1" | tr -d '\n' | sed 's/<[^>]*>/|/g' | tr -s '|'; }
ok "Twig template query lists customers" "$(names twig | grep -c 'Grace Hopper')" "1"
ok "Blade template query lists customers" "$(names blade | grep -c 'Grace Hopper')" "1"
ok "Twig and Blade return the same rows" "$(names twig)" "$(names blade)"
ok "status command runs" "$(ddev artisan craft:wingman:status 2>/dev/null | grep -c '| Customer')" "1"
J=$("$H/bin/cp-session")
ok "CP index loads" "$(curl -sk -b "$J" -o /dev/null -w '%{http_code}' "$U/admin/customers")" "200"
ok "Utilities → Wingman loads" "$(curl -sk -b "$J" -o /dev/null -w '%{http_code}' "$U/admin/utilities/wingman")" "200"
ok "settings page loads" "$(curl -sk -b "$J" -o /dev/null -w '%{http_code}' "$U/admin/settings/plugins/wingman")" "200"
echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
