import { defineStore } from 'pinia';
import { ref, computed, watch } from 'vue';

export const useCartStore = defineStore('cart', () => {
    const items = ref([]);
    const isOpen = ref(false);

    // Persist to LocalStorage for a "Locked-In" experience
    if (typeof window !== 'undefined') {
        const saved = localStorage.getItem('serana_bag');
        if (saved) items.value = JSON.parse(saved);
    }

    watch(items, (val) => {
        localStorage.setItem('serana_bag', JSON.stringify(val));
    }, { deep: true });

    const totalItems = computed(() => items.value.reduce((acc, item) => acc + item.quantity, 0));
    const totalPrice = computed(() => items.value.reduce((acc, item) => acc + (item.price * item.quantity), 0));

    function addItem(product) {
        // Check stock availability before adding
        if (product.batch_limit) {
            const available = product.batch_limit - (product.batch_sold || 0);
            const currentQty = items.value.find(item => item.id === product.id)?.quantity ?? 0;
            
            if (currentQty >= available) {
                window.dispatchEvent(new CustomEvent('serana-toast', {
                    detail: { message: `Only ${available} available for "${product.name}"`, type: 'error' }
                }));
                return;
            }
        }

        const existing = items.value.find(item => item.id === product.id);
        const price = typeof product.price === 'string'
            ? parseFloat(product.price.replace(/[^0-9.]/g, ''))
            : parseFloat(product.price);

        if (existing) {
            // Also validate stock on quantity increment
            if (product.batch_limit) {
                const available = product.batch_limit - (product.batch_sold || 0);
                if (existing.quantity >= available) {
                    window.dispatchEvent(new CustomEvent('serana-toast', {
                        detail: { message: `Maximum stock reached for "${product.name}"`, type: 'error' }
                    }));
                    return;
                }
            }
            existing.quantity++;
        } else {
            items.value.push({
                id: product.id,
                name: product.name,
                price: price,
                display_price: typeof product.price === 'number' ? `KSh ${product.price.toLocaleString()}` : product.price,
                image: product.image_url || '/images/hero_editorial.png',
                quantity: 1,
                slug: product.slug,
                is_bespoke: product.is_customizable || false
            });
        }

        // Dispatch Global Toast for Bag Update
        window.dispatchEvent(new CustomEvent('serana-toast', {
            detail: { message: `"${product.name}" added to your curated bag.`, type: 'success' }
        }));

        isOpen.value = true; // Auto-reveal the bag for momentum
    }

    function removeItem(id) {
        items.value = items.value.filter(item => item.id !== id);
    }

    function updateQuantity(id, delta) {
        const item = items.value.find(i => i.id === id);
        if (item) {
            item.quantity += delta;
            if (item.quantity <= 0) {
                removeItem(id);
            }
        }
    }

    function toggleBag() {
        isOpen.value = !isOpen.value;
    }

    function clearBag() {
        items.value = [];
    }

    return {
        items,
        isOpen,
        totalItems,
        totalPrice,
        addItem,
        removeItem,
        toggleBag,
        clearBag
    };
});
