<script setup>
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Account Settings" />

    <StorefrontLayout>
        <header class="max-w-5xl mx-auto px-6 mb-8">
            <h1 class="text-4xl font-headline mb-3">Account Settings</h1>
            <p class="text-on-surface-variant">Update your contact details and password. Each section saves separately.</p>
        </header>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div
                    class="bg-surface-container border border-outline-variant p-5 rounded-2xl sm:p-8"
                >
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div
                    class="bg-surface-container border border-outline-variant p-5 rounded-2xl sm:p-8"
                >
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div
                    class="bg-surface-container border border-outline-variant p-5 rounded-2xl sm:p-8"
                >
                    <DeleteUserForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>
