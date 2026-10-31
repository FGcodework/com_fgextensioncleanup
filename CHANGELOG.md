# Changelog

## [1.9.0] - 2026-10-31

### Added
- **Removal of orphaned component records.** A component record whose directories are missing gets a "Remove record" button (also in bulk) when it is not Joomla core, not `protected`, not part of a package and its site, administrator **and** API directory are all confirmed missing. Besides the `#__extensions`, `#__schemas` and `#__update_sites_extensions` rows, its administrator menu entries are removed the way Joomla's uninstaller does it (menu table delete + rebuild). Database tables of the component, `#__assets`, site menu items and files are never touched (site menu items of a missing component show up in the "Other orphaned records" report).
- A JSON backup is saved first and includes the administrator menu rows. Restoring a component backup re-creates the extension record, schemas and update-site links, but not the administrator menu entries (reinstall the component to recreate them) – the restore message says so.

### Changed
- Removal messages and the safe-mode note now mention components.

## [1.8.0] - 2026-10-31

### Added
- **Search box** above the table: filters by name, element, ID, folder and type (case-insensitive, combines with the status and type filters, Esc clears it).
- **Other orphaned records (read-only report).** Module instances without an installed module extension, site menu items of a missing component, schema versions of missing extensions and update-site links to missing extensions. The component only reports them and never deletes them.
- **User Actions Log registration.** The installer script registers the component in `#__action_logs_extensions` (on install and on every update, removed on uninstall), so it appears in the "Extension" filter of the Users → User Actions Log.

### Changed
- `script.php` is now part of the package and `build.sh`.

## [1.7.2] - 2026-10-31

### Changed
- Update server and changelog URLs in the manifest now point to the `master` branch of the public GitHub repository `FGcodework/com_fgextensioncleanup`.

## [1.7.1] - 2026-10-31

### Fixed
- Restore from backup failed with "insertObject(): Argument #2 ($object) could not be passed by reference" – `insertObject()` needs a variable, not an expression (all four insert calls fixed).

## [1.7.0] - 2026-10-31

### Added
- **Bulk removal.** Orphaned plugin/library records that can be removed get a checkbox; "Select all removable (visible)" and a red "Remove selected (N)" button remove them with one confirmation. Every record still goes through exactly the same server-side checks and gets its own backup and Action Log entry; a filtered-out row can never stay selected, and a failure of one record does not stop the others.
- **Orphaned update sites.** New panel listing update sites with no linked extension (or whose linked `#__extensions` records no longer exist) – the usual cause of "Update site not found". Remove one or all; the link rows and cached `#__updates` rows go with them, a JSON backup is saved first. Update sites of Joomla itself (`update.joomla.org`) are never offered for removal.
- **Restore from backup.** New panel "Backups of removed records" (core.admin only) lists the JSON backups with Restore and Delete buttons. Restore re-inserts the rows in one transaction, refuses when the same ID or the same extension already exists, accepts only existing table columns, and only plugin/library/update-site backups; a restored file is renamed to `.restored`. File names from the browser are accepted only as a base name in the exact backup pattern.
- Confirmation dialog can now be customised per form (title, button label, accent tone for non-destructive actions such as Restore).

### Changed
- Backup handling moved into one class (`Service/BackupStore`); write actions share one guard (token, `core.admin`, error handling).

## [1.6.0] - 2026-10-31

### Added
- **Backup before removal.** Before an orphaned record is deleted, the complete rows (`#__extensions`, `#__schemas`, `#__update_sites_extensions`) are saved as JSON to Joomla's log folder (`administrator/logs/fgextensioncleanup_backup_<type>_<id>_<date>_<random>.json`, permissions 0600, random part in the name because plugin parameters can contain secrets). The path is shown in the success message. If the backup cannot be written, nothing is removed; if the deletion fails, the backup is deleted again.
- **Action log.** Repairs and removals are written to Joomla's User Actions Log (Users → User Actions Log) with user, record name, element, ID and backup file. A problem with logging never turns a completed change into an error.

## [1.5.1] - 2026-10-31

### Changed
- The browser's native `confirm()` box is replaced by a dialog in the FG style (light/dark): title, question, name of the extension being removed, "Cancel" (initial focus, Esc closes, click outside closes) and a red "Remove record" button.
- "Refresh scan" and every action now show a "Please wait…" overlay with a spinner until the page reloads (also cleared when returning through browser history).

## [1.5.0] - 2026-10-31

### Added
- Safe removal of the database record of an **orphaned library** (e.g. a leftover `FOF` record from Joomla 3). Same safeguards as for plugins, plus library-specific ones: not Joomla core, not `protected`, not part of a package, the library manifest **and** the `libraries/<name>` directory are confirmed missing, and no other extension mentions the library in its manifest cache or parameters. Deletes only the `#__extensions` row (plus its `#__schemas` and `#__update_sites_extensions` rows) in one transaction; the decision is recomputed on the server, not taken from the form.
- When a library cannot be removed, the detail text says why (belongs to a package / files still present / mentioned by another extension).

