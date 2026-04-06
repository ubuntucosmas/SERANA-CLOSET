<template>
    <StorefrontLayout>
        <Head>
            <title>Custom Order | Serana Closet</title>
            <meta name="description" content="Begin your custom order. Upload inspiration, select elite fabrics, and provide your measurements for a perfect fit.">
        </Head>

        <div class="relative">
            <!-- ── Cinematic Shifting Background ─────────────────────────── -->
            <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
                <img :src="slotA" class="absolute inset-0 w-full h-full object-cover scale-110 grayscale brightness-50 blur-[2px] transition-opacity duration-[3000ms]" :class="showA ? 'opacity-30' : 'opacity-0'" alt="" />
                <img :src="slotB" class="absolute inset-0 w-full h-full object-cover scale-110 grayscale brightness-50 blur-[2px] transition-opacity duration-[3000ms]" :class="showA ? 'opacity-0' : 'opacity-30'" alt="" />
                <div class="absolute inset-0" style="background: radial-gradient(ellipse 70% 80% at 50% 40%, transparent 30%, rgba(0,0,0,0.6) 70%, rgba(0,0,0,0.9) 100%);"></div>
                <div class="absolute inset-x-0 top-0 h-48 bg-gradient-to-b from-black/70 via-black/20 to-transparent"></div>
                <div class="absolute inset-x-0 bottom-0 h-64 bg-gradient-to-t from-background via-background/80 to-transparent"></div>
                <div class="absolute inset-x-0 top-0 h-[1px] bg-gradient-to-r from-transparent via-primary/40 to-transparent"></div>
                <div class="absolute inset-0 scan-lines opacity-[0.05]"></div>
            </div>
            <!-- ─────────────────────────────────────────────────────────── -->

            <main class="relative z-10 pt-24 sm:pt-32 pb-24 px-4 sm:px-6 md:px-12 xl:px-24 2xl:px-32 w-full space-y-12">
            
            <!-- Artisan Terminal System Header -->
            <header class="mb-20 flex items-center justify-between border-b dark:border-white/5 border-black/5 pb-8 reveal">
                <div class="flex items-center gap-6">
                    <span class="font-headline text-[10px] tracking-[0.4em] text-primary font-bold uppercase">Studio Command</span>
                    <div class="w-1.5 h-1.5 rounded-full bg-primary luminous-glow"></div>
                    <span class="serif-text text-lg dark:text-white text-on-surface opacity-60">Curating Perfection</span>
                </div>
                <div class="hidden md:flex items-center gap-6 opacity-40">
                    <span class="font-headline text-[9px] tracking-[0.3em] dark:text-white text-on-surface uppercase">{{ new Date().toLocaleTimeString() }} — UTC+3</span>
                </div>
            </header>

                <!-- Global System Error Alert -->
                <transition name="fade">
                    <div v-if="globalError" class="mb-12 p-6 bg-error/10 border border-error/20 rounded-sm flex items-center justify-between gap-6 backdrop-blur-sm">
                        <div class="flex items-center gap-4">
                            <span class="material-symbols-outlined text-error">warning</span>
                            <p class="text-error font-headline text-[10px] font-medium tracking-[0.2em] uppercase">{{ globalError }}</p>
                        </div>
                        <button @click="globalError = null" class="text-error/40 hover:text-error transition-colors">
                            <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                    </div>
                </transition>

                <form @submit.prevent="submitOrder" class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16">
                    <!-- Form Side -->
                    <div class="lg:col-span-7 space-y-20">
                        
                <!-- ── Step Progress Tracker ──────────────────────────── -->
                <div class="flex items-center gap-3 mb-12">
                    <template v-for="(step, i) in ['Silhouette', 'Foundation', 'Identity']" :key="i">
                        <div class="flex items-center gap-2 group">
                            <div class="w-7 h-7 rounded-full border text-[9px] font-headline font-medium flex items-center justify-center transition-all duration-500"
                                :class="formProgress > i ? 'bg-primary border-primary text-background ' : 'dark:border-white/10 border-black/10 dark:text-white text-on-surface/20'"
                            >{{ i + 1 }}</div>
                            <span class="text-xs font-medium tracking-widest transition-colors duration-500"
                                :class="formProgress > i ? 'text-primary' : 'dark:text-white/40 text-black/40'">{{ step }}</span>
                        </div>
                        <div v-if="i < 2" class="flex-1 h-px transition-all duration-700"
                            :class="formProgress > i + 1 ? 'bg-primary/40' : 'bg-white/5'"></div>
                    </template>
                </div>
                <!-- ─────────────────────────────────────────────────────── -->

                        <!-- ════ STEP 01: SILHOUETTE & ARCHITECTURE ════ -->
                        <section class="form-section">
                            <!-- Step Header -->
                            <div class="flex items-center gap-4 mb-10">
                                <div class="w-10 h-10 rounded-sm bg-primary/10 border border-primary/30 flex items-center justify-center  shrink-0">
                                    <span class="font-headline text-[10px] tracking-[0.2em] text-primary font-medium">01</span>
                                </div>
                                <div>
                                    <p class="text-[10px] font-headline tracking-[0.2em] text-primary/60 font-medium uppercase">Step one</p>
                                    <h3 class="text-2xl font-headline font-medium dark:text-white text-on-surface tracking-widest uppercase">Style & Design</h3>
                                    <p class="text-[10px] text-on-surface-variant/40 mt-2 leading-relaxed uppercase tracking-widest max-w-md">Choose your basic style and provide some details for your custom piece.</p>
                                </div>
                                <div class="h-px flex-1 bg-gradient-to-r from-primary/20 to-transparent ml-2"></div>
                            </div>

                            <div class="space-y-12">
                                <!-- Base Reference Context -->
                                <div v-if="baseProduct" class="p-6 bg-white/[0.01] border dark:border-white/10 border-black/10 rounded-sm flex items-center gap-6 mb-8">
                                    <div class="w-16 h-16 rounded-sm overflow-hidden border dark:border-white/5 border-black/5 grayscale hover:grayscale-0 transition-all duration-700">
                                        <img :src="baseProduct.image_url || '/images/hero_editorial.png'" class="w-full h-full object-cover" />
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-[10px] font-headline tracking-[0.2em] uppercase text-primary/60 font-medium">Reference_</p>
                                        <p class="dark:text-white text-on-surface font-headline text-lg font-medium tracking-widest">{{ baseProduct.name }}</p>
                                    </div>
                                </div>

                                <!-- Outfit Type Selection -->
                                <div class="space-y-3">
                                    <div class="flex flex-col mb-4">
                                        <label class="text-xs font-medium tracking-widest uppercase dark:text-white text-on-surface/75">Outfit Type</label>
                                        <p class="text-[9px] text-primary/40 uppercase tracking-widest mt-1">Select the type of outfit you want us to create.</p>
                                    </div>
                                    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                                        <button 
                                            v-for="type in outfitTypes" 
                                            :key="type"
                                            type="button"
                                            @click="form.outfit_type = type"
                                            class="px-4 py-3 rounded-sm border text-xs font-medium tracking-widest uppercase transition-all"
                                            :class="form.outfit_type === type ? 'bg-primary text-background border-primary' : 'bg-white/[0.03] dark:border-white/10 border-black/10 dark:text-white text-on-surface/55 hover:dark:text-white text-on-surface hover:dark:border-white/20 border-black/20'"
                                        >
                                            {{ type }}
                                        </button>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <div class="space-y-2">
                                        <label class="text-xs font-medium tracking-widest uppercase dark:text-white text-on-surface/75">Occasion / Event</label>
                                        <input v-model="form.occasion" class="w-full bg-white/[0.04] border dark:border-white/10 border-black/10 focus:border-primary/50 focus:ring-0 rounded-sm px-3 py-2.5 text-base dark:text-white text-on-surface transition-all placeholder:dark:text-white text-on-surface/30 tracking-wide" placeholder="e.g. Gala, Wedding, Office" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-xs font-medium tracking-widest uppercase dark:text-white text-on-surface/75">Target Date</label>
                                        <input v-model="form.deadline" type="date" class="w-full bg-white/[0.04] border dark:border-white/10 border-black/10 focus:border-primary/50 focus:ring-0 rounded-sm px-3 py-2.5 text-base dark:text-white text-on-surface transition-all color-scheme-dark tracking-wide" />
                                    </div>
                                </div>

                                <!-- Inspiration Upload -->
                                <div 
                                    class="group relative bg-white/[0.03] border dark:border-white/10 border-black/10 hover:border-primary/20 transition-all duration-500 rounded-sm p-8 text-center flex flex-col items-center justify-center min-h-[180px] cursor-pointer"
                                    :class="{'border-error/30': validationErrors.images}"
                                    @click="triggerFileInput"
                                >
                                    <input type="file" ref="fileInput" class="hidden" multiple accept="image/png, image/jpeg, application/pdf" @change="handleFileSelect" />
                                    <div class="w-12 h-12 bg-white/[0.05] rounded-full flex items-center justify-center border dark:border-white/10 border-black/10 mb-4 group-hover:scale-105 group-hover:border-primary/30 transition-all duration-500">
                                        <span class="material-symbols-outlined text-2xl dark:text-white/40 text-black/40 group-hover:text-primary transition-colors">add_photo_alternate</span>
                                    </div>
                                    <p class="dark:text-white text-on-surface text-base font-medium uppercase tracking-widest" v-if="!form.images.length">Upload Inspiration Photos</p>
                                    <p class="text-primary text-base font-medium" v-else>{{ form.images.length }} photos(s) attached</p>
                                    <p class="dark:text-white/40 text-black/40 text-[9px] mt-2 uppercase tracking-widest leading-loose max-w-xs mx-auto" v-if="!form.images.length">
                                        Front, back, and side views help us understand your vision better.
                                    </p>
                                    <p v-if="validationErrors.images" class="mt-3 text-xs text-error font-medium">{{ validationErrors.images }}</p>
                                </div>

                                <!-- Measurements -->
                                <div class="space-y-3">
                                    <div class="flex flex-col mb-4">
                                        <label class="text-xs font-medium tracking-widest uppercase dark:text-white text-on-surface/75">Body Measurements <span class="dark:text-white/40 text-black/40 font-normal normal-case tracking-normal">(optional)</span></label>
                                        <p class="text-[9px] text-primary/40 uppercase tracking-widest mt-1">Providing your measurements now helps us create a better fit.</p>
                                    </div>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                        <div v-for="m in [['Height', 'height_cm'], ['Bust', 'bust_cm'], ['Waist', 'waist_cm'], ['Hips', 'hips_cm'], ['Length', 'length_cm']]" :key="m[1]" class="group/input">
                                            <label class="text-[11px] font-medium tracking-widest uppercase dark:text-white/60 text-black/60 group-hover/input:text-primary transition-colors block mb-1.5">{{ m[0] }} <span class="dark:text-white text-on-surface/30 font-normal">cm</span></label>
                                            <input 
                                                v-model="form[m[1]]" 
                                                @input="validateField(m[1])" 
                                                @focus="activeField = m[1]" 
                                                @blur="activeField = null" 
                                                class="w-full bg-white/[0.04] border dark:border-white/10 border-black/10 focus:border-primary/50 focus:ring-0 rounded-sm px-3 py-2 text-base dark:text-white text-on-surface placeholder:dark:text-white text-on-surface/25 transition-all text-center" 
                                                :class="{'border-error/50 text-error': validationErrors[m[1]]}"
                                                placeholder="—" 
                                                type="number" 
                                                step="0.1" 
                                            />
                                            <p v-if="validationErrors[m[1]]" class="text-[11px] text-error font-medium mt-1">{{ validationErrors[m[1]] }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div class="space-y-2">
                                    <div class="flex flex-col mb-4">
                                        <label class="text-xs font-medium tracking-widest uppercase dark:text-white text-on-surface/75">Style Instructions</label>
                                        <p class="text-[9px] text-primary/40 uppercase tracking-widest mt-1">Describe any specific details or changes you want.</p>
                                    </div>
                                    <textarea v-model="form.notes" class="w-full bg-white/[0.04] border dark:border-white/10 border-black/10 focus:border-primary/40 focus:ring-0 rounded-sm px-4 py-3 text-base dark:text-white text-on-surface transition-all placeholder:dark:text-white text-on-surface/30 resize-none h-28 leading-relaxed" placeholder="e.g. Specific pocket styles, zipper types, or length changes..."></textarea>
                                </div>
                            </div>
                        </section>

                        <!-- ════ STEP 02: THE FOUNDATION ════ -->
                        <section class="form-section">
                            <!-- Step Header -->
                            <div class="flex items-center gap-4 mb-10">
                                <div class="w-10 h-10 rounded-sm bg-primary/10 border border-primary/30 flex items-center justify-center  shrink-0">
                                    <span class="font-headline text-[10px] tracking-[0.2em] text-primary font-medium">02</span>
                                </div>
                                <div>
                                    <p class="text-[10px] font-headline tracking-[0.2em] text-primary/60 font-medium uppercase">Step two</p>
                                    <h3 class="text-2xl font-headline font-medium dark:text-white text-on-surface tracking-widest uppercase">Foundation & Fabric</h3>
                                </div>
                                <div class="h-px flex-1 bg-gradient-to-r from-primary/20 to-transparent ml-2"></div>
                            </div>

                            <div class="space-y-12">
                                <!-- Fit Preference -->
                                <div class="space-y-3">
                                    <label class="text-xs font-medium tracking-widest uppercase dark:text-white text-on-surface/75 block">Preferred Fit</label>
                                    <div class="flex gap-3">
                                        <button 
                                            v-for="fit in ['Tight', 'Regular', 'Oversized']" 
                                            :key="fit"
                                            type="button"
                                            @click="form.selected_fit = fit"
                                            class="flex-1 py-3 border text-xs font-medium tracking-widest uppercase transition-all rounded-sm"
                                            :class="form.selected_fit === fit ? 'bg-primary text-background border-primary ' : 'bg-white/[0.03] dark:border-white/10 border-black/10 dark:text-white text-on-surface/55 hover:dark:text-white text-on-surface hover:dark:border-white border-black/20/25'"
                                        >
                                            {{ fit }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Fabric Selection -->
                                <div class="space-y-3">
                                    <label class="text-xs font-medium tracking-widest uppercase dark:text-white text-on-surface/75 block">Fabric Material</label>
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                        <div 
                                            v-for="fabric in fabrics" 
                                            :key="fabric.name"
                                            @click="form.fabric_preference = fabric.name"
                                            class="group cursor-pointer relative aspect-[4/5] rounded-sm overflow-hidden border"
                                            :class="form.fabric_preference === fabric.name ? 'ring-2 ring-primary/60 border-primary/40' : 'dark:border-white/10 border-black/10 opacity-55 hover:opacity-90'"
                                        >
                                            <img :src="fabric.image" class="w-full h-full object-cover" />
                                            <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end p-2">
                                                <span class="text-[11px] font-medium dark:text-white text-on-surface tracking-widest uppercase">{{ fabric.name }}</span>
                                            </div>
                                            <div v-if="form.fabric_preference === fabric.name" class="absolute inset-0 bg-primary/10"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Color Selection -->
                                <div class="space-y-3">
                                    <label class="text-xs font-medium tracking-widest uppercase dark:text-white text-on-surface/75 block">Colour Preference</label>
                                    <div class="flex flex-wrap gap-3">
                                        <div 
                                            v-for="color in eliteColors" 
                                            :key="color.name"
                                            @click="form.selected_color = color.name"
                                            class="cursor-pointer flex items-center gap-2 px-4 py-2.5 rounded-full border transition-all"
                                            :class="form.selected_color === color.name ? 'border-primary bg-primary/10' : 'dark:border-white/10 border-black/10 hover:dark:border-white border-black/20/25'"
                                        >
                                            <div :style="{ backgroundColor: color.hex }" class="w-3.5 h-3.5 rounded-full border dark:border-white border-black/20/25 shadow-sm"></div>
                                            <span class="text-xs font-medium tracking-widest uppercase" :class="form.selected_color === color.name ? 'text-primary' : 'dark:text-white/60 text-black/60'">{{ color.name }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- ════ STEP 03: CONTACT DETAILS ════ -->
                        <section class="form-section">
                            <!-- Step Header -->
                            <div class="flex items-center gap-4 mb-10">
                                <div class="w-10 h-10 rounded-sm bg-primary/10 border border-primary/30 flex items-center justify-center  shrink-0">
                                    <span class="font-headline text-[10px] tracking-[0.2em] text-primary font-medium">03</span>
                                </div>
                                <div>
                                    <p class="text-[10px] font-headline tracking-[0.2em] text-primary/60 font-medium uppercase">Step three</p>
                                    <h3 class="text-2xl font-headline font-medium dark:text-white text-on-surface tracking-widest uppercase">Client Identity</h3>
                                </div>
                                <div class="h-px flex-1 bg-gradient-to-r from-primary/20 to-transparent ml-2"></div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-8 border-t dark:border-white/10 border-black/10">
                                <div class="space-y-2">
                                    <label class="text-xs font-medium tracking-widest uppercase dark:text-white text-on-surface/75 block">Full Name</label>
                                    <input v-model="form.full_name" required class="w-full bg-white/[0.04] border dark:border-white/10 border-black/10 focus:border-primary/50 focus:ring-0 rounded-sm px-3 py-2.5 text-base dark:text-white text-on-surface transition-all placeholder:dark:text-white text-on-surface/30 tracking-wide" placeholder="Your full name" type="text" />
                                    <p v-if="validationErrors.full_name" class="text-xs text-error font-medium mt-1">{{ validationErrors.full_name }}</p>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-xs font-medium tracking-widest uppercase dark:text-white text-on-surface/75 block">WhatsApp Number</label>
                                    <input v-model="form.whatsapp_number" required class="w-full bg-white/[0.04] border dark:border-white/10 border-black/10 focus:border-primary/50 focus:ring-0 rounded-sm px-3 py-2.5 text-base dark:text-white text-on-surface transition-all placeholder:dark:text-white text-on-surface/30 tracking-wide" placeholder="+254 7XX XXX XXX" type="tel" />
                                    <p v-if="validationErrors.whatsapp_number" class="text-xs text-error font-medium mt-1">{{ validationErrors.whatsapp_number }}</p>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-xs font-medium tracking-widest uppercase dark:text-white text-on-surface/75 block">Location</label>
                                    <input v-model="form.location" required class="w-full bg-white/[0.04] border dark:border-white/10 border-black/10 focus:border-primary/50 focus:ring-0 rounded-sm px-3 py-2.5 text-base dark:text-white text-on-surface transition-all placeholder:dark:text-white text-on-surface/30 tracking-wide" placeholder="e.g. Nairobi, CBD" type="text" />
                                    <p v-if="validationErrors.location" class="text-xs text-error font-medium mt-1">{{ validationErrors.location }}</p>
                                </div>
                            </div>
                        </section>

                        <!-- Final Action CTA -->
                        <div class="pt-16">
                            <button type="submit" :disabled="isSubmitting" class="w-full bg-primary text-background px-12 py-8 rounded-[2rem]  hover:bg-white hover:scale-[1.02] active:scale-[0.98] transition-all duration-700 disabled:opacity-50 group">
                                <span class="font-headline tracking-[0.6em] uppercase font-medium text-sm flex items-center justify-center gap-4">
                                    {{ isSubmitting ? 'SENDING ORDER_' : 'SUBMIT REQUEST_' }}
                                    <span v-if="!isSubmitting" class="material-symbols-outlined text-lg group-hover:translate-x-2 transition-transform">bolt</span>
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Visual/Editorial Side -->
                    <div class="lg:col-span-5 hidden lg:block">
                        <div class="sticky top-40 space-y-10">
                            <!-- Priority Pass Card -->
                            <div class="p-6 bg-primary/5 border border-primary/10 rounded-sm space-y-6 relative overflow-hidden group/pass">
                                <div class="absolute -right-4 -top-4 text-primary/5 material-symbols-outlined pointer-events-none group-hover/pass:rotate-12 transition-transform duration-700" style="font-size: 80px;">confirmation_number</div>
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-primary text-sm luminous-glow">shuttle</span>
                                    <h3 class="font-headline text-[10px] tracking-[0.2em] font-medium uppercase dark:text-white text-on-surface">Priority Pass_</h3>
                                </div>
                                <p class="text-[9px] text-on-surface-variant leading-relaxed uppercase tracking-wider font-light">
                                    Claim a priority production slot for <span class="text-primary font-medium">KSh 1,500</span>.
                                </p>
                                <div class="flex items-center gap-3 py-3 border-t dark:border-white/5 border-black/5">
                                    <input type="checkbox" v-model="form.priority_pass" class="w-4 h-4 rounded dark:border-white/10 border-black/10 bg-white/5 text-primary focus:ring-0 cursor-pointer" />
                                    <span class="text-[8px] font-headline font-medium uppercase tracking-[0.3em] text-[#99ff33]">Claim priority slot_</span>
                                </div>
                            </div>

                            <SizingIntelligence :active-field="activeField" />
                        </div>
                    </div>
                </form>

                <!-- 🛍️ Inspiration Archive -->
            <section class="pt-24 border-t dark:border-white/5 border-black/5">
                <header class="mb-12">
                    <span class="text-[8px] font-headline font-medium tracking-[0.4em] text-primary uppercase mb-3 block">The Archive</span>
                    <h2 class="font-headline text-3xl font-medium dark:text-white text-on-surface tracking-tighter uppercase">Inspiration_</h2>
                </header>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <ProductCard 
                        v-for="product in showcaseProducts" 
                        :key="product.id" 
                        :product="product"
                        layout="grid"
                    />
                </div>
            </section>
        </main>
        </div>
    </StorefrontLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import SizingIntelligence from '@/Components/SizingIntelligence.vue';
import CraftsmanshipBlock from '@/Components/CraftsmanshipBlock.vue';
import ProductCard from '@/Components/ProductCard.vue';
import axios from 'axios';

const props = defineProps({
    showcaseProducts: Array,
    baseProduct: Object
});

const activeField = ref(null);
const fileInput = ref(null);
const isSubmitting = ref(false);
const globalError = ref(null);

const outfitTypes = ['Dress', 'Hoodie', 'Suit', 'Corporate', 'Casual'];
const eliteColors = [
    { name: 'Midnight', hex: '#000000' },
    { name: 'Optic White', hex: '#FFFFFF' },
    { name: 'Serana Green', hex: '#39FF14' },
    { name: 'Desert Sand', hex: '#E2CBA3' },
    { name: 'Navy Tactical', hex: '#000080' }
];

const fabrics = [
    { name: 'Silk',   image: 'https://lh3.googleusercontent.com/aida-public/AB6AXuDaq4o2SQHDWsz8qAgivEvO3vF6LP9__nh7I1qE_3fcLBc1z8oJ575w7C_qniJcEVroCa4Wd0Mi_y0xf4IB6pIiogEf7JwRjyzMtJbCUIUIr7S29jb15rOz6gMZZvtr1fuaeVMHcC3_r0_featC_OEmsW3XUfaPVCs9uLQ7hH6AJCYrWhvCo-74Os94GNkiWPwj4kCHSmbFeCRu7ngJAOZ7g_8ILeElZU4-OUiRbs8kj8xNJGEuTvS9q5P-VkBF3QG-FshbudL3p6E' },
    { name: 'Lace',   image: 'https://lh3.googleusercontent.com/aida-public/AB6AXuDDTOgDD9OUKafDHQUOQCXewcgcAM_yAA51rVpl8LRRiwx-W1tBe-EdANVtKpVlMrPM2j_nStbfT6sjYfurUKdaUpRx1frksqlrLgYRLc7Ho7ixPJltdskqw7CCc_siAN_mro5EHVDacUVMdhOW4mSgwohinV7a1_PU8oJHaMaR7eaMDR0eA-PgvhlIy4hrEYEwpbTPwPzENdt257Vsj-RQeORWyBqmqVNlT-p4Hc4OSRdOhhekJh7FrCybnL8JqK8T9G-ZhjGMnzo' },
    { name: 'Cotton', image: 'https://lh3.googleusercontent.com/aida-public/AB6AXuAETqYRAK_vpiOnt7n8Q36G1K0pPGxws5MaYnh2WCn-7M6oIzfHj_B81-6B2PTgQTDHlL_eux1YhGCZihnbMexa1PyGV0DWh4m6NDCECp_FcfE5VmW_qYYTvwIFH7ajLhn1iRwBOfww_Eyb5UMjnRUwzo3-3FR-4XvWuMdXNiXcL0LhttqSJFSjKQkKKRnEZsqPIhnlUG4cz9chOGFGv8UgKU_DxXHeImljO1LrfJ_4xfZRjdmhe005iCwsZoX1l0237lMd5Bwx-oA' },
    { name: 'Wool',   image: 'https://lh3.googleusercontent.com/aida-public/AB6AXuBn75N6q7O9TR6DyY7BWy-FQmMJDC1yf6PejlEMqepElNdReo1fwdAVKAd-mJWodB_MW8q6wDi3xXaXKZruVotlq0o-VrnEWnt0CARUDaWwAlfZIa4PyPCAh_fnv2LtVVEZN4Uhtru-ZzaAaJQSfgoLUWKsXQKYG6ZPUE1adUx9h2sp5yZ3K15mVI4aq6oqUrLZiVGadw9EvtxSgo0waL-W-Rzla5GzlOXQ0rStT7jlHQ0SCCwPn5Zyt4t90HlEmGkjgeBd10czDgc' },
];

const form = reactive({
    full_name: '',
    whatsapp_number: '',
    location: '',
    outfit_type: '',
    fabric_preference: '',
    selected_color: '',
    notes: '',
    bust_cm: null,
    waist_cm: null,
    hips_cm: null,
    length_cm: null,
    height_cm: null,
    deadline: '',
    occasion: '',
    selected_fit: 'Regular', // New
    images: [],
    priority_pass: false,
    product: props.baseProduct?.id || null 
});

// ── Cinematic Background System ──────────────────────────────────────────────
const bgImages = [
    '/images/White hoodie with silver zipper details.png',
    '/images/Black and white hoodies showcase.png',
    '/images/beige_contrast_set.png',
    '/images/detailed_texture.png',
    '/images/navy_contrast_set.png',
    '/images/Black zip-up hoodie on gray background.png',
    '/images/hero_editorial.png',
    '/images/ready_to_wear_rack.png',
    '/images/category_ladies.png',
    '/images/black_cloth_texture.png',
];
const slotA = ref(bgImages[0]);
const slotB = ref(bgImages[1]);
const showA = ref(true);
let bgInterval = null;
let bgIndex = ref(1);
// ─────────────────────────────────────────────────────────────────────────────

onMounted(() => {
    bgInterval = setInterval(() => {
        bgIndex.value = (bgIndex.value + 1) % bgImages.length;
        if (showA.value) {
            slotB.value = bgImages[bgIndex.value];
            showA.value = false;
        } else {
            slotA.value = bgImages[bgIndex.value];
            showA.value = true;
        }
    }, 7000);

    if (props.baseProduct) {
        form.outfit_type = props.baseProduct.category?.name || 'Custom';
        form.notes = `I want to customize the ${props.baseProduct.name} (Base Reference).`;
    }
});

onUnmounted(() => clearInterval(bgInterval));

// ── Form Progress (drives the step tracker) ─────────────────────────
const formProgress = computed(() => {
    let p = 0;
    if (form.outfit_type || form.occasion) p = 1;
    if (form.fabric_preference || form.selected_fit !== 'Regular') p = 2;
    if (form.full_name || form.whatsapp_number) p = 3;
    return p;
});
// ─────────────────────────────────────────────────────────────────

const validationErrors = reactive({
    full_name: '',
    whatsapp_number: '',
    location: '',
    bust_cm: '',
    waist_cm: '',
    hips_cm: '',
    length_cm: '',
    height_cm: '', // New
    images: ''
});

const validateField = (field) => {
    validationErrors[field] = '';
    const val = form[field];
    
    if (val === null || val === '') return;

    // Measurement sanity checks
    if (field === 'bust_cm' && (val < 60 || val > 200)) validationErrors[field] = 'Range: 60-200cm';
    if (field === 'waist_cm' && (val < 40 || val > 180)) validationErrors[field] = 'Range: 40-180cm';
    if (field === 'hips_cm' && (val < 60 || val > 220)) validationErrors[field] = 'Range: 60-220cm';
    if (field === 'length_cm' && (val < 30 || val > 250)) validationErrors[field] = 'Range: 30-250cm';
};

const triggerFileInput = () => fileInput.value.click();

const handleFileSelect = (e) => {
    const files = Array.from(e.target.files);
    form.images = [...form.images, ...files];
};

const submitOrder = async () => {
    Object.keys(validationErrors).forEach(key => delete validationErrors[key]);
    globalError.value = null; // Clear previous system errors
    isSubmitting.value = true;
    
    try {
        const formData = new FormData();
        Object.keys(form).forEach(key => {
            if (key === 'images') {
                form.images.forEach(img => formData.append('images[]', img));
            } else if (form[key] !== null) {
                formData.append(key, form[key]);
            }
        });

        const response = await axios.post(route('custom-orders.store'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        if (response.data.whatsapp_url) {
            window.location.href = response.data.whatsapp_url;
        } else {
            // Fallback just in case
            window.location.reload();
        }
    } catch (error) {
        console.error("AxiosError:", error);
        
        if (error.response?.status === 422) {
            Object.assign(validationErrors, error.response.data.errors);
            
            // Scroll to first error
            const firstError = document.querySelector('.text-error');
            if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            // Emulate the requested Artisan connection error
            globalError.value = "SYSTEM ERROR: Artisan connection lost. Please try again.";
        }
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;900&family=Outfit:wght@300;400;700;900&display=swap');

:deep(body) {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    text-rendering: optimizeLegibility;
}

.font-headline {
    font-family: 'Outfit', 'Inter', sans-serif !important;
}

main {
    font-family: 'Inter', sans-serif;
}

.reveal-step {
    animation: revealStep 1.2s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

@keyframes revealStep {
    0% { opacity: 0; transform: translateY(30px); }
    100% { opacity: 1; transform: translateY(0); }
}

.animate-shake {
    animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
}

@keyframes shake {
    10%, 90% { transform: translate3d(-1px, 0, 0); }
    20%, 80% { transform: translate3d(2px, 0, 0); }
    30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
    40%, 60% { transform: translate3d(4px, 0, 0); }
}

.luminous-glow {
    text-shadow: 0 0 10px rgba(57, 255, 20, 0.3);
}

/* Form section glassmorphic cards */
.form-section {
    @apply glass-panel;
    padding: 2.5rem;
    border-radius: 1px;
    animation: sectionReveal 1.2s cubic-bezier(0.16, 1, 0.3, 1) both;
}

input, textarea {
    background: transparent !important;
    border: none !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
    border-radius: 0 !important;
    padding: 0.75rem 0 !important;
    transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

input:focus, textarea:focus {
    border-bottom: 1px solid var(--primary) !important;
    background: rgba(var(--primary-rgb), 0.02) !important;
}

@media (min-width: 640px) {
    .form-section { padding: 2.5rem; }
}
.form-section:hover {
    border-color: rgba(57, 255, 20, 0.15);
    box-shadow: 0 0 40px rgba(57, 255, 20, 0.04), inset 0 1px 0 rgba(255,255,255,0.04);
    transition: border-color 0.6s ease, box-shadow 0.6s ease;
}

@keyframes sectionReveal {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: translateY(0); }
}
.form-section:nth-child(1) { animation-delay: 0.1s; }
.form-section:nth-child(2) { animation-delay: 0.25s; }
.form-section:nth-child(3) { animation-delay: 0.4s; }

/* Scan lines */
.scan-lines {
    background-image: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 2px,
        rgba(255, 255, 255, 0.04) 2px,
        rgba(255, 255, 255, 0.04) 3px
    );
}
</style>
