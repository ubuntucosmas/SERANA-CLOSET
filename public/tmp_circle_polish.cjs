const fs = require('fs');
const path = 'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Pages\\Circle.vue';
let content = fs.readFileSync(path, 'utf8');

// Typography
content = content.replace(/\bfont-black\b/g, 'font-medium');
content = content.replace(/\bfont-bold\b/g, 'font-medium');

// Edge Sharpening
content = content.replace(/\brounded-\[40px\]\b/g, 'rounded-sm');
content = content.replace(/\brounded-3xl\b/g, 'rounded-sm');
content = content.replace(/\brounded-2xl\b/g, 'rounded-sm');

// Solid Container Erasure
content = content.replace(/\bbg-surface-container\/30\b/g, 'bg-transparent');
content = content.replace(/\bbg-surface-container\b/g, 'bg-transparent');
content = content.replace(/bg-background/g, 'bg-surface/5'); // Keep a subtle tint if needed, but 'bg-transparent' works too if layout requires, wait—main wrapper uses bg-background. bg-surface/5 is better.

// Clean up text properties
content = content.replace(/\btext-on-surface-variant\b/g, 'text-white/40');
content = content.replace(/\btext-on-surface\b/g, 'text-white');

// Button refinement
content = content.replace(/bg-white text-background px-10 py-5 rounded-sm font-headline text-xs tracking-\[0\.2em\] font-medium hover:bg-primary transition-colors shadow-2xl/g, 'bg-white/10 border border-white/20 text-white px-10 py-5 rounded-sm font-headline text-xs tracking-[0.2em] font-medium hover:bg-white hover:text-black transition-all shadow-none');
content = content.replace(/bg-primary text-background px-10 py-5 rounded-sm font-headline text-xs tracking-\[0\.2em\] font-medium hover:scale-105 transition-all shadow-\[0_0_20px_rgba\(57, 255, 20,0\.3\)\]/g, 'bg-transparent border border-primary/50 text-primary px-10 py-5 rounded-sm font-headline text-xs tracking-[0.2em] font-medium hover:bg-primary hover:text-black transition-all');

// Card drop shadows -> stripped
content = content.replace(/shadow-2xl/g, '');

fs.writeFileSync(path, content, 'utf8');
console.log('Community page (Circle.vue) fully synchronized with transparent wireframe clarity constraints.');
