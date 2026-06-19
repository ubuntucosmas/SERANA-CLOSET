<!-- Showcase gallery grid. Add/edit form lives in the parent (shared useForm). -->
<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    gallery: { type: Array, default: () => [] },
    archived: { type: Array, default: () => [] },
});
const emit = defineEmits(['edit', 'delete', 'restore', 'forceDelete']);

const showArchived = ref(false);
const list = computed(() => showArchived.value ? props.archived : props.gallery);
</script>

<template>
    <div class="space-y-5">
        <div class="flex justify-end">
            <button @click="showArchived = !showArchived"
                :class="['px-4 py-2 rounded-full text-xs font-semibold transition-colors',
                         showArchived ? 'bg-[#57d5fc] text-slate-900' : 'bg-white/5 text-slate-400 hover:text-white']">
                {{ showArchived ? 'Active photos' : 'View archive' }}
            </button>
        </div>

        <div v-if="list.length === 0" class="text-center text-slate-500 py-16 bg-slate-900/40 rounded-2xl ring-1 ring-white/5">
            <span class="material-symbols-outlined text-3xl">photo_library</span>
            <p class="mt-2 text-sm">{{ showArchived ? 'No archived photos.' : 'No showcase photos yet.' }}</p>
        </div>

        <div v-else class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
            <div v-for="g in list" :key="g.id" class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl overflow-hidden">
                <div class="aspect-[4/5] bg-slate-950">
                    <img v-if="g.image_url" :src="g.image_url" :alt="g.garment_name" class="w-full h-full object-cover" />
                </div>
                <div class="p-3">
                    <p class="text-white text-sm font-medium truncate">{{ g.garment_name || 'Untitled' }}</p>
                    <p class="text-slate-500 text-xs truncate">{{ g.client_name || '—' }}</p>
                    <div class="mt-3 flex gap-2">
                        <template v-if="!showArchived">
                            <button @click="emit('edit', g)" class="flex-1 text-xs font-semibold py-2 rounded-lg bg-white/5 text-slate-200 hover:bg-white/10">Edit</button>
                            <button @click="emit('delete', g.id)" title="Archive" class="px-2.5 py-2 rounded-lg bg-white/5 text-slate-400 hover:text-rose-400">
                                <span class="material-symbols-outlined text-[18px]">archive</span>
                            </button>
                        </template>
                        <template v-else>
                            <button @click="emit('restore', g.id)" class="flex-1 text-xs font-semibold py-2 rounded-lg bg-[#57d5fc]/15 text-[#57d5fc] hover:bg-[#57d5fc]/25">Restore</button>
                            <button @click="emit('forceDelete', g.id)" title="Delete permanently" class="px-2.5 py-2 rounded-lg bg-white/5 text-slate-400 hover:text-rose-400">
                                <span class="material-symbols-outlined text-[18px]">delete_forever</span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 300; vertical-align: middle; }
</style>
