<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { useCartStore } from '@/Stores/useCartStore';
import { Link, usePage } from '@inertiajs/vue3';
import { animate } from 'animejs';
import { useCurrency } from '@/Composables/useCurrency';

const page = usePage();
const cart = useCartStore();
const { formatAmount } = useCurrency();

// Payment options come from Studio → Settings → Payments.
const payments = computed(() => page.props.payments || { mpesa: true, whatsapp: false });
const checkoutLabel = computed(() => (payments.value.mpesa && payments.value.whatsapp ? 'Checkout'
    : payments.value.mpesa ? 'Checkout with M-Pesa' : 'Checkout · order on WhatsApp'));
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
            data-notification-modal role="dialog" aria-modal="true" aria-labelledby="bag-heading"
            ref="drawerRef"
            class="relative z-10 flex h-full w-full max-w-md flex-col border-l border-black/10 bg-surface shadow-2xl dark:border-white/10 dark:bg-black translate-x-full"
        >
            <div data-notification-host class="relative z-20 shrink-0 bg-surface"></div>
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
                    <span class="material-symbols-outlined text-lg" aria-hidden="true">{{ payments.mpesa ? 'lock' : 'chat' }}</span>
                    {{ checkoutLabel }}
                </Link>
                <div class="mt-3 flex items-center justify-between text-sm">
                    <button @click="closeDrawer" class="min-h-[40px] font-medium text-on-surface-variant hover:text-on-surface">Keep shopping</button>
                    <!-- Through checkout, so the order is recorded (code, stock hold, tracking, Studio). -->
                    <Link v-if="payments.mpesa && payments.whatsapp" :href="route('checkout', { method: 'whatsapp' })" @click="closeDrawer" class="inline-flex min-h-[40px] items-center gap-1.5 text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-base text-[#25D366]" aria-hidden="true">chat</span>
                        Order on WhatsApp instead
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
