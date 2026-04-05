const fs = require('fs');

const files = [
    'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Pages\\Home.vue',
    'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Components\\HeroSection.vue',
    'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Components\\CategoryGrid.vue',
    'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Components\\CraftsmanshipBlock.vue'
];

function alignClarity(content) {
    // 1. Typography Weights
    content = content.replace(/\bfont-black\b/g, 'font-medium');
    content = content.replace(/\bfont-bold\b/g, 'font-medium');

    // 2. Eradicate Italics (User wants clear precision style)
    content = content.replace(/\bitalic\b/g, '');

    // 3. Sharpen Corners
    content = content.replace(/\brounded-3xl\b/g, 'rounded-sm');
    content = content.replace(/\brounded-2xl\b/g, 'rounded-sm');
    content = content.replace(/\brounded-xl\b/g, 'rounded-sm');
    content = content.replace(/\brounded-lg\b/g, 'rounded-sm');

    // 4. Eviscerate Heavy Background Containers
    content = content.replace(/\bbg-surface-container\/20\b/g, 'bg-transparent');
    content = content.replace(/\bbg-surface-container\b/g, 'bg-transparent border border-white/5');
    // For CategoryGrid specifically which uses bg-background inside articles
    content = content.replace(/\bbg-background border border-white\/5\b/g, 'bg-transparent border border-white/5');

    // 5. Unify High Contrast Text
    content = content.replace(/\btext-on-surface-variant\b/g, 'text-white/40');
    content = content.replace(/\btext-on-surface\b/g, 'text-white');
    content = content.replace(/\bdark:text-white\b/g, 'text-white');
    content = content.replace(/\bdark:hover:text-white\b/g, 'hover:text-white');

    return content;
}

files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    content = alignClarity(content);
    fs.writeFileSync(file, content, 'utf8');
});

console.log('Home architecture visually synchronized with wireframe constraints.');
