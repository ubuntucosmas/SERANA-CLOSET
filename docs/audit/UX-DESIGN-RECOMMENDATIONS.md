# SERANA-CLOSET: UX, Navigation, Design and Architecture Recommendations

**Date:** 2026-10-16
**Basis:** the real-user QA pass (`ISSUE-REGISTER.md`), the fixes made since, and a structural review of the storefront and Studio as they stand on `design/novatek-layout-system`.
**Goal:** a customer should never have to think about where to go next. Every screen has **one obvious next step**. Everything else is quieter, folded away, or one tap deeper.

Each item: **what's wrong → why it matters → what to do**. Ordered by impact within each section. Effort: **S** < 1h, **M** < 1 day, **L** > 1 day.

---

## 1. The core idea: two journeys, nothing else competing

The business has exactly two ways to make money:

1. **Buy a ready piece:** Shop → Product → Bag → Checkout → M-Pesa → Track.
2. **Commission a bespoke piece:** Inspiration → Brief → WhatsApp quote → Pay → Track.

Every page should visibly belong to one of these, and hand off to the next step. Today the site also offers Circle, About, Track, Drops (off), Contact, a newsletter, theme toggle and account links, often all at once. These aren't wrong, but they're given the same visual weight as the two money paths. **Rule of thumb: per screen, one primary button, at most one secondary, everything else as plain text links.**

---

## 2. Navigation

### N-1. Three navigations on mobile at once (M)
**Wrong:** on a phone the customer sees the top header (logo, bag, hamburger), a hamburger menu, and a floating bottom bar (Home, Shop, Bag, Circle, Track). They overlap in purpose and compete for the eye.
**Why:** duplicate controls make people hesitate ("is this the same Shop?"). The floating bar also covers content and the last button on every page.
**Do:**
- Make the **bottom bar the only primary mobile navigation**: **Shop · Bespoke · Bag (centre) · Track · Account**.
- Strip the mobile header to **logo + bag**. Move the theme toggle, About, Contact and Circle into the Account sheet / footer.
- Remove "Home" from the bar. The logo already goes home, and Home is not a task.
- Add `padding-bottom` to the page equal to the bar height so it never covers a CTA.

### N-2. Bespoke, a core revenue path, has no permanent entry point on mobile (S)
**Wrong:** "Circle" (a client gallery) sits in the bottom bar, but **Bespoke / Custom Order is not there**. The word "Circle" doesn't tell a first-time visitor what it is.
**Do:**
- Replace **Circle** with **Bespoke** in the bar.
- Fold the gallery into the Bespoke page as "Pieces we've made", where it does its real job: proof right before the brief form.

### N-3. Labels are 8px (S)
**Wrong:** bottom-bar labels are `text-[8px]` uppercase-tracked. That's below comfortable reading size for anyone over 40 or in sunlight.
**Do:** use 11–12px, normal tracking, sentence case ("Shop", "Bespoke", "Track").

### N-4. Desktop header: too many equal-weight links (S)
**Wrong:** Home · Shop ▾ · Circle · About · Track, plus icons. All are styled identically.
**Do:**
- Keep **Shop** and **Bespoke** as the two prominent items.
- Put About / Circle / Contact under a quiet "Studio" text link or the footer.
- Move Track to the account icon menu and the footer. Customers look for it after buying, from the receipt email link, not while browsing.

### N-5. Consistent vocabulary everywhere (S, ongoing)
The same thing currently has several names. Pick one and use it in nav, buttons, emails, Studio and tracking:

| Concept | Use | Avoid |
|---|---|---|
| Cart | **Bag** | cart, basket |
| Custom work | **Custom Order** (decided 2026-10-16; replaces "Bespoke") | bespoke, commission, brief, request |
| Order reference | **Order code** (SRN-…) | cipher, reference, ID |
| Account orders page | **Your Orders** | My Vault, Designs |
| Payment waiting | **Payment Pending** | Awaiting payment (now unified) |

The URL `/my-vault` and the route name `profile.designs` are internal. No visible text says "vault" today. Keep it that way.

