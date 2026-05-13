# Publishly WordPress.org Submission Notes

Date: 2026-05-13
Version: 0.1.43

## Package

Submit the generated `publishly` plugin zip, not the whole repository.

The zip should contain a single top-level directory:

```text
publishly/
```

Runtime/plugin files included:

- `publishly.php`
- `readme.txt`
- `uninstall.php`
- `assets/`
- `includes/`

The bundled JavaScript and CSS in `assets/` are readable runtime files.

`samples/` is excluded from the submitted zip. Sample CSV files remain in the repository only as QA fixtures.

`tests/` is excluded from the submitted zip. Unit tests remain in the repository and should be run before packaging.

`src/` and `package.json` are excluded from the submitted zip because they are not required at runtime.

## WordPress.org Checks

- Main plugin header includes `Version`, `Requires at least`, `Tested up to`, `Requires PHP`, `License`, and `License URI`.
- `readme.txt` stable tag matches `PUBLISHLY_VERSION`.
- `Tested up to` is `6.9`, matching the current WordPress 6.9 release family.
- CSV upload, storage, config, and frontend rendering boundaries are covered by unit tests.
- A JavaScript/XSS payload CSV fixture verifies parsing and rendered HTML escaping.
- Suspicious CSV fixtures parse successfully in development and remain outside the submission zip.
- Official Plugin Check plugin 1.9.0 was run in the clean Docker zip-test install with `wp plugin check publishly`; result: no errors found.

## Manual Steps Before Submission

- Confirm the WordPress.org username in `Contributors:` is correct. It is currently `publishly`.
- On the WordPress.org submission form, it is OK to confirm that the plugin has been tested with Plugin Check and indicated issues are resolved.
- Submit the zip through the WordPress.org plugin submission form from the intended owner account.
- After approval, add directory assets through SVN if desired: icon, banner, screenshots, and polished long description copy.

## Positioning

Use conservative release language:

> Publishly is an MVP/private beta plugin that turns CSV spreadsheets into reusable searchable WordPress Displays.

Avoid promising XLSX, Google Sheets, advanced dashboards, or spreadsheet editing in this first release.
