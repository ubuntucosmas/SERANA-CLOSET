<script setup>
</script>

<template>
    <section id="home" class="relative min-h-screen flex items-center px-8 overflow-hidden pt-24 pb-12">
        <div class="max-w-screen-2xl mx-auto w-full grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            
            <!-- Text Content -->
            <div v-reveal class="lg:col-span-7 z-10">
                <div class="inline-flex items-center gap-3 px-4 py-2 bg-surface-container rounded-full mb-6 outline outline-primary/20 hover:outline-primary/50 transition-colors cursor-default">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                    </span>
                    <span class="text-xs font-bold uppercase tracking-[0.2em] text-primary">Free deliveries in Nairobi</span>
                </div>
                
                <h1 class="font-headline text-5xl md:text-7xl lg:text-8xl font-black leading-[0.9] tracking-tighter mb-8 text-on-surface">
                    Turn that <span class="text-primary italic neon-glow">imaginable</span> design into a reality.
                </h1>
                
                <p class="font-body text-lg md:text-xl text-on-surface-variant max-w-xl mb-10 leading-relaxed">
                    Precision carving of brand identities. From premium heat press branding to technical embroidery, we bring your vision to life in our neon atelier.
                </p>
                
                <div class="flex flex-wrap gap-6">
                    <button class="bg-gradient-to-br from-primary to-primary-container text-on-primary px-8 md:px-10 py-4 md:py-5 rounded-xl font-bold text-base md:text-lg uppercase tracking-widest shadow-lg shadow-primary/20 hover:scale-105 active:scale-95 transition-all duration-300">
                        Start Your Project
                    </button>
                    <button class="px-8 md:px-10 py-4 md:py-5 rounded-xl font-bold text-base md:text-lg uppercase tracking-widest border border-outline-variant/30 text-secondary hover:bg-surface-container hover:border-primary/50 transition-all duration-300 group">
                        <span class="group-hover:text-primary transition-colors">View Gallery</span>
                    </button>
                </div>
            </div>
            
            <!-- Image Grid -->
            <div v-reveal class="lg:col-span-5 relative mt-12 lg:mt-0 reveal-right">
                <div class="relative grid grid-cols-2 gap-4">
                    <div class="mt-8 md:mt-12 group">
                        <img 
                            class="w-full h-[300px] md:h-[400px] object-cover rounded-2xl shadow-2xl rotate-[-2deg] group-hover:rotate-0 transition-all duration-500 border border-outline-variant/20 group-hover:border-primary/30" 
                            alt="High quality white t-shirt mockup" 
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuBcU60Hui8MyHVvZrTgnBHKWrOA_TO-gjp_8oWw7mfizVGa7B4QRE_Uu0KLIniaQxqPhcNg6i7wThWixyWnUQfcerBNr3qP2owe8Dpyg0u2jniuV_6sW3XJkkFhVPTKe3B-Wrl6BOkgszX1tyHTTISyi8WaAl9pdsZ4_oY2FFUNDvEIOCOFw6SQRNy3a72ff8onnlBOE_wLhZTGES22NcjrY1RSeaH9C8b-_CO27nKtkn41NPZaE8BvExB64ssGxpjigcUzT2X8hXn5"
                        />
                    </div>
                    <div class="space-y-4">
                        <img 
                            class="w-full h-[180px] md:h-[250px] object-cover rounded-2xl shadow-2xl rotate-[3deg] hover:rotate-0 transition-all duration-500 border border-outline-variant/20 hover:border-primary/30" 
                            alt="Sleek black ceramic mug" 
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuDuQrMDTVfZE5MV7EIJyS_mDM4sBBp0a1e2sPfyut4ZITJdtq9Wl7_vMZeh3DAmCghtSR6juJ8tioabMY5MJGFSeulUhJnhiNfW4k0bx_OVLRvyLgscAooJRuoEXuA35cGfLcaddpFujqcX60uwiXDHhiPZpQgN6evcEYgVd1kXUmd3CNqHt1wwgY1nU9U1tYFZVqNkDZPnPtfJWywFteNVrR6kf4pZfhDRaGI-hL8nJQXTDRVXBm2dw8Ra1X9PbyMYdOPkYsRoip2x"
                        />
                        <img 
                            class="w-full h-[180px] md:h-[250px] object-cover rounded-2xl shadow-2xl hover:scale-105 transition-all duration-500 border border-outline-variant/20 hover:border-primary/30" 
                            alt="Elegant corporate gift set" 
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuDGYQfqq2MGhnREgtYKdTPE3RnmDVy6KlWnmz7s0leqeVU2c_StlsumAB0PEOtF_S7-2n5LOOSnfDEZWciSB9tbAZ5ArHdKQ4tQZxXYRryoZZILsKigu7qByBcbH4boD_lIxWh8qAZ0CtmMjTBrdWtSb1YgiX10dpEdgr1cZwEWEpPC7DaZ6lRBTG3RqXoGh2fPHvNeUdZDVg3aPZHaP4l291W-j-4AWV6euM8OnecGNhuLE76O7-Fb7zZfcEuamyx4X-ywSH7NZEdI"
                        />
                    </div>
                </div>
                
                <!-- Decorative Background Glow -->
                <div class="absolute -z-10 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[120%] h-[120%] md:w-[150%] md:h-[150%] bg-primary/10 blur-[100px] md:blur-[140px] rounded-full pointer-events-none"></div>
            </div>
            
        </div>
    </section>
</template>