---

## 3. Page-by-page: guiding without overwhelming

### Home (owned by the other tool's in-progress work; recommendations only)
- **One hero, one button.** The hero should end in a single primary CTA (**Shop the Collection**), with Bespoke as a text link beneath. Two equal buttons split attention.
- **Sequence sections as a story that ends in action:** Hero → 4 featured pieces → *how bespoke works in 3 steps* → proof (Circle/testimonials) → final CTA. Each section's last element should point to the next step.
- **Weight budget:** the in-progress Home is 2.1–2.5MB / ~90 requests (SC-041). Aim for < 1MB on first load. Lazy-load everything below the fold, and use WebP at 1600px max.
- **Motion:** cinematic scroll is a brand asset but must respect `prefers-reduced-motion`. It must never hijack the scroll speed on phones.

### Shop
- **Filters as chips, not a panel.** The categories are few. A single horizontal row of chips (All · Hoodies · Sets · …) above the grid beats a drawer. Sort stays as the one secondary control. It is now persisted in the URL.
- **Card hierarchy:** image → name → price. Badges ("New", "Bespoke") at most one per card. Stacked badges read as noise.
- **Sold-out pieces sink** to the end of the grid instead of interrupting it.

### Product
- **Above the fold on a phone:** image, name, price, size, **Add to Bag**. Nothing else. Fit help, fabric, delivery and "Request a Custom Version" go below as collapsible rows (progressive disclosure).
- **Size first, then button.** Keep Add to Bag disabled with the label "Choose a size" until a size is picked. The button text itself teaches the step, so no error message is needed.
- **After adding:** open the bag drawer with **Checkout** as the primary button and "Keep shopping" as text. That sends the customer to checkout without a toast to dismiss.

### Bag and Checkout
- **Checkout is a single column with a visible 3-step header:** *Details → Pay with M-Pesa → Done*. People tolerate forms when they can see the end.
- **Ask only what fulfilment needs:** name, phone, area/town, delivery note. Email optional, explained ("for your receipt").
- **M-Pesa moment:** after the prompt is sent, the screen should show only one thing, "Check your phone and enter your M-Pesa PIN", with the amount and a countdown. Hide everything else until it resolves. Resume / Start Over and the mismatch state now exist (SC-004/006). Keep them calm and specific.
- **Success page = tracking page.** Show the order code large, "Save this code", and a WhatsApp share of the tracking link.

### Bespoke
- **Three visible steps** above the form: *1. Share your idea → 2. We quote on WhatsApp → 3. Pay and we make it*. This removes the biggest fear (committing money too early).
- **Form in two short stages:** (a) what you want plus photos, (b) contact details. Ask for measurements only if the customer chooses "I know my measurements". Otherwise the studio collects them on WhatsApp.
- Put the gallery ("Pieces we've made") directly above the form as proof.

### Track
- One field pair (order code + phone), then a **timeline** with the current step highlighted and a plain sentence: "We're sewing your piece. Usually ready in 5–7 days." Customers want *when*, not just *status*.

### Account
- Your Orders first, settings second. Sign Out is now reachable on every surface (SC-022).

---

## 4. Visual design system

### D-1. One accent, used only for "do this next" (M)
**Wrong:** cyan appears on kickers, links, icons, borders, badges, active nav, prices and buttons. When everything is accented, nothing is.
**Do:** reserve electric cyan (`#57d5fc` dark / `#07617f` light) for **primary actions, the active nav item and focus rings**. Kickers and decorative icons move to `on-surface-variant`. The eye then lands on the next step by default.

### D-2. Stray colours from the old theme (S)
- The bag button in the mobile bar has a **neon-green glow** (`rgba(57,255,20,0.3)`), left over from a previous palette. Use the cyan token.
- Hard-coded `#00d2ff` / `#57d5fc` in components should use the `primary` token, so light mode and future rebrands stay consistent.

### D-3. Typography scale (M)
Settle on 5 sizes and stop using one-off pixel values (`text-[8px]`, `text-[12px]`, `text-[21px]` …):

