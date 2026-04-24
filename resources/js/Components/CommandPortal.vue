<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { animate, stagger } from 'animejs';

const isOpen = ref(false);
const searchQuery = ref('');
const inputRef = ref(null);
const selectedIndex = ref(0);
const page = usePage();

const openPortal = () => {
    isOpen.value = true;
    searchQuery.value = '';
    selectedIndex.value = 0;
    nextTick(() => {
        inputRef.value?.focus();
    });
};

const closePortal = () => {
    isOpen.value = false;
};

// Global Key Listening
const handleKeyDown = (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        openPortal();
    }
    if (e.key === 'Escape' && isOpen.value) {
        closePortal();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});

// System Commands & Pages
const staticLinks = [
    { title: 'The Archive', description: 'Browse all pieces', icon: 'shopping_bag', route: 'shop', category: 'Pages' },
    { title: 'Client Vault', description: 'Your personal designs', icon: 'inventory_2', route: 'profile.designs', category: 'Personal' },
    { title: 'Custom Order', description: 'Start a new brief', icon: 'edit_note', route: 'custom-order', category: 'Services' },
    { title: 'The Circle', description: 'Join the community', icon: 'group', route: 'circle', category: 'Pages' },
    { title: 'About Serana', description: 'The artisan philosophy', icon: 'info', route: 'about', category: 'Pages' },
    { title: 'Concierge', description: 'Direct WhatsApp link', icon: 'chat', action: 'whatsapp', category: 'Support' },
];

// Computed Results
const filteredResults = computed(() => {
    const query = searchQuery.value.toLowerCase().trim();
    
    let results = [];

    // 1. Static Links
    results.push(...staticLinks.filter(link => 
        link.title.toLowerCase().includes(query) || 
        link.description.toLowerCase().includes(query)
    ).map(l => ({ ...l, type: 'link' })));

    // 2. Categories
    const categories = page.props.categories || [];
    results.push(...categories.filter(cat => 
        cat.name.toLowerCase().includes(query)
    ).map(cat => ({
        title: cat.name,
        description: `Browse ${cat.name} collection`,
        icon: 'category',
        route: 'shop',
        params: { category: cat.slug },
        category: 'Collections',
        type: 'link'
    })));

    // 3. System Actions
    if ('dark mode'.includes(query) || 'light mode'.includes(query) || 'theme'.includes(query)) {
        results.push({
            title: 'Toggle Neural Theme',
            description: 'Switch between light/dark interface',
            icon: 'contrast',
            action: 'toggle-theme',
            category: 'System',
            type: 'action'
        });
    }

    return results;
});

const navigateTo = (item) => {
    if (item.type === 'link') {
        if (item.params) {
            router.get(route(item.route, item.params));
        } else {
            router.visit(route(item.route));
        }
    } else if (item.type === 'action') {
        if (item.action === 'whatsapp') {
            window.open(`https://wa.me/${page.props.whatsapp_number}`, '_blank');
        } else if (item.action === 'toggle-theme') {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
        }
    }
    closePortal();
};

const handleResultKeyDown = (e) => {
    if (e.key === 'ArrowDown') {
        selectedIndex.value = (selectedIndex.value + 1) % filteredResults.value.length;
    } else if (e.key === 'ArrowUp') {
        selectedIndex.value = (selectedIndex.value - 1 + filteredResults.value.length) % filteredResults.value.length;
    } else if (e.key === 'Enter') {
        if (filteredResults.value[selectedIndex.value]) {
            navigateTo(filteredResults.value[selectedIndex.value]);
        }
    }
};

// Portal Stagger Animation
watch(isOpen, (val) => {
    if (val) {
        nextTick(() => {
            animate('.portal-item', {
                opacity: [0, 1],
                translateY: [10, 0],
                delay: stagger(40),
                easing: 'easeOutExpo',
                duration: 600
            });
        });
    }
});

