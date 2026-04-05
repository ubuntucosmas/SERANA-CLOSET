const fs = require('fs');

function syncFile(filePath, replacements) {
    let content = fs.readFileSync(filePath, 'utf8');
    replacements.forEach(r => {
        content = content.replace(r.search, r.replace);
    });
    fs.writeFileSync(filePath, content, 'utf8');
}

// 1. Studio Dashboard Sync
const dashboardPath = 'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Pages\\Admin\\StudioDashboard.vue';
syncFile(dashboardPath, [
    // Client Name in Feed (if still text-24px or text-2xl from previous passes)
    { search: /font-headline text-\[24px\]/g, replace: 'font-headline text-lg' },
    // Feed Labels (Client, Garment, Deadline)
    { search: /text-\[12px\] dark:text-white text-on-surface\/30 tracking-\[0\.25em\] mb-2 font-black/g, replace: 'text-[10px] dark:text-white text-on-surface/30 tracking-[0.2em] mb-2 font-black uppercase' },
    // Status Badge & simulated dates
    { search: /text-\[11px\] font-black tracking-\[0\.25em\] text-primary/g, replace: 'text-[10px] font-black tracking-[0.2em] text-primary uppercase' },
    { search: /text-\[12px\] font-black tracking-\[0\.25em\] border/g, replace: 'text-[10px] font-black tracking-[0.2em] border' }
]);

// 2. Custom Order Sync
const customOrderPath = 'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Pages\\CustomOrder.vue';
syncFile(customOrderPath, [
    // Terminal Headers
    { search: /font-headline text-\[9px\] tracking-\[0\.4em\]/g, replace: 'font-headline text-[10px] tracking-[0.2em]' },
    { search: /font-headline text-\[8px\] tracking-\[0\.4em\]/g, replace: 'font-headline text-[10px] tracking-[0.2em]' },
    // Step Headers
    { search: /text-\[11px\] tracking-\[0\.3em\] text-primary font-medium/g, replace: 'text-[10px] tracking-[0.2em] text-primary font-medium' },
    { search: /text-\[8px\] font-headline tracking-\[0\.4em\] text-primary\/60/g, replace: 'text-[10px] font-headline tracking-[0.2em] text-primary/60' },
    { search: /text-sm font-headline font-medium dark:text-white text-on-surface tracking-widest uppercase/g, replace: 'text-2xl font-headline font-medium dark:text-white text-on-surface tracking-widest uppercase' },
    // Reference Label
    { search: /text-\[9px\] font-headline tracking-widest uppercase text-primary\/60/g, replace: 'text-[10px] font-headline tracking-[0.2em] uppercase text-primary/60' }
]);

console.log('Global Typographic Sync Complete: Dashboard and Custom Order pages aligned with CartDrawer tokens.');
