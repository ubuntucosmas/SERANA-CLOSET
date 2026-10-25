<?php

// Route names the browser may call (app.blade.php @routes). Shoppers get the
// storefront group: staff screens and server-only endpoints stay out of every
// public page (they were ~75% of its HTML). Staff get every route.
return [
    // resources/js/app.js bundles route() and exposes it; don't inline a second copy in every page.
    'skip-route-function' => true,
    'groups' => [
        'storefront' => ['!studio.*', '!admin.*', '!pos.*', '!mpesa.callback', '!storage.*', '!health'],
    ],
];
