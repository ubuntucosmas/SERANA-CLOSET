const fs = require('fs');

const shopPath = 'resources/js/Pages/Shop.vue';
let shopText = fs.readFileSync(shopPath, 'utf8');

// Replace "All Collections" with "All collections"
shopText = shopText.replace(/All Collections/g, 'All collections');

// Replace {{ category.name }} with a sentence case formatter
shopText = shopText.replace(/\{\{\s*category\.name\s*\}\}/g, "{{ category.name.charAt(0).toUpperCase() + category.name.slice(1).toLowerCase() }}");

// Replace {{ activeCategory ? activeCategory.name : 'All Pieces' }} with sentence case
shopText = shopText.replace(/\{\{\s*activeCategory\s*\?\s*activeCategory\.name\s*:\s*'All Pieces'\s*\}\}/g, "{{ activeCategory ? activeCategory.name.charAt(0).toUpperCase() + activeCategory.name.slice(1).toLowerCase() : 'All pieces' }}");

// Replace {{ activeCategory\?\.name || 'All Pieces' }}
shopText = shopText.replace(/\{\{\s*activeCategory\?\.name\s*\|\|\s*'All Pieces'\s*\}\}/g, "{{ activeCategory?.name ? activeCategory.name.charAt(0).toUpperCase() + activeCategory.name.slice(1).toLowerCase() : 'All pieces' }}");

fs.writeFileSync(shopPath, shopText);

const carouselPath = 'resources/js/Components/InfiniteHorizontalCarousel.vue';
let carouselText = fs.readFileSync(carouselPath, 'utf8');

// Replace {{ categoryName }} with formatter
carouselText = carouselText.replace(/\{\{\s*categoryName\s*\}\}/g, "{{ categoryName.charAt(0).toUpperCase() + categoryName.slice(1).toLowerCase() }}");

fs.writeFileSync(carouselPath, carouselText);
