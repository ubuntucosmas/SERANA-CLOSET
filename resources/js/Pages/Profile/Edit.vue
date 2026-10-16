<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, Link } from '@inertiajs/vue3';

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
        <div class="page-container">
            <PageHeader kicker="Account" title="Account Settings" subtitle="Update your contact details and password. Each section saves separately.">
                <template #actions>
                    <div class="flex flex-wrap gap-3">
                        <Link :href="route('profile.designs')" class="action-secondary">Your Orders</Link>
                        <Link :href="route('logout')" method="post" as="button" class="action-secondary">Sign Out</Link>
                    </div>
                </template>
            </PageHeader>
        </div>

        <div class="py-12">
            <div class="page-container space-y-6">
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
