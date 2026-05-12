=== Publishly ===
Contributors: publishly
Tags: spreadsheet, csv, gutenberg, listings, directory
Requires at least: 6.3
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 0.1.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publishly turns spreadsheet rows into reusable searchable WordPress Displays.

== Description ==

Publishly is a friendly spreadsheet publishing assistant for WordPress.

Upload a CSV spreadsheet, create a reusable Published Display, and insert that Display in Gutenberg with the Publishly Display block.

This MVP focuses on the core loop:

* CSV upload
* smart column detection
* field roles
* visible fields
* simple search, filters, sorting, and pagination
* reusable Published Displays
* Gutenberg block rendering by Display ID
* shortcode fallback: [publishly id="123"]

== Installation ==

1. Upload the `publishly` folder to `/wp-content/plugins/`.
2. Activate Publishly from the WordPress Plugins screen.
3. Open Publishly -> Create Display.
4. Upload a CSV file and follow the guided flow.

== Frequently Asked Questions ==

= Does Publishly edit spreadsheet rows? =

No. The spreadsheet remains the source of truth. Publishly controls how the data appears as a reusable Display.

= Does the Gutenberg block store the full Display configuration? =

No. The block stores the selected Display ID. The Display configuration lives in Publishly.

= Does this MVP support XLSX? =

The architecture includes an XLSX parser service, but the current MVP imports CSV files first.

== Changelog ==

= 0.1.8 =

* Removed the duplicate side preview while the Preview editor tab is active.
* Improved admin preview card field wrapping so emails and phone numbers do not break into vertical text.

= 0.1.7 =

* Limited field-role dropdowns to relevant columns so price-like roles are not mapped to names, emails, phones, or photos.
* Hid optional roles when a source does not contain matching columns.

= 0.1.6 =

* Added a dedicated Display Editor with tabs for preview, fields, search and filters, style, data source, and publishing.
* Added in-editor CSV replacement with a visible column match summary.

= 0.1.5 =

* Made appearance controls update the preview immediately and clarified that preview shows a sample.
* Rendered real image URLs in the admin preview.

= 0.1.4 =

* Added visible pagination status and preview pagination controls.

= 0.1.3 =

* Improved card layout, field label readability, and fallback titles for mixed row datasets.

= 0.1.2 =

* Tightened smart detection so identifiers, names, dates, and quantities are not mislabeled as status.

= 0.1.1 =

* Fixed admin loading state event binding.

= 0.1.0 =

* Initial MVP implementation.
