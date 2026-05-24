=== Tabloria Searchable Displays ===
Contributors: tabloria
Tags: spreadsheet, csv, gutenberg, listings, directory
Requires at least: 6.3
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.1.44
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tabloria turns CSV files into searchable WordPress displays: listings, catalogs, directories, schedules, pricing tables, and data tables.

== Description ==

Tabloria turns CSV files into reusable WordPress displays: searchable listings, product catalogs, directories, schedules, pricing tables, and simple data tables.

Instead of publishing spreadsheet rows as a plain table, Tabloria helps you turn CSV data into structured website content. Upload a CSV, choose a display type, map columns to visual fields, preview the result, and insert the saved display into Gutenberg.

Live demo: https://tabloria.site/demo.html

= Best for =

* Product catalogs from CSV files
* Searchable listings for services, resources, properties, or locations
* Team or organization directories
* Event schedules from date-based spreadsheet rows
* Pricing tables from spreadsheet data
* Simple searchable data tables
* WordPress pages where a plain table is not enough

= Display types =

Tabloria currently includes six display types:

* Searchable Listings
* Product Catalog
* Team Directory
* Event Schedule
* Pricing Table
* Simple Data Table

= Core workflow =

* Upload a CSV file
* Review detected columns
* Choose field roles and visible fields
* Configure search, filters, sorting, and pagination
* Save a reusable Published Display
* Insert the Display in Gutenberg by Display ID
* Use the Tabloria Display Grid block for multiple Displays
* Use the shortcode fallback: `[tabloria id="123"]`

= Gutenberg and shortcode support =

Saved displays can be inserted with the Tabloria Display block. Use the Tabloria Display Grid block when you want to arrange multiple Published Displays on the same page. Each display also has a shortcode fallback for classic workflows, widgets, or page builders.

== Installation ==

1. Upload the `tabloria-searchable-displays` folder to `/wp-content/plugins/`.
2. Activate Tabloria Searchable Displays from the WordPress Plugins screen.
3. Open Tabloria -> Create Display.
4. Upload a CSV file and follow the guided flow.
5. Insert a Tabloria Display block, or use Tabloria Display Grid for multiple Displays.

== Frequently Asked Questions ==

= Is Tabloria a table plugin? =

Tabloria can publish simple data tables, but its main purpose is creating reusable WordPress displays from spreadsheet data.

= What display types are available? =

Tabloria currently supports Searchable Listings, Product Catalog, Team Directory, Event Schedule, Pricing Table, and Simple Data Table.
 
= What file format does it support? =

The current MVP imports CSV files first. The architecture includes an XLSX parser service, but XLSX import is not the main release path yet.

= Does Tabloria edit spreadsheet rows? =

No. The spreadsheet remains the source of truth. Tabloria controls how the data appears as a reusable Display.

= Does the Gutenberg block store the full Display configuration? =

No. The block stores the selected Display ID. The Display configuration lives in Tabloria.

= Can I put several Displays in a Gutenberg table? =

Use Tabloria Display Grid instead. Gutenberg tables are for tabular content, not layout, and can squeeze interactive Displays into table cells.

= Can I use shortcodes? =

Yes. Every saved display has a shortcode fallback, for example `[tabloria id="123"]`.

== Screenshots ==

1. Choose the display type that fits your CSV data.
2. Upload a CSV file directly inside WordPress.
3. Review detected columns and field types before mapping.
4. Map CSV columns to visual fields and preview the display.
5. Save the display as reusable WordPress content.
6. Insert a saved Tabloria display in the Gutenberg editor.
7. Publish a page with the selected Tabloria display.

== Changelog ==

= 0.1.44 =

* Renamed the plugin to Tabloria Searchable Displays with the `tabloria-searchable-displays` text domain and package slug.
* Restricted Display REST list, detail, and preview endpoints to site administrators.

= 0.1.43 =

* Added centralized CSV value validation for uploaded spreadsheet data.
* Hardened URL, image, email, formula-looking, oversized, and binary-looking CSV values.
* Added unit tests for suspicious CSV data, config validation, and frontend rendering boundaries.

= 0.1.42 =

* Added a Tabloria Display Grid block for arranging multiple Published Displays safely.
* Contained Tabloria Displays inside Gutenberg tables and narrow containers, with an admin-only table warning.

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

* Fixed WordPress admin select arrows repeating inside Tabloria dropdown controls.

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

* Initial MVP implementation
