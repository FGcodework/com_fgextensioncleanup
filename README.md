<p align="center"><img src="assets/logo.png" alt="FG Extension Cleanup" width="128"></p>

# FG Extension Cleanup

[![Release](https://img.shields.io/github/v/release/FGcodework/com_fgextensioncleanup?style=flat-square)](https://github.com/FGcodework/com_fgextensioncleanup/releases/latest)
![Joomla 5 | 6](https://img.shields.io/badge/Joomla-5.x%20%7C%206.x-1a73e8?style=flat-square)
![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777bb3?style=flat-square)
[![License GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-green?style=flat-square)](LICENSE)

Administrator component for **Joomla 5 and 6** that diagnoses and safely cleans orphaned records in `#__extensions` and `#__update_sites` – the leftovers of extensions whose files are gone but whose database registration remained. Part of the **FG** extension series. UI languages: English (en-GB) and Slovak (sk-SK).

## Features

- **Read-only diagnostics** of all extension types (plugins, components, modules, libraries, packages, templates, languages, files): missing directories or manifests, ambiguous or invalid manifests, unsafe paths, symbolic links, package constituents.
- **Joomla core extensions** (as defined by `ExtensionHelper::checkIfCoreExtension()`, so it follows the running Joomla version) are grouped separately, never counted as orphaned and never offered for repair or removal.
- **Repair** of a plugin with an empty `folder` when exactly one valid plugin directory is found.
- **Removal of orphaned plugin and library records** (single or bulk). A record must be orphaned, not `protected`, not Joomla core; a library additionally must not belong to a package, its manifest and `libraries/<name>` must be missing and no other extension may mention it. Only the registration rows (`#__extensions`, `#__schemas`, `#__update_sites_extensions`) are deleted – never files or custom tables.
- **Orphaned update sites** (no linked extension, or the linked records are gone) – the usual cause of "Update site not found". Joomla's own update sites are never removed.
- **Backup before every deletion** as JSON in Joomla's log folder (`administrator/logs/`, mode 0600, random part in the file name), with **Restore** and **Delete** buttons in the component. If a backup cannot be written, nothing is deleted.
- Entries in Joomla's **User Actions Log** for every repair, removal, restore and backup deletion.
- **Search** by name, element, ID or folder, plus a **read-only report** of other orphaned records (modules without a module extension, menu items of a missing component, schema versions, update-site links).
- Status tiles and type chips as filters, expandable detail rows, light and dark mode, FG design.

## Permissions

`core.manage` opens the component (read-only view). Repair, removal, restore and backup deletion need `core.admin` (configure in *Options → Permissions*). All write actions are POST requests protected by a CSRF token, and every decision is recomputed on the server – nothing is trusted from the browser.

## Installation

1. Download `com_fgextensioncleanup_vX.Y.Z.zip` from [Releases](https://github.com/FGcodework/com_fgextensioncleanup/releases/latest).
2. *System → Install → Extensions* – upload the ZIP (installing over an older version upgrades it).
3. Open *Components → FG Extension Cleanup*.

Updates are delivered through the built-in Joomla update server (`updates.xml` in this repository).

> Always make a database backup before removing records on a production site. The built-in JSON backups restore database rows only, not files.

## Building the package

```bash
./build.sh   # creates com_fgextensioncleanup_v<version>.zip from the manifest version
```

## Releasing

1. Bump `<version>` in `com_fgextensioncleanup.xml`, add an entry to `CHANGELOG.md`, `changelog.xml` and `updates.xml` (version and download URL).
2. `./build.sh`
3. Commit, push to `master`, then publish a GitHub release tagged `vX.Y.Z` with the ZIP attached (the file name in `updates.xml` must match).

## Support

If this saved you some time, you can [buy me a coffee on Ko-fi](https://ko-fi.com/FGcodework).

## License

GNU General Public License version 2 or later – see [LICENSE](LICENSE).
