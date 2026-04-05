const fs = require('fs');
const path = 'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Components\\TopNavBar.vue';
let content = fs.readFileSync(path, 'utf8');

// 1. Fix Lints Context
// Adding lang="postcss" fixes the `@apply` lint errors in Vue 3 SFCs natively.
content = content.replace(/<style scoped>/, '<style scoped lang="postcss">');

// 2. Reduce font weights globally across the component to 'MEDIUM FONT' as requested.
// We keep the headline/label font families but reduce the heaviness.
content = content.replace(/\bfont-black\b/g, 'font-medium');
content = content.replace(/\bfont-bold\b/g, 'font-medium');
// There's a specific inline font-black on line 113 for the Log in button, 
// and line 107 for the cart total badge (`font-black` there is okay because it's a bubble, but replacing it globally to `font-medium` handles the nav links perfectly).
// Mobile links had `font-black` dynamically injected via `:class="{ 'text-primary... font-black': isActive(...) }"`, replaced globally to `font-medium`.

// 3. Transparent aesthetics ("CLEAR") & wireframe alignment
// Main bar background
content = content.replace(/bg-background\/95/g, 'bg-surface/60');
// Dropdown menus - previously bg-surface-container/95 and heavy shadow. Make them transparent wireframes with a heavy blur.
content = content.replace(/bg-surface-container\/95/g, 'bg-black/40');
content = content.replace(/shadow-\[0_40px_100px_rgba\(0,0,0,0\.8\)\]/g, 'shadow-2xl');

// Image placeholders inside dropdowns - swap backgrounds to transparent
content = content.replace(/bg-background rounded-xl/g, 'bg-transparent border border-white/5 rounded-sm p-1');

// Text color tweaks
content = content.replace(/text-on-surface-variant/g, 'text-white/40');
content = content.replace(/text-on-surface/g, 'text-white');
content = content.replace(/text-background/g, 'text-black');

fs.writeFileSync(path, content, 'utf8');
console.log('Top Nav has been aligned to medium fonts and clear transparent wireframes.');
