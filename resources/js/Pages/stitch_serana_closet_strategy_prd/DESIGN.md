```markdown
# Design System Strategy: The High-Tech Atelier

## 1. Overview & Creative North Star
The Creative North Star for this design system is **"The Neon Artisan."** 

We are moving away from the "standard" luxury aesthetic of muted beiges and soft fades. Instead, we are merging the meticulous precision of a high-end fashion atelier with the aggressive, high-energy pulse of a digital-first future. This system is built on the tension between the classic elegance of `notoSerif` and the electric vibration of `primary` (#f3ffca / #cafd00). 

To break the "template" look, we utilize **Intentional Asymmetry**. Do not feel forced to center-align every hero or grid. Use heavy-weight typography to anchor corners, allow elements to overlap across different `surface-container` tiers, and use high-contrast "stabs" of Luminous Green to guide the eye through a dark, immersive environment.

---

## 2. Colors & Surface Architecture
The palette is dominated by `surface-dim` (#0e0e0e) to create a void-like depth, allowing the Luminous Green and Crisp White to feel like light sources rather than just colors.

*   **The "No-Line" Rule:** 1px solid borders are strictly prohibited for defining sections. Structure is created through "Tonal Carving." A section transition is marked by moving from `surface` (#0e0e0e) to `surface-container-low` (#131313). 
*   **Surface Hierarchy & Nesting:** Treat the interface as a physical stack of materials. 
    *   Base: `surface`
    *   Primary Content Areas: `surface-container`
    *   Floating Interactive Elements: `surface-container-high` or `highest`
*   **The "Glass & Gradient" Rule:** For high-impact areas, use `surface-variant` with a `backdrop-blur` of 20px-40px. 
*   **Signature Textures:** Main CTAs should not be flat. Use a subtle linear gradient from `primary` (#f3ffca) to `primary_container` (#cafd00) at a 135-degree angle to create a "neon tube" glow effect.

---

## 3. Typography: The Editorial Edge
The typographic system relies on the friction between a timeless serif and a technical sans-serif.

*   **Display & Headline (Noto Serif):** Used for "Brand Moments"—product names, editorial titles, and high-level storytelling. The wide tracking and serif detail convey the "Atelier" heritage.
*   **UI & Metadata (Manrope):** All functional UI elements (labels, inputs, small body text) use Manrope. Its geometric construction mimics technical blueprints, reinforcing the "Precision" vibe.
*   **Hierarchy Note:** Use `display-lg` in all-caps or tightly tracked settings to create a "poster" feel. Contrast this with `label-sm` in `primary` color for a "technical spec" look.

---

## 4. Elevation & Depth
In this system, elevation is "Glow and Layer" rather than "Shadow and Light."

*   **The Layering Principle:** Avoid shadows on cards. Instead, use a `surface-container-lowest` (#000000) card placed on a `surface-container-high` (#20201f) background to create "inset" depth.
*   **Ambient Shadows:** If a floating element (like a modal) requires a shadow, use a large blur (60px+) with the color set to a 5% opacity version of `primary`. This creates a subtle neon "aura" rather than a muddy grey shadow.
*   **The "Ghost Border" Fallback:** For buttons or inputs that lack high contrast against a background, use the `outline-variant` token at 15% opacity. It should feel like a suggestion of an edge, not a hard line.
*   **Glassmorphism:** Use `surface-bright` at 40% opacity with a heavy blur for navigation bars. This allows the high-energy product photography to bleed through the UI, maintaining the "High-Tech" atmosphere.

---

## 5. Components

### Buttons: The "Power Switches"
*   **Primary:** Filled with `primary_fixed` (#cafd00). Text in `on_primary_fixed` (#3a4a00). Shape: `sm` (0.125rem) for a sharp, precision-tooled look.
*   **Secondary:** Ghost style. `outline-variant` border at 20% opacity. Text in `on_surface`.
*   **States:** On hover, Primary buttons should emit a `primary_dim` outer glow (4px spread).

### Cards & Lists: The Editorial Grid
*   **Cards:** Strictly no borders. Use `surface-container-low` backgrounds. Image-to-edge layouts only.
*   **Lists:** Forbid divider lines. Separate list items using 16px or 24px of vertical white space from the spacing scale. Use a `primary` color vertical bar (2px wide) next to an active list item to indicate selection.

### Input Fields: Technical Precision
*   **Styling:** Underline-only or subtle `surface-container-highest` fills. 
*   **Focus:** Transition the underline from `outline` to `primary` with a 0.3s ease. 
*   **Error State:** Use `error` (#ff7351) but keep the typography in `Manrope` for readability.

### Signature Component: The "Atelier Spec" Label
*   Small, all-caps `label-sm` text strings in `primary` color, often placed in the corners of containers or images to mimic the technical labeling of a garment pattern or a high-tech blueprint.

---

## 6. Do’s and Don’ts

### Do:
*   **DO** use extreme scale. Pair a massive `display-lg` headline with a tiny `label-sm` detail.
*   **DO** utilize negative space. Let the `surface-dim` (#0e0e0e) "breathe" to make the content feel more premium.
*   **DO** use the `ROUND_TWO` (0.125rem - 0.25rem) radius. It should look sharp enough to cut, but just soft enough to feel digital.

### Don’t:
*   **DON'T** use 100% opaque grey borders. It breaks the "Atelier" immersion and feels like a generic framework.
*   **DON'T** use standard "Drop Shadows." Use tonal shifts or neon auras instead.
*   **DON'T** center-align everything. Experiment with left-heavy layouts and right-aligned metadata for a custom, editorial feel.
*   **DON'T** use pure black (#000000) for everything. Use `surface-dim` (#0e0e0e) for the background to keep the "Deep Charcoal" sophisticated feel. Only use #000000 for `surface-container-lowest` to create "pits" of depth.