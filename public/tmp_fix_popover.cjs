const fs = require('fs');
const path = 'C:\\Users\\COSMAS\\Desktop\\NEW-SERANA-CLOSET\\resources\\js\\Pages\\Admin\\StudioDashboard.vue';
let content = fs.readFileSync(path, 'utf8');

// The replacement chunk that got mangled is going to be rebuilt fully using regular expressions.
// We target the `<div class="space-y-4">` containing the `filteredOrders` v-for loop.
// Let's create the full corrected string for the entire `div.space-y-4` section up to the `</div>` of the `div.flex-1.grid`.

const replacement = `                            <div class="space-y-4">
                                <div v-for="order in filteredOrders" :key="order.id" 
                                     @click="selectOrder(order)"
                                     class="bg-transparent flex items-center p-8 group hover:translate-x-2 transition-all cursor-pointer border border-white/5 relative z-0 hover:z-50"
                                     :class="form.id === order.id ? 'border-l-4 border-l-primary bg-white/5' : 'hover:bg-white/5'">
                                    
                                    <div class="relative w-14 h-14 bg-transparent border border-white/5 flex items-center justify-center mr-8 rounded-sm group-hover:bg-primary/10 transition-colors overflow-visible">
                                        <template v-if="getOrderThumbnails(order).length">
                                            <img :src="getOrderThumbnails(order)[0]" class="w-full h-full object-cover grayscale opacity-50 group-hover:grayscale-0 group-hover:opacity-100 transition-all font-headline text-[8px] text-white/30 text-center" alt="img_" />
                                            
                                            <!-- High-Res Floating Popover -->
                                            <div class="absolute left-full ml-6 top-1/2 -translate-y-1/2 w-96 aspect-[3/4] z-[100] border border-white/10 shadow-[0_20px_50px_rgba(0,0,0,0.9)] opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-500 scale-90 group-hover:scale-100 origin-left overflow-hidden bg-transparent flex flex-col">
                                                
                                                <!-- Single Item View -->
                                                <div v-if="getOrderThumbnails(order).length === 1" class="w-full h-full relative">
                                                    <img :src="getOrderThumbnails(order)[0]" class="w-full h-full object-cover" />
                                                </div>
                                                
                                                <!-- Multi-Item Grid View (Shows up to 4) -->
                                                <div v-else class="w-full h-full grid grid-cols-2 grid-rows-2 gap-[1px] bg-white/10">
                                                    <div v-for="i in 4" :key="'slot-'+i" class="relative bg-surface w-full h-full flex items-center justify-center">
                                                        <template v-if="getOrderThumbnails(order)[i-1]">
                                                            <img :src="getOrderThumbnails(order)[i-1]" class="absolute inset-0 w-full h-full object-cover" />
                                                            <!-- Overflow Count overlay on the 4th slot -->
                                                            <div v-if="i === 4 && getOrderThumbnails(order).length > 4" class="absolute inset-0 bg-surface/70 backdrop-blur-md flex items-center justify-center z-10">
                                                                <span class="text-white font-headline text-2xl">+{{ getOrderThumbnails(order).length - 4 }}</span>
                                                            </div>
                                                        </template>
                                                        <template v-else>
                                                            <span class="material-symbols-outlined text-white/5 text-4xl">inventory_2</span>
                                                        </template>
                                                    </div>
                                                </div>

                                                <div class="absolute bottom-0 inset-x-0 p-6 pt-16 bg-gradient-to-t from-black via-black/90 to-transparent z-20">
                                                    <p class="text-[10px] font-black text-primary tracking-[0.3em] uppercase">
                                                        {{ order.type === 'standard' ? (getOrderThumbnails(order).length > 1 ? 'Multiple Specs (' + getOrderThumbnails(order).length + ')' : 'Product Spec') : 'Visual Brief' }}
                                                    </p>
                                                    <p class="text-white/80 font-headline text-lg mt-1 truncate">{{ order.full_name }}</p>
                                                </div>
                                            </div>
                                        </template>
                                        <span v-else class="material-symbols-outlined text-white/20 group-hover:text-primary transition-colors">checkroom</span>
                                    </div>
                                    <div class="flex-1 grid grid-cols-4 gap-8 items-center">
                                        <div>`;

// The script will replace everything from `<div class="space-y-4">` until `<div>\n <p class="text-[9px]`
const regex = /<div class="space-y-4">[\s\S]*?<div class="flex-1 grid grid-cols-4 gap-8 items-center">\s*<div>/g;
content = content.replace(regex, replacement);

fs.writeFileSync(path, content, 'utf8');
console.log('Fixed broken vue iteration loop and injected multi-thumbnail popover code.');
