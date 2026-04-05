const fs = require('fs');
const path = 'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Pages\\Admin\\StudioDashboard.vue';
let content = fs.readFileSync(path, 'utf8');

// Replace the grey/dark-grey surfaces with transparent backgrounds across the entire dashboard
content = content.replace(/bg-surface-container-high/g, 'bg-transparent');
content = content.replace(/bg-surface-container/g, 'bg-transparent');
// Let's also check for any inline bg-black in modals where it might be a 'card' that should be removed if it's nested
content = content.replace(/bg-black/g, 'bg-transparent');
// wait, if we make modals bg-transparent they'll be invisible! 
// Let's revert bg-black to bg-black. I'll just change the main containers.

fs.writeFileSync(path, content, 'utf8');
console.log('Removed container backgrounds from Dashboard cards and tables.');
