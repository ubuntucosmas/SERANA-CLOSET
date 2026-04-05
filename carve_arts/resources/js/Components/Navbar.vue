<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const isScrolled = ref(false);
const activeSection = ref('home');

const handleScroll = () => {
    isScrolled.value = window.scrollY > 20;
};

let observer = null;

onMounted(() => {
    window.addEventListener('scroll', handleScroll);
    
    // Intersection Observer for active section highlighting
    const observerOptions = {
        root: null,
        rootMargin: '-20% 0px -70% 0px',
        threshold: 0
    };

    observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                activeSection.value = entry.target.id;
            }
        });
    }, observerOptions);

    ['home', 'services', 'gallery', 'contact'].forEach(id => {
        const el = document.getElementById(id);
        if (el) observer.observe(el);
    });
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
    if (observer) observer.disconnect();
});
</script>

<template>
    <!-- Top Navigation -->
    <header :class="['fixed top-0 w-full z-50 transition-all duration-300', isScrolled ? 'bg-[#0c0c1f]/80 backdrop-blur-xl shadow-lg border-b border-outline-variant/20' : 'bg-transparent']">
        <nav class="flex justify-between items-center px-8 py-6 max-w-screen-2xl mx-auto">
            <div class="text-2xl font-bold tracking-tighter text-[#99ff33] font-headline">
                Carve Arts &amp; Design
            </div>
            <div class="hidden md:flex items-center gap-12">
                <a 
                    v-for="link in [
                        { name: 'Home', href: '#home', id: 'home' },
                        { name: 'Services', href: '#services', id: 'services' },
                        { name: 'Gallery', href: '#gallery', id: 'gallery' },
                        { name: 'Contact', href: '#contact', id: 'contact' }
                    ]"
                    :key="link.id"
                    :class="[
                        'font-[\'Epilogue\'] uppercase tracking-widest text-sm transition-all duration-300 hover:scale-105',
                        activeSection === link.id 
                            ? 'text-primary border-b-2 border-primary pb-1 font-bold' 
                            : 'text-on-surface-variant hover:text-primary'
                    ]"
                    :href="link.href"
                >
                    {{ link.name }}
                </a>
            </div>
            <div class="flex items-center gap-6">
                <button class="material-symbols-outlined text-[#e2e0fc] text-2xl hover:text-[#99ff33] transition-all">shopping_cart</button>
                <button class="bg-primary text-on-primary px-6 py-2 rounded-xl font-bold uppercase tracking-wider text-sm hover:scale-105 active:opacity-80 transition-all duration-300 shadow-[0_0_15px_rgba(153,255,51,0.3)] hover:shadow-[0_0_25px_rgba(153,255,51,0.6)]">
                    Quick Order
                </button>
            </div>
        </nav>
    </header>

    <!-- Side Quick Contact -->
    <aside class="fixed right-0 top-1/2 -translate-y-1/2 z-40 flex flex-col p-4 bg-[#17172f]/90 backdrop-blur-md rounded-l-2xl shadow-2xl shadow-[#0c0c1f] transition-transform border border-r-0 border-outline-variant/30">
        <div class="mb-4 px-2 border-b border-outline-variant/15 pb-4">
            <h4 class="text-[#99ff33] font-headline font-bold text-xs uppercase tracking-tighter">Quick Contact</h4>
            <p class="text-[10px] text-on-surface-variant font-medium">Direct line to the Atelier</p>
        </div>
        <div class="flex flex-col gap-4">
            <a class="flex items-center gap-3 p-3 bg-[#99ff33]/10 text-[#99ff33] rounded-xl hover:translate-x-1 transition-transform border border-primary/20" href="#">
                <span class="material-symbols-outlined">chat</span>
                <span class="text-xs font-['Manrope'] font-medium whitespace-nowrap">WhatsApp Support</span>
            </a>
            <a class="flex items-center gap-3 p-3 text-[#e2e0fc] hover:bg-[#23233f] rounded-xl hover:translate-x-1 transition-transform" href="#">
                <span class="material-symbols-outlined">receipt_long</span>
                <span class="text-xs font-['Manrope'] font-medium whitespace-nowrap">Order Status</span>
            </a>
            <a class="flex items-center gap-3 p-3 text-[#e2e0fc] hover:bg-[#23233f] rounded-xl hover:translate-x-1 transition-transform" href="#">
                <span class="material-symbols-outlined">brush</span>
                <span class="text-xs font-['Manrope'] font-medium whitespace-nowrap">Portfolio</span>
            </a>
        </div>
        <button class="mt-6 bg-primary text-on-primary py-2 rounded-lg text-xs font-bold uppercase tracking-widest hover:scale-95 transition-all shadow-[0_0_10px_rgba(153,255,51,0.2)]">
            Message Now
        </button>
    </aside>
</template>
