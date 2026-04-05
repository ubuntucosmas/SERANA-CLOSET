const fs = require('fs');
const path1 = 'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Pages\\Shop.vue';
const path2 = 'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Components\\ProductCard.vue';

function applyParity(content) {
    // Fonts
    content = content.replace(/\bfont-black\b/g, 'font-medium');
    content = content.replace(/\bfont-bold\b/g, 'font-medium');

    // Corners
    content = content.replace(/rounded-xl/g, 'rounded-sm');
    content = content.replace(/rounded-2xl/g, 'rounded-sm');
    content = content.replace(/rounded-lg/g, 'rounded-sm');

    // Colors & Overlays
    content = content.replace(/bg-surface-container\/30/g, 'bg-white/5');
    content = content.replace(/bg-surface-container\/95/g, 'bg-black/60');
    content = content.replace(/bg-surface-container/g, 'bg-transparent');

    // Text cleanup
    content = content.replace(/text-on-surface-variant/g, 'text-white/40');
    content = content.replace(/text-on-surface/g, 'text-white');
    content = content.replace(/text-background/g, 'text-black');
    content = content.replace(/bg-background/g, 'bg-surface');

    // Clean up bloated dark: utility classes
    content = content.replace(/dark:text-white/g, 'text-white');
    content = content.replace(/dark:border-white\/5 border-black\/5/g, 'border-white/10');
    content = content.replace(/dark:border-white\/10 border-black\/10/g, 'border-white/10');
    content = content.replace(/dark:bg-background\/80 bg-surface\/80/g, 'bg-black/60');
    content = content.replace(/dark:bg-gradient-to-t dark:from-black\/80 dark:to-transparent bg-gradient-to-t from-white\/80 to-transparent/g, 'bg-gradient-to-t from-black via-black/80 to-transparent');
    content = content.replace(/dark:hover:text-white hover:text-white\/40/g, 'hover:text-white');
    content = content.replace(/dark:hover:text-white hover:text-white/g, 'hover:text-white');

    return content;
}

let shopContent = fs.readFileSync(path1, 'utf8');
fs.writeFileSync(path1, applyParity(shopContent), 'utf8');

let cardContent = fs.readFileSync(path2, 'utf8');

// There is a specific CTA in ProductCard that uses a chunky gradient background.
// "bg-gradient-to-br from-primary to-primary-container text-black py-5... shadow-[0_0_25px_rgba(57, 255, 20,0.4)]"
// I will strip it down to a neat wireframe button for "Add to Bag": "glass-card border border-primary text-primary py-5 hover:bg-primary hover:text-black shadow-none"
cardContent = cardContent.replace(/bg-gradient-to-br from-primary to-primary-container text-black /g, 'bg-transparent border border-primary/50 text-primary hover:bg-primary hover:text-black ');

fs.writeFileSync(path2, applyParity(cardContent), 'utf8');

console.log('Shop and ProductCard visually aligned with medium fonts and transparent clarity rules.');
