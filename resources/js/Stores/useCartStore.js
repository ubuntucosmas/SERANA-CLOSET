import { defineStore } from 'pinia';
import { ref, computed, watch } from 'vue';
import { availableStock } from '../Support/stock.js';

export const useCartStore = defineStore('cart', () => {
    const items = ref([]);
    const isOpen = ref(false);

    // A stable line key keeps the same product in different sizes independent.
    const lineKey = (item) => `${item.id}:${JSON.stringify(Object.entries(item.options || {}).filter(([, value]) => value != null && value !== '').sort(([a], [b]) => a.localeCompare(b)))}`;
    if (typeof window !== 'undefined') {
        try {
            const saved = JSON.parse(localStorage.getItem('serana_bag') || '[]');
            if (Array.isArray(saved)) items.value = saved.filter(item =>
                item && item.id && Number.isFinite(Number(item.price)) && Number(item.price) >= 0 &&
                Number.isInteger(item.quantity) && item.quantity > 0
            ).map(item => ({ ...item, line_key: lineKey(item) }));
        } catch { items.value = []; }
    }
    watch(items, (value) => {
        try { localStorage.setItem('serana_bag', JSON.stringify(value)); } catch { /* Bag still works when storage is unavailable. */ }
    }, { deep: true });

    // Units a shopper may hold in the bag: unpaid holds by others count too.
    const capOf = (x) => availableStock(x) ?? Infinity;

    const totalItems = computed(() => items.value.reduce((acc, item) => acc + item.quantity, 0));
    const totalPrice = computed(() => items.value.reduce((acc, item) => acc + (item.price * item.quantity), 0));

    function addItem(product) {
        if (product.in_stock === false) return false;
        const currentQty = items.value.filter(item => item.id === product.id).reduce((sum, item) => sum + item.quantity, 0);
        if (currentQty >= capOf(product)) return false;
        const key = lineKey(product);
        const existing = items.value.find(item => item.line_key === key);

        const price = typeof product.price === 'string'
            ? parseFloat(product.price.replace(/[^0-9.]/g, ''))
            : parseFloat(product.price);

        if (existing) {
            existing.quantity++;
        } else {
            items.value.push({
                id: product.id,
                line_key: key,
                garment_type: product.garment_type,
                name: product.name,
                price: price,
                display_price: product.price,
                image: product.image_url || '/images/hero_editorial.png',
                quantity: 1,
                slug: product.slug,
                is_bespoke: product.is_customizable || false,
                options: product.options || null,
                // Carried so the +/- stepper can respect the batch cap too.
                batch_limit: product.batch_limit ?? null,
                batch_sold: product.batch_sold ?? 0,
                available_stock: product.available_stock ?? null
            });
        }

        isOpen.value = true;
        return true;
    }

    function removeItem(key) {
        items.value = items.value.filter(item => item.line_key !== key);
    }

    function updateQuantity(key, delta) {
        const item = items.value.find(item => item.line_key === key);
        if (!item || !Number.isInteger(delta)) return;
        const total = items.value.filter(line => line.id === item.id).reduce((sum, line) => sum + line.quantity, 0);
        if (delta > 0 && total + delta > capOf(item)) return;
        item.quantity += delta;
        if (item.quantity <= 0) removeItem(key);
    }

    /** Units of this product the customer may still add, across all its size lines. */
    function remainingFor(key) {
        const item = items.value.find(line => line.line_key === key);
        if (!item || item.batch_limit == null) return Infinity;
        const total = items.value.filter(line => line.id === item.id).reduce((sum, line) => sum + line.quantity, 0);
        return Math.max(0, capOf(item) - total);
    }
    const canIncrease = key => remainingFor(key) > 0;

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
        updateQuantity,
        remainingFor,
        canIncrease,
        toggleBag,
        clearBag
    };
});
