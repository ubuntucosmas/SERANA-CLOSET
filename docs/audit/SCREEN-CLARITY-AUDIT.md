# SERANA screen clarity and flow audit

Date: 15 October 2026. Reference: user-supplied NOVATEK HTML, adapted to SERANA’s fashion brand and existing Laravel capabilities. This is a source audit and implementation record; it does not assert that live Safaricom payments or production deployment were tested.

## Design and terminology

The shared system uses near-black/navy surfaces, blue primary actions, cyan accents, Plus Jakarta Sans body text, Space Grotesk headings and rounded controls. Light mode remains available. Small existing utility labels have a readable minimum, keyboard focus is visible, and reduced-motion preferences are respected. The global rule that forced every element to have square corners was removed.

| Concept | Preferred customer wording | Meaning/source |
| --- | --- | --- |
| Cart | Your Bag / Add to Bag | Local bag; stock and price revalidated at checkout |
| Custom order / commission | Bespoke Request | Brief saved, then discussed on WhatsApp; no online charge for submission |
| Pre-order campaign | Drop / Drop Reservation | Paid reservation; production depends on campaign goal |
| Profile | Account Settings | Contact information and password |
| Vault | Your Orders | Orders attached to the signed-in customer; guests use tracking |
| Order status lookup | Track Order | Reference and matching phone required |
| Paid | Payment Confirmed | Confirmed payment, distinct from fulfilment |
| Pending payment | Payment Pending | Payment not yet confirmed; avoid a second payment while waiting |
| Completed production | Ready for Dispatch | Does not imply the parcel has been dispatched |
| Shipped | Dispatched | Studio fulfilment stage |
| Greenlit | In Production | Customer wording; underlying campaign enum retained |

`Support/orderStatus.js` and `StatusBadge.vue` provide shared payment labels, explanation, next step and non-colour status text. The database still uses one legacy `status` column for payment and production; the presentation keeps payment confirmation separate using `is_paid`. Studio edits cannot change payment confirmation or commerce totals and cannot fulfil unpaid commerce orders. This avoids presenting a manual status edit as a settled payment.

## Screen inventory and decisions

Each row records purpose/user/question, required versus supporting information, action hierarchy, status/next step, and the source findings or retained behaviour. Routes with multiple states are supplemented by the state inventory below.

