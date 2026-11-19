---
title: FAQ
slug: faq
order: 90
summary: Craft 6 and the beta, how Wingman relates to core, editions, and safety.
---

**Does it work on Craft 5?** No. Wingman is a native Craft 6 plugin. Craft 6 is in beta, and so is
Wingman: use caution on production.

**Craft 6 already has Eloquent elements. What does Wingman add?** Core needs a PHP element class,
a field class and a registration call per model. Wingman registers models from the control panel,
guards against the collisions that would take a site down, adds health, backfill and orphan
cleanup, and in Pro, reverse lookups, attribute conditions and GraphQL.

**Does Wingman change my tables?** No. Craft records links in its own `elements_models` table.
Your model's rows change only when someone saves an element in the control panel (or trashes one,
if you chose soft deletes).

**Is `eval()` involved?** Wingman mints one empty `final` subclass per model from a fixed
template. The only variable part is the handle, which must match `^[A-Z][A-Za-z0-9]{0,63}$`, so
nothing typed into the control panel can become code.

**What happens if I uninstall or disable it?** Relation fields become missing fields, model
elements stop loading, and their index pages 404. Your rows are untouched. Re-enable it and
everything comes back.

**Can I rename a handle?** Not once it has elements: the generated class names are stored in
Craft's tables and project config.

**Is it free?** Lite is free. Pro is paid (price to be announced).
