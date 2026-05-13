You are Codex. Build a production-ready WordPress plugin called “Publishly”.

Publishly is a friendly spreadsheet publishing assistant for WordPress.

Core promise:
Upload a spreadsheet → create a reusable Published Display → insert it into Gutenberg.

This is NOT:
- a database management system
- an Airtable clone
- a wpDataTables clone
- a JetEngine replacement
- an ACF replacement
- an enterprise dashboard tool
- a developer framework
- a spreadsheet editor

This IS:
- a guided spreadsheet-to-WordPress publishing workflow
- a no-code assistant for turning CSV/XLSX files into searchable WordPress pages
- a WordPress-native way to create reusable listings, catalogs, directories, schedules, pricing tables, and simple data tables from spreadsheet data

The user must never feel like they are configuring a database.

The main emotional goal:
“Your spreadsheet became a reusable searchable WordPress Display.”

============================================================
0. MVP RELEASE STATUS — 0.1 PRIVATE BETA
============================================================

As of version 0.1.43, Publishly is considered ready for a first MVP/private beta release and a WordPress.org submission candidate.

The first publication target can be a WordPress.org review submission, but the product should still be framed as an MVP/private beta quality release until real tester feedback is collected.

MVP promise:
Upload CSV → create a reusable Published Display → insert it in Gutenberg → view a searchable frontend Display.

Current MVP scope:
- CSV upload and parsing.
- Smart column detection.
- Guided Display creation.
- Published Display stored as the core object.
- Display types:
  - Searchable Listings
  - Product Catalog
  - Team Directory
  - Event Schedule
  - Pricing Table
  - Simple Data Table
- Field roles.
- Visible fields.
- Default filter.
- Visitor filters.
- Sorting.
- Search.
- Pagination.
- Live preview in the admin flow/editor.
- Published Displays list and editor.
- Update Data flow.
- Gutenberg block: `publishly/display`.
- Gutenberg block: `publishly/display-grid` for arranging multiple Published Displays.
- Shortcode fallback: `[publishly id="123"]`.
- CSV-only user-facing upload support.
- Clear XLSX notice instead of partial Excel support.

Intentional MVP behavior:
- The user chooses the Display type at the start of creation, before upload.
- After upload/detection, the flow continues to setup/preview instead of asking for the Display type again.
- The Display type can still be changed in setup/editor.
- Multiple Displays should be arranged with Publishly Display Grid, Columns, or Group blocks.
- Gutenberg Table is not a layout container for Publishly Displays.
- If a Display is placed inside a table or narrow/semantic container, Publishly contains the rendering and shows an admin-only warning where appropriate.

MVP release-hardening fixtures:
- `samples/suspicious-duplicate-headers.csv`
- `samples/suspicious-js-payloads.csv`
- `samples/suspicious-mixed-types.csv`
- `samples/suspicious-sparse-columns.csv`

These files are QA fixtures for duplicate headers, sparse rows, quoted commas, mixed types, missing values, JavaScript/XSS payloads, formula-looking text, non-URL image values, and long text wrapping.

WordPress.org submission notes:
- Main plugin headers include Requires at least, Tested up to, Requires PHP, License, and License URI.
- `readme.txt` Stable tag must match `PUBLISHLY_VERSION`.
- The WordPress.org `Contributors` value must be a real WordPress.org username before submission.
- QA sample CSVs stay in the repository but are excluded from the WordPress.org submission zip.
- CSV values pass centralized validation before storage and type-aware validation before rendering.
- Submit only a clean `publishly/` plugin directory as a zip, without repository-only QA fixtures.

============================================================
0.1 POST-MVP ROADMAP
============================================================

Do not add the following items before the first MVP/private beta publication unless they fix a release-blocking bug.

0.2 — Beta Stabilization
- Polish empty/error states found during tester usage.
- Improve first-run guidance and success-state actions.
- Add clearer admin guidance for Display Grid vs Gutenberg Table.
- Add package/release checklist and screenshots.
- Tighten no-JS frontend fallback where practical.
- Improve frontend accessibility copy and keyboard behavior.
- Add more automated smoke checks for sample CSV parsing and generated configs.

