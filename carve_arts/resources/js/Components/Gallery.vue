<script setup>
const galleryItems = [
    {
        id: 1,
        title: 'Neon Acrylic Signage',
        category: 'Laser Cutting',
        imgUrl: 'https://images.unsplash.com/photo-1561571994-3c61c554181a?q=80&w=600&auto=format&fit=crop'
    },
    {
        id: 2,
        title: 'Custom Hoodie Embroidery',
        category: 'Textile Branding',
        imgUrl: 'https://lh3.googleusercontent.com/aida-public/AB6AXuDBJqtKEJNtnWyJUqrFAXiEC34fLpsUgGG6sU5LTONEU5wJPfYrjU2G_Ni6NTQr99T68aNvwaSw6by2uG-0Xn500dzaYkmQmcS1gWW7uVl76C08LAzkIj7CwyVr-gh_cTBuTZKagQSMzHcexqZ2moUqiBbqtRXrNvkbgVUfEF7yTGTinrrkVZeUY4Jvv-M0pWCcEjIx9Z0Q5IYqFyQOORcjsHrTMl0gJO12bT3-Ip4FAUeC3sONGAaTeI5Je7VQ12XODeuVZ0CLZkm8'
    },
    {
        id: 3,
        title: 'Branded Ceramic Mugs',
        category: 'Sublimation',
        imgUrl: 'https://lh3.googleusercontent.com/aida-public/AB6AXuDuQrMDTVfZE5MV7EIJyS_mDM4sBBp0a1e2sPfyut4ZITJdtq9Wl7_vMZeh3DAmCghtSR6juJ8tioabMY5MJGFSeulUhJnhiNfW4k0bx_OVLRvyLgscAooJRuoEXuA35cGfLcaddpFujqcX60uwiXDHhiPZpQgN6evcEYgVd1kXUmd3CNqHt1wwgY1nU9U1tYFZVqNkDZPnPtfJWywFteNVrR6kf4pZfhDRaGI-hL8nJQXTDRVXBm2dw8Ra1X9PbyMYdOPkYsRoip2x'
    },
    {
        id: 4,
        title: 'Executive Desk Sets',
        category: 'Corporate Gifts',
        imgUrl: 'https://lh3.googleusercontent.com/aida-public/AB6AXuDGYQfqq2MGhnREgtYKdTPE3RnmDVy6KlWnmz7s0leqeVU2c_StlsumAB0PEOtF_S7-2n5LOOSnfDEZWciSB9tbAZ5ArHdKQ4tQZxXYRryoZZILsKigu7qByBcbH4boD_lIxWh8qAZ0CtmMjTBrdWtSb1YgiX10dpEdgr1cZwEWEpPC7DaZ6lRBTG3RqXoGh2fPHvNeUdZDVg3aPZHaP4l291W-j-4AWV6euM8OnecGNhuLE76O7-Fb7zZfcEuamyx4X-ywSH7NZEdI'
    },
    {
        id: 5,
        title: 'Premium Snapbacks',
        category: 'Headwear',
        imgUrl: 'https://lh3.googleusercontent.com/aida-public/AB6AXuCGqTR38kBIyHKwv3OA3fAnvJCO9zKONSKpUUzu9tW4moJVGKqx9nIE1ELHiziraftzOOT3e7ZdJto_eslK-4NIaEMuygRnQIPMv1hMfNeOepo-yvCT_wjAOixVDN_Ep0LIMaPB5MlR3CNyfhx38HoH6Twbx8tQv5Cw0n1Bo0rkMO6_7H9nWqvDB2YNBA4ovv3tnp2m8GD1S_ImVB5DYUOjKmESXFo3wxYbLFyR9AwsC6b2AuaBHB0Jgj6YjNAJeqrwcqdKEc88SJFN'
    },
    {
        id: 6,
        title: 'Heavy Duty Prints',
        category: 'Heat Press',
        imgUrl: 'https://images.unsplash.com/photo-1620121692029-d088224ddc74?q=80&w=600&auto=format&fit=crop'
    }
];
</script>

<template>
    <section id="gallery" class="py-24 px-8 max-w-screen-2xl mx-auto border-t border-outline-variant/10">
        <div v-reveal class="flex flex-col md:flex-row justify-between items-end mb-16 gap-6">
            <div>
                <h2 class="font-headline text-4xl md:text-6xl font-black text-on-surface uppercase tracking-tighter mb-4">
                    Project <span class="text-primary">Gallery</span>
                </h2>
                <div class="h-1 w-24 bg-primary mb-6"></div>
                <p class="text-on-surface-variant max-w-lg">A glimpse into our atelier. Real brands, real impact. Hover over the items to explore our craftsmanship.</p>
            </div>
            <button class="px-8 py-3 rounded-xl font-bold text-sm uppercase tracking-widest border border-primary text-primary hover:bg-primary hover:text-on-primary transition-all shadow-[0_0_15px_rgba(153,255,51,0.1)] hover:shadow-[0_0_20px_rgba(153,255,51,0.3)]">
                View Instagram
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div 
                v-for="(item, index) in galleryItems" 
                :key="item.id"
                v-reveal
                :style="{ transitionDelay: (index * 0.12) + 's' }"
                class="group relative h-80 rounded-2xl overflow-hidden bg-surface-container cursor-pointer shadow-lg reveal-scale"
            >
                <img 
                    :src="item.imgUrl" 
                    :alt="item.title"
                    class="absolute inset-0 w-full h-full object-cover grayscale opacity-70 group-hover:grayscale-0 group-hover:opacity-100 group-hover:scale-110 transition-all duration-700" 
                />
                <div class="absolute inset-0 bg-gradient-to-t from-background via-background/40 to-transparent opacity-80 group-hover:opacity-90 transition-opacity"></div>
                
                <div class="absolute inset-0 p-8 flex flex-col justify-end translate-y-4 group-hover:translate-y-0 transition-transform duration-500">
                    <span class="text-primary font-headline font-bold text-[10px] uppercase tracking-widest mb-2 opacity-0 group-hover:opacity-100 transition-opacity duration-700 delay-100">{{ item.category }}</span>
                    <h3 class="text-2xl font-headline font-bold text-on-surface">{{ item.title }}</h3>
                </div>
            </div>
        </div>
    </section>
</template>
