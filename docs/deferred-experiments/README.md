# Deferred experiments — excluded from the application

Decision, 15 October 2026: **QUARANTINE** the unreferenced StripeCheckoutService, CurrencyConverter and ProcessAIStyleRecommendation prototypes. Their original source is retained as `.txt` under `source/`, outside Composer autoload and application execution. They are incomplete planned work, not supported production capabilities. No credentials or runtime data are stored here.

**REMOVE from the browser runtime** the partial i18n plugin and unused GSAP dependency. The two translated strings are now normal existing English copy; Anime.js remains the established animation library. The original translation prototype is retained as text for future review. No Stripe, foreign exchange, AI or translation integration is enabled.

Restoring any prototype requires a separately authorized implementation, dependency/security review, tests and operational configuration. Do not copy these files back during deployment.
