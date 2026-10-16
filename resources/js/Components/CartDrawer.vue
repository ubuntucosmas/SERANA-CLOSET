<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { useCartStore } from '@/Stores/useCartStore';
import { Link, usePage } from '@inertiajs/vue3';
import { animate } from 'animejs';
import { useCurrency } from '@/Composables/useCurrency';

const page = usePage();
const cart = useCartStore();
const { formatAmount } = useCurrency();

// A courteous, scannable order message: numbered items with any chosen
// sizes, then the total. No decoration — the studio reads it on a phone.
const whatsappUrl = computed(() => {
    const itemsList = cart.items.map((item, index) => {
        let line = `${index + 1}. ${item.name} ×${item.quantity} — ${formatAmount(item.price * item.quantity, page.props)}`;
        if (item.options) {
            const sizes = [];
            if (item.options.top_size) sizes.push(`Hoodie ${item.options.top_size}`);
            if (item.options.bottom_size) sizes.push(`Jogger ${item.options.bottom_size}`);
            if (sizes.length) line += `\n    Size: ${sizes.join(', ')}`;
        }
        return line;
    }).join('\n');

    const message = `Hello Serana,\n\n` +
                    `I'd like to order the following:\n\n` +
                    `${itemsList}\n\n` +
                    `*Total: ${formatAmount(cart.totalPrice, page.props)}*\n\n` +
                    `Please confirm availability and the next steps. Thank you.`;

    return `https://wa.me/${page.props.whatsapp_number}?text=${rawurlencode(message)}`;
});

