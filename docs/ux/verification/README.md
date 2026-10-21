# Studio & Sales Desk verification

Run from the project root. The host does not provide PHP; backend checks use the existing DDEV PHP container. TestCase refuses non-test database names; the default test database is SQLite in memory.

## Automated checks

- `npm run build`: production bundle compiles successfully.
- `node --test tests/Frontend/*.test.mjs`: all 11 frontend test files pass, including internal payment-label priority and grouped role navigation.
- `ddev exec php artisan test --compact`: 541 passed (9,300 assertions), 17 skipped. The skipped concurrency checks require their designated database environment.
- `ddev exec php artisan test tests/Browser/OperationalPreviewTest.php --compact`: generates synthetic browser fixtures and tests actual register, sale, cash-payment and pickup endpoints in the isolated test database. These fixtures are ignored under `storage/app/ux-preview`; they contain no store records.
- `ddev exec php artisan test --compact --filter=OperationalUxTest`: 5 passed, including the final delivery-versus-pickup queue case (110 assertions).
- `git diff --check`: clean.

The focused OperationalUxTest covers bespoke filtering across partial reloads, deposit blockers changing only after server-recorded payment, refund attention, delivery/pickup queue separation, workspace-only subscriber data and catalogue matching by category/size/partial SKU.

## Browser walkthrough

Final result: 56 page/theme/viewport checks passed, with zero whole-page overflow, uncaught JavaScript errors or axe findings on scanned surfaces. A further [six register-naming checks](register-naming-results.json) passed against the final build. Cash change, sale completion, next sale, pickup handover, required variance explanation and disabled duplicate M-Pesa prompt scenarios passed.

[Browser results](browser-results.json) record viewport, theme, page overflow, uncaught JavaScript errors and axe WCAG A/AA scan findings. Browser operations replay the isolated server fixtures; they never create real payments or change store data. Live M-Pesa callbacks are covered by backend tests rather than a real handset transaction.

The runner covers Studio Overview, Orders, Bespoke, Refunds, Products, Stock, Staff, Activity, Settings, Sales Desk History, active and closed Sales Desk, manager navigation and cashier navigation. It checks desktop (1440), tablet (768), mobile (375) and desktop dark theme. WCAG scans run for desktop light and dark surfaces; mobile checks cover rendering and overflow. It also walks through cash change, payment confirmation, next sale, register variance explanation, pickup handover and pending M-Pesa duplicate-prompt prevention.

To rerun, first build assets, then regenerate fixtures using the explicit preview test above. `tests/Browser/operational-ux.mjs` expects Playwright Core and axe-core, plus Chrome. It accepts `SERANA_PLAYWRIGHT_MODULE`, `SERANA_AXE_PATH` and `SERANA_CHROME_PATH` for existing external installations; otherwise it resolves dependencies from the project. Run `node tests/Browser/operational-ux.mjs`. No dependency or lockfile changes were needed for this task; an existing local browser tooling installation was used.

## Screenshots

- [Overview, desktop](home-1440.png)
- [Overview, mobile](home-375.png)
- [Order detail](order-detail-1440.png)
- [Settings](settings-1440.png)
- [Sales Desk, desktop](pos-1440.png)
- [Sales Desk, tablet](pos-768.png)
- [Sales Desk, mobile](pos-375.png)
- [Cash payment and change preview](cash-payment.png)
- [Sale complete](sale-complete.png)
- [Cash drawer difference](cash-difference.png)
- [Pickup handover](pickup.png)
- [Waiting for M-Pesa](mpesa-waiting.png)

The browser and automated accessibility checks exercise representative synthetic data. They do not replace staff usability trials, assistive-technology review or production payment-provider validation.

## Staff password setup follow-up

Studio → Staff now starts with Create staff sign-in. The owner may enter and confirm a temporary password or leave both blank for generation. The account still requires a password change on first login. Issued credentials stay visible in the current view until dismissed rather than depending on a follow-up reload.

`ddev exec php artisan test --compact --filter=StaffManagementTest`: 10 passed (101 assertions), covering generated and chosen passwords, confirmation/strength validation, owner authorization, audit redaction and cashier first-login replacement. Production build also passes. [Staff setup screenshots](staff-1440.png) and [focused browser results](staff-password-results.json) cover desktop light/dark and mobile layouts.

## Existing staff access controls

The owner-only reset, detail-edit, session-revocation and access-history flow is implemented and documented in [Staff access management](../STAFF-ACCESS-MANAGEMENT.md). Final backend regression: **555 passed, 17 skipped, 9,431 assertions**. All 11 frontend test files and production build pass. [Three focused browser scenarios](staff-access-results.json) pass for desktop light/dark and mobile, including the issued-password panel and redacted access history.
