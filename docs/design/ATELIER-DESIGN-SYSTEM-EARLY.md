# Design System Document

## 1. Overview & Creative North Star: The Digital Atelier
This design system is not a template; it is a digital manifestation of haute couture. We move away from the "boxed-in" nature of traditional e-commerce to embrace the concept of **The Digital Atelier**. 

The goal is to create a sense of bespoke craftsmanship where every pixel feels intentional and every transition feels like the brush of fine silk. We achieve this through **Intentional Asymmetry**—where high-fashion photography breaks the grid—and **Tonal Layering**, replacing rigid structural lines with sophisticated shifts in light and depth. This system prioritizes the "breath" of the page (generous white space) to ensure that the garments remain the undisputed protagonists of the experience.

---

## 2. Colors: The Palette of Prestige
The color strategy uses a base of absolute contrast (Black and White) punctuated by a signature "Soft Gold" to denote luxury and interactive intent.

### Core Tones
- **Primary (`#745a27` / `primary-container: #c9a96e`):** Our Soft Gold. Use this for high-value actions and brand accents.
- **Surface Hierarchy (`surface-container-lowest` to `highest`):** We rely on the `surface` tokens to create a "paper-on-paper" effect. 
- **On-Surface (`#1a1c1c`):** An off-black used for readability, ensuring the text feels softer than the absolute `#000000` used in brand-heavy display areas.

### The "No-Line" Rule
To maintain a high-end editorial feel, **1px solid borders are strictly prohibited for sectioning.** Do not use lines to separate a header from a hero or a product grid from a footer. Instead, define boundaries through:
- **Background Shifts:** Place a `surface-container-low` section immediately following a `surface` section.
- **Generous Spacing:** Use the `20` (7rem) or `24` (8.5rem) spacing tokens to create a mental break between content blocks.

### Surface Hierarchy & Nesting
Treat the UI as physical layers. A product card (`surface-container-lowest`) should sit atop a gallery section (`surface-container-low`). This creates depth without the clutter of lines.

### The "Glass & Gradient" Rule
For floating navigation or high-end overlays, use **Glassmorphism**. Apply a backdrop-blur (12px–20px) to a semi-transparent `surface-container-lowest`. To add "soul" to CTAs, use a subtle linear gradient from `primary` (#745a27) to `primary-container` (#C9A96E) at a 45-degree angle.

---

## 3. Typography: Editorial Authority
The type system is a dialogue between the traditional elegance of the Serif and the modern efficiency of the Sans-Serif.

- **Headings (notoSerif):** High-contrast and sophisticated.
    - **Display-LG/MD:** Use for editorial storytelling and collection titles. These should often be center-aligned or intentionally offset to break the grid.
    - **Headline-SM:** Used for product names in a gallery.
- **Body & UI (manrope):** A clean, modern sans-serif that ensures legibility.
    - **Body-LG:** Our default for product descriptions.
    - **Label-MD/SM:** All-caps with increased letter-spacing (0.05rem) for functional UI like "ADD TO BAG" or "FILTER."

---

## 4. Elevation & Depth: Tonal Layering
We reject the heavy drop-shadows of standard web design. Depth in this system is ethereal.

- **The Layering Principle:** Stacking `surface-container` tiers is the primary method of elevation. For example, a "Quick View" modal should use `surface-container-highest` to appear naturally "closer" to the user than the `surface` background.
- **Ambient Shadows:** When a shadow is required for a floating element (like a dropdown), use a highly diffused shadow: `box-shadow: 0 10px 40px rgba(26, 28, 28, 0.06)`. The color is a tinted version of `on-surface`, not a neutral grey.
- **The "Ghost Border" Fallback:** If a container needs definition against a similar background (e.g., an input field), use the `outline-variant` token at **20% opacity**. Never use 100% opacity for borders.
- **Glassmorphism:** Use for persistent elements like the header. A blurred background allows the rich colors of the fashion photography to bleed through, integrating the UI into the imagery.

---

## 5. Components: The Bespoke UI

### Buttons
- **Primary:** Gradient fill (Gold), `label-md` (All Caps), `4px` (DEFAULT) rounded corners. Padding: `spacing-3` vertical, `spacing-8` horizontal.
- **Secondary:** "Ghost" style. No fill, `outline-variant` (at 20% opacity) border.
- **Tertiary:** Text only, underlined with a 1px `primary-container` stroke that expands on hover.

### Input Fields
Avoid the "boxed" look. Use a `surface-container-low` background with a `4px` radius. The label should use `label-sm` and sit clearly above the input area. Focus states are indicated by the Soft Gold (`primary`) appearing as a subtle 1px bottom-border only.

### Cards & Lists
**Forbid the use of divider lines.** 
- **Product Cards:** Use `surface-container-lowest` for the card body. Use `spacing-6` between cards.
- **Lists:** Use vertical white space (`spacing-4`) to separate list items. Use a subtle background change on `:hover`.

### Additional Component: The "Editorial Reveal"
For fashion Lookbooks, use an **Asymmetric Image Mask**. Instead of standard 4:3 ratios, use tall, narrow crops (e.g., 2:3) with images slightly overlapping each other. Use the `spacing-10` scale to create large, intentional gaps for floating text (`display-md`).

---

## 6. Do’s and Don’ts

### Do
- **Do** prioritize high-resolution photography; the UI is the frame, the photo is the art.
- **Do** use `DEFAULT` (4px) rounded corners consistently to maintain a "tailored" feel.
- **Do** use intentional asymmetry—place text on the left and images on the right with different vertical alignments.
- **Do** ensure `on-surface` text meets AA accessibility standards against all surface containers.

### Don't
- **Don't** use standard "Grey" (#808080). Use the `secondary` or `outline` tokens which are tonally balanced for this system.
- **Don't** use 100% opaque black borders. They are too aggressive for a premium brand.
- **Don't** crowd the interface. If you think a section needs more content, it probably needs more white space instead.
- **Don't** use standard animations. Use slow, ease-in-out transitions (300ms+) to mimic the deliberate pace of luxury.