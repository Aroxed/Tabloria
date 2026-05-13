=== Publishly ===
Contributors: publishly
Tags: spreadsheet, csv, gutenberg, listings, directory
Requires at least: 6.3
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.1.43
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publishly turns spreadsheet rows into reusable searchable WordPress Displays.

== Description ==

Publishly is a friendly spreadsheet publishing assistant for WordPress.

Upload a CSV spreadsheet, create a reusable Published Display, and insert that Display in Gutenberg with the Publishly Display block.

Use the Publishly Display Grid block when you want to arrange multiple Published Displays on the same page.

This MVP focuses on the core loop:

* CSV upload
* smart column detection
* field roles
* visible fields
* simple search, filters, sorting, and pagination
* reusable Published Displays
* Gutenberg block rendering by Display ID
* Publishly Display Grid block for multiple Displays
* shortcode fallback: [publishly id="123"]

== Installation ==

1. Upload the `publishly` folder to `/wp-content/plugins/`.
2. Activate Publishly from the WordPress Plugins screen.
3. Open Publishly -> Create Display.
4. Upload a CSV file and follow the guided flow.
5. Insert a Publishly Display block, or use Publishly Display Grid for multiple Displays.

== Frequently Asked Questions ==

= Does Publishly edit spreadsheet rows? =

No. The spreadsheet remains the source of truth. Publishly controls how the data appears as a reusable Display.

= Does the Gutenberg block store the full Display configuration? =

No. The block stores the selected Display ID. The Display configuration lives in Publishly.

= Can I put several Displays in a Gutenberg table? =

Use Publishly Display Grid instead. Gutenberg tables are for tabular content, not layout, and can squeeze interactive Displays into table cells.

= Does this MVP support XLSX? =

The architecture includes an XLSX parser service, but the current MVP imports CSV files first.

== Changelog ==

= 0.1.43 =

* Added centralized CSV value validation for uploaded spreadsheet data.
* Hardened URL, image, email, formula-looking, oversized, and binary-looking CSV values.
* Added unit tests for suspicious CSV data, config validation, and frontend rendering boundaries.

= 0.1.42 =

* Added a Publishly Display Grid block for arranging multiple Published Displays safely.
* Contained Publishly Displays inside Gutenberg tables and narrow containers, with an admin-only table warning.

= 0.1.41 =

* Prefixed visitor filter values with their field label, for example Status: In Stock.

= 0.1.40 =

* Clarified frontend controls so search says it searches all fields and visitor filter defaults read like value filters.

= 0.1.39 =

* Skipped the duplicate Display type selection screen after spreadsheet detection and moved type changes into setup.

= 0.1.38 =

* Ensured full-width table displays load their frontend alignment script with the Gutenberg block and align against the visible viewport.

= 0.1.37 =

* Centered wide and full-width frontend table displays against the viewport instead of inherited theme container offsets.

= 0.1.36 =

* Reworded the detection review step so users continue with detected columns instead of approving subjective copy.

= 0.1.35 =

* Removed the redundant Published badge from Published Display cards.

= 0.1.34 =

* Made Published Display cards open the editor on click and removed Update Data from card actions.

= 0.1.33 =

* Restored Display type switching in the editor and aligned the editor field controls.

= 0.1.32 =

* Moved Display type switching to the create setup flow and cleaned up the editor fields layout.

= 0.1.31 =

* Removed the editor Style tab and added Display type switching with live preview updates.

= 0.1.30 =

* Reworked the starting screen into a Display type decision step with type-specific previews.

= 0.1.29 =

* Made setup previews render the selected Display type instead of using one generic card grid.

= 0.1.28 =

* Added numeric less-than and greater-than operators for default filters.

= 0.1.27 =

* Made setup sections collapsible so the controls stay compact beside the live preview.

= 0.1.26 =

* Improved field mapping rows so descriptions sit above full-width column selectors.

= 0.1.25 =

* Fixed Default Filter controls so field and operator selections persist before a value is entered.

= 0.1.24 =

* Fixed source REST validation fatals and renamed the misleading Insert action to Copy shortcode.

= 0.1.23 =

* Added type-specific thumbnails for Published Display cards.

= 0.1.22 =

* Replaced the duplicate Upload Spreadsheet hero action with a Published Displays shortcut.

= 0.1.21 =

* Fixed table previews and published tables so wide column sets scroll horizontally instead of hiding fields.

= 0.1.20 =

* Fixed admin previews so default filters and sorting affect the visible sample immediately.

= 0.1.19 =

* Removed the old separate Customize and Preview wizard screens from the create flow.

= 0.1.18 =

* Combined Display setup, options, and full preview into one setup-and-preview step.

= 0.1.17 =

* Fixed admin previews so Simple Data Table displays render as tables instead of cards.

= 0.1.16 =

* Removed an outdated explanatory helper card from Display Content setup.

= 0.1.15 =

* Removed cosmetic Look controls from the main publishing flow and kept professional defaults.

= 0.1.14 =

* Simplified appearance controls into compact Look and Page controls panels.

= 0.1.13 =

* Fixed WordPress admin select arrows repeating inside Publishly dropdown controls.

= 0.1.12 =

* Combined appearance customization with the final website preview.
* Made visual controls compact so the preview takes most of the screen.

= 0.1.11 =

* Made Display Content mapping outcome-specific with listing, product, profile, schedule, pricing, and table examples.
* Renamed details controls by Display type so extra fields are separated from the main Display structure.

= 0.1.10 =

* Reworked field mapping as Display Content with real-time examples from the uploaded spreadsheet.
* Added live examples so column choices are no longer a black box.

= 0.1.9 =

* Improved the Gutenberg block preview with real Display rows, images, source metadata, and clearer edit actions.
* Added frontend display-type polish, including table rendering for Simple Data Table displays.

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
