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
    
    let variantMetadata = '';
    if (props.product.garment_type === 'set') {
        variantMetadata = `• Configuration: FULL SET\n• Hoodie Size: ${selectedTopSize.value || 'Unselected'}\n• Jogger Size: ${selectedBottomSize.value || 'Unselected'}`;
    } else if (props.product.garment_type === 'hoodie') {
        variantMetadata = `• Configuration: SINGLE HOODIE\n• Size: ${selectedTopSize.value || 'Unselected'}`;
    } else if (props.product.garment_type === 'jogger') {
        variantMetadata = `• Configuration: SINGLE JOGGER\n• Size: ${selectedBottomSize.value || 'Unselected'}`;
    }

    const message = `🏁 *NEW ORDER ENQUIRY* 🏁\n\n` +
                    `*[ 01: ITEM ]*\n` +
                    `• Name: ${props.product.name}\n` +
                    `${variantMetadata}\n` +
                    `• Price: ${formatAmount(props.product.price, page.props)}\n\n` +
                    `*[ 02: REFERENCE ]*\n` +
                    `• URL: ${window.location.href}\n` +
                    `• Image: ${imageUrl}\n\n` +
                    `--------------------------\n` +
                    `I'm interested in this piece. Can you help me with the purchase?\n` +
                    `Sent via Serana Studio.`;

    return `https://wa.me/${page.props.whatsapp_number}?text=${rawurlencode(message)}`;
});

