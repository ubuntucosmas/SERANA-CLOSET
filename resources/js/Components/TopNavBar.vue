<template>
    <nav
        class="fixed top-0 w-full z-50 transition-all duration-300"
        :class="scrolled ? 'bg-surface/60 backdrop-blur-xl shadow-2xl border-b dark:border-white/5 border-black/5' : 'bg-transparent pt-6'"
    >
        <div class="flex items-center justify-between px-4 py-4 sm:px-8 sm:py-6 lg:px-10 lg:py-8 w-full max-w-[1920px] mx-auto relative h-full">

            <!-- 1. Left Vector: Brand & Pulse -->
            <div class="flex items-center gap-10 relative z-20">
                <Link :href="route('home')" class="flex-shrink-0">
                    <ApplicationLogo class="[&_h1]:text-3xl" />
                </Link>
            </div>

            <!-- 2. Central Vector: Navigation Links (Absolute Centered) -->
            <div class="hidden lg:flex items-center gap-12 absolute left-1/2 -translate-x-1/2 z-10">
                <Link :href="route('home')"
                    class="nav-link" :class="{ 'active': isActive('home') }">
                    Home
                </Link>

                <!-- Shop Dropdown -->
                <div class="group relative">
                    <Link :href="route('shop')"
                        class="nav-link flex items-center gap-1" :class="{ 'active': isActive('shop*') }">
                        Shop
                        <span class="material-symbols-outlined text-[12px] opacity-40 group-hover:rotate-180 transition-transform duration-300">keyboard_arrow_down</span>
                    </Link>
                    <div class="absolute left-1/2 -translate-x-1/2 top-full pt-8 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-400 w-[580px]">
                        <div class="dark:bg-black/40 bg-white/40 backdrop-blur-3xl border dark:border-white/10 border-black/10 rounded-2xl shadow-2xl p-10 grid grid-cols-2 gap-10">
                            <div class="space-y-6">
                                <div>
                                    <h4 class="font-headline text-[13px] text-primary tracking-[0.4em] font-medium mb-3">Browse the shop</h4>
                                    <p class="font-body text-[12px] dark:text-white/40 text-black/40 leading-relaxed opacity-80">Our best pieces. Made to last. New items every season.</p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <Link :href="route('shop', {category: 'ladies-wear'})" class="dropdown-link" :class="{ 'text-primary': $page.url.includes('ladies-wear') }">Women</Link>
                                    <Link :href="route('shop', {category: 'mens-collection'})" class="dropdown-link" :class="{ 'text-primary': $page.url.includes('mens-collection') }">Men</Link>
                                    <Link :href="route('shop', {category: 'teens-archive'})" class="dropdown-link" :class="{ 'text-primary': $page.url.includes('teens-archive') }">Teens</Link>
                                    <Link :href="route('shop', {category: 'kids-collection'})" class="dropdown-link" :class="{ 'text-primary': $page.url.includes('kids-collection') }">Kids</Link>
                                    <Link :href="route('shop', {category: 'ready-to-wear'})" class="dropdown-link" :class="{ 'text-primary': $page.url.includes('ready-to-wear') }">Ready To Wear</Link>
                                    <Link :href="route('shop', {category: 'accessories'})" class="dropdown-link" :class="{ 'text-primary': $page.url.includes('accessories') }">Accessories</Link>
                                </div>
                                <Link :href="route('shop')" class="inline-flex items-center gap-2 text-[10px] font-headline font-medium text-primary hover:dark:text-white text-on-surface transition-colors tracking-[0.3em]">
                                    View all items_ <span class="material-symbols-outlined text-sm">arrow_right_alt</span>
                                </Link>
                            </div>
                            <div class="aspect-square bg-transparent border dark:border-white/5 border-black/5 rounded-sm p-1 overflow-hidden border dark:border-white/5 border-black/5 relative">
                                <img src="/images/ready_to_wear_rack.png" class="w-full h-full object-cover opacity-60 grayscale hover:grayscale-0 transition-all duration-1000" alt="Shop" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Custom Order Dropdown -->
                <div class="group relative">
                    <Link :href="route('custom-order')"
                        class="nav-link flex items-center gap-2" :class="{ 'active': isActive('custom-order*') }">
                        Custom
                        <span class="relative flex h-1.5 w-1.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-primary"></span>
                        </span>
                    </Link>
                    <div class="absolute left-1/2 -translate-x-1/2 top-full pt-8 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-400 w-[500px]">
                        <div class="dark:bg-black/40 bg-white/40 backdrop-blur-3xl border dark:border-white/10 border-black/10 rounded-2xl shadow-2xl p-10 grid grid-cols-2 gap-10">
                            <div class="aspect-[3/4] bg-transparent border dark:border-white/5 border-black/5 rounded-sm p-1 overflow-hidden border dark:border-white/5 border-black/5">
                                <img src="/images/detailed_texture.png" class="w-full h-full object-cover opacity-60 grayscale hover:grayscale-0 transition-all duration-1000" alt="Custom Order" />
                            </div>
                            <div class="space-y-6 flex flex-col justify-center">
                                <span class="text-[9px] font-medium tracking-[0.4em] text-primary">Made to order</span>
                                <div>
                                    <h4 class="font-headline text-[13px] dark:text-white text-on-surface tracking-[0.3em] mb-3">Start your order</h4>
                                    <p class="font-body text-[12px] dark:text-white/40 text-black/40 leading-relaxed opacity-80 mb-6">Made for you. Built to your exact measurements.</p>
                                    <Link :href="route('custom-order')" class="bg-primary text-black font-headline text-[10px] tracking-[0.4em] font-medium px-6 py-4 rounded-xl hover:bg-white transition-all flex items-center gap-2 w-fit">
                                    Get started
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <Link :href="route('about')"
                    class="nav-link" :class="{ 'active': isActive('about') }">
                    About
                </Link>

                <Link :href="route('circle')"
                    class="nav-link" :class="{ 'active': isActive('circle') }">
                    Community
                </Link>
            </div>

            <!-- 3. Right Vector: Absolute Actions (Registry & Settings) -->
            <div class="flex items-center gap-10 relative z-20">
                <div class="flex items-center gap-6 pr-6 border-r dark:border-white/10 border-black/10">
                    <ThemeToggle />
                    <a :href="whatsappUrl" target="_blank" title="Studio WhatsApp" class="icon-btn dark:text-white/40 text-black/40 hover:text-primary !p-0">
                        <span class="material-symbols-outlined !text-[28px]">chat</span>
                    </a>
                </div>

                <div class="flex items-center gap-8">
                    <button @click="cart.isOpen = true" title="Shopping Bag" class="relative icon-btn dark:text-white/80 text-black/80 hover:text-primary !p-0">
                        <span class="material-symbols-outlined !text-[26px]">shopping_bag</span>
                        <span v-if="cart.totalItems > 0" class="absolute -top-2 -right-2 bg-primary text-black text-[9px] font-medium w-4 h-4 rounded-full flex items-center justify-center shadow-[0_0_10px_rgba(57, 255, 20,0.4)]">
                            {{ cart.totalItems }}
                        </span>
                    </button>

                    <template v-if="!$page.props.auth?.user">
                        <button @click="showLogin = true" class="font-headline font-medium text-[10px] tracking-[0.3em] dark:text-white text-on-surface hover:text-primary transition-all pr-4">
                            Log in
                        </button>
                    </template>
                    <template v-else>
                        <Link v-if="$page.props.auth?.user?.role === 'admin'" :href="route('studio.index')" class="icon-btn dark:text-white/60 text-black/60 hover:text-primary" title="Studio Admin">
                            <span class="material-symbols-outlined !text-[28px]">dashboard</span>
                        </Link>
                        <Link :href="route('profile.edit')" class="icon-btn dark:text-white/80 text-black/80 hover:text-primary" title="My Profile">
                            <span class="material-symbols-outlined !text-[26px]">account_circle</span>
                        </Link>
                    </template>

                    <!-- Mobile Toggle -->
                    <button @click="menuOpen = !menuOpen" class="lg:hidden icon-btn text-primary">
                        <span class="material-symbols-outlined !text-[28px]">{{ menuOpen ? 'close' : 'menu' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <transition enter-active-class="transition-all duration-300 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition-all duration-200 ease-in" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">
            <div v-if="menuOpen" class="md:hidden border-t dark:border-white/10 border-black/10 bg-surface/60 backdrop-blur-xl px-6 pb-6 pt-4 space-y-3">
                <Link @click="menuOpen = false" :href="route('home')" class="mobile-link" :class="{ 'text-primary border-primary/20 bg-primary/5 px-2 -mx-2 rounded font-medium': isActive('home') }">Home</Link>
                <Link @click="menuOpen = false" :href="route('shop')" class="mobile-link" :class="{ 'text-primary border-primary/20 bg-primary/5 px-2 -mx-2 rounded font-medium': isActive('shop*') }">Shop</Link>
                <Link @click="menuOpen = false" :href="route('shop', {category: 'teens-archive'})" class="mobile-link" :class="{ 'text-primary border-primary/20 bg-primary/5 px-2 -mx-2 rounded font-medium': isActive('shop', {category: 'teens-archive'}) }">Teens</Link>
                <Link @click="menuOpen = false" :href="route('shop', {category: 'kids-collection'})" class="mobile-link" :class="{ 'text-primary border-primary/20 bg-primary/5 px-2 -mx-2 rounded font-medium': isActive('shop', {category: 'kids-collection'}) }">Kids</Link>
                <Link @click="menuOpen = false" :href="route('custom-order')" class="mobile-link" :class="{ 'text-primary border-primary/20 bg-primary/5 px-2 -mx-2 rounded font-medium': isActive('custom-order*') }">Custom Order</Link>
                <Link @click="menuOpen = false" :href="route('about')" class="mobile-link" :class="{ 'text-primary border-primary/20 bg-primary/5 px-2 -mx-2 rounded font-medium': isActive('about') }">About</Link>
                <Link @click="menuOpen = false" :href="route('circle')" class="mobile-link" :class="{ 'text-primary border-primary/20 bg-primary/5 px-2 -mx-2 rounded font-medium': isActive('circle') }">Community</Link>
                <Link @click="menuOpen = false" :href="route('profile.designs')" class="mobile-link" :class="{ 'text-primary border-primary/20 bg-primary/5 px-2 -mx-2 rounded font-medium': isActive('profile.designs*') }">My Orders</Link>
                <button v-if="!$page.props.auth?.user" @click="showLogin = true; menuOpen = false" class="mobile-link text-left text-primary" :class="{ 'text-primary border-primary/20 bg-primary/5 px-2 -mx-2 rounded font-medium': isActive('login') }">Log In</button>
            </div>
        </transition>

        <!-- Identity Access Modal -->
        <LoginModal v-if="showLogin" @close="showLogin = false" />
    </nav>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import LoginModal from '@/Components/LoginModal.vue';
import { useCartStore } from '@/Stores/useCartStore';

const cart = useCartStore();
const menuOpen = ref(false);
const showLogin = ref(false);
const scrolled = ref(false);
const page = usePage();

const whatsappUrl = `https://wa.me/${page.props.whatsapp_number}`; // Standard Serana Studio Line

function isActive(routeName) {
    try {
        return route().current(routeName);
    } catch {
        return false;
    }
}

onMounted(() => {
    window.addEventListener('scroll', () => {
        scrolled.value = window.scrollY > 50;
    });
});

onUnmounted(() => {
    window.removeEventListener('scroll', () => {
        scrolled.value = window.scrollY > 50;
    });
});
</script>

<style scoped lang="postcss">
/* Nav link base */
.nav-link {
    @apply relative font-headline font-medium text-[16px] tracking-[0.2em] transition-all duration-300 dark:text-white text-on-surface hover:text-primary py-1;
}

/* Premium active indicator (dot or line) */
.nav-link.active {
    @apply text-primary;
}

.nav-link::after {
    content: '';
    @apply absolute -bottom-1 left-0 w-0 h-[1.5px] bg-primary transition-all duration-500 opacity-0;
}

.nav-link.active::after {
    @apply w-full opacity-100;
}

/* Dropdown sub-link */
.dropdown-link {
    @apply block font-headline font-medium text-[13px] tracking-widest dark:text-white/40 text-black/40 hover:text-primary transition-colors;
}

/* Icon button */
.icon-btn {
    @apply hover:text-primary transition-colors;
}

/* Mobile menu link */
.mobile-link {
    @apply block font-headline font-medium text-sm tracking-[0.2em] dark:text-white text-on-surface hover:text-primary py-2.5 transition-colors border-b dark:border-white/5 border-black/5;
}

/* Pulse slide transition */
.pulse-slide-enter-active, .pulse-slide-leave-active {
    transition: all 0.5s ease;
}
.pulse-slide-enter-from {
    transform: translateY(100%);
    opacity: 0;
}
.pulse-slide-leave-to {
    transform: translateY(-100%);
    opacity: 0;
}
</style>
