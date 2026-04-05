<script setup>
</script>

<template>
    <footer class="w-full py-16 px-8 bg-surface-container-lowest border-t border-outline-variant/15 relative overflow-hidden">
        <!-- Decorative subtle background -->
        <div class="absolute inset-0 bg-primary/5 [mask-image:radial-gradient(ellipse_at_center,transparent_20%,black)] pointer-events-none"></div>
        
        <div class="max-w-screen-2xl mx-auto grid grid-cols-1 md:grid-cols-12 gap-12 lg:gap-8 items-start font-body text-sm text-secondary relative z-10">
            
            <!-- Brand Section -->
            <div class="md:col-span-4 lg:col-span-5 flex flex-col gap-6">
                <div>
                    <span class="font-headline font-black text-primary text-2xl tracking-tighter uppercase neon-glow">
                        Carve Arts &amp; Design
                    </span>
                    <p class="mt-4 text-on-surface-variant max-w-xs leading-relaxed text-base">
                        Precision branding for the modern era. We don't just print; we carve your legacy.
                    </p>
                </div>
                <!-- Location details -->
                <div class="flex items-start gap-3 mt-2 text-on-surface-variant">
                    <span class="material-symbols-outlined text-primary mt-0.5">location_on</span>
                    <p>Catherine Ndereba Rd,<br/>Imara Daima, Embakasi,<br/>Nairobi, KE.</p>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div class="md:col-span-4 lg:col-span-3">
                <h4 class="font-headline font-bold text-on-surface uppercase tracking-widest text-xs mb-6">Explore</h4>
                <div class="flex flex-col gap-4">
                    <a href="#services" class="hover:text-primary transition-colors w-fit">Services & Products</a>
                    <a href="#gallery" class="hover:text-primary transition-colors w-fit">Project Gallery</a>
                    <a href="#" class="hover:text-primary transition-colors w-fit">About Our Atelier</a>
                    <a href="#contact" class="hover:text-primary transition-colors w-fit">Get a Quote</a>
                </div>
            </div>
            
            <!-- Contact Section -->
            <div class="md:col-span-4 lg:col-span-4">
                <h4 class="font-headline font-bold text-on-surface uppercase tracking-widest text-xs mb-6">Get in Touch</h4>
                <div class="flex flex-col gap-5">
                    <a href="tel:0710164230" class="flex items-center gap-3 hover:text-primary hover:translate-x-1 transition-all group w-fit">
                        <div class="w-10 h-10 rounded-full bg-surface-container flex items-center justify-center group-hover:bg-primary/20 transition-colors">
                            <span class="material-symbols-outlined text-base">call</span>
                        </div>
                        <span class="text-base font-medium">0710 164 230</span>
                    </a>
                    
                    <a href="tel:0794845966" class="flex items-center gap-3 hover:text-primary hover:translate-x-1 transition-all group w-fit">
                        <div class="w-10 h-10 rounded-full bg-surface-container flex items-center justify-center group-hover:bg-primary/20 transition-colors">
                            <span class="material-symbols-outlined text-base">call</span>
                        </div>
                        <span class="text-base font-medium">0794 845 966</span>
                    </a>
                    
                    <div class="flex gap-4 mt-4">
                        <a href="#" class="w-10 h-10 rounded-full bg-surface-container flex items-center justify-center hover:bg-primary hover:text-on-primary transition-all shadow-sm">
                            <span class="material-symbols-outlined text-lg">public</span>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-surface-container flex items-center justify-center hover:bg-primary hover:text-on-primary transition-all shadow-sm">
                            <span class="material-symbols-outlined text-lg">share</span>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-surface-container flex items-center justify-center hover:bg-primary hover:text-on-primary transition-all shadow-sm">
                            <span class="material-symbols-outlined text-lg">alternate_email</span>
                        </a>
                    </div>
                </div>
            </div>
            
        </div>
        
        <!-- Bottom Bar -->
        <div class="max-w-screen-2xl mx-auto mt-16 pt-8 border-t border-outline-variant/10 flex flex-col md:flex-row justify-between items-center gap-4 text-xs text-on-surface-variant relative z-10">
            <p>© {{ new Date().getFullYear() }} Carve Arts &amp; Design. All rights reserved.</p>
            <div class="flex gap-6">
                <a href="#" class="hover:text-primary transition-colors">Privacy Policy</a>
                <a href="#" class="hover:text-primary transition-colors">Terms of Service</a>
            </div>
        </div>
    </footer>
</template>
