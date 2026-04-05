<script setup>
</script>

<template>
    <section id="services" class="py-24 px-8 max-w-screen-2xl mx-auto">
        <header v-reveal class="mb-20 max-w-3xl">
            <h2 class="font-headline text-5xl md:text-7xl font-black text-on-background tracking-tighter leading-none mb-6">
                OUR <span class="text-primary">SERVICES</span>
            </h2>
            <p class="text-secondary text-lg md:text-xl font-body max-w-xl opacity-80">
                Premium branding and precision craftsmanship. We carve your identity into high-quality merchandise using state-of-the-art tech.
            </p>
            <div class="h-1 w-24 bg-primary mt-8"></div>
        </header>

        <!-- Catalog Bento Grid -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
            
            <!-- T-shirts & Hoodies (Large Featured Card) -->
            <article v-reveal class="md:col-span-8 bg-surface-container rounded-2xl overflow-hidden group relative flex flex-col md:flex-row h-auto md:h-[500px] border border-outline-variant/10 hover:border-primary/30 transition-colors">
                <div class="md:w-1/2 p-8 md:p-12 flex flex-col justify-end relative z-10">
                    <span class="text-primary font-headline font-bold text-xs tracking-widest uppercase mb-4 drop-shadow-md">Core Branding</span>
                    <h3 class="text-3xl md:text-4xl font-headline font-extrabold text-on-surface mb-4">T-shirts &amp; Hoodies</h3>
                    <p class="text-on-surface-variant text-sm md:text-base mb-8 leading-relaxed max-w-sm">
                        From luxury cotton to performance wear. High-fidelity heat press, screen printing, and embroidery solutions for your team or brand.
                    </p>
                    <button class="w-fit bg-primary text-on-primary px-8 py-4 rounded-xl font-headline font-bold uppercase tracking-widest text-xs hover:scale-105 active:scale-95 transition-transform duration-300 shadow-[0_0_15px_rgba(153,255,51,0.2)] hover:shadow-[0_0_20px_rgba(153,255,51,0.4)]">
                        Get a Quote
                    </button>
                </div>
                <div class="md:w-1/2 relative bg-surface-container-highest h-64 md:h-full overflow-hidden">
                    <img 
                        alt="Premium hoodie branding" 
                        class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700 scale-105 group-hover:scale-110" 
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuCFdt0wczTlVv_dGKyG31IIduB0h0kr-N7fsZBd2XtwyYP0ZJhq-sJQlQkejPpWvFUlh977rmQD-O7_1FEREv3SC17fnaG6ycpXLU7KKgIyVI9ombRocyp6-tkUqlS3VAMWMGa7qT6D4ItXEcWfHOFB-whvrwihhOyrLO3QprH1UHx0XLCVaMDMbSTVVr7or5ilRb5nclPC2QWa9GOuGpSeHq3wfc9M94TnJXaCLktqEdRwu3Ffo_5lliS-7UeuYi8TNFpzEIkk-rJu"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t md:bg-gradient-to-r from-surface-container via-surface-container/80 md:via-transparent to-transparent"></div>
                </div>
            </article>

            <!-- Mugs & Kitchenware -->
            <article v-reveal class="md:col-span-4 bg-surface-container-high rounded-2xl p-8 flex flex-col justify-between group hover:bg-surface-container-highest transition-colors border border-outline-variant/10 hover:border-primary/30 reveal-delay-200">
                <div class="mb-8">
                    <div class="w-14 h-14 bg-primary/10 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 group-hover:bg-primary/20 transition-all duration-300">
                        <span class="material-symbols-outlined text-primary text-3xl">coffee</span>
                    </div>
                    <h3 class="text-2xl font-headline font-bold text-on-surface mb-3">Mugs &amp; Kitchenware</h3>
                    <p class="text-on-surface-variant text-sm leading-relaxed">
                        Sublimation printing on ceramic, enamel, and glassware. Perfect for corporate coffee breaks and personalized gifting.
                    </p>
                </div>
                <div class="flex flex-col gap-5 mt-auto">
                    <div class="overflow-hidden rounded-xl">
                        <img 
                            alt="Branded mugs" 
                            class="w-full h-40 object-cover grayscale group-hover:grayscale-0 group-hover:scale-105 transition-all duration-500" 
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuB6JGWwQZjS6K9ELhhe7lQJ5ScxyjW_Gc4uPyO5k0wsoL-dVsu55O_7CQlZsTRToVz-z7RDu9igcGzsao2L5ita0-mmvxabWrgNIYvfGTGvmpCkclI0so2DaiNFy1RraJ-sXU0Kt6AVI-t5o3G60cqFScEzlTSe6xJc4_qt49CiiOmnJlRBa1QQ-2Q3kcP9CJcqpYkG1qaUIouTXrjnPPcCg9SOa-Z7HixcQxuZEpP1gz88fqIkOe_R8q4RrFWI_vrxD7Wb1fN-e9u2"
                        />
                    </div>
                    <button class="text-primary font-headline font-bold uppercase text-[11px] tracking-widest flex items-center gap-2 hover:translate-x-2 transition-transform w-fit">
                        Request Pricing <span class="material-symbols-outlined text-base">arrow_forward</span>
                    </button>
                </div>
            </article>

            <!-- Notebooks & Stationery -->
            <article v-reveal class="md:col-span-4 bg-surface-container rounded-2xl p-8 flex flex-col group relative overflow-hidden border border-outline-variant/10 hover:border-primary/30 transition-colors reveal-delay-300">
                <div class="relative z-10 h-full flex flex-col">
                    <h3 class="text-2xl font-headline font-bold text-on-surface mb-3">Notebooks &amp; Stationery</h3>
                    <p class="text-on-surface-variant text-sm mb-8 leading-relaxed">
                        Custom journals, embossed leather planners, and premium desk accessories that elevate your professional identity.
                    </p>
                    <button class="mt-auto bg-surface-variant text-primary border border-primary/20 px-6 py-4 rounded-xl font-headline font-bold text-[10px] uppercase tracking-widest hover:bg-primary hover:text-on-primary transition-all shadow-sm w-fit">
                        Get a Quote
                    </button>
                </div>
                <div class="absolute -bottom-8 -right-8 w-48 h-48 opacity-10 group-hover:opacity-20 group-hover:scale-110 transition-all duration-500 pointer-events-none">
                    <span class="material-symbols-outlined text-primary text-[180px]">menu_book</span>
                </div>
            </article>

            <!-- Corporate & Custom Gifts (Medium Featured) -->
            <article v-reveal class="md:col-span-8 bg-surface-container-low border border-outline-variant/10 rounded-2xl flex flex-col md:flex-row group overflow-hidden hover:border-primary/30 transition-colors reveal-delay-100">
                <div class="md:w-2/5 p-8 md:p-10 flex flex-col justify-center bg-surface-container-high/50 group-hover:bg-surface-container-high transition-colors">
                    <h3 class="text-3xl font-headline font-bold text-on-surface mb-4 leading-tight">Corporate &amp; Custom Gifts</h3>
                    <p class="text-on-surface-variant text-sm md:text-base mb-8">
                        Curated gift sets featuring high-end tech, leather goods, and personalized packaging for premium brand experiences.
                    </p>
                    <button class="text-primary underline underline-offset-8 font-headline font-bold uppercase text-xs tracking-widest hover:text-white transition-colors w-fit">
                        Explore Gift Sets
                    </button>
                </div>
                <div class="md:w-3/5 h-64 md:h-auto overflow-hidden">
                    <img 
                        alt="Corporate gift set" 
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" 
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuBstL691YqI1uFRg2TSWDXO_osJ_naG1s92jT8BR7W6bIWy9k2k3DmyYv9Pbykp0SB5xFBgZb8h-xS12Evl5x_T6RPnwW3agRq6vZ8CckWKeiZeQaBOEvvzt48PM9b_MqRGGiZ4t7SXM9e93Wi0ZwB8Fq0nzJ4kn8N-laHVSWovK_OPEseEehm0ZYzSpK6dW0VJleRfpJCWk7zRW5i_NXcKGR2r3099t7rxPfr98o6ut91RxyqDXJMPkfciRIYHWxufdIQD7o0c-1bi"
                    />
                </div>
            </article>

            <!-- Caps & Umbrellas -->
            <article v-reveal class="md:col-span-6 bg-surface-container rounded-2xl p-8 flex items-center justify-between group overflow-hidden relative border border-outline-variant/10 hover:border-primary/30 transition-colors min-h-[220px] reveal-delay-200">
                <div class="max-w-[55%] relative z-10">
                    <h3 class="text-2xl font-headline font-bold text-on-surface mb-3">Caps &amp; Umbrellas</h3>
                    <p class="text-on-surface-variant text-sm mb-6 leading-relaxed hidden sm:block">
                        Weather-resistant branding. From snapbacks to heavy-duty golf umbrellas.
                    </p>
                    <button class="bg-primary text-on-primary px-6 py-3 rounded-xl font-headline font-bold uppercase tracking-widest text-[10px] shadow-[0_0_10px_rgba(153,255,51,0.2)] hover:scale-105 transition-transform">
                        Get a Quote
                    </button>
                </div>
                <div class="absolute right-[-20px] top-1/2 -translate-y-1/2 w-40 h-40 md:w-56 md:h-56">
                    <img 
                        alt="Branded cap" 
                        class="w-full h-full object-cover rounded-full rotate-12 group-hover:rotate-0 transition-transform duration-700 shadow-2xl" 
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuCGqTR38kBIyHKwv3OA3fAnvJCO9zKONSKpUUzu9tW4moJVGKqx9nIE1ELHiziraftzOOT3e7ZdJto_eslK-4NIaEMuygRnQIPMv1hMfNeOepo-yvCT_wjAOixVDN_Ep0LIMaPB5MlR3CNyfhx38HoH6Twbx8tQv5Cw0n1Bo0rkMO6_7H9nWqvDB2YNBA4ovv3tnp2m8GD1S_ImVB5DYUOjKmESXFo3wxYbLFyR9AwsC6b2AuaBHB0Jgj6YjNAJeqrwcqdKEc88SJFN"
                    />
                </div>
            </article>

            <!-- Design Consultation (CTA Card) -->
            <article v-reveal class="md:col-span-6 bg-gradient-to-br from-primary to-primary-container rounded-2xl p-8 flex flex-col justify-center items-center text-center group shadow-[0_0_30px_rgba(153,255,51,0.1)] hover:shadow-[0_0_40px_rgba(153,255,51,0.2)] transition-shadow reveal-delay-300">
                <h3 class="text-on-primary font-headline text-3xl font-black uppercase mb-3 drop-shadow-sm">Custom Artwork?</h3>
                <p class="text-on-primary/90 font-body mb-8 max-w-sm text-sm md:text-base font-medium">
                    Our studio can help you carve a new identity from scratch. Book a design consultation today.
                </p>
                <div class="flex gap-4">
                    <a href="#contact" class="bg-surface text-primary px-8 py-4 rounded-xl font-headline font-bold uppercase tracking-widest text-xs hover:scale-105 hover:bg-surface-container-high transition-all shadow-xl">
                        Message Atelier
                    </a>
                </div>
            </article>
            
        </div>
    </section>
</template>
