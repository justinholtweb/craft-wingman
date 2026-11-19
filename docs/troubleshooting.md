---
title: Troubleshooting
slug: troubleshooting
order: 80
summary: Models missing from the list, saves refused, missing sidebar items, and the Craft 6 beta.
---

**My model isn't in the list.** It must be a concrete (non-abstract) Eloquent model in one of
the model directories, under the project root and outside `vendor/`. Craft's own models, and app
models extending them (`App\Models\User` in a Craft 6 project), are never offered.

**“would share the reference handle … with …”** The handle collides with another element type
(`Entry`, `Asset`, `User` …). Pick another, such as `AppEntry`.

**“can't be removed while it has elements”** Delete the model's elements first (select all in its
index → Delete, then empty the trash), or keep it registered.

**A model disappeared from the sidebar.** Check its *Sidebar* setting, and the person's *View*
permission (or the model policy's `viewAny`). The log says if Wingman skipped a model at boot.

**Elements print as “Customer 27”.** The model has no `name` or `title` column; set a *Label column*.

**Reverse lookups throw “need Wingman Pro”.** They're a Pro feature.

**A GraphQL query says “Cannot query field”.** Wingman's queries need Pro and the model's
*Query …* permission on the schema. Hidden attributes are never fields.

**Craft 6 is in beta.** So is Wingman. Expect changes between betas, keep backups, and use
caution on production.
