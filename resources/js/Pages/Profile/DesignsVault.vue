<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { animate, stagger } from 'animejs';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import VaultSeal from '@/Components/VaultSeal.vue';

const isUnlocked = ref(false);
const vaultRoot = ref(null);

const props = defineProps({
    orders: Array,
    user: Object
});

import { useCurrency } from '@/Composables/useCurrency';
import { usePage } from '@inertiajs/vue3';
const page = usePage();
const { formatAmount } = useCurrency();

const statuses = [
    { key: 'pending', label: 'Signup', icon: 'assignment' },
    { key: 'designing', label: 'Design', icon: 'architecture' },
    { key: 'sewing', label: 'Making', icon: 'precision_manufacturing' },
    { key: 'completed', label: 'Seal', icon: 'verified' },
    { key: 'shipped', label: 'Transit', icon: 'local_shipping' }
];

function getStatusIndex(status) {
    return statuses.findIndex(s => s.key === status);
}

// --- Animations ---
function handleUnlocked() {
    isUnlocked.value = true;
    setTimeout(() => {
        // Double check selector exists to prevent "No target found"
        if (document.querySelector('.reveal-item')) {
            animate('.reveal-item', {
                opacity: [0, 1],
                translateY: [20, 0],
                duration: 1000,
                delay: stagger(100),
                easing: 'easeOutExpo'
            });
        }
    }, 100);
}

const getOrderThumbnails = (order) => {
    if (!order) return [];
    if (order.type === 'standard') {
        const items = order.items_with_urls || [];
        return items.map(i => i.image_url || i.image_path).filter(Boolean);
    }
    return order.inspiration_urls || [];
};
</script>

