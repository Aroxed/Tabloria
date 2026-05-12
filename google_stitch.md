Design a modern WordPress admin plugin called “Publishly”.

Publishly is a friendly spreadsheet publishing assistant for WordPress.

Core promise:
Upload a spreadsheet → create a reusable Published Display → insert it into Gutenberg.

IMPORTANT PRODUCT MODEL:

The plugin does NOT simply render uploaded spreadsheet data directly.

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

In other words:

Spreadsheet = source data
Published Display = saved presentation of that data
Gutenberg Block = where the display appears on the site

The user should understand this through the interface, but without technical language.

Use the word “Display” in the UI.
Avoid the word “View”.

Good UI terms:
- Display
- Published Display
- Create Display
- Edit Display
- Update Data
- Insert in Gutenberg
- Preview
- Searchable Page

Avoid:
- View
- Schema
- Database
- Query Builder
- CPT
- Taxonomy
- Meta Fields
- SQL
- Relations
- Entity
- Model

The product should feel:
- guided
- calm
- visual
- lightweight
- modern SaaS
- beginner-friendly
- WordPress-native

It should NOT feel like:
- Airtable
- Retool
- phpMyAdmin
- BI software
- enterprise data platform
- classic WordPress settings page

Visual direction:
- modern SaaS UI
- soft blue/purple accent
- clean white cards
- rounded corners
- subtle shadows
- spacious layout
- Plus Jakarta Sans / Inter style typography
- calm light background
- friendly no-code publishing feel

Replace all placeholder names from previous mockups:
- Do NOT use SheetPublish
- Do NOT use SheetAssistant
- Use Publishly everywhere

Also reduce heavy workspace/sidebar complexity.
Avoid large enterprise-style navigation like:
- Analytics
- Documentation
- Upgrade to Pro
- Data Source
- Formatting

Use simple product navigation:
- Create Display
- Published Displays
- Settings

==================================================
SCREEN 1 — WELCOME / CREATE DISPLAY
==================================================

Purpose:
Start the guided flow.

Large headline:
“Turn spreadsheets into searchable WordPress pages.”

Supporting text:
“Upload CSV or Excel files and create reusable displays for listings, catalogs, directories, schedules, and more.”

Primary CTA:
[ Create Display ]

Secondary CTA:
[ Upload Spreadsheet ]

Optional secondary:
[ Connect Google Sheets ] with “Coming soon” badge

Below:
Section title:
“Popular Starting Points”

Show flexible starting-point cards:
- Listings
- Catalog
- Directory
- Schedule
- Pricing
- Data Table

Important:
These cards should NOT feel industry-specific.
They are examples, not limits.

Each card should include:
- simple visual preview
- short description
- helper text like:
  “Great for apartments, services, products, teams, events, and more.”

The screen should feel welcoming and extremely simple.

==================================================
SCREEN 2 — UPLOAD & SMART DETECTION
==================================================

Purpose:
Upload source data and show that Publishly understood it.

Show:
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
- Price
- Status
- Date
- Image
- URL
- Email
- Phone

The interface should communicate:
“Publishly understood your spreadsheet automatically.”

Simple actions only:
- Rename column
- Remove column
- Continue

Do NOT show:
- technical mapping UI
- schema editor
- database setup
- query settings

Primary CTA:
[ Looks good — continue ]

==================================================
SCREEN 3 — WHAT WOULD YOU LIKE TO DISPLAY?
==================================================

Purpose:
Create the Display from the uploaded source.

Headline:
“What would you like to display?”

Subtext:
“Choose the kind of page you want to create from this spreadsheet.”

Large selectable cards:
- Searchable Listings
- Product Catalog
- Team Directory
- Event Schedule
- Pricing Table
- Simple Data Table

Important:
This is NOT “Choose Layout”.
This is outcome selection.

Each card should include:
- realistic mini-preview
- one-line explanation
- selected state
- hover state

When selected, the UI should imply:
“Publishly will automatically choose a good layout, filters, sorting, and field roles.”

Example:
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
[ Continue ]

==================================================
SCREEN 4 — DISPLAY SETUP / FIELD ROLES
==================================================

Purpose:
This is the missing conceptual screen.
It should show that a Published Display is more than raw spreadsheet output.

Headline:
“Set up your Display”

Subtext:
“Choose how spreadsheet fields should appear on the page.”

Use friendly field role controls.

For Searchable Listings example:

Display name:
[ Available Apartments ]

Field roles:
- Title field: Apartment Name
- Image field: Photo
- Price field: Price
- Badge field: Status
- Subtitle field: Building

Visible fields:
Checkbox list:
- Apartment Name
- Building
- Rooms
- Price
- Status
- Location

Drag-and-drop order for visible fields.

Default filter section:
“Show rows where”
Field dropdown: Status
Condition dropdown: is
Value dropdown: Available

