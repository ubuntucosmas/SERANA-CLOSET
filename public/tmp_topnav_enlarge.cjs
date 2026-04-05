const fs = require('fs');
const path = 'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Components\\TopNavBar.vue';
let content = fs.readFileSync(path, 'utf8');

// 1. Enlarge Nav Links in <style scoped>
content = content.replace(/text-\[14px\]/g, 'text-[16px]'); // .nav-link
content = content.replace(/text-\[11px\]/g, 'text-[13px]'); // .dropdown-link
content = content.replace(/text-xs/g, 'text-sm'); // .mobile-link

// 2. Enlarge Logo
content = content.replace(/\[&_h1\]:text-2xl/g, '[&_h1]:text-3xl');

// 3. Enlarge Action Icons
content = content.replace(/!text-\[20px\]/g, '!text-[24px]'); // chat, dashboard
content = content.replace(/!text-\[22px\]/g, '!text-[26px]'); // shopping-bag, profile
content = content.replace(/!text-\[24px\]/g, '!text-[28px]'); // mobile menu

fs.writeFileSync(path, content, 'utf8');
console.log('Top Nav elements successfully enlarged.');
