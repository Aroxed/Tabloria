=== Tabloria Searchable Displays ===
Contributors: publishly
Tags: csv, searchable table, product catalog, directory, data table
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn a CSV into searchable catalogs, directories, coupon displays, listings, and responsive data tables—without code.

== Description ==

Tabloria turns CSV rows into polished, searchable WordPress content without custom code.

Upload a CSV, choose which columns become titles, images, prices, badges, descriptions, coupon codes, or links, and publish the result as a product catalog, directory, coupon display, listing, schedule, pricing display, or responsive data table.

Live demo: [Try the searchable product catalog](https://tabloria.site/demo.html)

= Best for =

* CSV to searchable WordPress displays
* Searchable directories for people, teams, services, resources, locations, or organizations
* Product catalogs from spreadsheet data
* Coupon codes, affiliate offers, and link-only deals
* Resource libraries and public data lists
* Event schedules from date-based CSV rows
* Pricing tables from structured spreadsheet rows
* Simple searchable data tables
* WordPress pages where a plain table is not enough

= Display types =

Tabloria currently includes seven display types:

* Searchable Listings
* Product Catalog
* Team Directory
* Event Schedule
* Pricing Table
* Coupon Code Display
* Simple Data Table

= Core workflow =

* Upload a CSV file
* Review the detected columns
* Choose a display type
* Map CSV columns to titles, descriptions, images, prices, dates, links, or other visual fields
* Configure search, filters, sorting, and pagination
* Preview the display before publishing
* Save a reusable display
* Insert the display with the Tabloria Display block
* Optionally filter each block placement by a different column value
* Arrange several displays with the Tabloria Display Grid block
* Use the shortcode fallback: `[tabloria id="123"]`

= Gutenberg and shortcode support =

Saved displays can be inserted with the Tabloria Display block. A page-specific exact-value filter lets one master Display power separate category pages without creating duplicate Displays. Choose the filter column and value in the block sidebar, or use `[tabloria id="123" filter_field="category" filter_value="Shoes"]` in classic workflows and page builders. Matching is case-insensitive.

Use the Tabloria Display Grid block when you want to arrange multiple saved displays on the same page. Each display also has a shortcode fallback.

= Coupon and affiliate displays =

Coupon Code Display supports coupon and deal-only rows in the same CSV. When a row contains a coupon code, Show coupon reveals the code and opens the affiliate link in a new tab from the same click. Visitors can then copy the code. When the coupon code cell is empty, the row shows a direct deal button instead. Reveal, copy, and deal button text can be customized, including labels such as View deal or Visit website.

= Visitor controls and responsive layout =

In Search & Filters, you can customize the search placeholder and no-results message, choose visitor filters, and set a dropdown highlight color. When a visitor has selected a filter, a Reset all button clears the active filters and search query.

Enable Show result count to display a summary below the results. Set Result count label to use the wording that fits your content, such as `Offers`, `Properties`, or `Courses`; for example, “Showing 1 to 12 of 48 Offers”.

For card-based Displays, Maximum card width prevents a single matching result from stretching across the entire desktop or tablet layout. Choose 320px, 420px, 520px, or No limit. Simple Data Tables scroll horizontally inside their own container on narrow screens, so the page itself does not scroll sideways.

= Why Tabloria? =

Traditional table plugins are useful when you want to publish rows and columns. Tabloria is focused on turning CSV data into visitor-friendly displays that can be searched, filtered, and reused across your WordPress site.

That makes it useful when your data is more like a directory, catalog, listing, schedule, or resource library than a simple spreadsheet table.

== Installation ==

1. Upload the `tabloria-searchable-displays` folder to `/wp-content/plugins/`.
2. Activate Tabloria Searchable Displays from the WordPress Plugins screen.
3. Open Tabloria -> Create Display.
4. Upload a CSV file and follow the guided setup flow.
5. Insert a Tabloria Display block, use Tabloria Display Grid for multiple displays, or copy the shortcode.

== Frequently Asked Questions ==

= Is Tabloria a table plugin? =

Tabloria can publish simple searchable data tables, but its main purpose is creating reusable WordPress displays from CSV and spreadsheet-style data. It is especially useful for directories, catalogs, listings, schedules, pricing tables, and resource libraries.

= Is Tabloria a TablePress alternative? =

It depends on your use case. If you mainly need a traditional table with rows and columns, a classic table plugin may be enough. If you want to turn CSV data into searchable directories, catalogs, listings, schedules, or reusable displays, Tabloria is designed for that workflow.

= What display types are available? =

Tabloria currently supports Searchable Listings, Product Catalog, Team Directory, Event Schedule, Pricing Table, Coupon Code Display, and Simple Data Table.

= Can one Display power multiple category pages? =

Yes. Insert the same saved Display on each page and set a different page-specific filter in each block. The shortcode equivalent is `[tabloria id="123" filter_field="category" filter_value="Category A"]`.

= Can coupon and deal-only rows share one CSV? =

Yes. Map the coupon code and affiliate URL columns. Rows with a code get reveal and copy controls; rows with an empty code get the customizable deal-only button.

= What file format does Tabloria support? =

The current MVP imports CSV files. XLSX import is not part of the main public workflow yet.

= Does Tabloria edit spreadsheet rows? =

No. The CSV file is used as the source data for the display. Tabloria controls how that data appears on your WordPress site.

= Does the Gutenberg block store the full display configuration? =

No. The block stores the selected Display ID. The display configuration lives inside Tabloria, so you can update the saved display without rebuilding the page content.

= Can I put several displays in a Gutenberg table? =

Use Tabloria Display Grid instead. Gutenberg tables are for tabular content, not layout, and can squeeze interactive displays into table cells.

= Can I use shortcodes? =

Yes. Every saved display has a shortcode fallback, for example `[tabloria id="123"]`.

== Screenshots ==

1. Publish a polished, searchable product catalog from CSV rows.
2. Help visitors find the right result with instant search and filters.
3. Map CSV columns and preview the finished Display inside WordPress.
4. Insert a saved Display with the native Gutenberg block.
5. Manage reusable Displays without rebuilding page content.

== Changelog ==

= 0.2.1 =

* Fixed PHP 8.1 compatibility for CSV validation and upload request checks.

= 0.2.0 =

* Added page-specific exact-value filters so one master Display can be reused across category pages.
* Added Coupon Code Display with reveal-and-visit, click-to-copy, and customizable deal-only actions.
* Added a customizable no-results message for search and visitor filters.

= 0.1.49 =

* Added Reset all for visitor filters, customizable result labels, card-width controls, and mobile table scrolling.
* Fixed card-width rendering so an unlimited layout does not create an invalid CSS grid.

= 0.1.48 =

* Added configurable search placeholder text, result count visibility, filter-value column labels, and dropdown highlight color.

= 0.1.47 =

* Added the optional Hide rows until search setting, disabled by default.
* Keep rows hidden on initial render and after clearing search, while reusing existing search, filters, and pagination.

= 0.1.46 =

* Added card URL display options for rendering URL fields as links or theme-friendly buttons.
* Added optional custom link/button text and button style choices for URL fields in card displays.

= 0.1.45 =

* Render detected URL columns as clickable links in frontend cards, data tables, and admin previews.
* Added clearer CSV upload size feedback and restored the row limit used by CSV parsing and sanitization.

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