| Screen / route | Purpose · primary user · primary question | Required information / supporting information | Primary → secondary action | Status / next step and audit decision |
| --- | --- | --- | --- | --- |
| Home `/` | Discover Serana · shopper · What can I buy here? | Collection, fashion image, buying choices / brand story | Shop Collection → Start Bespoke Request | Hero rewritten around one main shopping action; supplied visual system applied; false wishlist wording removed. |
| Shop `/shop` | Browse · shopper · Which piece suits me? | Product, price, category, result count / sort | Open Product → Clear Filters | Dead search button replaced with labelled search available on mobile and desktop; active category/query and count visible; empty search has recovery. |
| Category `/shop?category=…` | Narrow collection · shopper · What is in this category? | Current category and filtered products / other categories | Open Product → Clear Filters | Existing server category source retained; search works inside selected category. |
| Product `/shop/{slug}` | Choose a piece · shopper · Price, availability and size? | Gallery, price, description, garment options, availability / fabric details and fit help | Add to Bag → WhatsApp Size Help | Explicit add wording replaces Buy Now; unavailable products blocked; floating action bypass removed; breadcrumb and delivery/payment next step added. |
| Product card / quick view | Preview · shopper · Is this worth opening? | Image, name, price, stock / extra images | Choose Size → WhatsApp Inquiry | Quick add now opens product selection; false Waitlist becomes disabled Out of Stock. |
| Product gallery | Inspect · shopper · What does it look like? | Product images / next and previous controls | Next/Previous Image → Product Options | Image controls labelled; image data/fallback retained. |
| Size selection | Configure · shopper · Which size am I adding? | Chosen garment component and size / fit help | Select Size → Add to Bag | Existing actual garment types retained; both sizes required for a set; stock caps combine all bag lines of the same product. Studio can now edit the supported garment type. |
| Bag drawer | Review · shopper · What is in my bag? | Item, chosen sizes, quantity, subtotal, delivery and total / support | Checkout → WhatsApp Order | Quantity function exported; stable variant keys; removal affects one size; empty action goes to Shop; dialog semantics, Escape, focus containment and scroll lock added. |
| Bag `/cart` | Review · shopper · Am I ready to checkout? | Same line details and totals / shop navigation | Checkout → Continue Shopping | Missing page implemented; empty state has Shop Collection; stock and price confirmation explained. |
| Checkout `/checkout` | Provide delivery details · shopper · What is required before payment? | Items, sizes, delivery fields, phone and total / optional measurements | Review Order & Pay → Back to Collection | Labels associated with fields, required details explained, inline errors and empty bag recovery; existing order reused when resuming. |
| Payment review | Approve current amount · shopper · What amount will I authorize? | Server-created total and M-Pesa phone / PIN privacy | Pay with M-Pesa → Back to Checkout | Panel shows authoritative total before a prompt; duplicate client submissions blocked. |
| Payment request / waiting | Complete M-Pesa · shopper · What is happening on my phone? | Actual request phase, amount, clear phone instruction / PIN privacy | Enter PIN on phone → Wait | Timed fake progress stages removed; awaiting state never claims PIN entry or payment is complete. |
| Payment confirmation | Complete purchase · shopper · Was I paid and what next? | Confirmed amount, reference, receipt, line sizes / email | Track Order → Download Receipt / Support | Rewritten around one primary next action; line snapshot preserved from server checkout. |
| Track Order `/track` | Find status · guest/customer · Where is my order? | Reference, matching phone, payment and fulfilment / photos and receipt | Track Order → Contact Support | Human page title, bound labels, shared status, refresh action; Drop correctly identified as a reservation and included in payment timeline. |
| Tracking result / order view | Understand progress · guest/customer · What is pending? | Reference, date, amount, payment, fulfilment, items / progress photos | Refresh Status → Receipt / Support | Payment explanation and fulfilment displayed separately; next step is explicit; no claimed delivery tracking number when none exists. |
| Drops `/drops` | Discover reservations · shopper · What can I reserve? | Image, title, price, goal and campaign status / progress | View & Reserve → Shop Collection | Closed campaigns say View Drop; no-Drop state links to collection. |
| Drop detail `/drops/{slug}` | Evaluate pre-order · shopper · What does reserving mean? | Price, goal, paid reservation, size and open/closed state / countdown | Reserve → Ask About This Drop | Paid pre-order and production condition explicit; directs timing/goal-failure questions to studio without inventing refund policy. |
| Drop reservation modal | Reserve and pay · shopper · What must I provide? | Name, phone, offered size / email and optional measurements | Reserve & Continue to Payment → Close | Busy state prevents repeated reservation requests; reference captured immediately; resumed modal retains order and pending payment. |
| Bespoke `/custom-order` | Submit brief · customer · How do I start a custom piece? | Name and WhatsApp / inspiration, optional measurements, fabric, occasion and deadline | Submit Request → WhatsApp Follow-up | Required/optional and no-charge explanation added; labels bound; duplicate submission guarded; unsupported priority surcharge removed. |
| Bespoke confirmation | Continue discussion · customer · Was my brief saved? | Reference and WhatsApp handoff / tracking | Open WhatsApp → Track Order | Existing saved confirmation survives popup blockers; studio confirms quote and timing after submission. |
| Contact `/contact` | Ask for help · customer · How do I reach Serana? | Real configured phone/email, inquiry details / studio address | Open WhatsApp with Message → Email | Fake timeout success removed; form opens real prefilled WhatsApp and explains pressing Send there; hardcoded display phone removed. |
| About `/about` | Explain brand · shopper · Who makes these pieces? | Brand story / craftsmanship imagery | Existing collection/bespoke links | Single page heading retained; shared visual and readable text system. |
| Circle `/the-circle` | Explore previous work · shopper · Can I request something like this? | Gallery and captions / studio story | Start Bespoke Request | Unsupported invitation/queue promises replaced with a real bespoke workflow. Gallery empty state retained. |
| Sign In `/login` | Authenticate · customer/staff · How do I view my account? | Email, password, error / remember me | Sign In → Reset Password / Create Account | Staff-only claim removed; terminology simplified; labels bound; guest shopping link; navigation uses this one sign-in screen. |
| Register `/register` | Create account · new customer · What does account creation do? | Name, email, password confirmation / existing account link | Create Account → Sign In | Clear heading/purpose; existing field validation and processing retained; shared guest visual system. |
| Forgot password `/forgot-password` | Request recovery · customer · How do I regain access? | Email / delivery status | Request Reset Link → Sign In | Heading and purpose added; backend status/error handling retained. |
| Reset `/reset-password/{token}` | Set credentials · customer · What do I enter now? | Email, password, confirmation / token | Reset Password | Heading/purpose and shared controls; existing validation preserved. |
| Verify email `/verify-email` | Complete verification · customer · What is still pending? | Verification explanation/status / resend | Resend Verification → Sign Out | Clear heading and next step; existing sent-link confirmation retained. |
| Confirm `/confirm-password` | Reauthenticate · customer · Why enter password again? | Password / protected-action explanation | Confirm Password | Clear purpose and inherited validation/loading behaviour. |
| Account `/profile` | Maintain details · customer · What will this save? | Contact/password forms / account deletion | Save within current section → Your Orders | Shared storefront layout and breadcrumb; each section saves separately; password/contact success feedback retained. |
| Delete account | Remove account · customer · Is this permanent? | Deletion explanation, password and confirmation / cancel | Delete Account → Cancel | Existing destructive confirmation remains distinct and validated. |
| Orders `/my-vault` | Review purchases · customer · What have I ordered? | Reference, type, date, amount, payment, fulfilment / guest lookup | Track Order → Account Settings | Replaced fake measurement-refresh and unused invite controls with actual orders; distinguishes shop, Drop and bespoke. |
| Dashboard `/dashboard` | Choose workspace · signed-in user · Where do I manage my work? | Role / redirect | Automatic redirect | Existing admin→Studio and customer→orders redirect retained. |
| Studio `/studio` | Triage · owner · What needs attention? | Failed/mismatched payments, paid awaiting fulfilment, new briefs, stock / weekly metrics | Open Attention Item → Orders / Products | Queue expanded beyond pending payments; readable next action per order. |
| Studio Orders `?tab=orders` | Manage fulfilment · staff · Which order needs action? | Order/reference, type, payment, stage, total / search/archive | Open Order → Search / Archive View | Shared payment badge; search/filter empty state differentiates no orders from no matches. |
| Studio order editor | Update work · staff · What can I safely update? | Immutable payment confirmation, fulfilment, notes, quote rules / progress image | Save → Upload / Archive | Server validates stages, protects payment and commerce totals; field errors visible; confirmation via shared notifications. |
| Studio Products `?tab=products` | Manage catalogue/inventory · staff · What is for sale? | Price, availability, batch, garment type / images, description | Add/Edit Product → Archive / Categories | Supported garment type editable; stock/price bounds; all form errors visible; searches distinguish no results. |
| Studio Categories | Organize catalogue · staff · Where does this item belong? | Name, assigned products / description/banner | Save Category → Remove | Existing validation and reassignment requirement retained. |
| Studio Drops `?tab=drops` | Manage campaigns · staff · What is funded or open? | Campaign state, price, goal, deadline, reservations / fit details | New/Edit Drop → View Reservations | Existing truthful campaign/reservation data retained; shared visual system and save feedback. |
| Studio Showcase `?tab=showcase` | Manage gallery · staff · Which work is published? | Photo/caption and active/archive state / client/garment label | Add/Edit Photo → Archive / Restore | Existing actual CRUD retained; shared notifications and visual system. |
| Studio Subscribers `?tab=subscribers` | Review leads · staff · Who subscribed? | Email/date and count / search | Export CSV → Search | Search labelled; no matches distinguishable from no subscribers; empty export disabled. |
| Studio Settings `?tab=settings` | Maintain storefront · owner · What will customers see? | Store/contact settings, logo/images / captions and banners | Save Section → Replace/Remove Image | Existing section save actions retained; shared save/error notifications. |
| Legacy `/studio/catalog` | Reach product management · staff · Where is the catalogue? | Studio product workspace | Redirect to Products | Fixed partial-prop dashboard render by redirecting to canonical Studio Products. |
| Receipt `/orders/{id}/receipt` | Keep proof · buyer · Can I download my receipt? | Paid order and signed link / order summary | Download Receipt → Track Order | Existing signed link protection retained; receipt offered only after payment confirmation. |

