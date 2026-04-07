<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import SizingIntelligence from '@/Components/SizingIntelligence.vue';
import { useCartStore } from '@/Stores/useCartStore';
import { ref } from 'vue';

const cart = useCartStore();
const step = ref(1);
const isProcessing = ref(false);
const isSuccess = ref(false);
const orderId = ref(null);
const activeField = ref(null);

const form = ref({
    email: '',
    full_name: '',
    address: '',
    city: '',
    phone: '',
    payment_method: 'mpesa',
    // Optional Precision Sizing
    height_cm: '',
    bust_cm: '',
    waist_cm: '',
    hips_cm: '',
});

async function handlePayment() {
    isProcessing.value = true;
    
    try {
        const payload = {
            ...form.value,
            items: cart.items,
            total: cart.totalPrice
        };
        
        if (!payload.height_cm) delete payload.height_cm;
        if (!payload.bust_cm) delete payload.bust_cm;
        if (!payload.waist_cm) delete payload.waist_cm;
        if (!payload.hips_cm) delete payload.hips_cm;

        const response = await axios.post(route('checkout.process'), payload);

        if (response.data.success) {
            isSuccess.value = true;
            orderId.value = response.data.order_id || Date.now();
            cart.clearBag();
            window.dispatchEvent(new CustomEvent('serana-toast', {
                detail: { message: 'Order created. Proceed to payment.', type: 'success' }
            }));
        }
    } catch (err) {
        console.error(err);
        const message = err.response?.data?.message || err.message;
        if (err.response?.data?.errors) {
            alert('Please check the form. Some required fields might be missing or invalid:\n' + Object.values(err.response.data.errors).map(e => e.join(', ')).join('\n'));
        } else {
            alert(message || 'Order processing failed. Please try again.');
        }
    } finally {
        isProcessing.value = false;
    }
}

function nextStep() {
    if (step.value < 3) step.value++;
}
</script>

