<template>
<nav
    aria-label="Main"
    class="sticky top-0 z-50 w-full border-b border-black/5 bg-background/85 backdrop-blur-xl transition-shadow duration-300 dark:border-white/5"
    :class="scrolled ? 'shadow-lg shadow-black/5 dark:shadow-black/30' : ''"
>
    <div class="page-container relative flex h-[72px] sm:h-[88px] items-center justify-between gap-2 sm:gap-4 xl:gap-6">

            <!-- 1. Left Vector: Brand & Pulse -->
            <div class="relative z-20 flex items-center">
<Link :href="route('home')" class="flex-shrink-0" aria-label="Serana Closet home">
                     <ApplicationLogo class="" />
                </Link>
            </div>

            <!-- 2. Central Vector: Navigation Links (Absolute Centered) -->
            <div class="hidden items-center gap-4 lg:flex xl:gap-7">
                <Link :href="route('home')" prefetch
                    class="nav-link" :class="{ 'active': isActive('home') }" :aria-current="isActive('home') ? 'page' : undefined">
                    Home
                </Link>

                <Link v-if="$page.props.drops_enabled" :href="route('drops')"
                    class="nav-link flex items-center gap-1.5" :class="{ 'active': isActive('drops*') }" :aria-current="isActive('drops*') ? 'page' : undefined">
                    Drops
                    <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                </Link>

                <!-- Shop Dropdown -->
                <div class="group relative">
                    <Link :href="route('shop')" prefetch
                        class="nav-link flex items-center gap-1" :class="{ 'active': isActive('shop*') }" :aria-current="isActive('shop*') ? 'page' : undefined">
                        Shop
                        <svg class="h-4 w-4 shrink-0 text-on-surface-variant transition-transform duration-200 group-hover:rotate-180 group-focus-within:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                    </Link>
                    <div class="absolute left-1/2 -translate-x-1/2 top-full pt-5 opacity-0 invisible pointer-events-none group-hover:visible group-focus-within:visible group-hover:opacity-100 group-hover:pointer-events-auto group-focus-within:opacity-100 group-focus-within:pointer-events-auto transition-all duration-300 w-[560px]">
                        <div class="card grid grid-cols-2 gap-8 p-6 shadow-2xl">
                            <div class="space-y-6">
                                <div>
                                    <h4 class="font-headline text-[13px] text-primary tracking-wider font-medium mb-3">Browse the shop</h4>
                                    <p class="font-body text-[12px] dark:text-white/40 text-black/40 leading-relaxed opacity-80">Our best pieces. Made to last. New items every season.</p>
                                </div>
                                <div class="grid grid-cols-2 gap-x-8 gap-y-4">
                                    <Link v-for="cat in ($page.props.categories || []).slice(0, 6)" :key="cat.id" 
                                          :href="route('shop', {category: cat.slug})" 
                                          class="dropdown-link" 
                                          :class="{ 'text-primary': $page.url.includes(cat.slug) }">
                                        {{ cat.name }}
                                    </Link>
                                </div>
                                <Link :href="route('shop')" class="inline-flex items-center gap-2 text-[10px] font-headline font-medium text-primary hover:dark:text-white text-on-surface transition-colors tracking-wider">
                                    Shop Collection <span class="material-symbols-outlined text-sm">arrow_right_alt</span>
                                </Link>
                            </div>
                            <div class="card-media aspect-square">
                                <img src="/images/ready_to_wear_rack.png" class="h-full w-full object-cover" alt="" loading="lazy" />
                            </div>
                        </div>
                    </div>
                </div>

                <Link :href="route('custom-order')" prefetch class="nav-link" :class="{ 'active': isActive('custom-order*') }" :aria-current="isActive('custom-order*') ? 'page' : undefined">
                    Custom Order
                </Link>

                <Link :href="route('about')"
                    class="nav-link" :class="{ 'active': isActive('about') }" :aria-current="isActive('about') ? 'page' : undefined">
                    About
                </Link>

                <Link :href="route('circle')"
                    class="nav-link" :class="{ 'active': isActive('circle') }" :aria-current="isActive('circle') ? 'page' : undefined">
                    Community
                </Link>

                <Link :href="route('track')" prefetch
                    class="nav-link" :class="{ 'active': isActive('track') }" :aria-current="isActive('track') ? 'page' : undefined">
                    Track Order
                </Link>
            </div>

            <!-- 3. Right Vector: Absolute Actions (Registry & Settings) -->
            <div class="relative z-20 flex items-center gap-1 sm:gap-3">
                <div class="hidden items-center gap-3 border-r border-black/10 pr-3 dark:border-white/10 lg:flex">
                    <a :href="whatsappUrl" target="_blank" rel="noopener" aria-label="Chat with the studio on WhatsApp" class="icon-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" style="fill:#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.57-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    </a>
                </div>

                <div class="flex items-center gap-1 sm:gap-2">
                    <ThemeToggle class="shrink-0" />
                    <button @click="cart.isOpen = true" aria-label="Open bag" class="relative icon-btn">
                        <span class="material-symbols-outlined !text-[24px]" aria-hidden="true">shopping_bag</span>
                        <span v-if="cart.totalItems > 0" class="absolute right-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-background ring-2 ring-background">
                            {{ cart.totalItems }}
                        </span>
                    </button>

                    <button
                        type="button"
                        class="icon-btn lg:!hidden"
                        aria-label="Toggle menu"
                        :aria-expanded="mobileMenuOpen"
                        aria-controls="mobile-menu"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                    >
                        <span class="material-symbols-outlined !text-[22px]" aria-hidden="true">
                            {{ mobileMenuOpen ? 'close' : 'menu' }}
                        </span>
                    </button>

                    <template v-if="!$page.props.auth?.user">
                        <Link :href="route('login')" class="hidden rounded-full px-4 py-2 text-sm font-medium text-on-surface hover:bg-black/5 hover:text-primary dark:hover:bg-white/5 sm:inline-flex">
                            Sign In
                        </Link>
                    </template>
                    <template v-else>
                        <Link v-if="$page.props.auth?.user?.abilities?.includes('pos.sell')" :href="route('pos.index')" class="hidden sm:inline-flex min-h-11 items-center gap-2 rounded-full bg-primary/10 px-3 text-sm font-semibold text-primary" aria-label="Open POS"><span aria-hidden="true" class="material-symbols-outlined text-xl">point_of_sale</span>POS</Link>
                        <Link v-if="$page.props.auth?.user?.studio" :href="route('studio.index')" class="icon-btn !hidden sm:!inline-flex" aria-label="Studio admin">
                            <span class="material-symbols-outlined !text-[24px]" aria-hidden="true">dashboard</span>
                        </Link>
                        <Link :href="route('profile.designs')" class="icon-btn !hidden sm:!inline-flex" aria-label="Your orders">
                            <span class="material-symbols-outlined !text-[24px]" aria-hidden="true">account_circle</span>
                        </Link>
                        <Link :href="route('logout')" method="post" as="button" class="icon-btn !hidden lg:!inline-flex" aria-label="Sign out">
                            <span class="material-symbols-outlined !text-[22px]" aria-hidden="true">logout</span>
                        </Link>
                    </template>

                </div>
            </div>
        </div>

        <!-- Teleported: the header's backdrop-blur would otherwise make this
             fixed layer size itself to the header, leaving the page uncovered. -->
        <teleport to="body">
        <transition name="mobile-nav-slide">
            <div v-if="mobileMenuOpen" id="mobile-menu" class="fixed inset-x-0 bottom-0 top-[72px] sm:top-[88px] z-[110] overflow-y-auto px-4 pt-3 pb-24 lg:hidden">
                <div class="absolute inset-0 bg-background/90 backdrop-blur-2xl" @click="mobileMenuOpen = false"></div>
                <div class="card relative z-10 p-4 shadow-2xl">
                    <div class="space-y-2">
                        <Link v-for="item in mobileNavItems" :key="item.route" :href="route(item.route)" :aria-current="isActive(item.active || item.route) ? 'page' : undefined" class="flex min-h-11 items-center justify-between rounded-2xl border border-transparent px-4 py-2.5 text-sm sm:text-lg font-normal transition-all hover:border-primary/30 hover:bg-primary/5" :class="isActive(item.active || item.route) ? 'text-primary border-primary/30 bg-primary/5' : 'text-on-surface dark:text-white'" @click="closeMobileMenu()">
                            <span>{{ item.label }}</span>
                            <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
                        </Link>
                    </div>

                    <div v-if="!$page.props.auth?.user" class="mt-5 border-t border-black/5 pt-5">
                        <Link :href="route('login')" @click="closeMobileMenu()" class="action-primary w-full">
                            Sign In
                        </Link>
                    </div>
                    <div v-else class="mt-5 space-y-2 border-t border-black/5 pt-5 dark:border-white/5">
                        <Link v-if="$page.props.auth?.user?.abilities?.includes('pos.sell')" :href="route('pos.index')" @click="closeMobileMenu()" class="action-primary w-full"><span aria-hidden="true" class="material-symbols-outlined text-xl">point_of_sale</span>Open POS</Link>
                        <Link v-if="$page.props.auth?.user?.studio" :href="route('studio.index')" @click="closeMobileMenu()" class="flex min-h-11 items-center justify-between rounded-2xl px-4 py-3 text-sm font-medium text-on-surface hover:bg-primary/5">Studio management <span class="material-symbols-outlined text-base" aria-hidden="true">dashboard</span></Link>
                        <Link :href="route('profile.designs')" @click="closeMobileMenu()" class="flex items-center justify-between rounded-2xl px-4 py-3 text-sm font-medium text-on-surface hover:bg-primary/5">Your Orders <span class="material-symbols-outlined text-base" aria-hidden="true">receipt_long</span></Link>
                        <Link :href="route('profile.edit')" @click="closeMobileMenu()" class="flex items-center justify-between rounded-2xl px-4 py-3 text-sm font-medium text-on-surface hover:bg-primary/5">Account Settings <span class="material-symbols-outlined text-base" aria-hidden="true">settings</span></Link>
                        <Link :href="route('logout')" method="post" as="button" @click="closeMobileMenu()" class="action-secondary w-full">Sign Out</Link>
                    </div>
                </div>
            </div>
        </transition>
        </teleport>
    </nav>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { useCartStore } from '@/Stores/useCartStore';

