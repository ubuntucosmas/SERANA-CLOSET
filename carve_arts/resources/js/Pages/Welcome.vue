<script setup>
import { Head } from '@inertiajs/vue3';
import Navbar from '../Components/Navbar.vue';
import Hero from '../Components/Hero.vue';
import Services from '../Components/Services.vue';
import Gallery from '../Components/Gallery.vue';
import Contact from '../Components/Contact.vue';
import Footer from '../Components/Footer.vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Head title="Turn That Imaginable Design Into A Reality" />

    <div class="bg-background text-on-background font-body selection:bg-primary selection:text-on-primary min-h-screen flex flex-col antialiased">
        <Navbar />
        
        <main class="flex-grow">
            <Hero />
            <Services />
            <Gallery />
            <Contact />
        </main>

        <Footer />
    </div>
</template>
