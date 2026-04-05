const fs = require('fs');
const path = 'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Pages\\About.vue';
let content = fs.readFileSync(path, 'utf8');

// Typography
content = content.replace(/\bfont-black\b/g, 'font-medium');
content = content.replace(/\bfont-bold\b/g, 'font-medium');
content = content.replace(/\bitalic\b/g, '');

// Corner Geometry
content = content.replace(/rounded-xl/g, 'rounded-sm');
content = content.replace(/rounded-2xl/g, 'rounded-sm');

// Solid Background Removals & Blur logic
content = content.replace(/bg-surface-container\/50/g, 'bg-black/40');
content = content.replace(/bg-surface-container\/30/g, 'bg-transparent');
content = content.replace(/bg-surface-container\/20/g, 'bg-transparent');
content = content.replace(/bg-surface-container/g, 'bg-transparent');

// Color mapping (Dark Neon Artisan exclusively)
content = content.replace(/text-on-surface-variant/g, 'text-white/40');
content = content.replace(/text-on-surface/g, 'text-white');
content = content.replace(/dark:text-white/g, 'text-white');
content = content.replace(/dark:border-white\/5 border-black\/5/g, 'border-white/10');
content = content.replace(/dark:border-white\/20 border-black\/20/g, 'border-white/20');
content = content.replace(/dark:text-on-surface/g, 'text-white');
content = content.replace(/dark:hover:text-primary/g, 'hover:text-primary');

// Button Refinement
// Change solid primary buttons to transparent wireframes
content = content.replace(/bg-primary text-background(.*?)hover:shadow-\[0_0_30px_rgba\(57, 255, 20,0\.4\)\].*?transition-all/g, 'bg-transparent border border-primary/50 text-primary hover:bg-primary hover:text-black transition-all');
content = content.replace(/hover:shadow-\[0_0_30px_rgba\(57, 255, 20,0\.4\)\]/g, '');

// Removing heavy uppercase & trailing underscores that break the subtle mood
content = content.replace(/The Future<\/span> of Nairobi Fashion_/g, 'The Future</span> of Nairobi Fashion');
content = content.replace(/Our <span class="text-white\/20">Story_<\/span>/g, 'Our <span class="text-white/20">Story</span>');

fs.writeFileSync(path, content, 'utf8');
console.log('About page polished with transparent wireframes and medium fonts.');