<template>
    <Head title="My Designs Vault" />

    <VaultSeal v-if="!isUnlocked" @unlocked="handleUnlocked" />

    <StorefrontLayout :class="!isUnlocked ? 'hidden' : 'block'">
        <main ref="vaultRoot" class="min-h-screen bg-background pt-32 pb-20 px-6 max-w-screen-xl mx-auto font-body">
            
            <!-- ═══ HEADER ═══ -->
            <header class="mb-20 reveal-item opacity-0">
                <div class="flex items-center gap-3 text-primary font-headline tracking-[0.4em] uppercase text-[9px] font-black mb-4">
                    <span class="w-8 h-px bg-primary/40"></span>
                    Digital Dossier / SV-77
                </div>
                <h1 class="text-6xl md:text-8xl font-headline font-black leading-tight selection:bg-primary selection:text-background text-on-surface">
                    Hello, <span class="font-light text-on-surface-variant">{{ user.name.split(' ')[0] }}</span>
                </h1>
                <p class="text-lg md:text-xl text-on-surface-variant max-w-2xl leading-relaxed font-light mt-6">
                    Your collection is a permanent record of fit and craft. Track active orders and view your measurement profile.
                </p>
            </header>

            <div class="grid lg:grid-cols-12 gap-12 items-start">
                
                <!-- ═══ COMMISSIONS (MAIN) ═══ -->
                <div class="lg:col-span-8 space-y-12">
                        <h2 class="font-headline text-[10px] tracking-[0.3em] font-black uppercase text-on-surface flex items-center gap-3">
                            <span class="material-symbols-outlined text-primary text-sm">history_edu</span>
                            Order History_
                        </h2>
                        <span class="text-[9px] font-mono text-on-surface-variant/40">{{ orders.length }} SAVED ORDERS</span>

                    <div v-if="orders.length" class="space-y-6">
                        <div v-for="order in orders" :key="order.id" 
                            class="reveal-item opacity-0 p-1 bg-gradient-to-br from-white/10 via-transparent to-transparent rounded-[2rem] overflow-hidden group">
                            <div class="p-8 md:p-10 bg-surface-container rounded-[1.9rem] border dark:border-white/5 border-black/5 transition-all duration-500 hover:border-primary/20">
                                
                                <!-- Top Row: Identity & Status -->
                                <div class="flex flex-col md:flex-row justify-between items-start gap-6 mb-12">
                                    <div class="space-y-4">
                                        <div class="inline-flex items-center gap-3 px-3 py-1 bg-white/5 border dark:border-white/10 border-black/10 rounded-full">
                                            <span class="text-[8px] font-mono text-primary/70 tracking-widest uppercase">REF: SV-{{ String(order.id).padStart(4, '0') }}</span>
                                            <span v-if="order.cipher" class="w-1 h-1 rounded-full bg-white/20"></span>
                                            <span v-if="order.cipher" class="text-[8px] font-mono text-on-surface-variant uppercase tracking-widest">CIPHER: {{ order.cipher }}</span>
                                        </div>
                                        <h3 class="text-3xl md:text-5xl font-headline font-black dark:text-white text-on-surface tracking-tighter italic">Custom Made_</h3>
                                        <p class="text-[9px] font-headline uppercase font-bold tracking-[0.2em] text-on-surface-variant">Status: <span class="text-primary">{{ order.status }}</span></p>
                                    </div>
                                    <div v-if="order.price_quoted" class="text-left md:text-right">
                                        <p class="text-[8px] text-on-surface-variant uppercase tracking-[0.2em] font-black mb-1">Value</p>
                                        <p class="text-3xl font-headline font-black text-primary luminous-glow">{{ formatAmount(order.price_quoted, page.props) }}</p>
                                    </div>
                                </div>

                                <!-- ═══ MINIMAL PROGRESS BAR (THE PULSE) ═══ -->
                                <div class="mb-12 relative px-2">
                                    <!-- Background Line -->
                                    <div class="absolute top-4 left-4 right-4 h-px bg-white/5">
                                        <div class="h-full bg-primary/40 shadow-[0_0_15px_rgba(57, 255, 20,0.2)] transition-all duration-1000 origin-left"
                                             :style="{ width: (getStatusIndex(order.status) / (statuses.length - 1)) * 100 + '%' }">
                                        </div>
                                    </div>

                                    <div class="flex justify-between items-start relative z-10">
                                        <div v-for="(s, i) in statuses" :key="s.key" class="flex flex-col items-center gap-3">
                                            <div class="w-8 h-8 rounded-full border transition-all duration-700 flex items-center justify-center bg-background"
                                                 :class="i <= getStatusIndex(order.status) ? 'border-primary' : 'dark:border-white/10 border-black/10'">
                                                <div v-if="i < getStatusIndex(order.status)" class="w-1.5 h-1.5 rounded-full bg-primary/40"></div>
                                                <div v-else-if="i === getStatusIndex(order.status)" class="w-2 h-2 rounded-full bg-primary shadow-[0_0_15px_rgba(57, 255, 20,0.6)] animate-pulse"></div>
                                                <div v-else class="w-0.5 h-0.5 rounded-full bg-white/10"></div>
                                            </div>
                                            <span class="text-[7px] font-black uppercase tracking-widest transition-colors"
                                                  :class="i <= getStatusIndex(order.status) ? 'text-on-surface' : 'text-on-surface-variant opacity-30'">
                                                {{ s.label }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Order Details Grid -->
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
                                    <div v-for="m in [['Bust', order.bust_cm], ['Waist', order.waist_cm], ['Hips', order.hips_cm], ['Length', order.length_cm]]" 
                                        :key="m[0]" class="p-5 bg-background border dark:border-white/5 border-black/5 rounded-2xl group-hover:border-primary/10 transition-colors">
                                        <span class="block text-[8px] uppercase tracking-widest text-on-surface-variant font-black mb-2">{{ m[0] }}</span>
                                        <span class="text-xl font-headline font-light dark:text-white text-on-surface tracking-wider">{{ m[1] }}<span class="text-[10px] text-on-surface-variant ml-1">cm</span></span>
                                    </div>
                                </div>

                                <!-- Internal Note -->
                                <div v-if="order.internal_notes" class="p-6 bg-background rounded-2xl mb-10 flex gap-4 items-start border-l border-primary/30">
                                    <span class="material-symbols-outlined text-primary text-lg">history_edu</span>
                                    <div class="space-y-1">
                                        <span class="text-[8px] font-headline font-black uppercase tracking-widest text-primary">Maker's notes</span>
                                        <p class="text-xs text-on-surface-variant font-light leading-relaxed">"{{ order.internal_notes }}"</p>
                                    </div>
                                </div>

                                <!-- ═══ ORDER VISUALS (Blueprints & Assets) ═══ -->
                                <div v-if="getOrderThumbnails(order).length" class="mb-12 space-y-6">
                                    <div class="flex items-center gap-3 text-on-surface-variant font-black uppercase text-[8px] tracking-widest">
                                        <span class="w-4 h-px bg-white/10"></span>
                                        Design Blueprint_
                                    </div>
                                    <div class="flex gap-4 overflow-x-auto pb-4 custom-scrollbar">
                                        <div v-for="(img, idx) in getOrderThumbnails(order)" :key="idx" 
                                             class="flex-shrink-0 w-32 aspect-[3/4] bg-background rounded-xl overflow-hidden border dark:border-white/5 border-black/5 hover:border-primary/30 transition-all">
                                            <img :src="img" class="w-full h-full object-cover" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Progress Snaps (Artisan Journey) -->
                                <div v-if="order.progress_snaps?.length" class="space-y-6 pt-6 border-t dark:border-white/5 border-black/5">
                                    <div class="flex items-center gap-3 text-on-surface-variant font-black uppercase text-[8px] tracking-widest">
                                        <span class="w-4 h-px bg-white/10"></span>
                                        Order Progress_
                                    </div>
                                    <div class="flex gap-4 overflow-x-auto pb-4 custom-scrollbar">
                                        <div v-for="snap in order.progress_snaps" :key="snap.id" class="flex-shrink-0 w-44 group/snap">
                                            <div class="aspect-square bg-background rounded-2xl overflow-hidden border dark:border-white/5 border-black/5 mb-3">
                                                <img :src="snap.image_url" class="w-full h-full object-cover group-hover/snap:scale-110 transition-transform duration-1000" />
                                            </div>
                                            <p class="text-[9px] text-on-surface font-light leading-tight mb-1">{{ snap.description || 'Production details' }}</p>
                                            <p class="text-[7px] text-on-surface-variant uppercase font-black tracking-widest">{{ new Date(snap.created_at).toLocaleDateString() }}</p>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- EMPTY STATE -->
                    <div v-else class="text-center py-32 bg-surface-container/20 rounded-[3rem] border border-dashed dark:border-white/5 border-black/5 space-y-8 reveal-item opacity-0">
                        <div class="w-20 h-20 bg-background rounded-full flex items-center justify-center mx-auto border dark:border-white/5 border-black/5">
                            <span class="material-symbols-outlined text-3xl text-on-surface-variant/30">architecture</span>
                        </div>
                        <div class="space-y-3">
                             <p class="text-[10px] font-headline text-on-surface-variant uppercase tracking-[0.4em] font-black">Collection Empty</p>
                             <p class="text-sm font-light text-on-surface-variant/60 max-w-xs mx-auto">Your private collection begins with your first order.</p>
                        </div>
                        <Link :href="route('custom-order')" class="inline-flex items-center gap-3 bg-primary text-background font-headline text-[10px] tracking-widest font-black uppercase px-8 py-4 rounded-xl hover:shadow-[0_0_30px_rgba(57, 255, 20,0.3)] transition-all">
                            Open the Studio
                        </Link>
                    </div>
                </div>

                <!-- ═══ SIDEBAR ═══ -->
                <aside class="lg:col-span-4 space-y-8">
                    
                    <!-- Fit Profile Card -->
                    <div class="p-10 bg-surface-container rounded-[2.5rem] border dark:border-white/5 border-black/5 reveal-item opacity-0 space-y-10 group">
                        <div class="flex items-center gap-4">
                            <span class="material-symbols-outlined text-primary text-xl">straighten</span>
                            <h2 class="font-headline text-[10px] tracking-[0.3em] font-black uppercase dark:text-white text-on-surface">Secure Measurements</h2>
                        </div>
                        
                        <p class="text-sm text-on-surface-variant leading-relaxed font-light">Your measurements are secured here for automated precision in future orders.</p>

                        <div v-if="orders.length" class="space-y-6">
                             <div class="flex justify-between items-end pb-4 border-b dark:border-white/5 border-black/5">
                                <span class="text-[9px] uppercase tracking-widest text-on-surface-variant font-black">Last Update</span>
                                <span class="text-sm dark:text-white text-on-surface font-headline">{{ new Date(orders[0].created_at).toLocaleDateString('en-GB') }}</span>
                             </div>
                             <button class="w-full py-5 bg-background border dark:border-white/10 border-black/10 text-on-surface hover:text-primary hover:border-primary/40 font-headline text-[10px] tracking-widest font-black uppercase rounded-2xl transition-all duration-300">
                                Refresh Profile
                             </button>
                        </div>
                    </div>

                    <!-- Invitation Card -->
                    <div class="p-10 bg-surface-container rounded-[2.5rem] border dark:border-white/5 border-black/5 reveal-item opacity-0 space-y-10 relative overflow-hidden group">
                        <!-- BG Icon -->
                        <span class="absolute -right-6 -bottom-6 text-primary/5 material-symbols-outlined pointer-events-none" style="font-size: 140px;">qr_code_2</span>

                        <div class="flex items-center gap-4">
                            <span class="material-symbols-outlined text-primary text-xl">qr_code_2</span>
                            <h2 class="font-headline text-[10px] tracking-[0.3em] font-black uppercase dark:text-white text-on-surface">The Invite</h2>
                        </div>

                        <p class="text-sm text-on-surface-variant leading-relaxed font-light">Vault members can recommend one person to skip the order queue.</p>

                        <div v-if="user.invitation_code" class="relative group/code">
                            <div class="p-8 bg-background border border-primary/20 rounded-[2rem] text-center group-hover:border-primary/50 transition-all duration-500">
                                <span class="block text-[8px] uppercase tracking-[0.3em] text-primary font-black mb-3">Priority Code</span>
                                <span class="text-3xl font-headline font-black dark:text-white text-on-surface tracking-[0.2em] selection:bg-primary selection:text-background text-on-surface">{{ user.invitation_code }}</span>
                            </div>
                        </div>
                        <Link v-else :href="route('profile.invite')" method="post" as="button" 
                            class="w-full py-5 bg-primary text-background font-headline text-[10px] tracking-widest font-black uppercase rounded-2xl hover:scale-[1.02] active:scale-95 transition-all shadow-xl">
                            Request Invitation
                        </Link>
                    </div>

                    <!-- Guarantee Card -->
                    <div class="p-10 bg-primary/5 border border-primary/20 rounded-[2.5rem] reveal-item opacity-0 space-y-6">
                         <p class="text-sm text-on-surface-variant leading-relaxed font-light">Priority access to new items and free adjustments on all custom orders.</p>
                    </div>
                </aside>

            </div>
        </main>
    </StorefrontLayout>
</template>

<style scoped>
.selection-primary::selection {
    background: #99FF33;
    color: #0d0d0d;
}
.custom-scrollbar::-webkit-scrollbar {
    height: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.02);
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(57, 255, 20, 0.2);
    border-radius: 10px;
}
</style>
