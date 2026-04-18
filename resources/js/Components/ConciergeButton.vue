<template>
    <div @click="openWhatsApp" class="fixed bottom-6 right-6 z-[100] group cursor-pointer hidden lg:block">
        <div class="flex items-center gap-3">
            <!-- Label (Reveal on Hover) -->
            <div class="bg-black/80 backdrop-blur-xl border border-white/10 px-4 py-2 opacity-0 group-hover:opacity-100 -translate-x-2 group-hover:translate-x-0 transition-all duration-500 pointer-events-none">
                <span class="font-headline text-[9px] tracking-[0.3em] text-white uppercase font-bold">Talk to Us</span>
            </div>
            
            <!-- Icon Bubble (No Background, Larger) -->
            <div class="w-20 h-20 flex items-center justify-center group-hover:scale-110 transition-all duration-500">
                <svg viewBox="0 0 24 24" class="w-12 h-12 fill-[#25D366] drop-shadow-[0_0_20px_rgba(37,211,102,0.4)]">
                    <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766 0-3.18-2.587-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793 0-.853.448-1.273.607-1.446.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86.173.088.274.072.376-.043.101-.116.433-.506.548-.68.116-.173.231-.144.39-.087.158.058 1.011.477 1.184.564.173.087.289.129.332.202.043.073.043.423-.101.827z"/>
                </svg>
            </div>
        </div>
        
        <!-- Subtle Glow Pulse -->
        <div class="absolute inset-0 bg-primary/20 rounded-full blur-[20px] animate-pulse -z-10"></div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const openWhatsApp = () => {
    const phoneNumber = page.props.theme_settings.whatsapp_number || '254700000000'; // Fallback
    const pageTitle = document.title.split('|')[0].trim();
    const message = encodeURIComponent(`Hello Serana Studio, I'm browsing your ${pageTitle} and have a question.`);
    window.open(`https://wa.me/${phoneNumber.replace(/\+/g, '')}?text=${message}`, '_blank');
};
</script>

<style scoped>
/* Zen pulse animation */
@keyframes pulse {
    0% { transform: scale(1); opacity: 0.2; }
    50% { transform: scale(1.1); opacity: 0.3; }
    100% { transform: scale(1); opacity: 0.2; }
}
.animate-pulse {
    animation: pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>
