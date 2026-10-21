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

## Audit fixes (docs/audit/FRONTEND-AUDIT-2026-10-21.md), 12 checks, all pass

| Check | Evidence |
|---|---|
| The till shows pieces without typing, newest first | `04-till-pieces-on-load.png` |
| No register picker when there is one till | `04-till-pieces-on-load.png` |
| **Change items** voids the untouched sale and puts the pieces back in the bag | — |
| After a page reload, **Today** lists the unpaid sale. Take payment resumes it, and it is paid with change. | `05-till-today.png` |
| Today offers the receipt of a paid sale | `05-till-today.png` |
| The closed drawer's Z report prints black on white, alone on the page | `06-z-report-print.png` |
| The Overview flags a drawer closed KSh 500 short this week | `07-overview-drawer-difference.png` |
| Studio → Till shows the session, its Z report, the reason and who approved it | `08-studio-till-history.png` |
| Pre-orders are hidden while Drops are off | — |
| Orders show "Till · <seller>" and filter on Till sales; a paid till sale reads Collected | `09-orders-till-filter.png` |
