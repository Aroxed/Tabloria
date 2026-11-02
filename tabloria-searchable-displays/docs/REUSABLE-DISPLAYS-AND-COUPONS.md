# Reusable Displays, page filters, and coupons

## Reuse one Display on category pages

Create and publish one Display from the full CSV. On every category page, insert the same **Tabloria Display** block and use **Filter this placement by** in the block sidebar:

1. Select the category column.
2. Enter the exact category value for that page.
3. Repeat with the same Display ID and another value on another page.

Matching is case-insensitive. The saved Display and source rows are not duplicated. Search, visitor filters, sorting, and pagination operate on the rows that remain after the page filter.

The shortcode equivalent is:

```text
[tabloria id="123" filter_field="category" filter_value="Travel"]
```

An unknown column or an empty filter value is ignored safely.

## Customize the empty result message

Open the Display editor, choose **Search & Filters**, and set **No results message**. This message appears when search or visitor filters leave no matching rows.

## Build a Coupon Code Display

The included `samples/coupons.csv` demonstrates the recommended shape. Create a **Coupon Code Display** and map:

- **Offer title** to the offer or deal name.
- **Store / category** to the store, merchant, brand, or category.
- **Discount badge** to the discount or saving.
- **Coupon code** to the optional code column.
- **Affiliate link** to the destination URL.

Rows may mix both behaviors:

- With a coupon code, the reveal button exposes the code and opens the affiliate URL in a new tab. The visitor can then copy the code.
- Without a coupon code, the direct deal button opens the affiliate URL. Its text can be changed to “View deal”, “Visit website”, or another label.

Reveal, copy, and deal button text are configured under **Coupon actions**. Affiliate links are validated as HTTP/HTTPS URLs and rendered with `noopener`, `noreferrer`, and `sponsored` link relationships.
