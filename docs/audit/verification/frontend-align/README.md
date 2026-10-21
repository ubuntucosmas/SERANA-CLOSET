# Studio wiring after the owner's layout refresh (2026-10-21)

This was run in Chrome on the local ddev site, signed in as the QA owner.

## What changed

- **The owner's layout edits** are committed as their own commit (`style: storefront and Studio layout refresh`). They build, and the frontend tests pass.
- **Studio → Staff** is now in the Studio, for the owner only (`staff.manage`). Sections with an ability show only to people who have it, both in the sidebar and in the phone "More" sheet. Stock shows only with `stock.manage`.
- **Open the till** appears in the sidebar and the More sheet for anyone with `pos.sell`.
- **The payment form** on an order shows only with `money.record`; the server already refused everyone else.
- **"Collected"** was already absent from the Studio's stage list. It shows only as the current value of an order collected at the till.

## Results (6 checks, all pass)

| Check | Evidence |
|---|---|
| Staff is in the Studio sidebar | `01-staff.png` |
| An "Open the till" link | `01-staff.png` |
| Till rules save. The cap was changed, read back through `ShopPolicy`, then restored to 500 | `02-staff-after.png` |
| A till PIN is set from the screen, and audited (`staff.pin_set`) | `02-staff-after.png` |
| At phone width, Staff and Open the till are in the More sheet | `03-more-sheet-phone.png` |
| No sideways scroll at 390px | — |

`tests/Frontend/studioSections.test.mjs` covers who sees which section.
