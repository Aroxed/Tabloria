# Publishly Product And Design Audit

Date: 2026-05-12

## Product Core

Publishly should not feel like a spreadsheet renderer, database UI, or table plugin. The product object is the Published Display:

- Spreadsheet or Source: uploaded rows and columns.
- Published Display: saved publishing rules for that source.
- Gutenberg Block: placement that references a Published Display by ID.

The core promise is:

> Upload a spreadsheet -> create a reusable Published Display -> insert it into Gutenberg.

The strongest emotional message is:

> Your spreadsheet became a reusable searchable WordPress Display.

## MVP Focus

The full spec is intentionally broad, but the first implementation should be an end-to-end vertical slice:

1. CSV upload.
2. Smart column detection.
3. Searchable Listings as the first fully working outcome.
4. Field roles and visible fields.
5. Save Published Display.
6. Gutenberg block references `displayId`.
7. Frontend render with search, visitor filters, sorting, and pagination.
8. Update Data flow after the core loop is stable.

XLSX, all six outcome types, rich editor tabs, and advanced styling can follow once this loop is reliable.

## Design Notes

Keep:

- Calm light background.
- Indigo/purple primary action.
- Spacious guided wizard.
- Clear spreadsheet preview.
- Field role setup screen.
- Live website preview.
- Strong success state after save.

Fix before production:

- Remove placeholder version labels such as `V2.4.0` from the user-facing UI.
- Avoid heavy sidebar navigation such as `Overview`, `Analytics`, `Editor`, and `Integration`.
- Use simple navigation: `Create Display`, `Published Displays`, `Settings`.
- Replace user-facing `View` language with `Display`, except where the word is part of generic browser/admin context and unavoidable.
- Avoid labeling outcome selection as `Layout`; users are choosing what they want to display, not configuring layout.
- In Gutenberg, keep the block simple: select a Published Display and preview it. Do not duplicate Display configuration in block attributes.
- Remove CDN dependencies and Google Fonts from production. Use bundled assets and a system font stack.
- Avoid in-app documentation panels, upgrade banners, analytics, and workspace-style noise in MVP.

## UX Rules

- The user must never feel like they are configuring a database.
- Do not expose schema, SQL, CPT, taxonomy, relation, model, entity, query builder, or ORM language.
- Source data remains the source of truth. Publishly edits how data appears.
- Data preview is inspection only, not spreadsheet editing.
- Update Data should feel safe and guided: matched, new, missing columns.

## Implementation Guardrails

- WordPress plugin slug: `publishly`.
- Namespace or prefix: `Publishly`.
- Text domain: `publishly`.
- Gutenberg block name: `publishly/display`.
- Store Published Display configuration centrally and have Gutenberg reference it by ID.
- Do not store duplicated display configuration inside post content.
- Use custom tables for sources, rows, and displays.
- Enqueue frontend assets only when a Publishly Display is rendered.
- Shortcode is a fallback, not the primary UX.