0.3 — Spreadsheet Update Confidence
- Improve Update Data review when columns are missing, renamed, or newly added.
- Add simple remap UI for missing fields used by saved Displays.
- Improve column matching by similar labels.
- Show safer warnings before updating data that may affect published Displays.

0.4 — Field Management
- Rename detected columns in the Publishly UI.
- Hide/remove columns from a Display setup without editing source data.
- Drag-and-drop field order for visible fields.
- Better visible-field ordering controls in the editor.
- Data Preview improvements for inspection only.

0.5 — Import Formats
- Add real XLSX import support with a bundled production-safe parser.
- Keep CSV as the reliable baseline.
- Add stronger file validation and better import error copy.

0.6 — Source Integrations
- Google Sheets connection.
- Refresh/re-sync flows.
- Safer conflict handling for changed sheet columns.

0.7 — Visual Polish
- More Display Grid layout controls if user testing proves they are needed.
- More refined type-specific frontend designs.
- Theme compatibility improvements.
- Screenshot-ready demo pages.

Later / Not MVP
- Relations between spreadsheets.
- Multi-file joins.
- AI inference.
- Automations.
- Dashboards.
- Kanban.
- Full spreadsheet editing.
- Row-level permissions.
- Complex import mapping.
- Advanced query builder.
- Elementor/page-builder widgets.
- Upgrade banners or monetization UI.

============================================================
1. FINAL PRODUCT MODEL
============================================================

The plugin must NOT simply render uploaded spreadsheet data directly.

The core product object is:

Published Display

A Published Display is a saved configuration that defines:
- which spreadsheet/source is used
- which rows are shown
- which fields are visible
- how fields are mapped to visual roles
- default filtering
- visitor filters
- sorting
- layout
- search
- pagination
- visual style
- Gutenberg block usage

Conceptual model:

Source Data
= uploaded spreadsheet rows and columns

Published Display
= saved presentation rules for that source data

Gutenberg Block
= where the Published Display appears on the WordPress site

Important:
A Gutenberg block should reference a Published Display by ID.
It should NOT duplicate the full display configuration inside the post content.

Example:

Source:
apartments.csv

Published Displays:
- Available Apartments
- Sold Apartments
- Luxury Apartments
- Apartment Price List

Each Published Display can use the same source but have different:
- filters
- sorting
- visible fields
- layout
- style
- frontend behavior

Do not use the word “View” in the user-facing UI.
Use “Display” or “Published Display”.

Use friendly user-facing vocabulary:
- Spreadsheet
- Source
- Display
- Published Display
- Create Display
- Edit Display
- Update Data
- Searchable Page
- Listing
- Catalog
- Directory
- Schedule
- Pricing Table
- Fields
- Preview
- Publish
- Insert in Gutenberg

Avoid exposing these terms in the UI:
- CPT
- custom post type
- taxonomy
- schema
- relation
- SQL
- query builder
- meta fields
- entity
- model
- database table
- ORM
- view

============================================================
2. BRANDING
============================================================

Final product name:
Publishly

Plugin slug:
publishly

PHP namespace / prefix:
Publishly

Main plugin file:
publishly.php

Text domain:
publishly

Important:
The attached design mockups may contain old placeholder names like:
- SheetPublish
- SheetAssistant

Replace all of them with:
Publishly

Do not use SheetPublish or SheetAssistant anywhere in the implementation.

============================================================
3. PRODUCT DIRECTION
============================================================

The UX should match the latest attached design direction:

- modern SaaS UI
- calm light background
- soft blue/purple primary accent
- rounded cards
- spacious layout
- friendly onboarding
- visual outcome selection
- clean spreadsheet preview
- live website preview
- Gutenberg-native publishing

The product should feel closer to:
- Canva onboarding
- Notion templates
- modern no-code publishing tools
- WordPress media upload flow

And less like:
- phpMyAdmin
- Retool
- Airtable
- BI dashboards
- classic WordPress settings pages

The interface should be:
- guided
- calm
- lightweight
- visual
- beginner-friendly
- WordPress-native

Avoid:
- enterprise sidebar complexity
- analytics dashboards in MVP
- upgrade banners in MVP
- documentation panels inside the main workflow
- “workspace overload”
- data platform language

============================================================
4. TECH STACK
============================================================

