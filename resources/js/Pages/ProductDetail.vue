<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
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

const allImages = computed(() => {
    const images = [];
    if (props.product.optimized_image_url) images.push(props.product.optimized_image_url);
    if (props.product.optimized_secondary_urls) images.push(...props.product.optimized_secondary_urls);
    return images.length > 0 ? images : ['/images/hero_editorial.png'];
});

const galleryRef = ref(null);
let autoScrollInterval = null;

const scrollGallery = (direction) => {
    if (!galleryRef.value) return;
    
    const container = galleryRef.value;
    const scrollAmount = container.clientWidth;
    const maxScroll = container.scrollWidth - container.clientWidth;
    
    if (direction === 'next') {
        if (container.scrollLeft >= maxScroll - 10) {
            container.scrollTo({ left: 0, behavior: 'smooth' });
        } else {
            container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        }
    } else {
        if (container.scrollLeft <= 10) {
            container.scrollTo({ left: maxScroll, behavior: 'smooth' });
        } else {
            container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        }
    }
    
    // Reset timer on manual interaction
    resetAutoScroll();
};

const startAutoScroll = () => {
    if (allImages.value.length <= 1) return;
    autoScrollInterval = setInterval(() => {
        scrollGallery('next');
    }, 5000);
};

const resetAutoScroll = () => {
    if (autoScrollInterval) {
        clearInterval(autoScrollInterval);
        startAutoScroll();
    }
};

onMounted(() => {
    startAutoScroll();
});

onUnmounted(() => {
    if (autoScrollInterval) clearInterval(autoScrollInterval);
});
</script>

<template>
    <StorefrontLayout>
        <Head :title="`${product.name} | Serana Closet`" />

        <main class="pt-20 md:pt-32 pb-32 font-body text-on-surface">
            <div class="max-w-[1440px] mx-auto px-0 md:px-16">
                <!-- PDP Header Section: Editorial Asymmetry -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-0 md:gap-20 items-start">
                    
                    <!-- Left: Editorial Gallery (Horizontal Slider) -->
                    <div class="lg:col-span-7 relative group/slider">
                        <!-- Scrollable Container -->
                        <div ref="galleryRef" class="flex overflow-x-auto snap-x snap-mandatory no-scrollbar md:rounded-2xl border-b md:border border-black/5 dark:border-white/5 shadow-2xl bg-black scroll-smooth">
                            <div v-for="(img, idx) in allImages" :key="idx" class="flex-none w-full snap-center aspect-[4/5] md:aspect-[2/3] overflow-hidden">
                                <img :src="img" class="w-full h-full object-cover" />
                            </div>
                        </div>

                        <!-- Directional Arrows (Overlaid on Image) -->
                        <div v-if="allImages.length > 1" class="absolute inset-y-0 inset-x-0 pointer-events-none z-20 flex items-center justify-between px-4">
                            <button @click="scrollGallery('prev')" class="pointer-events-auto w-10 h-10 md:w-14 md:h-14 rounded-full bg-black/10 backdrop-blur-md border border-white/10 flex items-center justify-center text-white/40 hover:text-white hover:bg-black/40 transition-all duration-300">
                                <span class="material-symbols-outlined text-xl md:text-2xl">chevron_left</span>
                            </button>
                            <button @click="scrollGallery('next')" class="pointer-events-auto w-10 h-10 md:w-14 md:h-14 rounded-full bg-black/10 backdrop-blur-md border border-white/10 flex items-center justify-center text-white/40 hover:text-white hover:bg-black/40 transition-all duration-300">
                                <span class="material-symbols-outlined text-xl md:text-2xl">chevron_right</span>
                            </button>
                        </div>

                        <!-- Gallery Counter (Zen Indicator) -->
                        <div v-if="allImages.length > 1" class="absolute bottom-10 right-10 z-20">
                            <div class="bg-black/40 backdrop-blur-xl border border-white/10 px-6 py-3 rounded-full">
                                <span class="font-headline text-[10px] tracking-[0.4em] text-white font-bold opacity-80 italic">GALLERY_0{{ allImages.length }}</span>
                            </div>
                        </div>

                        <!-- Zen "Atelier Tag" (Mobile Header Overlay) -->
                        <div class="md:hidden absolute inset-x-0 bottom-0 p-6 bg-gradient-to-t from-black/95 via-black/40 to-transparent z-10 pointer-events-none">
                            <div class="flex justify-between items-end">
                                <div class="flex-1">
                                    <p class="text-[7px] font-black tracking-[0.5em] text-primary uppercase mb-1.5 opacity-80">{{ product.category?.name || 'Archive' }}</p>
                                    <h1 class="text-2xl font-headline font-black text-white uppercase tracking-tight leading-none drop-shadow-2xl">{{ product.name }}</h1>
                                </div>
                                <div class="flex flex-col items-end gap-1">
                                    <span class="text-xl font-black text-primary luminous-glow leading-none">{{ formatAmount(product.price, page.props) }}</span>
                                    <span class="text-[6px] text-white/40 uppercase tracking-widest">Inclusive of VAT</span>
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
                                <div class="hidden md:block absolute inset-0 opacity-[0.03] pointer-events-none" style="background-image: radial-gradient(circle, #99ff33 1px, transparent 1px); background-size: 20px 20px;"></div>
                                
                                <div class="relative z-10 grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8">
                                    <div v-for="(value, label) in product.specifications" :key="label" class="space-y-1 md:space-y-1.5 border-l-2 border-primary/20 md:border-0 pl-4 md:pl-0">
                                        <div class="flex items-center gap-2">
                                            <!-- Desktop only indicator -->
                                            <div class="hidden md:block h-[1px] w-3 bg-primary/40"></div>
                                            <span class="text-[7px] font-black tracking-[0.4em] text-on-surface-variant uppercase whitespace-nowrap">{{ label.replace('_', ' ') }}</span>
                                        </div>
                                        <p class="text-[11px] md:text-[11px] font-headline dark:text-white text-on-surface md:pl-5 leading-tight">{{ value }}</p>
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

        <!-- Fixed "Action Hub" (Floating Mobile CTAs) -->
        <div class="md:hidden fixed bottom-24 right-6 z-[60] flex flex-col gap-4">
            <!-- Add to Bag Bubble -->
            <button 
                @click="cart.addItem(product)"
                class="w-14 h-14 bg-white text-black rounded-full flex items-center justify-center shadow-[0_20px_40px_rgba(255,255,255,0.1)] active:scale-90 transition-all border border-white/20"
                title="Add to Bag"
            >
                <div class="relative">
                    <span class="material-symbols-outlined text-[24px] font-black">shopping_bag</span>
                    <span v-if="cart.items.find(i => i.id === product.id)" class="absolute -top-1 -right-1 w-3 h-3 bg-primary rounded-full animate-pulse"></span>
                </div>
            </button>
            
            <!-- WhatsApp Bubble -->
            <a 
                :href="whatsappUrl" 
                target="_blank"
                class="w-14 h-14 bg-[#25D366] text-white rounded-full flex items-center justify-center shadow-[0_20px_40px_rgba(37,211,102,0.2)] active:scale-90 transition-all"
                title="Direct Checkout"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 fill-white" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.57-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            </a>
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
