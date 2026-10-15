<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { useCartStore } from '@/Stores/useCartStore';
import { useCurrency } from '@/Composables/useCurrency';
const cart = useCartStore();
const { formatAmount } = useCurrency();
</script>

<template>
    <StorefrontLayout hideMobileCard>
        <Head title="Your Bag — Serana" />
        <section class="max-w-5xl mx-auto px-6 pb-24">
            <h1 class="font-headline text-4xl mb-3">Your Bag</h1>
            <p class="text-on-surface-variant mb-8">Review your pieces and sizes before checkout. Availability and prices are confirmed when you place your order.</p>
            <div v-if="!cart.items.length" class="rounded-2xl bg-surface-container p-8 text-center">
                <h2 class="text-xl mb-3">Your bag is empty</h2>
                <p class="text-on-surface-variant mb-6">Browse the collection to find your next piece.</p>
                <Link :href="route('shop')" class="inline-block rounded-full bg-primary text-background px-7 py-3 font-semibold">Shop Collection</Link>
            </div>
            <div v-else class="grid lg:grid-cols-[1fr_300px] gap-8">
                <ul class="divide-y divide-outline-variant">
                    <li v-for="item in cart.items" :key="item.line_key" class="flex gap-4 py-5">
                        <img :src="item.image" :alt="item.name" class="w-20 h-28 object-cover rounded-lg" />
                        <div class="flex-1 min-w-0">
                            <Link :href="route('shop.show', item.slug)" class="text-lg font-semibold">{{ item.name }}</Link>
                            <p v-for="(value, key) in Object.fromEntries(Object.entries(item.options || {}).filter(([, value]) => value))" :key="key" class="text-sm text-on-surface-variant">{{ key.replaceAll('_', ' ') }}: {{ value }}</p>
                            <p class="my-2 font-semibold">{{ formatAmount(item.price * item.quantity) }}</p>
                            <div class="flex items-center gap-4">
                                <button :aria-label="`Decrease quantity of ${item.name}`" @click="cart.updateQuantity(item.line_key, -1)" class="w-11 h-11 border border-outline-variant rounded-lg">−</button>
                                <span aria-live="polite">{{ item.quantity }}</span>
                                <button :aria-label="`Increase quantity of ${item.name}`" @click="cart.updateQuantity(item.line_key, 1)" class="w-11 h-11 border border-outline-variant rounded-lg">+</button>
                                <button @click="cart.removeItem(item.line_key)" class="text-sm underline">Remove</button>
                            </div>
                        </div>
                    </li>
                </ul>
                <aside class="bg-surface-container rounded-2xl p-6 h-fit lg:sticky lg:top-28">
                    <h2 class="text-xl mb-5">Order Summary</h2>
                    <p class="flex justify-between mb-3"><span>Delivery</span><span>Included</span></p>
                    <p class="flex justify-between text-lg font-semibold mb-6"><span>Total</span><span>{{ formatAmount(cart.totalPrice) }}</span></p>
                    <Link :href="route('checkout')" class="block text-center rounded-full bg-primary text-background py-4 font-semibold">Checkout</Link>
                    <Link :href="route('shop')" class="block text-center text-sm mt-5 underline">Continue Shopping</Link>
                </aside>
            </div>
        </section>
    </StorefrontLayout>
</template>
