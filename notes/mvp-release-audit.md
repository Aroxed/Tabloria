# Publishly MVP Release Audit

Date: 2026-05-13
Target: 0.1 private beta / WordPress.org review candidate

## Release Decision

Publishly is ready for first MVP/private beta publication as a zip-ready WordPress plugin and WordPress.org review candidate.

The product should still be described conservatively as an MVP/private beta quality release until real tester feedback is collected.

## MVP Scope

- CSV upload.
- Smart column detection.
- Published Display as the core saved object.
- Gutenberg Display block.
- Gutenberg Display Grid block.
- Shortcode fallback.
- Search, visitor filters, default filters, sorting, pagination.
- Published Displays management.
- Display editor.
- Update Data flow.
- Frontend rendering through `FrontendRenderer`.

## Explicitly Deferred

- Real XLSX import.
- Google Sheets.
- Column rename/remove UI.
- Drag-and-drop visible field order.
- Advanced style controls.
- Advanced query/filter builder.
- Page builder widgets.
- Advanced WordPress.org directory polish such as screenshots, banners, and localized marketing copy.

## Suspicious CSV Fixtures

Added:

- `publishly/samples/suspicious-duplicate-headers.csv`
- `publishly/samples/suspicious-js-payloads.csv`
- `publishly/samples/suspicious-mixed-types.csv`
- `publishly/samples/suspicious-sparse-columns.csv`

These cover duplicate headers, quoted commas, mixed types, missing values, JavaScript/XSS payloads, formula-looking text, invalid image values, sparse rows, and long text wrapping.

## Verification Run

Passed:

- Parsed every CSV in `publishly/samples`.
- Generated sanitized Display configs for every sample across all six Display types.
- `node --check` for `admin.js`, `block.js`, and `frontend.js`.
- `php -l` for all plugin PHP files through the WordPress Docker container.
- `git diff --check`.
- WordPress.org-facing headers are present in `publishly.php` and `readme.txt`.
- QA sample CSVs stay in the repository but are excluded from the WordPress.org submission zip.
- Unit tests cover unsafe CSV values, parser limits, config validation, and renderer output boundaries.
- Official Plugin Check plugin 1.9.0 passed against the installed submission zip: `Success: Checks complete. No errors found.`

## Product Notes

- Use Publishly Display Grid for multiple Displays on a page.
- Gutenberg Table should not be used as a layout container for Publishly Displays.
- Publishly contains unsafe/narrow embeds and shows admin-only guidance where appropriate.
- CSV-only support is acceptable for this MVP as long as the user-facing notice remains clear.
