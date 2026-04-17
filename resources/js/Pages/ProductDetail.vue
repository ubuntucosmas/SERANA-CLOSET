<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { useCartStore } from '@/Stores/useCartStore';
import { useCurrency } from '@/Composables/useCurrency';

const page = usePage();
const { formatAmount } = useCurrency();

const props = defineProps({
    product: Object
});

const whatsappUrl = computed(() => {
    const baseUrl = window.location.origin;
    const imageUrl = props.product.image_url.startsWith('http') ? props.product.image_url : `${baseUrl}/${props.product.image_url.replace(/^\//, '')}`;
    
    const message = `🏁 *NEW ORDER DETAILS* 🏁\n\n` +
                    `*[ 01: ITEM ]*\n` +
                    `• Name: ${props.product.name}\n` +
                    `• Collection: ${props.product.category?.name || 'Serana Archive'}\n` +
                    `• Price: ${formatAmount(props.product.price, page.props)}\n\n` +
                    `*[ 02: REFERENCE ]*\n` +
                    `• URL: ${window.location.href}\n` +
                    `• Image: ${imageUrl}\n\n` +
                    `--------------------------\n` +
                    `I'm interested in this item. Can you help me with the purchase?\n` +
                    `Sent via Serana Closet.`;

    return `https://wa.me/${page.props.whatsapp_number}?text=${rawurlencode(message)}`;
});