<template>
    <StorefrontLayout>
        <Head title="Secure Checkout" />

        <main class="pt-32 pb-24 bg-surface min-h-screen">
            <div class="max-w-[1200px] mx-auto px-8 lg:px-16">
                
                <div class="grid lg:grid-cols-12 gap-16">
                    <!-- Checkout Steps -->
                    <div class="lg:col-span-7 space-y-12">
                        <header class="space-y-4">
                            <Link :href="route('shop')" class="text-[11px] font-headline tracking-[0.2em] font-black uppercase text-primary flex items-center gap-2 mb-8">
                                <span class="material-symbols-outlined text-sm">arrow_back</span> Return to Collection
                            </Link>
                            <h1 class="font-headline text-5xl lg:text-7xl font-black dark:text-white text-on-surface leading-tight">The Final <span class="text-primary luminous-glow">Stitch.</span></h1>
                            <p class="dark:text-white text-on-surface font-label text-sm font-medium">Secure your position in Batch #04. Complete your details below.</p>
                        </header>

                        <!-- Step Tabs -->
                        <div class="flex gap-4 border-b dark:border-white/10 border-black/10 dark:border-white/10 border-black/10 pb-5">
                            <button @click="step = 1" class="text-[11.5px] uppercase tracking-widest font-black transition-all" :class="step >= 1 ? 'text-primary' : 'dark:text-white/40 text-black/40'">01 Identification</button>
                            <span class="dark:text-white/10 dark:text-white text-on-surface/10">/</span>
                            <button @click="step = 2" class="text-[11.5px] uppercase tracking-widest font-black transition-all" :class="step >= 2 ? 'text-primary' : 'dark:text-white/40 text-black/40'">02 Logistics</button>
                            <span class="dark:text-white/10 dark:text-white text-on-surface/10">/</span>
                            <button @click="step = 3" class="text-[11.5px] uppercase tracking-widest font-black transition-all" :class="step >= 3 ? 'text-primary' : 'dark:text-white/40 text-black/40'">03 Secure Payment</button>
                        </div>

                        <!-- Form Content -->
                        <div v-if="step === 1" class="space-y-8 reveal">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div class="space-y-4">
                                    <label class="text-[10.5px] font-black uppercase tracking-widest dark:text-white text-on-surface">Full Legal Name</label>
                                    <input v-model="form.full_name" type="text" placeholder="John Doe" class="w-full bg-transparent border dark:border-white/10 border-black/10 hover:border-primary/50 rounded-sm p-5 dark:text-white text-on-surface font-headline text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:bg-transparent border border-primary/20 transition-all outline-none placeholder:opacity-50" />
                                </div>
                                <div class="space-y-4">
                                    <label class="text-[10.5px] font-black uppercase tracking-widest dark:text-white text-on-surface">Email Address</label>
                                    <input v-model="form.email" type="email" placeholder="john@example.com" class="w-full bg-transparent border dark:border-white/10 border-black/10 hover:border-primary/50 rounded-sm p-5 dark:text-white text-on-surface font-headline text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:bg-transparent border border-primary/20 transition-all outline-none placeholder:opacity-50" />
                                </div>
                            </div>
                            <button @click="nextStep" class="w-full py-5 bg-primary text-black bg-primary text-black rounded-sm font-headline text-[11px] tracking-[0.2em] font-bold uppercase hover:bg-primary hover:text-background transition-all shadow-2xl">
                                Continue to Logistics
                            </button>
                        </div>

                        <div v-if="step === 2" class="space-y-8 reveal">
                            <div class="space-y-8 dark:text-white text-on-surface">
                                <div class="space-y-4">
                                    <label class="text-[10.5px] font-black uppercase tracking-widest dark:text-white text-on-surface">Delivery Address</label>
                                    <input v-model="form.address" type="text" placeholder="House 123, Street Name" class="w-full bg-transparent border dark:border-white/10 border-black/10 hover:border-primary/50 rounded-sm p-5 dark:text-white text-on-surface font-headline text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:bg-transparent border border-primary/20 transition-all outline-none placeholder:opacity-50" />
                                </div>
                                <div class="grid grid-cols-2 gap-8">
                                     <div class="space-y-4">
                                        <label class="text-[10.5px] font-black uppercase tracking-widest dark:text-white text-on-surface">City</label>
                                        <input v-model="form.city" type="text" placeholder="Nairobi" class="w-full bg-transparent border dark:border-white/10 border-black/10 hover:border-primary/50 rounded-sm p-5 dark:text-white text-on-surface font-headline text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:bg-transparent border border-primary/20 transition-all outline-none placeholder:opacity-50" />
                                    </div>
                                    <div class="space-y-4">
                                        <label class="text-[10.5px] font-black uppercase tracking-widest dark:text-white text-on-surface">Phone (M-Pesa Preferred)</label>
                                        <input v-model="form.phone" type="text" placeholder="+254..." class="w-full bg-transparent border dark:border-white/10 border-black/10 hover:border-primary/50 rounded-sm p-5 dark:text-white text-on-surface font-headline text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:bg-transparent border border-primary/20 transition-all outline-none placeholder:opacity-50" />
                                    </div>
                                </div>
                            </div>

                            <!-- Precision Sizing Block (Optional) -->
                            <div class="space-y-6 p-8 border border-primary/20 rounded-sm bg-transparent border border-primary/20">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="material-symbols-outlined text-primary text-sm">straighten</span>
                                    <div>
                                        <h3 class="text-[11px] font-black uppercase tracking-widest dark:text-white text-on-surface">Precision Sizing <span class="text-primary">(Optional)</span></h3>
                                        <p class="text-[10px] dark:text-white/40 text-black/40 mt-1">Help our artisans ensure a perfect fit. All measurements in centimetres (cm).</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-6">
                                    <!-- Height -->
                                    <div class="space-y-3">
                                        <label class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest dark:text-white/40 text-black/40">
                                            Height (cm)
                                            <span class="relative group/tip cursor-help">
                                                <span class="material-symbols-outlined text-primary text-[14px] leading-none">info</span>
                                                <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-52 p-3 bg-surface border border-primary/50 rounded-sm text-[10px] dark:text-white/80 text-black/80 font-normal normal-case tracking-normal leading-relaxed shadow-[0_0_20px_rgba(57,255,20,0.15)] opacity-0 group-hover/tip:opacity-100 pointer-events-none transition-all duration-300 z-50">
                                                    Stand straight against a wall. Measure from the floor to the top of your head.
                                                    <span class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-x-4 border-x-transparent border-t-4 border-t-primary/50"></span>
                                                </span>
                                            </span>
                                        </label>
                                        <input v-model="form.height_cm" type="number" placeholder="e.g. 168"
                                            @focus="activeField = 'height_cm'" @blur="activeField = null"
                                            class="w-full bg-transparent border dark:border-white/10 border-black/10 hover:border-primary/50 rounded-sm p-4 dark:text-white text-on-surface font-headline text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:bg-transparent border border-primary/20 transition-all outline-none placeholder:opacity-40" />
                                    </div>
                                    <!-- Bust -->
                                    <div class="space-y-3">
                                        <label class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest dark:text-white/40 text-black/40">
                                            Bust (cm)
                                            <span class="relative group/tip cursor-help">
                                                <span class="material-symbols-outlined text-primary text-[14px] leading-none">info</span>
                                                <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-52 p-3 bg-surface border border-primary/50 rounded-sm text-[10px] dark:text-white/80 text-black/80 font-normal normal-case tracking-normal leading-relaxed shadow-[0_0_20px_rgba(57,255,20,0.15)] opacity-0 group-hover/tip:opacity-100 pointer-events-none transition-all duration-300 z-50">
                                                    Measure around the fullest part of your chest, keeping the tape horizontal.
                                                    <span class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-x-4 border-x-transparent border-t-4 border-t-primary/50"></span>
                                                </span>
                                            </span>
                                        </label>
                                        <input v-model="form.bust_cm" type="number" placeholder="e.g. 90"
                                            @focus="activeField = 'bust_cm'" @blur="activeField = null"
                                            class="w-full bg-transparent border dark:border-white/10 border-black/10 hover:border-primary/50 rounded-sm p-4 dark:text-white text-on-surface font-headline text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:bg-transparent border border-primary/20 transition-all outline-none placeholder:opacity-40" />
                                    </div>
                                    <!-- Waist -->
                                    <div class="space-y-3">
                                        <label class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest dark:text-white/40 text-black/40">
                                            Waist (cm)
                                            <span class="relative group/tip cursor-help">
                                                <span class="material-symbols-outlined text-primary text-[14px] leading-none">info</span>
                                                <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-52 p-3 bg-surface border border-primary/50 rounded-sm text-[10px] dark:text-white/80 text-black/80 font-normal normal-case tracking-normal leading-relaxed shadow-[0_0_20px_rgba(57,255,20,0.15)] opacity-0 group-hover/tip:opacity-100 pointer-events-none transition-all duration-300 z-50">
                                                    Measure around your natural waistline, typically the narrowest part of your torso.
                                                    <span class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-x-4 border-x-transparent border-t-4 border-t-primary/50"></span>
                                                </span>
                                            </span>
                                        </label>
                                        <input v-model="form.waist_cm" type="number" placeholder="e.g. 74"
                                            @focus="activeField = 'waist_cm'" @blur="activeField = null"
                                            class="w-full bg-transparent border dark:border-white/10 border-black/10 hover:border-primary/50 rounded-sm p-4 dark:text-white text-on-surface font-headline text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:bg-transparent border border-primary/20 transition-all outline-none placeholder:opacity-40" />
                                    </div>
                                    <!-- Hips -->
                                    <div class="space-y-3">
                                        <label class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest dark:text-white/40 text-black/40">
                                            Hips (cm)
                                            <span class="relative group/tip cursor-help">
                                                <span class="material-symbols-outlined text-primary text-[14px] leading-none">info</span>
                                                <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-52 p-3 bg-surface border border-primary/50 rounded-sm text-[10px] dark:text-white/80 text-black/80 font-normal normal-case tracking-normal leading-relaxed shadow-[0_0_20px_rgba(57,255,20,0.15)] opacity-0 group-hover/tip:opacity-100 pointer-events-none transition-all duration-300 z-50">
                                                    Measure around the fullest part of your hips, approximately 20cm below the waistline.
                                                    <span class="absolute top-full left-1/2 -translate-x-1/2 w-0 h-0 border-x-4 border-x-transparent border-t-4 border-t-primary/50"></span>
                                                </span>
                                            </span>
                                        </label>
                                        <input v-model="form.hips_cm" type="number" placeholder="e.g. 96"
                                            @focus="activeField = 'hips_cm'" @blur="activeField = null"
                                            class="w-full bg-transparent border dark:border-white/10 border-black/10 hover:border-primary/50 rounded-sm p-4 dark:text-white text-on-surface font-headline text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:bg-transparent border border-primary/20 transition-all outline-none placeholder:opacity-40" />
                                    </div>
                                </div>
                            </div>

                            <button @click="nextStep" class="w-full py-5 bg-primary text-black bg-primary text-black rounded-sm font-headline text-[11px] tracking-[0.2em] font-bold uppercase hover:bg-primary hover:text-background transition-all shadow-2xl">
                                Continue to Payment
                            </button>
                        </div>

                        <div v-if="step === 3 && !isSuccess" class="space-y-12 reveal">
                            <div class="grid grid-cols-2 gap-6">
                                <button @click="form.payment_method = 'mpesa'" 
                                        class="p-8 bg-transparent rounded-sm border-2 transition-all flex flex-col items-center gap-4 group"
                                        :class="form.payment_method === 'mpesa' ? 'border-primary shadow-[0_0_20px_rgba(57, 255, 20,0.1)]' : 'dark:border-white/10 border-black/10 dark:border-white/10 border-black/10 dark:text-white/40 text-black/40'">
                                    <span class="material-symbols-outlined text-5xl" :class="form.payment_method === 'mpesa' ? 'text-primary' : 'dark:text-white/40 text-black/40'">smartphone</span>
                                    <span class="font-headline text-[11px] uppercase font-black tracking-widest">M-Pesa Direct</span>
                                </button>
                                <button @click="form.payment_method = 'card'"
                                        class="p-8 bg-transparent rounded-sm border-2 transition-all flex flex-col items-center gap-4 group"
                                        :class="form.payment_method === 'card' ? 'border-primary shadow-[0_0_20px_rgba(57, 255, 20,0.1)]' : 'dark:border-white/10 border-black/10 dark:border-white/10 border-black/10 dark:text-white/40 text-black/40'">
                                    <span class="material-symbols-outlined text-5xl" :class="form.payment_method === 'card' ? 'text-primary' : 'dark:text-white/40 text-black/40'">credit_card</span>
                                    <span class="font-headline text-[11px] uppercase font-black tracking-widest">Secure Card</span>
                                </button>
                            </div>

                            <button @click="handlePayment" 
                                    :disabled="isProcessing"
                                    class="w-full py-6 bg-primary text-background rounded-sm font-headline text-xs tracking-[0.3em] font-black uppercase hover:scale-[1.02] transition-all shadow-[0_0_30px_rgba(57, 255, 20,0.3)] disabled:opacity-50">
                                {{ isProcessing ? 'Verifying Transaction...' : 'Confirm & Initialize Payment' }}
                            </button>
                        </div>

                        <!-- Success State -->
                        <div v-if="isSuccess" class="text-center py-20 bg-transparent/50 rounded-3xl border border-primary/20 space-y-8 reveal">
                            <span class="material-symbols-outlined text-7xl text-primary luminous-glow">hourglass_empty</span>
                            <div class="space-y-4">
                                <h2 class="font-headline text-4xl font-black dark:text-white text-on-surface">Order <span class="text-primary">Created.</span></h2>
                                <p class="text-sm dark:text-white/40 text-black/40">Order #{{ String(orderId).slice(-6) }} is pending payment. Complete payment to secure your order.</p>
                            </div>
                            <div class="flex flex-col gap-4 max-w-xs mx-auto">
                                <a href="https://wa.me/$page.props.whatsapp_number" target="_blank" class="py-4 bg-primary text-black rounded-sm font-headline text-[10px] tracking-widest font-bold uppercase shadow-2xl text-center">
                                    Complete Payment via WhatsApp
                                </a>
                                <Link :href="route('home')" class="text-xs dark:text-white/40 text-black/40 hover:text-primary transition-colors">Return to Homepage</Link>
                            </div>
                        </div>
                    </div>

                    <!-- Order Summary Sticky -->
                    <div class="lg:col-span-5">
                        <!-- Show Size Guide only on Step 2 -->
                        <div v-if="step === 2" class="mb-8">
                            <SizingIntelligence :activeField="activeField" />
                        </div>
                        <div class="lg:sticky lg:top-32 p-10 bg-transparent/30 backdrop-blur-xl border dark:border-white/10 border-black/10 hover:border-primary/50 rounded-3xl space-y-10">
                            <div class="flex items-center gap-4 border-b dark:border-white/10 border-black/10 dark:border-white/10 border-black/10 pb-4">
                                <span class="material-symbols-outlined text-primary luminous-glow">shopping_bag</span>
                                <h2 class="font-headline text-lg font-black dark:text-white text-on-surface">Order Summary</h2>
                            </div>

                            <div class="space-y-6 max-h-64 overflow-y-auto pr-2 custom-scrollbar">
                                <div v-for="item in cart.items" :key="item.id" class="flex justify-between items-center group">
                                    <div class="flex gap-4">
                                        <img :src="item.image" class="w-14 h-20 object-cover rounded-sm border dark:border-white/10 border-black/10 hover:border-primary/50" />
                                        <div class="flex flex-col justify-center">
                                            <p class="dark:text-white text-on-surface text-sm font-black">{{ item.name }}</p>
                                            <p class="text-[10.5px] text-primary uppercase tracking-widest font-black">Qty: {{ item.quantity }}</p>
                                        </div>
                                    </div>
                                    <span class="dark:text-white text-on-surface text-sm font-headline font-black">KSh {{ (item.price * item.quantity).toLocaleString() }}</span>
                                </div>
                            </div>

                            <div class="space-y-4 pt-10 border-t dark:border-white/10 border-black/10 hover:border-primary/50">
                                <div class="flex justify-between text-sm dark:text-white text-on-surface font-medium">
                                    <span class="uppercase tracking-widest text-[11px] font-black">Subtotal</span>
                                    <span class="font-black">KSh {{ cart.totalPrice.toLocaleString() }}</span>
                                </div>
                                <div class="flex justify-between text-sm dark:text-white text-on-surface font-medium">
                                    <span class="uppercase tracking-widest text-[11px] font-black">Shipping</span>
                                    <span class="text-primary font-black">Complimentary</span>
                                </div>
                                <div class="flex justify-between items-baseline pt-8 border-t dark:border-white/10 border-black/10 hover:border-primary/50">
                                    <span class="text-lg font-headline dark:text-white text-on-surface uppercase tracking-widest font-black">Total</span>
                                    <span class="text-4xl font-headline text-primary font-black luminous-glow">KSh {{ cart.totalPrice.toLocaleString() }}</span>
                                </div>
                            </div>

                            <div class="p-6 bg-transparent border border-primary/20 rounded-sm border border-primary/20 space-y-4">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-primary text-sm">enhanced_encryption</span>
                                    <span class="text-[10.5px] font-black uppercase tracking-widest dark:text-white text-on-surface">Serana Secure Protocol</span>
                                </div>
                                <p class="text-[11px] dark:text-white text-on-surface leading-relaxed font-medium">
                                    Your secure payment session is active. Once confirmed, your garment moves to immediate priority allocation in our Nairobi Studio.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </StorefrontLayout>
</template>

<style scoped>
.reveal {
    animation: reveal-up 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    opacity: 0;
}

@keyframes reveal-up {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.custom-scrollbar::-webkit-scrollbar {
    width: 1px;
}
</style>
