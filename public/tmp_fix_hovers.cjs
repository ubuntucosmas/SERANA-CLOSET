const fs = require('fs');
const path = 'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Pages\\Admin\\StudioDashboard.vue';
let content = fs.readFileSync(path, 'utf8');

// Fix broken hovers resulting from aggressive background purging
content = content.replace(/hover:bg-transparent/g, 'hover:bg-white/5');

// Fix broken active/focused border selection states inside the feed
content = content.replace(/'border-l-4 border-l-primary bg-transparent'/g, "'border-l-4 border-l-primary bg-white/5'");

// In the Stats grid, the hover state got stripped to hover:bg-transparent. The grid item was: h-36 group hover:bg-transparent
content = content.replace(/hover:bg-transparent transition-all/g, 'hover:bg-white/5 transition-all');

// Modals: I'll ensure any modal overlay or backdrop didn't get scrubbed too hard, though my regex was specific to surface-container.

fs.writeFileSync(path, content, 'utf8');
console.log('Restored hover background interactions for the minimalist transparent wireframes.');
