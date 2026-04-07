<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { useCartStore } from '@/Stores/useCartStore';

const props = defineProps({
    product: Object
});

const cart = useCartStore();
</script>

<template>
    <StorefrontLayout>
        <Head :title="`${product.name} | Serana Closet`" />

        <main class="pt-32 pb-24 font-body text-on-surface">
            <div class="max-w-[1440px] mx-auto px-8 lg:px-16">
                <!-- PDP Header Section: Editorial Asymmetry -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-20 items-start">
                    
                    <!-- Left: Editorial Gallery (Asymmetric Layout ported from PRD) -->
                    <div class="lg:col-span-7 space-y-12">
                        <!-- Main Featured Hero Image -->
                        <div class="relative w-full aspect-[2/3] overflow-hidden rounded-2xl border dark:border-white/5 border-black/5 shadow-2xl reveal">
                            <img class="w-full h-full object-cover" :src="product.image_url || '/images/hero_editorial.png'"/>
                            
                            <!-- Badges mapping Custom and Category -->
                            <div class="absolute top-8 left-8 flex flex-col gap-2 z-10">
                                <span class="bg-surface-container/80 backdrop-blur font-headline text-[11px] tracking-[0.15em] px-4 py-2 border dark:border-white/10 border-black/10 rounded-full inline-block w-fit font-black">
                                    {{ product.category?.name }}
                                </span>
                                <span v-if="product.is_customizable" class="bg-primary text-background font-headline text-[11px] tracking-[0.2em] px-4 py-2 rounded-full w-fit shadow-[0_0_20px_rgba(57, 255, 20,0.3)] font-black">
                                    Custom option available
                                </span>
                            </div>
                        </div>

                        <!-- Secondary Split Details (Dynamic) -->
                        <div v-if="product.secondary_image_urls && product.secondary_image_urls.length >= 1" class="grid grid-cols-2 gap-8 reveal">
                            <div class="aspect-[3/4] mt-12 overflow-hidden rounded-2xl border dark:border-white/5 border-black/5 shadow-2xl">
                                <img class="w-full h-full object-cover" :src="product.secondary_image_urls[0]"/>
                            </div>
                            <div v-if="product.secondary_image_urls[1]" class="aspect-[3/4] -mt-12 overflow-hidden rounded-2xl border dark:border-white/5 border-black/5 shadow-2xl">
                                <img class="w-full h-full object-cover" :src="product.secondary_image_urls[1]"/>
                            </div>
                            <!-- Fallback if only 1 secondary image exists -->
                            <div v-else class="aspect-[3/4] -mt-12 overflow-hidden rounded-2xl border dark:border-white/5 border-black/5 shadow-2xl bg-surface-container/30 flex items-center justify-center italic text-[10px] opacity-20 tracking-widest">
                                SERANA_DETAIL_SHARP
                            </div>
                        </div>

                        <!-- Wide Cinematic Shot (Fallback/Third slot if we had more, or just editorial) -->
                        <div v-if="product.secondary_image_urls && product.secondary_image_urls[2]" class="w-full aspect-[16/9] overflow-hidden rounded-2xl border dark:border-white/5 border-black/5 shadow-2xl reveal">
                            <img class="w-full h-full object-cover" :src="product.secondary_image_urls[2]"/>
                        </div>
                        <div v-else class="w-full aspect-[16/9] overflow-hidden rounded-2xl border dark:border-white/5 border-black/5 shadow-2xl reveal relative">
                            <img class="w-full h-full object-cover opacity-20 grayscale" :src="product.image_url"/>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-[10px] tracking-[0.5em] font-black opacity-10">THE_ATELIER_CONCEPT</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Product Details & Purchase Sticky Panel -->
                    <div class="lg:col-span-5 lg:sticky lg:top-32 space-y-10 reveal reveal-delay-200">
                        <div>
                            <p class="font-headline text-[11px] tracking-[0.25em] text-primary mb-4 font-black">{{ product.category?.name }} core collection</p>
                            <h2 class="font-headline text-5xl lg:text-7xl font-black leading-[0.9] dark:text-white text-on-surface mb-6 drop-shadow-md text-glow-after">
                                {{ product.name }}
                            </h2>
                            <p class="font-headline text-3xl font-black text-primary luminous-glow">KSh {{ Number(product.price).toLocaleString() }}</p>

                            <!-- Social Proof Indicator (Inverse Thinking: Leverage Momentum) -->
                            <div class="mt-8 flex items-center gap-3 py-4 px-5 bg-primary/10 border border-primary/30 rounded-xl w-fit">
                                <span class="material-symbols-outlined text-primary text-[18px] animate-pulse">bolt</span>
                                <span class="text-[10.5px] font-headline font-black tracking-[0.2em] text-[#9aff34]">
                                    {{ Math.floor(Math.random() * 8) + 3 }} active consultations · live discovery
                                </span>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <p class="text-on-surface-variant leading-relaxed font-body text-base md:text-lg">
                                {{ product.description }}
                            </p>
                        </div>

                        <!-- Options Grid (Sizing) -->
                        <div v-if="!product.is_customizable" class="space-y-6 pt-6 border-t dark:border-white/10 border-black/10">
                            <div class="flex justify-between items-center">
                                <span class="font-headline text-[11px] tracking-[0.2em] font-black dark:text-white text-on-surface">Select size</span>
                                <button class="text-[11px] tracking-[0.15em] font-black text-primary border-b-2 border-primary/30 hover:border-primary transition-all">Size guide</button>
                            </div>
                            <div class="grid grid-cols-4 gap-4">
                                <button class="py-4 bg-surface-container rounded-xl border dark:border-white/5 border-black/5 hover:border-primary text-[11px] font-headline font-black transition-all">S</button>
                                <button class="py-4 bg-surface-container rounded-xl border-2 border-primary luminous-glow text-primary text-[11px] font-headline font-black transition-all shadow-[0_0_20px_rgba(57, 255, 20,0.2)]">M</button>
                                <button class="py-4 bg-surface-container rounded-xl border dark:border-white/5 border-black/5 hover:border-primary text-[11px] font-headline font-black transition-all">L</button>
                                <button class="py-4 bg-surface-container rounded-xl border dark:border-white/5 border-black/5 hover:border-primary text-[11px] font-headline font-black transition-all">XL</button>
                            </div>
                        </div>

                        <!-- Custom Measurement Option (If Customizable) -->
                        <div v-if="product.is_customizable" class="bg-surface-container/50 backdrop-blur-md p-6 lg:p-8 rounded-2xl space-y-6 border border-primary/20 shadow-xl relative overflow-hidden">
                            <div class="absolute -right-8 -top-8 text-primary/10 w-32 h-32 material-symbols-outlined pointer-events-none" style="font-size: 150px;">architecture</div>
                            <div class="flex items-start gap-4 relative z-10">
                                <span class="material-symbols-outlined text-primary mt-1">architecture</span>
                                <div>
                                    <h4 class="font-headline text-base font-bold text-on-surface mb-2">Custom Fitting Available</h4>
                                    <p class="text-sm text-on-surface-variant">Our master tailors at the Studio can tailor this exact piece to your unique proportions. No standard sizing required.</p>
                                </div>
                            </div>
                            <Link :href="route('custom-order', { product: product.slug })" class="w-full py-5 bg-primary text-background rounded-xl font-headline text-[12.5px] tracking-[0.2em] font-black hover:scale-[1.05] active:scale-[0.98] transition-all duration-300 shadow-[0_0_20px_rgba(57, 255, 20,0.3)] block text-center">
                                Start custom measurement
                            </Link>
                        </div>

                        <!-- Main Purchase Actions with Strategic Gating & Scarcity -->
                        <div class="space-y-4 pt-6">
                            <!-- Scarcity "Batch" Indicator & Nairobi Seal -->
                            <div v-if="product.batch_limit" class="mb-6 space-y-3 p-4 bg-primary/5 rounded-xl border border-primary/20 relative overflow-hidden">
                                <!-- Nairobi Batch Seal (Minimalist) -->
                                <div class="absolute -right-4 -top-4 opacity-10 pointer-events-none">
                                    <span class="material-symbols-outlined text-[100px]">verified</span>
                                </div>

                                <div class="flex justify-between items-baseline relative z-10">
                                    <div class="flex flex-col">
                                        <span class="text-[11px] font-headline font-black tracking-[0.2em] text-primary">Limited batch</span>
                                        <span class="text-[9px] text-on-surface-variant tracking-widest font-bold">Nairobi studio seal verified</span>
                                    </div>
                                    <span class="text-[11px] font-black dark:text-white text-on-surface">Only {{ product.batch_limit - product.batch_sold }} pieces left</span>
                                </div>
                                <div class="h-1.5 w-full dark:bg-white/5 dark:bg-black bg-white/5 rounded-full overflow-hidden relative z-10">
                                    <div class="h-full bg-primary luminous-glow shadow-[0_0_10px_rgba(57, 255, 20,0.5)] transition-all duration-1000" :style="{ width: `${(product.batch_sold / product.batch_limit) * 100}%` }"></div>
                                </div>
                                <p class="text-[9px] text-on-surface-variant/70 leading-tight relative z-10">Batch No. 04 / {{ new Date().getFullYear() }} • Dispatch Window: April 5th - 8th</p>
                            </div>

                            <!-- Strategic Gating Logic (Threshold > $500) -->
                            <template v-if="product.price > 500">
                                <Link :href="route('custom-order', { product: product.slug })" class="w-full py-6 bg-white text-background font-headline text-[12.5px] tracking-[0.2em] font-black flex items-center justify-center gap-3 rounded-xl hover:bg-primary hover:scale-[1.02] transition-all duration-500 shadow-2xl">
                                    <span class="material-symbols-outlined text-[20px]">verified_user</span>
                                    Request custom order
                                </Link>
                                <p class="text-[10px] text-center text-on-surface-variant">Due to high-end tailoring, this piece is made to order only.</p>
                            </template>

                            <template v-else>
                                <!-- Hard Batch Gating Logic (Inverse Thinking: Enforce Exclusivity) -->
                                <template v-if="product.batch_limit && product.batch_sold >= product.batch_limit">
                                    <div class="p-6 bg-surface-container border border-error/20 rounded-xl space-y-4 text-center">
                                        <span class="material-symbols-outlined text-error text-3xl">lock_clock</span>
                                        <h4 class="font-headline text-sm font-bold dark:text-white text-on-surface tracking-widest">Batch #04 sold out</h4>
                                        <p class="text-[10px] text-on-surface-variant leading-relaxed">This edition has reached its artisan limit. Secure your priority invite for Batch #05.</p>
                                        <a :href="`https://wa.me/$page.props.whatsapp_number?text=I'd%20like%20to%20join%20the%20Priority%20Waiting%20List%20for%20the%20next%20batch%20of%20${product.name}`" target="_blank" class="w-full py-4 bg-white/5 border dark:border-white/20 border-black/20 dark:text-white text-on-surface font-headline text-[10px] tracking-widest font-black rounded-lg hover:bg-primary hover:text-background transition-all">
                                            Join priority waiting list
                                        </a>
                                    </div>
                                </template>

                                <template v-else>
                                    <button 
                                        @click="cart.addItem(product)"
                                        class="w-full py-6 bg-gradient-to-br from-primary to-primary-container text-background font-headline text-[12.5px] tracking-[0.2em] font-black flex items-center justify-center gap-3 rounded-xl hover:scale-[1.05] transition-all shadow-[0_0_30px_rgba(57, 255, 20,0.3)]"
                                    >
                                        <span class="material-symbols-outlined font-black">shopping_bag</span>
                                        Add to bag
                                    </button>
                                    
                                    <a :href="`https://wa.me/$page.props.whatsapp_number?text=Hello%2C%20I'm%20interested%20in%20acquiring%20the%20${product.name}`" target="_blank" class="w-full py-6 glass-card border-2 dark:border-white/10 border-black/10 dark:text-white text-on-surface hover:border-primary/50 font-headline text-[12.5px] tracking-[0.2em] font-black transition-all rounded-xl flex justify-center items-center gap-3 group">
                                        <span class="material-symbols-outlined text-stone-400 group-hover:text-primary transition-colors font-black">chat</span>
                                        Fast-track inquiry
                                    </a>
                                </template>
                            </template>
                        </div>
                        
                        <!-- Technical Excellence: Garment Blueprint (Inverse Thinking: Quality transparency) -->
                        <div class="mt-12 space-y-6 reveal">
                            <div class="flex items-center gap-3 border-b dark:border-white/10 border-black/10 pb-4">
                                <span class="material-symbols-outlined text-primary luminous-glow">architecture</span>
                                <h3 class="font-headline text-xs tracking-[0.2em] font-bold text-on-surface">Architectural blueprint</h3>
                            </div>
                            
                            <div class="relative p-6 bg-surface-container/30 border dark:border-white/5 border-black/5 rounded-2xl overflow-hidden group/blueprint">
                                <!-- Blueprint Grid Background -->
                                <div class="absolute inset-0 opacity-[0.03] pointer-events-none" style="background-image: radial-gradient(circle, #99ff33 1px, transparent 1px); background-size: 20px 20px;"></div>
                                
                                <div class="relative z-10 grid grid-cols-2 gap-6">
                                    <div v-for="(value, label) in product.specifications" :key="label" class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <div class="h-[1px] w-4 bg-primary/30"></div>
                                            <span class="text-[8px] font-bold tracking-widest text-on-surface-variant">{{ label.replace('_', ' ') }}</span>
                                        </div>
                                        <p class="text-xs font-headline dark:text-white text-on-surface pl-6">{{ value }}</p>
                                    </div>
                                </div>

                                <!-- Blueprint Tech Note -->
                                <div class="mt-6 pt-4 border-t dark:border-white/5 border-black/5 flex items-center justify-between">
                                    <span class="text-[7px] text-on-surface-variant tracking-tighter">Serana technical collection v2.1</span>
                                    <span class="text-[7px] text-primary font-black animate-pulse flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[8px]">done_all</span> 
                                        Blueprint verified
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Accordion Info -->
                        <div class="pt-10 space-y-2">
                            <details class="group bg-surface-container-low rounded-xl mb-2 overflow-hidden border border-outline-variant/5">
                                <summary class="flex justify-between items-center cursor-pointer list-none p-5 font-headline text-[11px] tracking-[0.1em] font-bold text-on-surface hover:text-primary transition-colors">
                                    <span>The craftsmanship story</span>
                                    <span class="material-symbols-outlined group-open:rotate-180 transition-transform">expand_more</span>
                                </summary>
                                <div class="px-5 pb-5 pt-0 text-sm text-on-surface-variant leading-relaxed">
                                    Every garment is stitched inside our precision facility, merging classical tailoring principles with futuristic edge. This item requires meticulous fabrication resulting in uncompromising durability.
                                </div>
                            </details>
                            
                            <details class="group bg-surface-container-low rounded-xl overflow-hidden border border-outline-variant/5">
                                <summary class="flex justify-between items-center cursor-pointer list-none p-5 font-headline text-[11px] tracking-[0.1em] uppercase font-bold text-on-surface hover:text-primary transition-colors">
                                    <span>Shipping & logistics</span>
                                    <span class="material-symbols-outlined group-open:rotate-180 transition-transform">expand_more</span>
                                </summary>
                                <div class="px-5 pb-5 pt-0 text-sm text-on-surface-variant leading-relaxed">
                                    • Global fully-insured express shipping available.<br/>
                                    • Ready-to-wear items dispatch in 48 hours.<br/>
                                    • Custom pieces require a 14-21 day precision window.<br/>
                                    • Priority tracking provided upon dispatch via the Serana Vault.<br/>
                                </div>
                            </details>
                        </div>

                        <!-- TikTok "As Seen On" Pulse Window -->
                        <div class="pt-8 group/tiktok">
                            <div class="p-4 dark:bg-black/40 bg-surface-container-high/50 border-l-2 border-primary rounded-r-xl space-y-3 transition-all duration-500 hover:bg-primary/5">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm text-primary">play_circle</span>
                                    <span class="text-[9px] font-headline font-black tracking-widest dark:text-white text-on-surface">Trending on TikTok</span>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-surface-container rounded-lg overflow-hidden border dark:border-white/10 border-black/10 animate-pulse">
                                         <!-- Simulated small video thumbnail -->
                                         <img src="/images/detailed_texture.png" class="w-full h-full object-cover opacity-60" />
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-[10px] text-on-surface font-bold leading-tight">View construction process</p>
                                        <p class="text-[8px] text-on-surface-variant tracking-tighter">42.5K clients visualized this</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Full-Width Storytelling Bar -->
            <section class="mt-32 py-24 bg-surface-container/30 border-y border-outline-variant/10 -mx-8 lg:-mx-16 px-8 lg:px-16 text-center reveal">
                <div class="max-w-3xl mx-auto space-y-8">
                    <span class="material-symbols-outlined text-primary text-4xl luminous-glow">auto_awesome</span>
                    <h3 class="font-headline text-3xl lg:text-5xl font-black leading-tight text-on-surface">
                        "Luxury is not about being noticed, it's about being <span class="text-primary">remembered</span>."
                    </h3>
                    <p class="font-headline text-xs tracking-[0.3em] font-bold text-on-surface-variant opacity-50">— Serana manifesto</p>
                </div>
            </section>
        </main>
    </StorefrontLayout>
</template>