defineExpose({ openPortal });
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-[1000] flex items-start justify-center pt-[15vh] px-4">
        <!-- Backdrop -->
        <div @click="closePortal" class="absolute inset-0 bg-background/80 backdrop-blur-3xl transition-opacity animate-fade-in"></div>

        <!-- Terminal Hub -->
        <div class="relative w-full max-w-2xl bg-surface border border-white/10 shadow-[0_60px_100px_rgba(0,0,0,0.8)] rounded-2xl overflow-hidden animate-portal-pop">
            <!-- Technical Scanning Line -->
            <div class="absolute top-0 left-0 w-full h-1 bg-primary/20 blur-[2px] z-50 animate-scan"></div>
            
            <!-- Search Interface -->
            <div class="relative flex items-center border-b border-white/5 p-6">
                <span class="material-symbols-outlined text-primary text-2xl mr-4 opacity-70">terminal</span>
                <input 
                    ref="inputRef"
                    v-model="searchQuery"
                    @keydown="handleResultKeyDown"
                    type="text" 
                    placeholder="Search the Archive... (Try 'Custom', 'Vault', or 'Silk')"
                    class="w-full bg-transparent border-none text-white font-headline text-lg tracking-wider placeholder:text-white/20 focus:ring-0"
                />
                <div class="hidden md:flex items-center gap-1.5 opacity-20">
                    <span class="text-[10px] font-black border border-white/20 px-1.5 py-0.5 rounded">ESC</span>
                </div>
            </div>

            <!-- Results Grid -->
            <div class="max-h-[60vh] overflow-y-auto p-4 custom-scrollbar">
                <div v-if="filteredResults.length === 0" class="py-12 text-center">
                    <p class="text-white/20 font-headline text-xs uppercase tracking-[0.4em]">No matching neural data_</p>
                </div>

                <div v-else class="space-y-1">
                    <div 
                        v-for="(item, index) in filteredResults" 
                        :key="index"
                        @mouseover="selectedIndex = index"
                        @click="navigateTo(item)"
                        class="portal-item group flex items-center justify-between p-4 rounded-xl cursor-pointer transition-all border border-transparent"
                        :class="selectedIndex === index ? 'bg-primary/10 border-primary/20' : 'hover:bg-white/5'"
                    >
                        <div class="flex items-center gap-5">
                            <div class="w-10 h-10 rounded-lg bg-surface-variant flex items-center justify-center border border-white/5 group-hover:border-primary/40 transition-colors">
                                <span class="material-symbols-outlined text-xl" :class="selectedIndex === index ? 'text-primary' : 'text-white/40'">{{ item.icon }}</span>
                            </div>
                            <div>
                                <h3 class="text-sm font-headline tracking-widest uppercase font-black" :class="selectedIndex === index ? 'text-primary' : 'text-white/80'">{{ item.title }}</h3>
                                <p class="text-[10px] tracking-wide text-white/30 uppercase font-medium mt-0.5">{{ item.description }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-[8px] font-black tracking-[0.2em] text-white/10 uppercase">{{ item.category }}</span>
                            <span v-if="selectedIndex === index" class="material-symbols-outlined text-primary text-sm animate-pulse-fast">subdirectory_arrow_left</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Portal Footer -->
            <div class="bg-black/20 p-4 border-t border-white/5 flex items-center justify-between">
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                        <span class="text-[8px] font-black tracking-[0.3em] text-white/20 uppercase">Neural Connection Stable</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-[8px] font-black tracking-widest text-white/10 uppercase">v2.0_CORE_NAVI</span>
                    </div>
                </div>
                <div class="flex items-center gap-4 text-white/20">
                    <span class="text-[8px] tracking-widest uppercase">Select with Arrow Keys</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
.animate-portal-pop { animation: portalPop 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.animate-pulse-fast { animation: pulse 1.5s infinite; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes portalPop { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
@keyframes scan { 0% { transform: translateY(-100%); opacity: 0; } 50% { opacity: 1; } 100% { transform: translateY(600px); opacity: 0; } }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

.animate-scan { animation: scan 4s linear infinite; }

.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(57, 255, 20, 0.3); }
</style>
