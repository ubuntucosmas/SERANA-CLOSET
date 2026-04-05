<script setup>
</script>

<template>
    <section id="contact" class="py-24 px-8 relative overflow-hidden bg-surface-container-low">
        <!-- Interactive background blobs -->
        <div class="absolute -z-10 top-0 left-0 w-full h-full bg-gradient-to-b from-transparent via-primary/5 to-transparent"></div>
        <div class="absolute -z-10 top-20 -left-20 w-96 h-96 bg-primary/10 blur-[100px] rounded-full"></div>
        <div class="absolute -z-10 bottom-20 -right-20 w-96 h-96 bg-secondary/10 blur-[100px] rounded-full"></div>

        <div class="max-w-5xl mx-auto glass-card flex flex-col md:flex-row gap-12 p-8 md:p-16 rounded-[2rem] border border-outline-variant/15 shadow-2xl relative">
            
            <div v-reveal class="md:w-1/2 flex flex-col justify-center">
                <span class="material-symbols-outlined text-primary text-5xl mb-6 neon-glow">bolt</span>
                <h2 class="font-headline text-4xl md:text-5xl font-black mb-6 text-on-surface leading-tight">Ready to start carving?</h2>
                <p class="text-on-surface-variant text-base mb-10 max-w-sm leading-relaxed">
                    Join hundreds of brands in Nairobi who trust us with their vision. Get a quote within 24 hours.
                </p>
                
                <div class="flex flex-col gap-4">
                    <a href="https://wa.me/254710164230" target="_blank" class="flex items-center justify-center gap-3 bg-primary text-on-primary px-8 py-4 rounded-xl font-black uppercase tracking-widest text-sm hover:scale-105 transition-all shadow-[0_0_20px_rgba(153,255,51,0.3)] hover:shadow-[0_0_30px_rgba(153,255,51,0.5)]">
                        <span class="material-symbols-outlined group-hover:animate-bounce">chat</span>
                        WhatsApp Us
                    </a>
                    <a href="tel:254710164230" class="flex items-center justify-center gap-3 border border-outline-variant/30 text-secondary px-8 py-4 rounded-xl font-black uppercase tracking-widest text-sm hover:bg-surface-container-high transition-all">
                        <span class="material-symbols-outlined">call</span>
                        0710 164 230
                    </a>
                </div>
            </div>

            <div v-reveal class="md:w-1/2 bg-surface-container-highest p-8 md:p-10 rounded-2xl border border-outline-variant/10 shadow-inner reveal-right reveal-delay-200">
                <h3 class="font-headline font-bold text-xl text-on-surface mb-6">Send an Inquiry</h3>
                <form class="flex flex-col gap-5" @submit.prevent="">
                    <div>
                        <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-widest mb-2">Your Name</label>
                        <input class="w-full bg-background border border-outline-variant/20 rounded-xl px-5 py-3 text-on-surface focus:ring-2 focus:ring-primary/40 focus:border-primary/40 transition-all font-body outline-none" placeholder="Brand or Full Name" type="text" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-widest mb-2">Email Address</label>
                        <input class="w-full bg-background border border-outline-variant/20 rounded-xl px-5 py-3 text-on-surface focus:ring-2 focus:ring-primary/40 focus:border-primary/40 transition-all font-body outline-none" placeholder="hello@yourbrand.com" type="email" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-widest mb-2">Project Details</label>
                        <textarea class="w-full bg-background border border-outline-variant/20 rounded-xl px-5 py-3 text-on-surface focus:ring-2 focus:ring-primary/40 focus:border-primary/40 transition-all font-body h-32 resize-none outline-none" placeholder="Tell us about your merchandise requirements..."></textarea>
                    </div>
                    <button class="w-full bg-surface-bright text-primary border border-primary/20 mt-2 px-8 py-4 rounded-xl font-black uppercase tracking-widest text-sm hover:bg-primary hover:text-on-primary transition-all group">
                        Submit Inquiry
                        <span class="material-symbols-outlined align-middle ml-2 text-sm group-hover:translate-x-1 transition-transform">send</span>
                    </button>
                </form>
            </div>

        </div>
    </section>
</template>
