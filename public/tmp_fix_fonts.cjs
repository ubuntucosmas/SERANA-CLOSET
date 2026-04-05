const fs = require('fs');
const path = 'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Pages\\Admin\\StudioDashboard.vue';
let content = fs.readFileSync(path, 'utf8');

// 1. Strip styling classes
// Keep `tracking-[...]` classes as they still add elegance to sentence case, but remove italic and uppercase.
content = content.replace(/\bitalic\b/g, '');
content = content.replace(/\buppercase\b/g, '');

// 2. Dictionary of string replacements for labels and constants
const dict = {
    'NEON_ARTISAN': 'Neon Artisan',
    'ATELIER_SPEC': 'Atelier Spec',
    'V.01_PRECISION': 'v.01 Precision',
    'ADD_PIECE_ \\[ \\+ \\]': 'Add Piece',
    'ADD_SHOWCASE_ \\[ \\+ \\]': 'Add Showcase',
    'Edit_Details_': 'Edit Details',
    'Add_Note_': 'Add Note',
    'SAVE_PRODUCT_': 'Save Product',
    'SAVING...': 'Saving...',
    'PUBLISH_SHOWCASE_': 'Publish Showcase',
    'UPLOADING...': 'Uploading...',
    'Awaiting_Asset_Upload': 'Awaiting Asset Upload',
    'Awaiting_Capture_Asset': 'Awaiting Capture Asset',
    'Load Master File_': 'Load Master File',
    'New_Showcase_': 'New Showcase',
    'Serana_Circle': 'Serana Circle',
    'Client_Identity_': 'Client Identity',
    'Garment_Concept_': 'Garment Concept',
    'Testimonial_': 'Testimonial',
    'Add_Product_': 'Add Product',
    'Catalog_Addition': 'Catalog Addition',
    'Drop_Scarcity_Limit': 'Drop Scarcity Limit',
    'Price_KSH_': 'Price (KSh)',
    'Description_': 'Description',
    'Name_': 'Name',
    'Collection_Group_': 'Collection Group',
    'Custom_Order_': 'Custom Order',
    'ACCESS_GRANTED': 'Access Granted',
    'Security_Log: REF': 'Security Log: Ref',
    'Circle_Exhibit:': 'Circle Exhibit:',
    'Active_Detail': 'Active Detail',
    'SPEC_LABEL:': 'Spec Label:',
    'Measurements_\\(CM\\)': 'Measurements (cm)',
    'Fabric_Selection': 'Fabric Selection',
    'Design Notes_': 'Design Notes',
    'Sort_By': 'Sort By',
    'FREE_': 'Free',
    'HAND-MADE_': 'Hand-made',
    'READY_TO_WEAR': 'Ready to Wear',
    'ACTIVE': 'Active',
    'COMPLETED': 'Completed',
    'PENDING': 'Pending'
};

for (const [key, value] of Object.entries(dict)) {
    // using regex g to replace everywhere
    const regex = new RegExp(key, 'g');
    content = content.replace(regex, value);
}

// Clean up weird double spaces resulting from class removals
content = content.replace(/  +/g, ' ');

fs.writeFileSync(path, content, 'utf8');
console.log('Removed italics, trailing underscores, and set sentence case globally.');
