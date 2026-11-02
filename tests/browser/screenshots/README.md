# README screenshots

`readme.shot.ts` writes the PNGs in `docs/images/` that the module README shows. It is not a test
suite: it runs with its own config, `playwright.screenshots.config.ts`, which the browser tests and
CI never load, so a normal test run never rewrites the images. Never edit the PNGs by hand;
regenerate them.

What it needs:

- a Silverstripe 6 host with this module installed and the browser fixtures from
  `tests/browser/fixtures/` copied into its `app/src/`, then the database built.
  `FaShowcaseHolder` seeds the content on that build: a published "News" holder at `/news` (date
  archive by month, categories, tags, four items per page) with nine news items below it. The
  holder and item pages are rendered by `FaShowcaseHolderController` and
  `FaShowcaseItemController`, around the module's own templates;
- network access to cdn.jsdelivr.net: those pages load Bootstrap 5's stylesheet, because the
  module's templates carry Bootstrap 5 classes;
- `BROWSER_TARGET_URLS` pointing at that host (only the `ss6` target is used, else the first).

The shared runner in the maintainers' workspace does all of that:

```bash
BROWSER_ONLY=ss6 tools/browser/run.sh silverstripe-filterablearchive --config=playwright.screenshots.config.ts
```

Against a host you started yourself, from this directory:

```bash
npm ci
BROWSER_TARGET_URLS="ss6=http://127.0.0.1:8876" npx playwright test --config=playwright.screenshots.config.ts
```

The demo content is seeded once. To reseed it after changing `FaShowcaseHolder`, archive the News
page in the CMS (or rebuild the host) and build the database again.
