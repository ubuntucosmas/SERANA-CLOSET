const fs = require('fs');
const path = 'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Pages\\Admin\\StudioDashboard.vue';
let content = fs.readFileSync(path, 'utf8');

// Colors
content = content.replace(/text-\[\#08CB00\]/g, 'text-primary');
content = content.replace(/bg-\[\#08CB00\]/g, 'bg-primary');
content = content.replace(/border-\[\#08CB00\]/g, 'border-primary');
content = content.replace(/border-l-\[\#08CB00\]/g, 'border-l-primary');
content = content.replace(/border-b-\[\#08CB00\]/g, 'border-b-primary');
content = content.replace(/ring-\[\#08CB00\]/g, 'ring-primary');

// Backgrounds
content = content.replace(/bg-\[\#0e0e0e\]/g, 'bg-surface');
content = content.replace(/bg-\[\#141414\]/g, 'bg-surface-container');
content = content.replace(/bg-\[\#1a1a1a\]/g, 'bg-surface-container-high');

// Fonts
content = content.replace(/font-serif/g, 'font-headline');
content = content.replace(/font-sans/g, 'font-label');

// Hover / Focus / Group variants for primary
content = content.replace(/hover:text-\[\#08CB00\]/g, 'hover:text-primary');
content = content.replace(/hover:bg-\[\#08CB00\]/g, 'hover:bg-primary');
content = content.replace(/hover:border-\[\#08CB00\]/g, 'hover:border-primary');
content = content.replace(/group-hover:text-\[\#08CB00\]/g, 'group-hover:text-primary');
content = content.replace(/focus:border-\[\#08CB00\]/g, 'focus:border-primary');

// Shadow neon adjustments
content = content.replace(/rgba\(202,253,0,0\.\d+\)/g, 'rgba(204, 255, 0, 0.4)');
content = content.replace(/rgba\(8,203,0,0\.\d+\)/g, 'rgba(204, 255, 0, 0.4)');

// Style block replacement
content = content.replace(/<style scoped>[\s\S]*?<\/style>/, `<style scoped>\n::-webkit-scrollbar { width: 4px; }\n::-webkit-scrollbar-track { background: #000000; }\n::-webkit-scrollbar-thumb { background: #333333; border-radius: 2px; }\n::-webkit-scrollbar-thumb:hover { background: #CCFF00; }\n</style>`);

fs.writeFileSync(path, content, 'utf8');
console.log('Refactored Dashboard classes successfully.');
