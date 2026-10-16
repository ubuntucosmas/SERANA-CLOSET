# Homepage product showcase

The hero displays up to 12 recent, photographed catalogue products in a shuffled order. Each cycle visits every selected product before reshuffling, avoiding an immediate repeat between cycles. Eligibility is evaluated on page load: active products marked in stock, with remaining batch capacity after active checkout holds, or with unlimited stock. Checkout remains authoritative for later stock changes.

Each product uses its real image, category, name, price and detail link in a dark gradient overlay. Transitions fade the image and overlay together every 6.5 seconds. Hover, keyboard focus, a hidden tab and an offscreen showcase suspend rotation; manual navigation pauses it until Play is selected. Reduced-motion settings disable autoplay and transitions. Empty catalogues show the editorial image and collection link; a single product needs no rotation controls.

Validation: production build passed; 113 backend tests passed (415 assertions), including sold-out, held, expired-hold, archived, missing-image and empty-catalogue cases. Local Chrome checks passed at widths 320, 375, 768, 1024 and 1440 with no horizontal overflow or JavaScript errors. Automatic changes, manual pause, product links and reduced-motion behavior passed.
