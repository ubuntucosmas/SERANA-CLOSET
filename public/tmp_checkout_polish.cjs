const fs = require('fs');
const path = 'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Pages\\Checkout.vue';
let content = fs.readFileSync(path, 'utf8');

// Strip bulky rounding to match square aesthetic of CartDrawer
content = content.replace(/rounded-xl/g, 'rounded-sm');
content = content.replace(/rounded-2xl/g, 'rounded-sm');

// Replace solid container fills with clean transparent/surface
content = content.replace(/bg-surface-container/g, 'bg-transparent');

// Enhance input field clarity
content = content.replace(/focus:border-primary/g, 'focus:border-primary focus:ring-1 focus:ring-primary focus:bg-primary/5');

// Clean up dark: classes that add bloat, pushing towards the unified dark Neon Artisan aesthetic
content = content.replace(/dark:border-white\/10 border-black\/10/g, 'border-white/10 hover:border-primary/50');
content = content.replace(/dark:text-white text-on-surface/g, 'text-white');
content = content.replace(/bg-on-surface text-surface/g, 'bg-primary text-black');
content = content.replace(/dark:bg-white dark:text-background/g, 'bg-primary text-black');
content = content.replace(/text-on-surface-variant/g, 'text-white/40');
content = content.replace(/text-on-surface/g, 'text-white');
content = content.replace(/border-black\/5/g, 'border-white/10');
content = content.replace(/dark:border-white\/5/g, 'border-white/10');

// Typography alignments
content = content.replace(/font-body/g, 'font-label'); // Label feels sharper for forms
content = content.replace(/text-base/g, 'text-sm'); // Tighter text

// Specific fixes
content = content.replace(/bg-\[\#0e0e0e\]/g, 'bg-surface');
content = content.replace(/bg-background/g, 'bg-surface');
content = content.replace(/bg-primary\/5/g, 'bg-transparent border border-primary/20'); // remove block background from Precision Sizing

fs.writeFileSync(path, content, 'utf8');
console.log('Checkout visual parity aligned with Cart Drawer rules.');