## State audit

| State | Required feedback | Recovery / implementation |
| --- | --- | --- |
| Empty bag/checkout | Nothing to purchase yet | Shop Collection; no payable empty checkout |
| Empty category/search | No pieces match the selection | Clear Filters; search and category visibly active |
| Sold-out product/quick view | Out of Stock in text | Disabled add, browse collection, support; no fake Waitlist |
| Bag quantity cap | Cannot add above combined product limit | Existing quantities stay intact; server checks authoritative holds |
| Form invalid | Useful validation message; no success claim | Required fields labelled; checkout inline and summary; bespoke/reservation errors retained |
| Creating checkout | Creating your order | Disabled CTA; server order/reference reused for resume |
| Sending STK | Request in progress | Disabled payment action; wait for result |
| Awaiting STK confirmation | Not confirmed; enter PIN only on phone | Wait; no animated claims of completed steps |
| Delayed confirmation | Order/reservation saved; reference shown | Track Order, support; no immediate second-payment CTA |
| Failed/expired/mismatched payment | Failed, expired or requires review | Explain charge uncertainty; contact support before paying again if charged |
| Successful payment | Confirmed amount/reference/receipt | Track Order primary; receipt/support secondary |
| Bespoke saved | Brief saved and reference supplied | WhatsApp discussion, quote and timing confirmation |
| No account orders | Only signed-in orders are listed | Shop Collection or guest Track Order |
| Account/profile saved | Saved feedback from existing forms | Continue or view orders |
| No admin search matches | Filters/search may hide records | Clear search/select All; active/archive distinction retained |
| No Studio queue items | No new payment/brief issues in this queue | Check Orders for existing production work; avoids claiming all work is complete |
| Saving/uploading Studio | Processing state and validation | Existing form guards, visible errors, flash notifications |
| Archived entities | Active and archive views distinct | Restore or explicitly confirmed permanent deletion |

