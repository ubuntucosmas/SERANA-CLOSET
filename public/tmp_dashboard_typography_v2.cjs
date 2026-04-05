const fs = require('fs');
const path = 'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Pages\\Admin\\StudioDashboard.vue';
let content = fs.readFileSync(path, 'utf8');

// 1. STATS BENTO: 60px -> 40px (text-6xl -> text-4xl)
content = content.replace(/\btext-6xl\b/g, 'TEMP_STATS_4XL');

// 2. HEADERS: 36px -> 24px (text-4xl -> text-2xl)
content = content.replace(/\btext-4xl\b/g, 'text-2xl');

// Restore Stats to 4xl
content = content.replace(/TEMP_STATS_4XL/g, 'text-4xl');

// 3. INTERNAL HEADERS & LOGO: text-xl -> text-base (20px -> 16px)
content = content.replace(/\btext-xl\b/g, 'text-base');
content = content.replace(/\btext-lg\b/g, 'text-[14px]');

// 4. FEED CLIENT NAMES: text-[24px] -> text-base (16px)
content = content.replace(/\btext-\[24px\]\b/g, 'text-base');

// 5. NAV / BUTTONS: text-[13px] -> text-[11px]
content = content.replace(/\btext-\[13px\]\b/g, 'text-[11px]');

// 6. METADATA LABELS: text-[12px] -> text-[10px]
content = content.replace(/\btext-\[12px\]\b/g, 'text-[10px]');

// 7. MODALS & SUBHEADERS (text-3xl -> text-xl)
content = content.replace(/\btext-3xl\b/g, 'text-xl');

// 8. CLEANING UP: Ensure the remaining uppercase is tracked
content = content.replace(/tracking-\[0\.20?em\]/g, 'tracking-[0.25em]');
content = content.replace(/tracking-widest/g, 'tracking-[0.25em]');

fs.writeFileSync(path, content, 'utf8');
console.log('Studio Dashboard typography refined: Fixed double-shrinkage and corrected regex boundaries.');