Backend:
- WordPress plugin architecture
- PHP 8.1+
- WordPress REST API
- WordPress nonces
- capability checks
- sanitization and escaping everywhere
- custom database tables where appropriate
- no dependency on page builders

Admin frontend:
- React
- WordPress scripts/build tooling
- @wordpress/element
- @wordpress/components
- @wordpress/api-fetch
- @wordpress/i18n
- modern CSS modules or SCSS
- no external CDN dependencies in production

Gutenberg:
- register a block called “Publishly Display”
- block name: publishly/display
- dynamic frontend rendering via PHP render callback
- editor preview should fetch saved Published Displays through REST API

Spreadsheet parsing:
- CSV must work fully in MVP
- XLSX should be supported if feasible using a bundled PHP library
- if XLSX support is not fully implemented initially, keep architecture ready and show a clear user-facing notice

Security:
- check user capabilities before every admin and REST action
- validate uploaded file type
- protect upload and REST actions with nonces
- sanitize all stored values
- escape all frontend output
- do not allow direct file access

============================================================
5. PLUGIN STRUCTURE
============================================================

Create this plugin structure:

publishly/
  publishly.php
  readme.txt
  package.json
  vite.config.js or webpack config
  src/
    AdminApp.jsx
    block/
      index.js
      edit.jsx
      block.json
    components/
      WelcomeScreen.jsx
      UploadDetectionScreen.jsx
      OutcomeScreen.jsx
      DisplaySetupScreen.jsx
      CustomizationScreen.jsx
      PreviewScreen.jsx
      PublishedDisplays.jsx
      DisplayEditor.jsx
      UpdateDataFlow.jsx
      DataPreviewModal.jsx
    styles/
      admin.css
      block.css
  includes/
    Plugin.php
    Activator.php
    Database.php
    REST/
      SourcesController.php
      DisplaysController.php
      UploadController.php
    Services/
      CsvParser.php
      XlsxParser.php
      ColumnDetector.php
      DisplayGenerator.php
      FrontendRenderer.php
      DataUpdater.php
    Models/
      Source.php
      Display.php
  assets/
    icon.svg
  samples/
    apartments.csv
    products.csv
    team.csv
    events.csv

Use OOP PHP with a clear bootstrap class.

============================================================
6. DATABASE DESIGN
============================================================

Create custom tables on plugin activation using dbDelta.

Use $wpdb->prefix.
Do not hardcode wp_.

Table 1:
{$wpdb->prefix}publishly_sources

Fields:
- id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- name VARCHAR(255)
- original_filename VARCHAR(255)
- file_type VARCHAR(20)
- row_count INT UNSIGNED
- columns_json LONGTEXT
- created_by BIGINT UNSIGNED
- created_at DATETIME
- updated_at DATETIME

Table 2:
{$wpdb->prefix}publishly_rows

Fields:
- id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- source_id BIGINT UNSIGNED
- row_index INT UNSIGNED
- row_json LONGTEXT
- created_at DATETIME

Indexes:
- source_id
- row_index

Table 3:
{$wpdb->prefix}publishly_displays

Fields:
- id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- source_id BIGINT UNSIGNED
- name VARCHAR(255)
- display_type VARCHAR(80)
- config_json LONGTEXT
- status VARCHAR(20)
- created_by BIGINT UNSIGNED
- created_at DATETIME
- updated_at DATETIME

Display types:
- searchable_listings
- product_catalog
- team_directory
- event_schedule
- pricing_table
- simple_data_table

Do not use WordPress posts as primary storage for MVP.
The Gutenberg block should reference display IDs.

============================================================
7. DISPLAY CONFIG MODEL
============================================================

Each Published Display stores its configuration in config_json.

Example:

{
  "displayType": "searchable_listings",
  "title": "Available Apartments",
  "sourceId": 1,
  "fieldRoles": {
    "title": "apartment_name",
    "subtitle": "building",
    "image": "photo",
    "price": "price",
    "badge": "status"
  },
  "visibleFields": [
    "apartment_name",
    "building",
    "rooms",
    "price",
    "status",
    "location"
  ],
  "defaultFilters": [
    {
      "field": "status",
      "operator": "equals",
      "value": "Available"
    }
  ],
  "visitorFilters": [
    "building",
    "rooms",
    "status"
  ],
  "sort": {
    "field": "price",
    "direction": "asc"
  },
  "search": {
    "enabled": true
  },
  "filters": {
    "enabled": true
  },
  "pagination": {
    "enabled": true,
    "perPage": 12
  },
  "style": {
    "accentColor": "#4648d4",
    "density": "comfortable",
    "imageShape": "rounded"
  }
}

