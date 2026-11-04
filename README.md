<p align="center"><img src="assets/logo.png" alt="FG Extension Cleanup" width="128"></p>

<h1 align="center">FG Extension Cleanup</h1>

<p align="center">
  <img src="https://img.shields.io/github/v/release/FGcodework/com_fgextensioncleanup?color=FF6B4A&label=release" alt="Latest release">
  <img src="https://img.shields.io/badge/Joomla-5.x%20%7C%206.x-1a3867.svg?logo=joomla&logoColor=white" alt="Joomla">
  <img src="https://img.shields.io/badge/PHP-8.1%2B-purple.svg?logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/license-GPL--2.0%2B-green.svg" alt="License">
  <img src="https://img.shields.io/github/downloads/FGcodework/com_fgextensioncleanup/total?cacheSeconds=3600&color=brown" alt="Downloads">
  <a href="https://ko-fi.com/FGcodework"><img src="https://img.shields.io/badge/support-Ko--fi-F16061.svg?logo=ko-fi&logoColor=white" alt="Support on Ko-fi"></a>
</p>

Administrator component for **Joomla 5 and 6** that diagnoses and safely cleans orphaned records in `#__extensions` and `#__update_sites` – the leftovers of extensions whose files are gone but whose database registration remained. Part of the **FG** extension series. UI languages: English (en-GB) and Slovak (sk-SK).

## Features

- **Read-only diagnostics** of all extension types (plugins, components, modules, libraries, packages, templates, languages, files): missing directories or manifests, ambiguous or invalid manifests, unsafe paths, symbolic links, package constituents.
- **Joomla core extensions** (as defined by `ExtensionHelper::checkIfCoreExtension()`, so it follows the running Joomla version) are grouped separately, never counted as orphaned and never offered for repair or removal.
- **Repair** of a plugin with an empty `folder` when exactly one valid plugin directory is found.
- **Removal of orphaned plugin, library and component records** (single or bulk). A record must be orphaned, not `protected`, not Joomla core; a library additionally must not belong to a package, its manifest and `libraries/<name>` must be missing and no other extension may mention it; a component must be absent from the site, administrator and API directory and not be part of a package (its administrator menu entries are removed with it). Only the registration rows (`#__extensions`, `#__schemas`, `#__update_sites_extensions`) are deleted – never files or custom tables.
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
python build.py   # creates com_fgextensioncleanup_v<version>.zip and writes its SHA-256 and SHA-512 into updates.xml
```

The ZIP is reproducible (sorted entries, fixed timestamps), so the checksum in `updates.xml` always belongs to the file `build.py` produced. Upload **that exact file** as the release asset; a re-zipped copy would fail Joomla's checksum verification.

## Tests

```bash
php tests/run.php            # all tests
php tests/run.php restore    # only tests whose name contains "restore"
```

The suite is dependency-free (a tiny runner plus stubs of the Joomla classes and a fake database), so it runs anywhere PHP 8.1+ is installed - no Composer or PHPUnit needed.

## Releasing

1. Bump `<version>` in `com_fgextensioncleanup.xml`, add an entry to `CHANGELOG.md`, `changelog.xml` and `updates.xml` (version and download URL).
2. `python build.py` (after `php tests/run.php`) - it fills in the `<sha256>` and `<sha512>` of `updates.xml`.
3. Commit, push to `master`, then publish a GitHub release tagged `vX.Y.Z` with the ZIP attached (the file name in `updates.xml` must match).

## Support

If this saved you some time, you can [buy me a coffee on Ko-fi](https://ko-fi.com/FGcodework).

## License

GNU General Public License version 2 or later – see [LICENSE](LICENSE).
