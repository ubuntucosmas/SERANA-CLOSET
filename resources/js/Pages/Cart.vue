<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { useCartStore } from '@/Stores/useCartStore';
import { useCurrency } from '@/Composables/useCurrency';
const cart = useCartStore();
const { formatAmount } = useCurrency();
</script>

<template>
    <StorefrontLayout>
        <Head title="Your Bag — Serana" />
        <section class="page-container pb-24">
            <PageHeader title="Your Bag" subtitle="Review your pieces and sizes before checkout. Availability and prices are confirmed when you place your order." />
            <div v-if="!cart.items.length" class="card p-8 text-center">
                <h2 class="text-xl mb-3">Your bag is empty</h2>
                <p class="text-on-surface-variant mb-6">Browse the collection to find your next piece.</p>
                <Link :href="route('shop')" class="action-primary">Shop Collection</Link>
            </div>
            <div v-else class="grid lg:grid-cols-[1fr_300px] gap-8">
                <ul class="divide-y divide-outline-variant">
                    <li v-for="item in cart.items" :key="item.line_key" class="flex gap-4 py-5">
                        <img :src="item.image" :alt="item.name" class="w-20 h-28 object-cover rounded-lg" />
                        <div class="flex-1 min-w-0">
                            <Link :href="route('shop.show', item.slug)" class="text-lg font-semibold">{{ item.name }}</Link>
                            <p v-if="item.options?.top_size || item.options?.bottom_size" class="text-sm text-on-surface-variant">{{ item.options.top_size && item.options.bottom_size ? `Top ${item.options.top_size} · Bottom ${item.options.bottom_size}` : `Size ${item.options.top_size || item.options.bottom_size}` }}</p>
                            <!-- A line saved before sizes were tracked: checkout will ask for one, so offer it here first. -->
                            <Link v-else-if="!item.variant_ids?.length" :href="route('shop.show', item.slug)" class="text-sm text-primary underline">Choose a size</Link>
                            <p class="my-2 font-semibold">{{ formatAmount(item.price * item.quantity) }}</p>
                            <div class="flex items-center gap-4">
                                <button :aria-label="`Decrease quantity of ${item.name}`" @click="cart.updateQuantity(item.line_key, -1)" class="w-11 h-11 border border-outline-variant rounded-lg">−</button>
                                <span aria-live="polite">{{ item.quantity }}</span>
                                <button :aria-label="`Increase quantity of ${item.name}`" :disabled="!cart.canIncrease(item.line_key)" @click="cart.updateQuantity(item.line_key, 1)" class="w-11 h-11 border border-outline-variant rounded-lg disabled:opacity-30">+</button>
                                <button @click="cart.removeItem(item.line_key)" class="text-sm underline">Remove</button>
                            </div>
                            <p v-if="!cart.canIncrease(item.line_key)" class="mt-2 text-xs text-on-surface-variant">That’s all we have of this piece right now.</p>
                        </div>
                    </li>
                </ul>
                <aside class="bg-surface-container rounded-2xl p-6 h-fit lg:sticky lg:top-28">
                    <h2 class="text-xl mb-5">Order Summary</h2>
                    <p class="flex justify-between mb-3"><span>Delivery</span><span>Included</span></p>
                    <p class="flex justify-between text-lg font-semibold mb-6"><span>Total</span><span>{{ formatAmount(cart.totalPrice) }}</span></p>
                    <Link :href="route('checkout')" class="action-primary w-full py-4">Checkout</Link>
                    <Link :href="route('shop')" class="block text-center text-sm mt-5 underline">Continue Shopping</Link>
                </aside>
            </div>
        </section>
    </StorefrontLayout>
</template>