============================================================
8. ADMIN MENU
============================================================

Add top-level menu:

Publishly

Submenu:
- Create Display
- Published Displays
- Settings

Avoid:
- My Tables
- Database
- Schema
- Data Models
- Analytics
- Workspace
- Formatting
- Data Source

The MVP admin should focus on the guided publishing flow.

============================================================
9. MAIN USER FLOW
============================================================

Implement the guided flow:

1. Welcome / Create Display
2. Upload & Smart Detection
3. What would you like to display?
4. Display Setup / Field Roles
5. Simple Customization
6. Live Website Preview
7. Save Published Display
8. Insert in Gutenberg

After the wizard, the user should be taken to a success state:

“Your Display is ready.”

Actions:
- Insert in Gutenberg
- Preview
- Edit Display
- Update Data

============================================================
10. SCREEN 1 — WELCOME / CREATE DISPLAY
============================================================

Headline:
“Turn spreadsheets into searchable WordPress pages.”

Supporting text:
“Upload CSV or Excel files and create reusable displays for listings, catalogs, directories, schedules, and more.”

Primary CTA:
“Create Display”

Secondary CTA:
“Upload Spreadsheet”

Optional secondary:
“Connect Google Sheets”
Add a “Coming soon” badge if not implemented.

Below:
Section title:
“Popular Starting Points”

Cards:
- Listings
- Catalog
- Directory
- Schedule
- Pricing
- Data Table

Important:
These cards should not feel industry-specific.
They are examples and starting points, not limits.

Each card should include:
- simple visual mini-preview
- short description
- helper text

Examples:

Listings:
“Create searchable cards from rows of data. Great for apartments, services, resources, or locations.”

Catalog:
“Show products, assets, or offers in a structured searchable layout.”

Directory:
“Create a people, team, vendor, or organization directory.”

Schedule:
“Turn date-based rows into an event or program schedule.”

Pricing:
“Create clean pricing tables from spreadsheet rows.”

Data Table:
“Publish a simple searchable table.”

============================================================
11. SCREEN 2 — UPLOAD & SMART DETECTION
============================================================

After upload, show:

- uploaded filename
- file type
- row count
- detected columns
- first rows preview

Example:
apartments.csv
248 rows detected

Show detected column badges:
- Text
- Number
- Price
- Status
- Date
- Image
- URL
- Email
- Phone

The UI should communicate:
“Publishly understood your spreadsheet automatically.”

Simple actions:
- Rename column
- Remove column
- Continue

Do not show:
- schema editor
- database setup
- query settings
- technical mapping UI

Primary CTA:
“Looks good — continue”

============================================================
12. COLUMN DETECTION LOGIC
============================================================

Implement ColumnDetector service.

Rules:
- If values look like currency, detect price
- If values parse as dates, detect date
- If values are mostly numeric, detect number
- If values are repeated labels like Available/Reserved/Sold, detect status
- If values are image URLs ending in jpg/png/webp/gif, detect image
- If values are URLs, detect URL
- If values contain @ and look like emails, detect email
- If values look like phone numbers, detect phone
- Otherwise detect text

Use sampling from the first 50 rows.

Return detection confidence internally.
Do not expose confidence in the UI unless useful for debugging.

============================================================
13. SCREEN 3 — WHAT WOULD YOU LIKE TO DISPLAY?
============================================================

This is not “Choose Layout”.

Headline:
“What would you like to display?”

Subtext:
“Choose the kind of page you want to create from this spreadsheet.”

Cards:
- Searchable Listings
- Product Catalog
- Team Directory
- Event Schedule
- Pricing Table
- Simple Data Table

Each card includes:
- realistic mini-preview
- one-line explanation
- hover state
- selected state

When selected, Publishly should automatically choose:
- layout
- field roles
- default filters
- default sorting
- visible fields
- search behavior
- pagination behavior

Examples:

Searchable Listings:
“Create searchable cards from rows of data. Great for properties, services, resources, or locations.”