const rawurlencode = (str) => {
    return encodeURIComponent(str).replace(/[!'()*]/g, (c) => '%' + c.charCodeAt(0).toString(16).toUpperCase());
};

const cart = useCartStore();
const isSoldOut = computed(() => props.product.batch_limit && props.product.batch_sold >= props.product.batch_limit);

// ── Jogger Set Configuration Engine ──────────────────────────────────────────
const selectedTopSize = ref(null);
const selectedBottomSize = ref(null);
const activeComponent = ref(props.product.garment_type === 'jogger' ? 'joggers' : 'hoodie'); 

const sizes = ['S', 'M', 'L', 'XL', 'XXL'];

const addToBag = () => {
    const isSet = props.product.garment_type === 'set';
    const isHoodie = props.product.garment_type === 'hoodie';
    const isJogger = props.product.garment_type === 'jogger';

    if (isSet) {
        if (!selectedTopSize.value || !selectedBottomSize.value) {
            const missing = !selectedTopSize.value && !selectedBottomSize.value 
                ? 'both hoodie & jogger sizes' 
                : (!selectedTopSize.value ? 'hoodie size' : 'jogger size');
                
            window.dispatchEvent(new CustomEvent('serana-toast', {
                detail: { message: `Please select ${missing} to add the set to your bag.`, type: 'error' }
            }));
            return;
        }
    } else if (isHoodie && !selectedTopSize.value) {
        window.dispatchEvent(new CustomEvent('serana-toast', {
            detail: { message: `Please select a size for your hoodie.`, type: 'error' }
        }));
        return;
    } else if (isJogger && !selectedBottomSize.value) {
        window.dispatchEvent(new CustomEvent('serana-toast', {
            detail: { message: `Please select a size for your joggers.`, type: 'error' }
        }));
        return;
    }
    
    // Create a specialized product object for the cart
    const itemToBag = {
        ...props.product,
        name: isSet ? `${props.product.name} (Set)` : props.product.name,
        options: {
            top_size: isJogger ? null : selectedTopSize.value,
            bottom_size: isHoodie ? null : selectedBottomSize.value
        }
    };
    
    cart.addItem(itemToBag);
};

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

        <main class="pt-20 md:pt-24 lg:pt-32 pb-0 font-body text-on-surface bg-background">
            <div class="max-w-[1920px] mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-0 lg:h-[calc(100vh-128px)]">
                    
                    <!-- Left: The Visual Axis (Atelier Gallery) -->
                    <div class="lg:col-span-7 relative group/slider h-[60vh] lg:h-full bg-black overflow-hidden">
                        <div ref="galleryRef" class="flex h-full overflow-x-auto snap-x snap-mandatory no-scrollbar scroll-smooth">
                            <div v-for="(img, idx) in allImages" :key="idx" class="flex-none w-full snap-center h-full">
                                <img :src="img" class="w-full h-full object-cover" />
                            </div>
                        </div>

                        <!-- Zen Directives (Overlay) -->
                        <div class="absolute inset-0 pointer-events-none z-20 flex flex-col justify-between p-10">
                            <div class="flex justify-between items-start">
                                <div class="bg-primary text-black px-6 py-2 rounded-full font-black text-[10px] tracking-[0.2em] uppercase shadow-2xl pointer-events-auto">
                                    {{ isSoldOut ? 'Archive Session' : (product.specifications?.batch_name || 'Active Batch #01') }}
                                </div>
                                <div class="hidden lg:flex flex-col items-end gap-2 text-right">
                                    <span class="text-white/20 font-headline text-[8px] tracking-[0.5em] uppercase">Drafting_ref_021</span>
                                    <span class="text-white/40 font-headline text-[10px] tracking-[0.3em] uppercase">{{ product.name }} // Technical_Analysis</span>
                                </div>
                            </div>

                            <div v-if="allImages.length > 1" class="flex justify-between items-center gap-4">
                                <button @click="scrollGallery('prev')" class="pointer-events-auto w-14 h-14 rounded-full bg-black/20 backdrop-blur-3xl border border-white/10 flex items-center justify-center text-white/40 hover:text-primary transition-all">
                                    <span class="material-symbols-outlined text-2xl">chevron_left</span>
                                </button>
                                
                                <div class="flex gap-2">
                                    <div v-for="(_, i) in allImages" :key="i" class="w-1 h-8 bg-white/10 rounded-full overflow-hidden">
                                        <div class="w-full h-full bg-primary transition-transform duration-500" :style="{ transform: `translateY(${currentIndex === (allImages.length > 0 ? (Math.round(galleryRef?.scrollLeft / galleryRef?.clientWidth) || 0) : 0) ? '0' : '100%'})` }"></div>
                                    </div>
                                </div>

                                <button @click="scrollGallery('next')" class="pointer-events-auto w-14 h-14 rounded-full bg-black/20 backdrop-blur-3xl border border-white/10 flex items-center justify-center text-white/40 hover:text-primary transition-all">
                                    <span class="material-symbols-outlined text-2xl">chevron_right</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right: The Configuration Canvas (Atelier Workspace) -->
                    <div class="lg:col-span-5 bg-surface border-l border-white/5 p-8 lg:p-16 flex flex-col overflow-y-auto no-scrollbar relative">
                        
                        <!-- Header: Blueprint Annotations -->
                        <header class="mb-12 border-b border-white/5 pb-10">
                            <div class="flex justify-between items-start mb-6">
                                <div>
                                    <p class="text-primary font-black text-[9px] tracking-[0.5em] uppercase mb-3">{{ product.category?.name || 'ARTISAN COLLECTION' }}</p>
                                    <h1 class="text-4xl lg:text-6xl font-black uppercase tracking-tighter leading-none dark:text-white text-on-surface">
                                        {{ product.name }}<span class="text-primary">_</span>
                                    </h1>
                                </div>
                                <div class="text-right">
                                    <p class="text-3xl font-black text-primary luminous-glow leading-none">{{ formatAmount(product.price, page.props) }}</p>
                                    <p class="text-[7px] text-white/30 uppercase tracking-widest mt-2">READY FOR ORDER</p>
                                </div>
                            </div>
                            <p class="text-on-surface-variant text-sm font-medium leading-relaxed max-w-md opacity-60">
                                {{ product.description }}
                            </p>
                        </header>

                        <!-- The Set Configuration Engine -->
                        <div class="flex-grow space-y-12">
                            <!-- Workspace Navigation (Upper/Lower Split) -->
                            <div v-if="product.garment_type === 'set'" class="space-y-6">
                                <label class="text-[8px] font-black uppercase tracking-[0.4em] text-white/20">SET PIECES</label>
                                <div class="flex gap-4">
                                    <button 
                                        @click="activeComponent = 'hoodie'"
                                        class="flex-1 flex flex-col p-6 rounded-sm border-2 transition-all group"
                                        :class="activeComponent === 'hoodie' ? 'border-primary bg-primary/5' : 'border-white/5 border-white/5 hover:border-white/20'"
                                    >
                                        <span class="text-[8px] font-black uppercase tracking-widest mb-2" :class="activeComponent === 'hoodie' ? 'text-primary' : 'text-white/20'">PIECE 01</span>
                                        <div class="flex justify-between items-end">
                                            <span class="text-[11px] font-black uppercase tracking-widest font-headline">The Hoodie</span>
                                            <span class="text-[9px] font-black text-primary" v-if="selectedTopSize">{{ selectedTopSize }}</span>
                                        </div>
                                    </button>
                                    <button 
                                        @click="activeComponent = 'joggers'"
                                        class="flex-1 flex flex-col p-6 rounded-sm border-2 transition-all group"
                                        :class="activeComponent === 'joggers' ? 'border-primary bg-primary/5' : 'border-white/5 border-white/5 hover:border-white/20'"
                                    >
                                        <span class="text-[8px] font-black uppercase tracking-widest mb-2" :class="activeComponent === 'joggers' ? 'text-primary' : 'text-white/20'">PIECE 02</span>
                                        <div class="flex justify-between items-end">
                                            <span class="text-[11px] font-black uppercase tracking-widest font-headline">The Joggers</span>
                                            <span class="text-[9px] font-black text-primary" v-if="selectedBottomSize">{{ selectedBottomSize }}</span>
                                        </div>
                                    </button>
                                </div>
                            </div>
                            <div v-else class="space-y-2">
                                <p class="text-[8px] font-black uppercase tracking-[0.4em] text-primary">SINGLE GARMENT</p>
                                <h3 class="text-[11px] font-black uppercase tracking-widest font-headline opacity-40">
                                    {{ product.garment_type === 'hoodie' ? 'Piece 01: The Hoodie' : 'Piece 02: The Joggers' }}
                                </h3>
                            </div>

                            <!-- Contextual Size Selector -->
                            <div class="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-700">
                                <div class="flex justify-between items-center">
                                    <label class="text-[8px] font-black uppercase tracking-[0.4em] text-white/20">SELECT SIZE: {{ activeComponent }}</label>
                                    <button class="text-[8px] font-black uppercase tracking-widest text-primary border-b border-primary/30">Size Guide</button>
                                </div>
                                <div class="grid grid-cols-5 gap-3">
                                    <button 
                                        v-for="size in sizes" :key="size"
                                        @click="activeComponent === 'hoodie' ? selectedTopSize = size : selectedBottomSize = size"
                                        class="py-4 border-2 rounded-sm text-[11px] font-black transition-all"
                                        :class="[
                                            (activeComponent === 'hoodie' ? selectedTopSize : selectedBottomSize) === size 
                                                ? 'border-primary text-primary bg-primary/10 shadow-[0_0_15px_rgba(57,255,20,0.2)]' 
                                                : 'border-white/5 text-white/40 hover:border-white/20'
                                        ]"
                                    >
                                        {{ size }}
                                    </button>
                                </div>
                            </div>

                            <!-- Drafting Annotations (Technical Excellence) -->
                            <div class="pt-8 border-t border-white/5 space-y-6">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-primary text-sm">architecture</span>
                                    <span class="text-[8px] font-black uppercase tracking-[0.4em] text-white/20">GARMENT DETAILS</span>
                                </div>
                                <div class="grid grid-cols-2 gap-8 text-[11px] opacity-80 font-medium">
                                    <div v-for="(spec, idx) in (product.specifications?.tech_specs?.filter(s => s) || ['450GSM Cotton Fleece', 'Reinforced finish', 'Heavy-duty cuffs', 'Limited batch'])" :key="idx" class="space-y-2 border-l border-primary/20 pl-4 py-1">
                                        <p class="text-[7px] font-black uppercase tracking-widest text-white/30">ITEM #0{{ idx + 1 }}</p>
                                        <p>{{ spec }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Density Visualizer (Atelier Metric) -->
                            <div class="p-6 bg-white/5 border border-white/5 rounded-sm animate-in fade-in slide-in-from-bottom-2 duration-1000">
                                <div class="flex justify-between items-center mb-4">
                                    <span class="text-[8px] font-black uppercase tracking-[0.4em] text-white/20">DENSITY LEVEL</span>
                                    <span class="text-[10px] font-black text-primary">{{ product.specifications?.fabric_weight || 'HEAVYWEIGHT (450 GSM)' }}</span>
                                </div>
                                <div class="h-1 w-full bg-white/5 rounded-full overflow-hidden">
                                    <div class="h-full bg-primary shadow-[0_0_10px_rgba(57,255,20,0.5)]" :style="{ width: (product.specifications?.weight_index || 85) + '%' }"></div>
                                </div>
                                <p class="text-[8px] mt-3 text-white/30 uppercase tracking-widest">Optimized for structural integrity & thermal isolation</p>
                            </div>
                        </div>

                        <!-- The Commission Action -->
                        <div class="mt-12 pt-10 border-t border-white/10 flex flex-col gap-4">
                            <button 
                                @click="addToBag"
                                class="w-full py-6 bg-primary text-black font-black text-[12px] tracking-[0.4em] uppercase rounded-sm hover:scale-[1.01] active:scale-[0.98] transition-all shadow-[0_20px_40px_rgba(57,255,20,0.1)] flex items-center justify-center gap-3"
                            >
                                <span class="material-symbols-outlined font-black">shopping_bag</span>
                                Add to Bag
                            </button>
                            <a :href="whatsappUrl" target="_blank" class="w-full py-5 border border-white/10 text-white/40 hover:text-white hover:border-primary/40 font-black text-[9px] tracking-[0.4em] uppercase rounded-sm transition-all text-center">
                                Order via WhatsApp
                            </a>
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