const rawurlencode = (str) => {
    return encodeURIComponent(str).replace(/[!'()*]/g, (c) => '%' + c.charCodeAt(0).toString(16).toUpperCase());
};
const drawerRef = ref(null);
let returnFocus = null;
const overlayRef = ref(null);

function formatPrice(price) {
    return formatAmount(price, page.props);
}

const sizeLabel = (item) => {
    const { top_size: top, bottom_size: bottom } = item.options || {};
    if (top && bottom) return `Top ${top} · Bottom ${bottom}`;
    return top || bottom ? `Size ${top || bottom}` : null;
};

// Cinematic Animation Logic
watch(() => cart.isOpen, async (open) => {
    await nextTick();
    if (open) {
        returnFocus = document.activeElement;
        document.body.style.overflow = 'hidden';
        drawerRef.value?.querySelector('button')?.focus();
        // Quick and quiet: the bag should feel instant, not staged.
        animate(overlayRef.value, { opacity: [0, 1], duration: 200, easing: 'easeOutQuad' });
        animate(drawerRef.value, { translateX: ['100%', '0%'], duration: 300, easing: 'easeOutCubic' });
    }
}, { immediate: true });

const closeDrawer = () => {
    animate(drawerRef.value, {
        translateX: '100%',
        duration: 220,
        easing: 'easeInQuad'
    });
    animate(overlayRef.value, {
        opacity: 0,
        duration: 200,
        easing: 'easeInQuad',
        onComplete: () => {
            cart.isOpen = false;
            document.body.style.overflow = '';
            returnFocus?.focus();
        }
    });
};
function handleKeys(event) {
    if (!cart.isOpen) return;
    if (event.key === 'Escape') { event.preventDefault(); closeDrawer(); }
    if (event.key === 'Tab') {
        const controls = [...(drawerRef.value?.querySelectorAll('a[href], button:not([disabled]), input') || [])].filter(el => el.offsetParent !== null);
        const first = controls[0], last = controls.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    }
}
onMounted(() => document.addEventListener('keydown', handleKeys));
onUnmounted(() => { document.removeEventListener('keydown', handleKeys); document.body.style.overflow = ''; });
</script>

<template>
    <div v-if="cart.isOpen" class="fixed inset-0 z-[150] overflow-hidden flex justify-end">
        <div ref="overlayRef" @click="closeDrawer" class="absolute inset-0 bg-black/60 backdrop-blur-sm opacity-0"></div>

        <div
            role="dialog" aria-modal="true" aria-labelledby="bag-heading"
            ref="drawerRef"
            class="relative z-10 flex h-full w-full max-w-md flex-col border-l border-black/10 bg-surface shadow-2xl dark:border-white/10 dark:bg-black translate-x-full"
        >
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-black/5 px-5 py-4 dark:border-white/5 sm:px-6">
                <div>
                    <h2 id="bag-heading" class="font-headline text-xl font-semibold text-on-surface">Your Bag</h2>
                    <p class="text-sm text-on-surface-variant">{{ cart.totalItems }} {{ cart.totalItems === 1 ? 'piece' : 'pieces' }}</p>
                </div>
                <button aria-label="Close bag" @click="closeDrawer" class="flex h-11 w-11 items-center justify-center rounded-full text-on-surface transition-colors hover:bg-black/5 hover:text-primary dark:hover:bg-white/5">
                    <span class="material-symbols-outlined" aria-hidden="true">close</span>
                </button>
            </div>

            <!-- Items -->
            <div class="flex-grow overflow-y-auto px-5 sm:px-6 no-scrollbar">
                <div v-if="cart.items.length === 0" class="flex h-full flex-col items-center justify-center gap-4 text-center">
                    <span class="icon-tile"><span class="material-symbols-outlined text-xl" aria-hidden="true">shopping_bag</span></span>
                    <h3 class="font-headline text-xl font-semibold text-on-surface">Your bag is empty</h3>
                    <p class="max-w-xs text-sm text-on-surface-variant">Pick a piece and your size. It will wait here until you check out.</p>
                    <Link :href="route('shop')" @click="closeDrawer" class="action-primary mt-2">Shop the Collection</Link>
                    <Link :href="route('custom-order')" @click="closeDrawer" class="text-sm font-medium text-primary hover:underline">Or start a custom order</Link>
                </div>

                <ul v-else class="divide-y divide-black/5 dark:divide-white/5">
                    <li v-for="item in cart.items" :key="item.line_key" class="flex gap-4 py-5">
                        <Link :href="route('shop.show', item.slug)" @click="closeDrawer" class="h-28 w-20 flex-shrink-0 overflow-hidden rounded-lg bg-surface-container">
                            <img :src="item.image" :alt="item.name" class="h-full w-full object-cover" />
                        </Link>
                        <div class="flex min-w-0 flex-grow flex-col">
                            <div class="flex items-start justify-between gap-3">
                                <Link :href="route('shop.show', item.slug)" @click="closeDrawer" class="font-semibold leading-snug text-on-surface hover:text-primary">{{ item.name }}</Link>
                                <span class="shrink-0 font-semibold text-on-surface">{{ formatPrice(item.price * item.quantity) }}</span>
                            </div>
                            <p v-if="sizeLabel(item)" class="mt-1 text-sm text-on-surface-variant">{{ sizeLabel(item) }}</p>
                            <div class="mt-auto flex items-center justify-between pt-3">
                                <div class="flex items-center gap-1 rounded-full border border-black/10 p-0.5 dark:border-white/10">
                                    <button :aria-label="`Decrease quantity of ${item.name}`" @click="cart.updateQuantity(item.line_key, -1)" class="h-9 w-9 rounded-full text-lg text-on-surface transition-colors hover:bg-black/5 hover:text-primary dark:hover:bg-white/10">
                                        {{ item.quantity === 1 ? '×' : '−' }}
                                    </button>
                                    <span class="min-w-[1.5rem] text-center text-sm font-medium text-on-surface" aria-live="polite">{{ item.quantity }}</span>
                                    <button :aria-label="`Increase quantity of ${item.name}`" :disabled="!cart.canIncrease(item.line_key)" @click="cart.updateQuantity(item.line_key, 1)" class="h-9 w-9 rounded-full text-lg text-on-surface transition-colors hover:bg-black/5 hover:text-primary disabled:opacity-30 dark:hover:bg-white/10">+</button>
                                </div>
                                <button @click="cart.removeItem(item.line_key)" class="min-h-[36px] text-sm text-on-surface-variant transition-colors hover:text-red-500">Remove</button>
                            </div>
                            <p v-if="!cart.canIncrease(item.line_key)" class="mt-2 text-xs text-on-surface-variant">That’s all we have of this piece right now.</p>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Checkout -->
            <div v-if="cart.items.length > 0" class="border-t border-black/5 bg-surface-container px-5 py-5 dark:border-white/5 dark:bg-[#050505] sm:px-6" style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))">
                <div class="mb-4 flex items-baseline justify-between">
                    <span class="text-on-surface">Total <span class="text-sm text-on-surface-variant">· delivery included</span></span>
                    <span class="font-headline text-2xl font-bold text-on-surface">{{ formatPrice(cart.totalPrice) }}</span>
                </div>
                <Link :href="route('checkout')" prefetch @click="closeDrawer" class="action-primary w-full py-4 text-base">
                    <span class="material-symbols-outlined text-lg" aria-hidden="true">lock</span>
                    Checkout with M-Pesa
                </Link>
                <div class="mt-3 flex items-center justify-between text-sm">
                    <button @click="closeDrawer" class="min-h-[40px] font-medium text-on-surface-variant hover:text-on-surface">Keep shopping</button>
                    <a :href="whatsappUrl" target="_blank" rel="noopener" class="inline-flex min-h-[40px] items-center gap-1.5 text-on-surface-variant hover:text-on-surface">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" style="fill:#25D366" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.57-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        Order on WhatsApp instead
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