Visitor filters:
Checkboxes:
- Building
- Rooms
- Status

Sort:
Sort by: Price
Direction: Low to high

Important:
This screen should still feel simple.
No technical query builder.
No nested conditions.
No SQL-like UI.

This screen explains the real product value:
The user is creating a reusable Display, not just importing data.

Primary CTA:
[ Preview Display ]

==================================================
SCREEN 5 — SIMPLE CUSTOMIZATION
==================================================

Purpose:
Light visual customization only.

Headline:
“Customize appearance”

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
Keep this lightweight.
No enterprise settings.
No complex layout builder.
No page builder-like UI.

Primary CTA:
[ Preview ]

==================================================
SCREEN 6 — LIVE WEBSITE PREVIEW
==================================================

Purpose:
Show the Display as a real website page.

Use browser-frame style preview.

Example:
Available Apartments

Show:
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
[ Save Display ]

Secondary CTA:
[ Back to edit ]

After saving, show success state:

“Your Display is ready.”

Actions:
[ Insert in Gutenberg ]
[ Preview ]
[ Edit Display ]
[ Update Data ]

This success state is very important.

==================================================
SCREEN 7 — PUBLISHED DISPLAYS
==================================================

Purpose:
Manage reusable Displays after the wizard.

This screen is currently missing and must be designed.

Title:
“Published Displays”

Subtitle:
“Manage the spreadsheet displays you can insert into WordPress pages.”

Show a list or card grid of saved Displays.

Each Display card includes:
- Display name
- Display type
- Source spreadsheet
- Row count
- Last updated
- Status
- Small visual preview

Example cards:
1. Available Apartments
Type: Searchable Listings
Source: apartments.csv
248 rows
Last updated: Today

2. Product Catalog
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
[ Create Display ]

Empty state:
“No displays yet. Upload a spreadsheet to create your first searchable page.”

This screen should make it clear:
A Display is reusable and can be inserted into Gutenberg.

==================================================
SCREEN 8 — DISPLAY EDITOR
==================================================

Purpose:
Edit a saved Display after the wizard.

This is NOT a spreadsheet editor.
This is a Display Editor.

Title:
“Edit Display”

Example:
Available Apartments

Main layout:
- left settings panel
- right live preview

Tabs or sections:
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
- Copy fallback shortcode, but shortcode must be secondary

==================================================
SCREEN 9 — UPDATE DATA FLOW
==================================================

Purpose:
Show how user updates source data without rebuilding the Display.

User clicks:
Update Data

Show:
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
[ Continue update ]
[ Review columns ]

Important:
This is a key product value:
User can update the spreadsheet while keeping the same Published Display.

Do not make this look like technical database migration.
Make it feel safe and guided.

==================================================
SCREEN 10 — GUTENBERG INTEGRATION
==================================================

Purpose:
Show how a saved Published Display is inserted into WordPress.

Show Gutenberg editor.

Custom block:
“Publishly Display”

Block state:
- dropdown to select a Published Display
- instant preview inside editor
- edit link:
  “Edit this Display in Publishly”

Dropdown examples:
- Available Apartments
- Product Catalog
- Team Directory

Block should store only display selection, not the whole configuration.

Concept:
Gutenberg Block → Published Display

Important:
No shortcode-first UX.
No need to configure filters or fields inside Gutenberg.
Gutenberg only selects and renders a saved Display.

This should feel native to WordPress and very simple.

==================================================
SCREEN 11 — DATA PREVIEW MODAL
==================================================

Purpose:
Let users inspect rows without turning Publishly into a spreadsheet editor.

Modal title:
“Data Preview”

Show:
- first rows
- columns
- detected field types
- row count

Allowed actions:
- Search preview
- Scroll horizontally
- Close

Not allowed in MVP:
- inline edit rows
- add rows
- delete rows
- spreadsheet formulas
- bulk editing

Show helper text:
“To change data, update your spreadsheet and replace the source file.”

==================================================
OVERALL UX RULES
==================================================

The user journey should be:

Create Display
↓
Upload Spreadsheet
↓
Smart Detection
↓
Choose Outcome
↓
Set Display Rules
↓
Customize
↓
Preview
↓
Save Published Display
↓
Insert in Gutenberg
↓
Later: Edit Display / Update Data

The product must clearly separate:

Source data
from
Published Display
from
Gutenberg Block

But in friendly language.

Never imply that the uploaded spreadsheet is simply rendered directly.
The value is the saved reusable Display.

Avoid making Publishly feel like:
- a spreadsheet editor
- a database admin
- a generic workspace
- a page builder
- a BI dashboard

Make it feel like:
“A simple publishing assistant that turns spreadsheets into reusable searchable WordPress displays.”

Core emotional message:
“Your spreadsheet became a reusable WordPress Display.”