Product Catalog:
“Display products, assets, or offers with images, prices, and filters.”

Team Directory:
“Create a clean people or organization directory.”

Event Schedule:
“Turn date-based rows into a readable schedule.”

Pricing Table:
“Create pricing cards from spreadsheet rows.”

Simple Data Table:
“Publish a searchable table with sorting and pagination.”

Primary CTA:
“Continue”

============================================================
14. SCREEN 4 — DISPLAY SETUP / FIELD ROLES
============================================================

This is a critical screen.

Purpose:
Show that a Published Display is more than raw spreadsheet output.

Headline:
“Set up your Display”

Subtext:
“Choose how spreadsheet fields should appear on the page.”

Example for Searchable Listings:

Display name:
Available Apartments

Field roles:
- Title field: Apartment Name
- Image field: Photo
- Price field: Price
- Badge field: Status
- Subtitle field: Building

Visible fields:
- Apartment Name
- Building
- Rooms
- Price
- Status
- Location

Allow:
- checkbox show/hide visible fields
- drag-and-drop order for visible fields
- dropdowns for field roles

Default filter section:
“Show rows where”

Controls:
- Field dropdown: Status
- Condition dropdown: is
- Value dropdown: Available

Visitor filters:
Checkboxes:
- Building
- Rooms
- Status

Sort:
- Sort by: Price
- Direction: Low to high

Important:
Keep this simple.
No SQL-like UI.
No nested conditions.
No advanced rule builder.
No database language.

Primary CTA:
“Preview Display”

============================================================
15. SCREEN 5 — SIMPLE CUSTOMIZATION
============================================================

Purpose:
Light visual customization only.

Controls:
- Accent color
- Card density: Comfortable / Compact
- Image shape: Rounded / Square
- Show search toggle
- Show filters toggle
- Show pagination toggle
- Items per page dropdown

Right side:
Live preview.

Important:
Do not build a page builder.
Do not build enterprise settings.
Do not add layout complexity beyond MVP.

Primary CTA:
“Preview”

============================================================
16. SCREEN 6 — LIVE WEBSITE PREVIEW
============================================================

Show realistic browser-frame preview.

Example:
Available Apartments

Preview includes:
- title
- search box
- filter controls
- responsive card grid
- image
- price
- status badge
- visible fields
- pagination

The user should feel:
“My spreadsheet is now a real website.”

Primary CTA:
“Save Display”

Secondary CTA:
“Back to edit”

After saving, show success state:

“Your Display is ready.”

Actions:
- Insert in Gutenberg
- Preview
- Edit Display
- Update Data

This success state is important.

============================================================
17. SCREEN 7 — PUBLISHED DISPLAYS
============================================================

Create a page:

Published Displays

Subtitle:
“Manage the spreadsheet displays you can insert into WordPress pages.”

Show saved displays as cards or a clean list.

Each Display card includes:
- Display name
- Display type
- Source spreadsheet
- Row count
- Last updated
- Status
- small visual preview

Example:

Available Apartments
Type: Searchable Listings
Source: apartments.csv
248 rows
Last updated: Today

Product Catalog
Type: Product Catalog
Source: products.xlsx
80 rows
Last updated: Yesterday

Actions per card:
- Edit Display
- Preview
- Insert in Gutenberg
- Update Data
- Delete

Primary CTA:
“Create Display”

Empty state:
“No displays yet. Upload a spreadsheet to create your first searchable page.”

============================================================
18. SCREEN 8 — DISPLAY EDITOR
============================================================

Purpose:
Edit a saved Display after the wizard.

This is not a spreadsheet editor.
This is a Display Editor.

Title:
“Edit Display”

Example:
Available Apartments

Layout:
- left settings panel
- right live preview

Sections:
1. Preview
2. Fields
3. Search & Filters
4. Style
5. Data Source
6. Publish

FIELDS section:
- Display name
- Title field
- Image field
- Price field
- Badge field
- Visible fields
- Field order

SEARCH & FILTERS section:
- Show search
- Visitor filters
- Default filter
- Sort by
- Pagination

STYLE section:
- Accent color
- Density
- Image shape

DATA SOURCE section:
Show:
- Source file: apartments.csv
- Rows: 248
- Last updated
- Matched columns