const cart = useCartStore();

const scrolled = ref(false);
const mobileMenuOpen = ref(false);
const page = usePage();

// Store contacts come from Studio → Settings (page.props.store).
const whatsappUrl = computed(() => page.props.store?.whatsapp_url || route('contact'));

const mobileNavItems = [
    { label: 'Home', route: 'home', active: 'home' },
    ...(page.props.drops_enabled ? [{ label: 'Drops', route: 'drops', active: 'drops*' }] : []),
    { label: 'Shop', route: 'shop', active: 'shop*' },
    { label: 'Custom Order', route: 'custom-order', active: 'custom-order*' },
    { label: 'About', route: 'about', active: 'about' },
    { label: 'Community', route: 'circle', active: 'circle' },
    { label: 'Track Order', route: 'track', active: 'track' },
];

function isActive(routeName) {
    try {
        return route().current(routeName);
    } catch {
        return false;
    }
}

function closeMobileMenu() {
    mobileMenuOpen.value = false;
}

// The menu covers the page, so the page behind it must not scroll, and any
// navigation (link, Back button) closes it.
watch(mobileMenuOpen, (open) => { document.body.style.overflow = open ? 'hidden' : ''; });
let stopNavListener = null;

const handleScroll = () => {
    scrolled.value = window.scrollY > 8;
};