### Changed
- Removal messages, confirmation and the safe-mode note now cover plugins and libraries.

## [1.4.0] - 2026-10-31

### Added
- Joomla core extensions are recognised through `ExtensionHelper::checkIfCoreExtension()` (the list maintained by Joomla for the running version). They are grouped under their own "Joomla core" status tile (with a count of findings, if any), are marked "Joomla core" in the table and are no longer counted as OK / Repairable / Ambiguous / Orphaned / Other.
- Core extensions are never offered for repair or removal (also enforced in the model).

### Fixed
- False "orphaned record – library directory missing" for core libraries such as `lib_joomla`: Joomla installs its library files directly into `libraries/`, so the directory `libraries/<libraryname>` is not expected for core libraries.

## [1.3.3] - 2026-10-31

### Changed
- Less cramped rows: the extension name and the element now use two lines (name / `protected` and `package_id`; element / client), and all rows have the same minimum height (58 px).

## [1.3.2] - 2026-10-31

### Changed
- The expand/collapse arrow moved from the Action column to its own first column (before ID), with an accent-coloured, more visible button; clicking the detail text of a record also expands it.
- Element, client and `package_id` no longer wrap onto extra lines.

## [1.3.1] - 2026-10-31

### Changed
- Compact results table: one line per record (about a third of the previous height). The detail text is shortened to one line with an ellipsis (full text on hover) for records that are OK; problem records keep the full text.
- Candidate paths, manifests, package constituents and blocked paths moved into an expandable row under the record (arrow button in the Action column); filters hide/show the expanded row together with its record.
- Element and client, `protected` and `package_id` are shown inline instead of on extra lines; smaller action buttons.

## [1.3.0] - 2026-10-31

### Changed
- Administrator page redesigned in the visual style of fgcodework.github.io (same colour variables, 18px panels, pill filters, eyebrow + hero heading with highlighted "FG"). Light and dark variants follow the Joomla administrator colour scheme (`data-bs-theme`).
- Status summary tiles are now the status filter (clickable, active tile highlighted); the extension type filter is a row of pill buttons with counts. The two drop-downs and the duplicate count cards are gone.
- Results table restyled (status and type as pills, details collapsible, action buttons in the same style); all styles are scoped to `.fg-cleanup` and registered through the Web Asset Manager, so they do not leak into the rest of the administrator.

## [1.2.0] - 2026-10-31

### Changed
- The status filter is now a row of clickable buttons with record counts (All, OK, Repairable, Ambiguous, Orphaned, Other) instead of a drop-down; the active button is highlighted. The extension type filter stays a drop-down.

## [1.1.3] - 2026-10-31

### Fixed
- Status and type filters (and the visible-records counter) did nothing since 1.1.1: the script registered through the Web Asset Manager is printed in `<head>`, before the table and selects exist. Initialisation now waits for `DOMContentLoaded`.

## [1.1.2] - 2026-10-31

### Fixed
- Translations were not displayed after upgrading from 1.1.0 (raw `COM_FGEXTENSIONCLEANUP_*` keys everywhere except the title). Joomla loads `administrator/language/<tag>/com_fgextensioncleanup.ini` first and only falls back to the component folder when that file is missing; the two-key files left by 1.1.0 therefore shadowed the new ones. Language files are installed to `administrator/language/<tag>/` again (via `<languages>`), which overwrites the old files on upgrade.

## [1.1.1] - 2026-10-31

### Added
- Full localisation: all UI texts, diagnostic results and messages now come from language files (`en-GB` and `sk-SK`); no hard-coded strings remain in PHP.
- `config.xml` with the Permissions tab and an Options toolbar button (visible with `core.admin`), so ACL for the component can be configured.
- `<updateservers>` and `<changelogurl>` in the manifest (GitHub raw `updates.xml` / `changelog.xml`).
- Removing an orphaned plugin record now also removes its rows in `#__schemas` and `#__update_sites_extensions`, in one transaction.

### Changed
- Repair and removal actions require `core.admin` (opening the component still requires `core.manage`); action buttons are hidden for users without it.
- Orphaned plugin records whose `folder` is set but whose directory is missing now point the user to the standard uninstall in Extensions → Manage.
- The model uses `getDatabase()`; the controller uses `$this->input` instead of the deprecated `Factory::getApplication()->getInput()`.
- The filter/confirmation script is registered through the Web Asset Manager and no longer uses inline `onsubmit`, so it works with a strict CSP.

### Fixed
- An invalid CSRF token now redirects with an error message (`checkToken()`) instead of raising an unhandled exception.
- Removed the stray `<?php` at the end of the template and the redundant `display()` override in the controller.

## [1.1.0] - 2026-10
- Read-only diagnostics extended from plugins to components, modules, libraries, packages, templates, languages and file extensions; package constituent matching; `open_basedir` protection; MariaDB fix for plugin `folder` repair.