Actions:
- Replace file
- Re-import data
- Download original
- View data preview

Important:
Do not make row editing the main feature.
The source spreadsheet remains the source of truth.
Publishly edits how the data appears.

PUBLISH section:
- Gutenberg block instructions
- “Insert in Gutenberg” button
- copy fallback shortcode as secondary option only

============================================================
19. SCREEN 9 — UPDATE DATA FLOW
============================================================

Purpose:
Let users update the source data without rebuilding the Display.

User clicks:
Update Data

Screen:
“Replace source spreadsheet”

Upload new CSV/XLSX.

After upload, show column matching result:

Example:
“We matched 6 of 7 columns.”
“1 new column detected: Discount”
“1 missing column: Old Price”

Show simple status indicators:
- Matched
- New
- Missing

Actions:
- Continue update
- Review columns

Important:
This must feel safe and guided.
Do not make it look like technical database migration.

Core value:
User can replace the spreadsheet while keeping the same Published Display.

============================================================
20. SCREEN 10 — DATA PREVIEW MODAL
============================================================

Purpose:
Let users inspect rows without turning Publishly into a spreadsheet editor.

Modal title:
“Data Preview”

Show:
- first rows
- columns
- detected field types
- row count
- horizontal scroll
- search preview

Allowed:
- inspect rows
- search preview
- close modal

Not allowed in MVP:
- inline row editing
- add rows
- delete rows
- spreadsheet formulas
- bulk editing

Helper text:
“To change data, update your spreadsheet and replace the source file.”

============================================================
21. GUTENBERG INTEGRATION
============================================================

Create a Gutenberg block.

Block title:
Publishly Display

Block name:
publishly/display

Block description:
“Insert a searchable display created from your spreadsheet.”

Block behavior:
- dropdown/select to choose a saved Published Display
- instant editor preview
- link/button: “Edit this Display in Publishly”
- placeholder state when no display exists

Dropdown examples:
- Available Apartments
- Product Catalog
- Team Directory

Block attributes:
- displayId: number
- showTitle: boolean optional
- overrideTitle: string optional

Important:
No shortcode-first UX.
No need to configure filters or fields inside Gutenberg.
Gutenberg only selects and renders a saved Display.

Concept:
Gutenberg Block → Published Display

Frontend:
- dynamic render via PHP render callback
- display ID stored in block attributes
- frontend rendered by FrontendRenderer service

============================================================
22. FRONTEND RENDERING
============================================================

Frontend display must support:
- search
- filters
- sorting
- pagination
- responsive cards/table
- accessible markup

For MVP, frontend interactivity can be implemented with lightweight vanilla JS.
Avoid loading large bundles on every page.

Only enqueue frontend assets when a Publishly display is present.

Display types:

Searchable Listings:
- responsive cards
- image if available
- title
- subtitle
- price
- status badge
- selected fields

Product Catalog:
- product cards
- image
- title
- price
- category/status

Team Directory:
- profile cards
- photo
- name
- role
- email/phone if available

Event Schedule:
- date grouped list or card list
- upcoming events first

Pricing Table:
- pricing cards
- highlighted price

Simple Data Table:
- table
- search
- sort
- pagination

============================================================
23. REST API ENDPOINTS
============================================================

Create REST namespace:
publishly/v1

Endpoints:

POST /upload
- accepts CSV/XLSX upload
- validates file
- parses spreadsheet
- stores source and rows
- returns source, columns, sample rows

GET /sources
- list sources

GET /sources/{id}
- get source metadata, columns, sample rows

POST /displays
- create Published Display

GET /displays
- list Published Displays

GET /displays/{id}
- get display config and preview data

PUT /displays/{id}
- update display config

DELETE /displays/{id}
- delete display

POST /displays/{id}/update-data
- replace source spreadsheet while preserving display config
- returns column matching summary

GET /displays/{id}/render-data
- returns rows prepared for frontend/editor preview

All endpoints must:
- verify nonce
- check capabilities
- sanitize inputs
- return WP_Error on failure

============================================================
24. DATA UPDATE / COLUMN MATCHING
============================================================

Implement DataUpdater service.

When replacing a source file:
- parse new spreadsheet
- compare new columns with existing source columns
- match by normalized key
- match by similar label if possible
- report matched, new, and missing columns
- preserve Display config where possible

