# Changelog

## 3.1.0 (unreleased)

Silverstripe 5 and 6 from one line (`main`, renamed from `master`). Silverstripe 4 is not supported; projects on it can
stay on the `2.0.x` tags.

### Upgrading

- **From 2.0.x (Silverstripe 5):** change your constraint to `^3.1`. Nothing to migrate in the
  database. If you applied the extensions by their SS3-era short names (`FilterableArchiveHolderExtension`
  and friends, as the old README showed), use the namespaced class names in the README's Setup section.
- **From 3.0.x (Silverstripe 6):** a `^3` constraint picks this up. Nothing to migrate.
- The module now requires `symbiote/silverstripe-gridfieldextensions` (`^4 || ^5`), which the
  holder's category/tag grids always used, and `silverstripe/cms` rather than only framework.
  Composer installs both.

### Fixed

- **An item page whose parent is not a holder no longer hangs.** `getHolderPage()` re-read the same
  parent on every pass of its loop, so for such an item (or one at the root of the site tree) the
  request, including the item's CMS edit form, ran until PHP's time limit. It now walks up the tree
  and finds a holder further up too.
- **Editing an item without a holder above it, and `getRelatedItems()` on it, no longer fatal.**
- **A `Datetime` date field keeps its time when the item is saved in the CMS.** The module always
  added a plain date field, which reset the time to 00:00:00 on every save. It now uses the field's
  own form field (a date field for a `Date`, a date-and-time field for a `Datetime`).
- **Silverstripe 6: the holder's filter settings respect their toggles again.** SS6 scaffolds page
  fields from extensions, so the label/unit fields and "items per page" showed even while switched
  off, and the categories/tags relations were scaffolded on items as well. Both extensions now
  exclude these from scaffolding (`scaffold_cms_fields_settings`).
- **Dropdown labels set in config on the holder class are used.** They were read from the
  extension's own config, so only the defaults (`Date`, `Categories`, `Tags`) ever showed.
- **Related items list each item once**, where one shares several tags/categories with this one.
- **The filter dropdowns build without a running controller** (a CLI task or queued job) instead of
  fataling.
- **Old `archive/2024/05` links filter again.** The route still existed but its year/month were
  never read, so it showed every item.
- **`FilterableArchiveFilter.ss` marks the active tag and date filters.** The tag wrapper closed its
  `class` attribute too early and both read the active value from the wrong scope, so the
  `tag-filtering`/`currently-filtering` classes never rendered.
- **English CMS labels load.** `lang/en.yml` was keyed `nl:`, so English fell back to the raw
  defaults (eg "DateTitle" instead of "Dates label").

### Changed

- Requires `silverstripe/cms ^5 || ^6`, `silverstripe/tagfield ^3 || ^4`,
  `symbiote/silverstripe-gridfieldextensions ^4 || ^5`, PHP `^8.1`.
- `composer.json` carries a `funding` entry.

### Removed

- `templates/GridFieldAddByDBField.ss`: an orphan. Its `GridFieldAddByDBField` class was deleted
  earlier (9a98395) and nothing in the module, in gridfieldextensions or in a known consumer theme
  renders it.
- `_config/upgrade.yml`: it mapped six SS3-era short names to namespaced classes that never existed
  on this line (see Upgrading above for the real class names), and without a `---` header block its
  `mappings:` key was loaded as ordinary config.

### Added

- A behavioural test suite (`tests/`) and CI across Silverstripe 5 and 6.
- A `LICENSE` file. The licence is unchanged (BSD-3-Clause, as `composer.json` always declared);
  the file names the copyright holders: Michael van Schaik (Restruct), Itayi Patrick Chito-voro and
  Bart van Irsel.
- README: setup with the current class names, every config option, the templates and the public API.

## 3.0.2, 3.0.1, 3.0.0

Silverstripe 6 only (`silverstripe/framework ^6`).

## 2.0.11

Silverstripe 4 and 5.
