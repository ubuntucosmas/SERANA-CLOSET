# Homepage engagement pass — 16 October 2026

The existing SERANA visual system and business flows remain in place. This pass changes the homepage presentation:

- A manually controlled, three-look editorial hero with descriptive image alternatives, pressed states and reduced-motion transitions. Existing Studio image settings remain supported.
- New arrivals filter by the real categories represented in the featured catalogue; counts describe this edit, not the whole shop.
- Category discovery follows the featured pieces, then a three-step bespoke story explains the request, WhatsApp discussion and progress updates.
- Compact mobile trust information and a simple support/tracking handoff reduce repeated card layouts.
- Hero image space is reserved to prevent layout jumps, and below-fold imagery loads lazily. No dependencies added.

Validation: production build passed; 111 backend tests passed (379 assertions). Browser checks cover 320, 375, 390, 430, 768, 1024, 1440 and 1920px; look switching, collection filters, light theme and reduced motion. Initial and final browser evidence are retained in this directory. No business data or payment flows were changed by the homepage pass.

## Cinematic scroll treatment

The homepage now adds layered hero parallax, masked image entrances, staggered product/category reveals and a subtle pan inside the bespoke image. The controller uses passive native-scroll listeners, IntersectionObserver and the browser animation API; it adds no dependencies. Mobile image movement is reduced to 40% of desktop strength. The frame loop stops when the layers settle, observers/listeners are cleaned up on navigation, keyboard-focused content appears immediately, and reduced-motion preferences bypass the effects, including when the preference changes during a visit.

Verification: build passed; Chrome checked 320, 375, 768, 1024 and 1440px with no overflow or JavaScript errors. Scroll position remained native, image transforms responded to scrolling, products still filter, keyboard focus reveals offscreen links, and Home → Shop → Home navigation passed. The idle frame count was zero. Both initial and changed reduced-motion preferences were checked. The initial browser check sampled the preference change before its browser event completed; the final check waits for the actual change and passes. Evidence is retained alongside the previous homepage checks.
