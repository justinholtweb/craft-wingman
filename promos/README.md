# Plugin Store promo images

Marketing images for the Wingman listing on the Craft Plugin Store, in the same theme as
[justinholt.com/plugins/craft-wingman](https://justinholt.com/plugins/craft-wingman).

```bash
./build.sh          # all slides
./build.sh "2 5"    # just slides 2 and 5
```

Output: `out/wingman-promo-N.jpg`, 1920×1080 (rendered at 2× in headless Chrome, downsampled).
**JPEG, never PNG.** `fonts.css` is generated and gitignored.

The cover and the closing slide carry the Craft 6 beta caution, and every footer says
"Craft CMS 6 (beta)". Keep it there until Craft 6 and Wingman leave beta. The Pro badge has no
price yet: add it to slides 1 and 7 once it's decided.

The watermark is the winged mark's **outline** (`assets/watermark.svg`, the stroked
`resources/icon-mask.svg` paths), not the filled mark.

| # | Slide |
| --- | --- |
| 1 | Cover — Lite free, Pro, Craft 6 beta caution |
| 2 | Pick a model, give it a handle (what you get) |
| 3 | Twig or Blade, same query |
| 4 | Guarded — what's refused |
| 5 | Health — status, queued backfill, orphan cleanup |
| 6 | Pro — reverse lookups, conditions, GraphQL |
| 7 | Closing — summary, beta caution |
