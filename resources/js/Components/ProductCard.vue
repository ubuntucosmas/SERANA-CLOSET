<script setup>
import { ref, computed } from 'vue';
import { useCartStore } from '@/Stores/useCartStore';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    product: { type: Object, required: true },
    layout: { type: String, default: 'grid' }
});

const page = usePage();
const cart = useCartStore();
const { formatAmount } = useCurrency();
const isHovered = ref(false);
const currentIndex = ref(0);

const allImages = computed(() => {
    const images = [props.product.image_url];
    if (props.product.secondary_image_urls) {
        images.push(...props.product.secondary_image_urls.filter(Boolean));
    }
    return images;
});

const optimizedAllImages = computed(() => {
    const images = [props.product.optimized_image_url || props.product.image_url];
    if (props.product.optimized_secondary_urls) {
        images.push(...props.product.optimized_secondary_urls.filter(Boolean));
    } else if (props.product.secondary_image_urls) {
        images.push(...props.product.secondary_image_urls.filter(Boolean));
    }
    return images.filter(Boolean);
});

const navigateToSpecs = () => {
    router.visit(route('shop.show', props.product.slug));
};

const whatsappUrl = computed(() => {
    const msg = `Serana: ${props.product.name}\nPrice: ${formatAmount(props.product.price, page.props)}\n${route('shop.show', props.product.slug)}`;
    return `https://wa.me/${page.props.whatsapp_number}?text=${encodeURIComponent(msg)}`;
});

const isSoldOut = computed(() => props.product.batch_limit && props.product.batch_sold >= props.product.batch_limit);
</script>

<template>
    <div 
        :class="[
            'group relative transition-all duration-500 cursor-pointer',
            layout === 'editorial' ? 'h-[600px] lg:h-[800px]' : ''
        ]"
        @mouseenter="isHovered = true"
        @mouseleave="isHovered = false"
        @click="navigateToSpecs"
    >
        <!-- Image Container -->
        <div :class="[
            'overflow-hidden bg-transparent relative rounded-sm border border-[#57d5fc]/14 shadow-xl transition-all duration-500',
            layout === 'editorial' ? 'h-full' : 'aspect-[3/4]'
        ]">
            <img
                class="w-full h-full object-cover transition-transform duration-700"
                :src="optimizedAllImages[currentIndex] || '/images/placeholder.png'"
                :alt="product.name"
                loading="lazy"
            />
        </div>

        <!-- Desktop Info -->
        <div class="hidden md:block mt-4">
            <h3 class="font-display text-lg font-light text-white transition-colors">{{ product.name }}</h3>
            <div class="flex justify-between items-center mt-2">
                <span class="text-sm font-mono text-white/60">{{ product.category?.name || 'Collection' }}</span>
                <span class="text-primary font-headline">{{ formatAmount(product.price, page.props) }}</span>
            </div>
        </div>

        <!-- Mobile Info -->
        <div class="md:hidden absolute inset-x-0 bottom-0 p-4 bg-gradient-to-t from-black/90 to-transparent">
            <div class="flex justify-between items-end">
                <h3 class="text-sm font-display text-white truncate">{{ product.name }}</h3>
                <span class="text-primary font-mono text-sm">{{ formatAmount(product.price, page.props) }}</span>
            </div>
        </div>

        <!-- Sold Out Overlay -->
        <div v-if="isSoldOut" class="absolute inset-0 z-10 bg-black/60 flex items-center justify-center">
            <span class="tag !border-white/30 !text-white/70">SOLD OUT</span>
        </div>
    </div>
</template>