| Role | Size |
|---|---|
| Caption | 12 |
| Body | 15–16 |
| Lead | 18 |
| Section title | 28–32 |
| Hero | 44–64 (clamp) |

Uppercase tracked text should be limited to short kickers (≤ 3 words). Long uppercase text is slow to read.

### D-4. Spacing rhythm (S)
Use one vertical section rhythm (e.g. `py-16 md:py-24`) and one card padding (`p-6`) across all pages. Consistent gaps tell the eye where a section ends without needing dividers.

### D-5. Motion budget (S)
Animate **state changes** (bag count, drawer, step progress), not decoration. Keep durations 150–250ms. Honour `prefers-reduced-motion` globally.

---

## 5. Studio (owner) UX

- **Open on "Today", not on tabs.** The Attention list is the right idea. Make it the landing view with three groups: *Needs action now* (refund owed, payment to record, briefs to quote), *Ready to dispatch*, *Low stock*. The full order table becomes a secondary tab.
- **Order editor as a checklist:** Payment ✓ → Items to pack → Deliver to → Stage. The new blocks (SC-009/011/012/013) are in place. Order them top-to-bottom as the owner's actual workflow.
- **Stage changes as one-tap buttons** ("Mark shipped") instead of a dropdown, showing only the *next* valid stage.
- **Pagination** once orders pass a few hundred (SC-015).

---

## 6. Architecture

| # | Recommendation | Why | Effort |
|---|---|---|---|
| A-1 | **Split `custom_orders.status` into `payment_status` and `fulfilment_stage`.** | One column currently mixes payment and production states. That forced the guard logic in `StudioController@update`, the "managed automatically" rules, and SC-007-type bugs. Two columns remove a whole class of errors. | L (migration + backfill) |
| A-2 | **Split `StudioDashboard.vue`** into routed pages (`/studio/orders`, `/studio/catalogue`, `/studio/settings`) with their own controllers and lazy props. | It's one large page loading everything (SC-015). Routing gives back-button behaviour, shareable links and per-page queries. | M–L |
| A-3 | **One status/label module shared by PHP and JS.** | Labels are defined in `OrderStatus.php` and `orderStatus.js`. Generate the JS map from PHP (or expose via Inertia shared props) so wording can't drift again. | S |
| A-4 | **Design tokens only.** | Ban raw hex in components (lint rule or grep in CI). Everything goes through `primary`, `surface`, `on-surface-variant` and friends. | S + ongoing |
| A-5 | **Browser smoke tests in the repo.** | The Playwright checks used for this QA live outside the repo. Move a small set (checkout happy path, bespoke submit, track, Studio record payment) into `tests/Browser` and run them in CI. | M |
| A-6 | **Enforce Pint and `composer audit` in CI.** | 33 dependency advisories exist today. | S |
| A-7 | **Drops as a module.** | When the policy exists (SC-053), keep Drops behind its flag with its own controller/routes. It already is mostly. Don't let its states leak into the shop order flow. | — |

---

## 7. Suggested order of work

1. **Quick wins (half a day):**
   - N-2 Bespoke in the bar
   - N-3 label size
   - D-2 stray green and hard-coded hex
   - N-5 vocabulary sweep (Bag, Bespoke, Order code)
   - Product "Choose a size" button
2. **Navigation consolidation:** N-1, N-4.
3. **Checkout step header and focused M-Pesa screen.**
4. **Bespoke 3-step framing and two-stage form.**
5. **Studio "Today" view.**
6. **A-1 status split.** Do it before order volume grows, while the backfill is small.

Home-specific items wait for the other tool's work to land.

---

## 8. Polish pass 1 — shipped (2026-10-16, uncommitted)

A customer walkthrough at 390px and 1440px (Playwright: every public page, then add-to-bag → checkout, Back button, menu, tracking) found the issues below. All are fixed on the working tree. Home was not touched; it shares `ProductCard`, so it inherits the card change.