const rawurlencode = (str) => {
    return encodeURIComponent(str).replace(/[!'()*]/g, (c) => '%' + c.charCodeAt(0).toString(16).toUpperCase());
};

const cart = useCartStore();
const isSoldOut = computed(() => props.product.batch_limit && props.product.batch_sold >= props.product.batch_limit);
</script>

<template>
    <StorefrontLayout>
        <Head :title="`${product.name} | Serana Closet`" />

        <main class="pt-20 md:pt-32 pb-32 font-body text-on-surface">
            <div class="max-w-[1440px] mx-auto px-0 md:px-16">
                <!-- PDP Header Section: Editorial Asymmetry -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-0 md:gap-20 items-start">
                    
                    <!-- Left: Editorial Gallery (Asymmetric Layout) -->
                    <div class="lg:col-span-7 space-y-4 md:space-y-12">
                        <!-- Main Featured Hero Image -->
                        <div class="relative w-full aspect-[4/5] md:aspect-[2/3] md:rounded-2xl overflow-hidden border-b md:border border-black/5 dark:border-white/5 shadow-2xl">
                            <img class="w-full h-full object-cover" :src="product.optimized_image_url || '/images/hero_editorial.png'"/>
                            
                            <!-- Zen "Atelier Tag" (Mobile Header Overlay) -->
                            <div class="md:hidden absolute inset-x-0 bottom-0 p-6 bg-gradient-to-t from-black/80 via-black/20 to-transparent">
                                <div class="bg-black/40 backdrop-blur-2xl border border-white/10 p-5 rounded-sm shadow-2xl">
                                    <div class="flex justify-between items-start mb-1">
                                        <div class="flex-1">
                                            <p class="text-[8px] font-black tracking-[0.4em] text-primary uppercase mb-1">{{ product.category?.name || 'Archive' }}</p>
                                            <h1 class="text-xl font-headline font-black text-white uppercase tracking-wider leading-tight">{{ product.name }}</h1>
                                        </div>
                                        <span class="text-lg font-black text-primary luminous-glow">{{ formatAmount(product.price, page.props) }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 mt-3 opacity-60">
                                        <span class="material-symbols-outlined text-[14px] text-primary">verified</span>
                                        <span class="text-[8px] text-white font-bold uppercase tracking-[0.2em]">Nairobi Master Tailored</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Desktop Badges -->
                            <div class="hidden md:flex absolute top-8 left-8 flex-col gap-2 z-10">
                                <span class="bg-surface-container/80 backdrop-blur font-headline text-[11px] tracking-[0.15em] px-4 py-2 border dark:border-white/10 border-black/10 rounded-full inline-block w-fit font-black uppercase">
                                    {{ product.category?.name }}
                                </span>
                                <span v-if="product.is_customizable" class="bg-primary text-background font-headline text-[11px] tracking-[0.2em] px-4 py-2 rounded-full w-fit shadow-[0_0_20px_rgba(57, 255, 20,0.3)] font-black uppercase">
                                    Customizable
                                </span>
                            </div>
                        </div>

                        <!-- Secondary Split Details (Desktop & Mobile) -->
                        <div v-if="product.optimized_secondary_urls && product.optimized_secondary_urls.length >= 1" class="grid grid-cols-2 gap-2 md:gap-8 px-2 md:px-0">
                            <div class="aspect-[3/4] md:mt-12 overflow-hidden md:rounded-2xl border dark:border-white/5 border-black/5 shadow-2xl">
                                <img class="w-full h-full object-cover" :src="product.optimized_secondary_urls[0]"/>
                            </div>
                            <div v-if="product.optimized_secondary_urls[1]" class="aspect-[3/4] md:-mt-12 overflow-hidden md:rounded-2xl border dark:border-white/5 border-black/5 shadow-2xl">
                                <img class="w-full h-full object-cover" :src="product.optimized_secondary_urls[1]"/>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Product Details & Purchase Sticky Panel -->
                    <div class="lg:col-span-5 lg:sticky lg:top-32 space-y-10 px-8 md:px-0 mt-12 md:mt-0 pb-20 md:pb-0">
                        <!-- Desktop Header (Hidden on Mobile) -->
                        <div class="hidden md:block">
                            <p class="font-headline text-[11px] tracking-[0.25em] text-primary mb-4 font-black uppercase">{{ product.category?.name }} core collection</p>
                            <h2 class="font-headline text-5xl lg:text-7xl font-black leading-[0.9] dark:text-white text-on-surface mb-6 drop-shadow-md text-glow-after uppercase">
                                {{ product.name }}
                            </h2>
                            <p class="font-headline text-3xl font-black text-primary luminous-glow">{{ formatAmount(product.price, page.props) }}</p>
                        </div>

                        <!-- Mobile Price/Status Bar -->
                        <div class="md:hidden flex items-center justify-between mb-8 pb-6 border-b border-white/5">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-primary text-[18px] animate-pulse">bolt</span>
                                <span class="text-[10px] font-headline font-black tracking-[0.2em] text-[#9aff34] uppercase">
                                    {{ Math.floor(Math.random() * 8) + 3 }} Viewing Live
                                </span>
                            </div>
                            <span v-if="isSoldOut" class="text-[9px] font-black text-error tracking-widest uppercase border border-error/30 px-3 py-1 bg-error/5">Sold Out edition</span>
                        </div>

                        <div class="space-y-6">
                            <p class="text-on-surface-variant leading-relaxed font-body text-base md:text-lg opacity-80">
                                {{ product.description }}
                            </p>
                        </div>

                        <!-- Technical Excellence: Garment Blueprint -->
                        <div class="space-y-6">
                            <div class="flex items-center gap-3 border-b dark:border-white/10 border-black/10 pb-4">
                                <span class="material-symbols-outlined text-primary luminous-glow">architecture</span>
                                <h3 class="font-headline text-[10px] tracking-[0.3em] font-black text-on-surface uppercase">Product Specifications_</h3>
                            </div>
                            
                            <div class="relative p-6 md:p-8 bg-surface-container/30 border dark:border-white/5 border-black/5 rounded-sm overflow-hidden group/blueprint">
                                <div class="absolute inset-0 opacity-[0.03] pointer-events-none" style="background-image: radial-gradient(circle, #99ff33 1px, transparent 1px); background-size: 20px 20px;"></div>
                                
                                <div class="relative z-10 grid grid-cols-2 gap-8">
                                    <div v-for="(value, label) in product.specifications" :key="label" class="space-y-1.5">
                                        <div class="flex items-center gap-2">
                                            <div class="h-[1px] w-3 bg-primary/40"></div>
                                            <span class="text-[7px] font-black tracking-[0.4em] text-on-surface-variant uppercase whitespace-nowrap">{{ label.replace('_', ' ') }}</span>
                                        </div>
                                        <p class="text-[11px] font-headline dark:text-white text-on-surface pl-5 leading-tight">{{ value }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Desktop Options Grid (Hidden on Mobile as it should be tailored via Chat) -->
                        <div v-if="!product.is_customizable" class="hidden md:block space-y-6 pt-6 border-t dark:border-white/10 border-black/10">
                            <div class="flex justify-between items-center">
                                <span class="font-headline text-[11px] tracking-[0.2em] font-black dark:text-white text-on-surface uppercase">Select size</span>
                                <button class="text-[10px] tracking-[0.15em] font-black text-primary border-b border-primary/30 hover:border-primary transition-all uppercase">Size guide</button>
                            </div>
                            <div class="grid grid-cols-4 gap-4">
                                <button v-for="size in ['S', 'M', 'L', 'XL']" :key="size" class="py-4 bg-surface-container rounded-sm border dark:border-white/5 border-black/5 hover:border-primary text-[11px] font-headline font-black transition-all">{{ size }}</button>
                            </div>
                        </div>

                        <!-- Desktop Purchase Actions (Hidden on Mobile Footer) -->
                        <div class="hidden md:block space-y-4 pt-6">
                            <button 
                                @click="cart.addItem(product)"
                                class="w-full py-6 bg-primary text-black font-headline text-[12px] tracking-[0.3em] font-black flex items-center justify-center gap-3 rounded-sm hover:scale-[1.02] transition-all shadow-[0_0_30px_rgba(57, 255, 20,0.2)]"
                            >
                                <span class="material-symbols-outlined font-black">shopping_bag</span>
                                Add to Bag
                            </button>
                            
                            <a :href="whatsappUrl" target="_blank" class="w-full py-6 bg-white/5 border border-white/10 dark:text-white text-on-surface hover:border-primary/50 font-headline text-[12px] tracking-[0.3em] font-black transition-all rounded-sm flex justify-center items-center gap-3 group">
                                <span class="material-symbols-outlined text-stone-400 group-hover:text-primary transition-colors font-black">chat</span>
                                Fast-Track Order
                            </a>
                        </div>
                        
                        <!-- Accordion Info -->
                        <div class="pt-10 space-y-2">
                            <details class="group bg-surface-container-low rounded-sm mb-2 overflow-hidden border border-outline-variant/10">
                                <summary class="flex justify-between items-center cursor-pointer list-none p-6 font-headline text-[10px] tracking-[0.2em] font-black text-on-surface hover:text-primary transition-colors uppercase">
                                    <span>Our Heritage & Quality</span>
                                    <span class="material-symbols-outlined group-open:rotate-180 transition-transform">expand_more</span>
                                </summary>
                                <div class="px-6 pb-6 pt-0 text-[12px] text-on-surface-variant leading-relaxed opacity-70">
                                    Every garment is stitched inside our precision facility, merging classical tailoring principles with futuristic edge. This item requires meticulous fabrication resulting in uncompromising durability.
                                </div>
                            </details>
                            
                            <details class="group bg-surface-container-low rounded-sm overflow-hidden border border-outline-variant/10">
                                <summary class="flex justify-between items-center cursor-pointer list-none p-6 font-headline text-[10px] tracking-[0.2em] font-black text-on-surface hover:text-primary transition-colors uppercase">
                                    <span>Shipping & Delivery</span>
                                    <span class="material-symbols-outlined group-open:rotate-180 transition-transform">expand_more</span>
                                </summary>
                                <div class="px-6 pb-6 pt-0 text-[12px] text-on-surface-variant leading-relaxed opacity-70">
                                    • Global fully-insured express shipping available.<br/>
                                    • Ready-to-wear items dispatch in 48 hours.<br/>
                                    • Custom pieces require a 14-21 day precision window.<br/>
                                    • Priority tracking provided upon dispatch via the Serana Vault.<br/>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Full-Width Storytelling Bar -->
            <section class="mt-32 py-24 bg-surface-container/30 border-y border-outline-variant/10 text-center">
                <div class="max-w-3xl mx-auto px-8 space-y-8">
                    <span class="material-symbols-outlined text-primary text-4xl luminous-glow">auto_awesome</span>
                    <h3 class="font-headline text-3xl md:text-5xl font-black leading-tight text-on-surface uppercase tracking-tight">
                        "Luxury is not about being noticed, it's about being <span class="text-primary">remembered</span>."
                    </h3>
                    <p class="font-headline text-[10px] tracking-[0.5em] font-black text-on-surface-variant opacity-40 uppercase">— Serana Manifesto</p>
                </div>
            </section>
        </main>

        <!-- Fixed "Matrix Footer" (Mobile Persistent CTAs) -->
        <div class="md:hidden fixed bottom-0 left-0 right-0 z-[60] p-4 bg-gradient-to-t from-black via-black/95 to-transparent">
            <div class="max-w-md mx-auto flex gap-3 h-16">
                <!-- Add to Bag -->
                <button 
                    @click="cart.addItem(product)"
                    class="flex-[2] bg-primary text-black rounded-sm flex items-center justify-center gap-3 font-headline text-[11px] font-black tracking-[0.25em] uppercase shadow-2xl active:scale-95 transition-all"
                >
                    <span class="material-symbols-outlined text-[20px] font-black">shopping_bag</span>
                    Add to Bag_
                </button>
                
                <!-- WhatsApp -->
                <a 
                    :href="whatsappUrl" 
                    target="_blank"
                    class="flex-1 bg-white/5 border border-white/10 text-white rounded-sm flex items-center justify-center active:scale-95 transition-all"
                >
                    <span class="material-symbols-outlined text-[20px]">chat</span>
                </a>
            </div>
            <!-- Safari Safe Area -->
            <div class="h-[env(safe-area-inset-bottom)]"></div>
        </div>
    </StorefrontLayout>
</template>

<style scoped>
.text-glow-after { transition: text-shadow 0.3s ease; }
.text-glow-after:hover { text-shadow: 0 0 15px rgba(57, 255, 20, 0.6); }

/* Animation for reveal */
.reveal {
    animation: revealUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes revealUp {
    from { opacity: 0; transform: translateY(40px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
