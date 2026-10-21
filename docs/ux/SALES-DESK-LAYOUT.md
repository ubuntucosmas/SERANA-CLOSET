# Sales Desk layout

The layout starts from the cashier’s immediate decisions: find the correct item, verify the current sale, confirm money received, then hand over the goods. Screen space follows those decisions.

- The workspace uses the available screen width with consistent outer gutters. Header, navigation, context and panels share the same edges.
- Product search and the current sale sit side by side on desktop and tablet. Product cards fill the available columns; names, prices and add actions have consistent positions.
- Search stays above a scrolling catalogue. The cart scrolls independently while its total and Continue to payment button remain visible on normal-height desktop and tablet screens.
- Payment keeps the sale review on the left and the amount due, tender controls and confirmation on the right. Confirmed change appears after payment is recorded.
- Pickup groups order lookup with handover, alongside returns. Register review, cash removal and closing use aligned columns. Custom order intake uses the full workspace.
- A sun/moon switch in the header and PIN screen changes between light and dark palettes. The choice persists across reloads and is shared with Studio.
- Phones use one column, with a View sale shortcut. Short desktop screens allow page scrolling to keep controls reachable.

Sale submission, stock checks, payment handling, cash calculations, permissions and register rules continue through the existing handlers and server endpoints.

## Verification

Production Vite build and all 11 frontend test files pass. Isolated browser fixtures exercise cash payment/change, completion, register variance explanation, verified pickup and pending M-Pesa handling. Responsive checks cover 375, 768, 1024, 1440 and 1920 pixels, plus dark mode. A populated fixture uses 36 products and 12 cart lines to check independent scrolling, aligned panels and visible checkout controls.

Browser evidence is in [verification](verification/): `pos-layout-results.json`, `pos-layout-stress-results.json`, and `pos-populated-*.png`. These checks intercept application requests and use synthetic records; they do not submit live sales.

Theme verification: `pos-theme-results.json` covers switching in both directions, persistence after reload, light/dark accessibility scans and tablet/mobile layouts.