| Area | Found | Now |
|---|---|---|
| Speed | Every page prefetched all 54 page chunks, Studio admin included (77 requests for a 182KB page). | `Vite::prefetch` removed. After load, the idle browser warms only Shop, Product, Checkout, Bespoke and Track (skipped on Save-Data). Hover-prefetch on nav and product links. Progress bar only after 250ms. **33 requests.** |
| Mobile nav (N-1/2/3, D-2) | Home · Shop · Bag · Circle · Track, 8px labels, hard-coded `#00d2ff`, green glow. | **Home · Shop · Bag · Bespoke · Track** (Drops takes Track's slot when enabled), 11px, 48px targets, tokens. Bag count pops on change. Account stays in the header and menu. Home kept on desktop and in the menu at the owner's request (supersedes N-1's "remove Home"). |
| Menu | Page scrolled behind the open menu. The menu stayed open after Back. | Scroll locked; closes on any navigation. |
| Product page | Add to Bag at y=1115 on an 844px screen. Set sizes hidden behind a Hoodie/Joggers toggle. Missing size → red "SOMETHING WENT WRONG" toast. Gallery auto-advanced every 5s. Duplicate WhatsApp buttons, a "SINGLE PIECE / ITEM 01" filler card, "(Set)" appended to names already ending in "Set". | Name → price → size rows (both visible for sets) → button. The button teaches the step ("Choose jogger size" → "Add to Bag · KSh 4,500"). Sticky buy bar on phones while the button is off-screen; tapping it with a size missing scrolls to that row. No auto-advance; arrow keys work. One WhatsApp text link. Details moved below the decision. |
| Bag drawer | 600ms slide + 800ms staggered items. Grayscale photos. Sizes unreadable in light mode. A ready-to-wear piece flagged "Offer a custom version" showed **"CUSTOM TAILORING — allow 14–21 business days"** (stale meaning of `is_customizable` after SC-016). Toast covered the item just added. Drawer could survive the Back button. | 300ms in / 220ms out, colour photos, "Top M · Bottom L", linked names. No tailoring claim. The drawer is the add-to-bag confirmation (no toast). Closes on any navigation. "Checkout with M-Pesa" primary; "Keep shopping" and "Order on WhatsApp instead" as text. |
| Product card | Quick view's only button opened the product page anyway. It printed the literal word "WHATSAPP" (no brand glyph in Material Symbols), ignored Esc and had no focus trap. ~60 lines of dead zoom/cycle code. | Quick view removed. Card = photo, name, price, one badge ("Bespoke option" instead of "Bespoke"). Sold-out photos desaturated. |
| Shop (phone) | Categories only in a hidden drawer whose text was `text-white/20` (invisible in light mode). Floating WhatsApp button sat under the bottom bar; a filter pill stacked above it. Sold-out pieces mixed into the grid. | Swipeable category chips under search, native sort picker. Both floating buttons and the drawer removed. Sold-out sinks to the end. |
| Checkout | Order summary first on phones (first field at y=1066). Pay button disabled with no reason given. Phone error rendered beside the input. Three success toasts repeated what the modal said. A stray backdrop tap dismissed the "check your phone" step. Bottom bar and newsletter on screen while paying. | **Focus mode** (no bottom bar, compact footer). Form first, with a one-line "1 piece · KSh X · View bag". **"Pay KSh X with M-Pesa"** is always tappable; with gaps it scrolls to the first empty field and names it. Errors clear as you type, and the top box only shows non-field errors. Signed-in name and email prefilled. The backdrop can't close an in-flight prompt. |
| Tracking | Phone re-typed on every visit, including from "Track Order" links. A screen of dead space before the footer. | After a successful payment or lookup, this device remembers the number for 90 days, so tracking links open straight on the order. "Use a different number" forgets it. "Track another order" keeps it. The code is upper-cased before sending. |
| Bespoke | `URL.createObjectURL` ran inside the template: new blob URLs on every keystroke, never revoked. "Drop images here" had no drop handler. PDFs previewed as broken images. 20px remove buttons. The WhatsApp-number error was set but never rendered. Server 422s were not mapped to fields. The controller loaded 6 products nobody used. | One preview per file, revoked on remove, submit and leave. Real drag-and-drop with a hover state; unsupported files are skipped with the iPhone hint. PDF tile. 32px labelled remove buttons. Inline WhatsApp error. 422s mapped to fields. Unused query removed. |
| Toasts | "SOMETHING WENT WRONG" heading on routine guidance. White text on a white card in light mode. Infinite scanner animation. Duplicates stacked without limit. | Calm icon + sentence, token colours (readable in both themes). Identical messages refresh rather than stack; at most 3. `role="alert"` for errors only. The newsletter no longer shows a toast and an inline error together. |
| Auth | Register used the old Breeze layout (no nav). Login had no `autocomplete`, so password managers couldn't fill it, plus ~130px of empty padding. | Register matches Login in the storefront. `username` / `current-password` / `new-password` hints. Padding fixed. |
| Layout | The page-enter animation left a `transform` on the page wrapper, so any `position: fixed` element inside a page anchored to the wrapper, not the screen. `hideMobileCard` prop declared and passed but never read. | Transform cleared on completion. Dead prop replaced by `focus`. Page transition 260ms (was 420ms). |

**Verified:** `php artisan test` 130 passed. `node --test tests/Frontend/` 11 passed (3 new in `feedback.test.mjs`: toast de-dupe, toast cap, tracking-phone memory and expiry). Browser checks repeated after the changes: CTA labels, sticky bar, drawer timing, focus mode, field focus, chip filtering, sold-out order, Back closes the drawer, menu scroll lock, tracking deep link. No console errors.

## 9. Next tricks and flows (not built yet)

Ordered by customer impact. Items marked **owner** need a business decision first.

1. **One-tap M-Pesa (owner).** Today it is "Pay" → modal → "Pay" again → STK. Sending the STK straight after the order is created saves a tap at the most nervous moment. The modal already shows the server-confirmed amount while waiting. This needs a decision on whether the separate review step is wanted.
2. **Bespoke in three visible steps + two-stage form** (section 3). Still a five-section single form (4,500px on a phone). Stage 1: idea + photos. Stage 2: contact. Measurements behind "I know my measurements".
3. **Share and save on success.** After payment: "Send tracking link to WhatsApp" (`wa.me/?text=` with the `/track?ref=` URL) and an "Add to home screen" hint. The tracking memory makes that link one tap for the buyer.
4. **ETA sentence on Track (owner, SC-051–054).** "We're sewing your piece. Usually ready in 5–7 days." Needs real lead times.
5. **Opt-in "Remember my delivery details" (owner).** Name, phone and address are kept for 60 minutes only. A checkbox would make repeat orders a single tap. It stores personal data on the device, so it must be opt-in.
6. **Server-side size check.** `CheckoutController` passes `options` through unchecked. A crafted request can buy a set with no sizes. Validate the required sizes per `garment_type`.
7. **Finish the mobile header (N-1).** The hamburger still holds About / Circle / Contact. Fold these into an Account sheet and the footer, so the header is just logo + bag.
8. **One name for the community page.** It is "Community" on the desktop nav, "The Serana Circle" in the footer and "Circle" in the old bar. Pick one (see N-5).
9. **Remaining Breeze screens.** Forgot / reset password, verify email and confirm password still use `GuestLayout`.
10. **Shop search in the URL** (`?q=`), so a search survives Back and can be shared. Close the desktop sort menu on outside click.
11. **Image weight.** `white_hoodie_silver_zipper.png`, `black_zip_up_hoodie_gray_bg.png` and `black_white_hoodies_showcase.png` (6.4MB together) are referenced nowhere in code or local settings. Check production settings, then remove them or convert them to WebP. Add `width`/`height` to product images to stop layout shift.
12. **Primary-button colour (owner).** Buttons use a blue→cyan gradient (`--action-from/to`), while the bag button, badge and active states are solid cyan. Confirm the gradient is intended, or align it with the agreed cyan accent.