## Workflow contracts

- Shopping: Home → Shop/category/search → Product → required sizes → Bag → Checkout → server-priced review → M-Pesa prompt → confirmed payment → Track Order/receipt.
- Drops: Drops → open campaign → details/size → saved reservation → payment → confirmation or pending handoff → Track Order. A saved reservation is reused when reopening the modal.
- Bespoke: brief → save → reference → WhatsApp discussion → quote/timing → order updates. A fabricated rush charge or invitation benefit is not advertised.
- Account: sign in/register → Your Orders → Track Order → receipt/support; Account Settings saves each section independently. Guest orders remain available through reference + phone.
- Studio: attention queue → order → confirmed payment review → fulfilment update → dispatch. Payment is confirmed by payment settlement, not by editing a checkbox.

## Verification and boundaries

The repository’s existing discovery report remains historical evidence; its earlier browser/test claims are not counted as current verification. Current results are recorded below after the final checks. Payments are tested using fakes/mocks, without sending an actual M-Pesa request or WhatsApp message. Production queue/scheduler operation, merchant refund policy and live payment credentials cannot be established by a local screen audit. No policies, delivery promises, shipping tracking integration or arbitrary new garment types were invented from the visual reference.

### HTTP error screens

Custom 401, 403, 404, 419, 429, 500 and 503 views provide a plain-language page title, explanation and Return Home / Contact Support / Track Order actions. Session expiry, request throttling and server errors explicitly direct customers to check an existing order before repeating payment. They render without depending on the JavaScript bundle.

### Completed checks

- `npm run build`: passed.
- `ddev exec php artisan test`: **90 passed, 267 assertions**, including combined variant stock, unavailable stock, retry reconciliation state and Studio payment/fulfilment safeguards.
- `node tests/Frontend/cart.test.mjs`: **6 passed**, covering independent size lines, combined quantity caps, stable option keys, unavailable stock and local-storage recovery/persistence.
- Chrome: **39 public page/viewport checks** (13 pages at 375, 768 and 1440px), all HTTP 200, one page heading, no horizontal overflow and no JavaScript errors. Search empty results and clearing passed.
- Chrome: shopping flow from product size selection through bag quantity, server amount review, pending payment, successful confirmation, cleared bag and tracking link passed with payment endpoints mocked.
- Chrome: **18 authenticated page/viewport checks** for seven Studio tabs and two account pages at 375 and 1440px. No JavaScript errors. The final pass detected mobile Orders overflow; search controls and order cards were corrected, rebuilt and specifically retested with no overflowing elements.

Raw browser evidence: `verification/browser-results.json`. It retains the detected Orders issue rather than erasing the original finding; `verification/orders-mobile-retest.json` records the corrective follow-up.

Live provider charges, WhatsApp delivery and production hosting are outside this local verification. Existing uncommitted project changes were preserved; no deployment or production database changes were made.

- Final Chrome request flows: bespoke form → saved reference with popup blocked, and Drop detail → size/details → reservation → payment confirmation both passed. Submissions and payments were mocked; no external message or charge was sent. No JavaScript errors. See `verification/request-flows.txt`.