const handleKeydown = (e) => {
    if (e.key === 'Escape') closeMobileMenu();
};

onMounted(() => {
    handleScroll();
    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('keydown', handleKeydown);
    // Real navigations only: hover prefetches also fire "start".
    stopNavListener = router.on('start', (event) => { if (!event.detail.visit.prefetch) closeMobileMenu(); });
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
    window.removeEventListener('keydown', handleKeydown);
    stopNavListener?.();
    document.body.style.overflow = '';
});
</script>

<style scoped lang="postcss">
/* Larger, lighter navigation with a fine active underline. */
.nav-link {
    @apply relative inline-flex min-h-11 items-center whitespace-nowrap py-2 text-[14px] font-normal leading-none tracking-[.005em] text-on-surface-variant transition-colors duration-200 hover:text-primary;
}

/* Keep the current page clear without adding font weight. */
.nav-link.active {
    @apply text-primary;
}

.nav-link::after {
    content: '';
    @apply absolute bottom-1.5 left-0 h-px w-0 bg-primary transition-all duration-200;
}
.nav-link:hover::after {
    @apply w-full;
}

.nav-link.active::after {
    @apply w-full;
}

@media (prefers-reduced-motion: reduce) {
    .nav-link, .nav-link::after { transition: none; }
}

/* Dropdown sub-link */
.dropdown-link {
    @apply block text-sm text-on-surface-variant transition-colors hover:text-primary;
}

/* Icon button */
.icon-btn {
    @apply inline-flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-black/5 hover:text-primary dark:hover:bg-white/5;
}

.mobile-nav-slide-enter-active,
.mobile-nav-slide-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.mobile-nav-slide-enter-from,
.mobile-nav-slide-leave-to {
    opacity: 0;
    transform: translateY(-16px);
}
</style>
