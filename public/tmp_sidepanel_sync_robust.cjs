const fs = require('fs');

const targetPath = 'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Pages\\Admin\\StudioDashboard.vue';
const sourcePath = 'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\public\\new_side_panel.vue.txt';

let content = fs.readFileSync(targetPath, 'utf8');
const newAside = fs.readFileSync(sourcePath, 'utf8');

// Regex to replace the entire <aside>...</aside> block
content = content.replace(/<!-- Right Column: Active Spec Panel [^>]* -->\s*<aside[^>]*>[\s\S]*?<\/aside>/i, newAside);

fs.writeFileSync(targetPath, content, 'utf8');
console.log('Studio Dashboard Side Panel successfully refactored via source file sync.');
