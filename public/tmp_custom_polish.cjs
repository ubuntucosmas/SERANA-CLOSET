const fs = require('fs');
const path = 'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Pages\\CustomOrder.vue';
let content = fs.readFileSync(path, 'utf8');

// 1. Typography Weights
content = content.replace(/\bfont-black\b/g, 'font-medium');
content = content.replace(/\bfont-bold\b/g, 'font-medium');
content = content.replace(/\bfont-semibold\b/g, 'font-medium');

// 2. Corner Radii Hardening
content = content.replace(/\brounded-3xl\b/g, 'rounded-sm');
content = content.replace(/\brounded-2xl\b/g, 'rounded-sm');
content = content.replace(/\brounded-xl\b/g, 'rounded-sm');
content = content.replace(/\brounded-lg\b/g, 'rounded-sm');

// 3. Opaque Background Deletion
content = content.replace(/\bbg-surface-container\/50\b/g, 'bg-transparent');
content = content.replace(/\bbg-surface-container\b/g, 'bg-transparent');
content = content.replace(/\bbg-background\b/g, 'bg-surface/10'); // Convert thick wrappers

// 4. Remove heavy neon glowing drop-shadows on buttons/boxes 
content = content.replace(/shadow-\[.*?\]/g, '');

fs.writeFileSync(path, content, 'utf8');
console.log('CustomOrder.vue perfectly unified to transparent wireframe clarity aesthetic.');