Do not silently break Displays.

If a field used in a Display is missing in the new spreadsheet:
- show warning in UI
- allow user to remap field
- do not delete Display automatically

============================================================
25. ADMIN DESIGN SYSTEM
============================================================

Use the latest attached design direction.

Visual tokens:
- primary: #4648d4 or similar indigo/purple
- background: #f8f9ff
- surface: #ffffff
- soft blue containers
- dark navy text
- rounded-xl cards
- subtle shadows
- spacious layout

Font:
Use system font stack:
Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif

Do not load Google Fonts from CDN in production.

UI feel:
- soft
- calm
- modern
- spacious
- friendly
- not classic WordPress settings-page

Important:
Reduce heavy sidebar complexity.
Use simple navigation:
- Create Display
- Published Displays
- Settings

Avoid:
- Analytics
- Documentation inside main workflow
- Upgrade banners in MVP
- Pro feature distractions
- database icons in user-facing navigation

============================================================
26. SETTINGS PAGE
============================================================

Keep MVP settings minimal.

Settings:
- default accent color
- default rows per page
- delete plugin data on uninstall checkbox

Do not create a large settings system.

============================================================
27. SAMPLE DATA
============================================================

Add sample CSV files in /samples.

apartments.csv:
- Apartment Name
- Building
- Rooms
- Price
- Status
- Photo
- Location

products.csv:
- Product Name
- Category
- Price
- Status
- Image
- Description

team.csv:
- Name
- Role
- Email
- Phone
- Photo

events.csv:
- Event Name
- Date
- Location
- Status
- Description

Use these to test all display types.

============================================================
28. ACCEPTANCE CRITERIA
============================================================

A user can:

1. Install and activate Publishly.
2. Open Publishly → Create Display.
3. Upload a CSV file.
4. See row count, detected columns, and first rows preview.
5. Rename or remove a column.
6. Choose “Searchable Listings” or another outcome.
7. Configure field roles.
8. Choose visible fields.
9. Add a simple default filter.
10. Enable visitor filters.
11. Choose sorting.
12. Customize accent color, search, filters, pagination.
13. See a live website-style preview.
14. Save a Published Display.
15. See success state: “Your Display is ready.”
16. Open Published Displays.
17. Edit a saved Display.
18. Update data by replacing the source spreadsheet.
19. Open Gutenberg.
20. Insert the “Publishly Display” block.
21. Select the saved Published Display.
22. See the preview inside the editor.
23. Publish the WordPress page.
24. View the searchable frontend Display.

============================================================
29. MVP LIMITS
============================================================

Keep MVP focused.

Do not implement:
- relations between spreadsheets
- multi-file joins
- AI inference
- automations
- dashboards
- kanban
- full spreadsheet editing
- row-level permissions
- complex import mapping
- advanced query builder
- page builder integrations

Architecture should allow future expansion, but do not build these now.

============================================================
30. CODE QUALITY
============================================================

Write clean, maintainable code.

Requirements:
- no fatal errors on activation
- ABSPATH guard in PHP files
- proper uninstall handling
- no hardcoded wp_ table prefix
- all output escaped
- all input sanitized
- REST errors handled clearly
- admin app handles loading/error/empty states
- frontend works with no JavaScript as much as possible
- JavaScript enhances search/filter/sort where needed
- assets are only loaded when needed
- no external CDN dependencies in production
- no page builder dependency

============================================================
31. FINAL DELIVERABLE
============================================================

Create the full plugin implementation.

The result should be a working WordPress plugin that can be zipped, installed, activated, and used.

Prioritize:
1. working end-to-end flow
2. Published Display as the core object
3. clean guided UX
4. Gutenberg integration
5. safe backend implementation
6. simple frontend rendering
7. update data without rebuilding displays

Do not overbuild.

MVP is Gutenberg-first, but rendering must be editor-agnostic.

A Published Display should be rendered through a shared FrontendRenderer service.

Gutenberg block is the primary integration.

Shortcode fallback is required.

Elementor widget is not required in MVP, but the architecture must allow adding it later without changing the Published Display model.

The final product should feel like:
“Publishly turned my spreadsheet into a reusable searchable WordPress Display.”
