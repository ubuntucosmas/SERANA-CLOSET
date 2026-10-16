<script setup>
import { computed, nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { nameForColour } from '@/Support/colourNames';

// What customers can pick on the Custom Order form (sections "The garment"
// and "Fabric & colour"). Garments are fixed for now to sets and the two single
// pieces: they can be renamed or switched off, not added.
const props = defineProps({
    options: { type: Object, required: true },
});

const clone = (v) => JSON.parse(JSON.stringify(v ?? []));
const form = useForm({
    garments: clone(props.options.garments),
    fabrics: clone(props.options.fabrics),
    colours: clone(props.options.colours),
});

const garmentHints = { set: 'Matches products of type “Set”', hoodie: 'Matches products of type “Hoodie”', jogger: 'Matches products of type “Jogger”' };
const enabledCount = computed(() => form.garments.filter(g => g.enabled).length);

function move(list, index, step) {
    const to = index + step;
    if (to < 0 || to >= list.length) return;
    const [item] = list.splice(index, 1);
    list.splice(to, 0, item);
}
const addFabric = () => form.fabrics.push('');
// Picking a colour names it ("Forest Green") until the owner types their own
// name; a name they typed is never overwritten. Clashes get a number.
const colourInputs = ref([]);
function uniqueName(base, self) {
    const taken = new Set(form.colours.filter(c => c !== self).map(c => c.name.trim().toLowerCase()));
    let name = base, n = 2;
    while (taken.has(name.toLowerCase())) name = `${base} ${n++}`;
    return name;
}
function onPick(c) {
    if (c._auto || !c.name.trim()) {
        c.name = uniqueName(nameForColour(c.hex), c);
        c._auto = true;
    }
}
function onRename(c) { c._auto = false; }
async function addColour() {
    const c = { name: '', hex: '#57d5fc', _auto: true };
    form.colours.push(c);
    onPick(form.colours.at(-1));
    await nextTick();
    colourInputs.value[form.colours.length - 1]?.click(); // open the picker straight away
}

// Errors arrive keyed like "fabrics.2" / "colours.1.name".
const err = (key) => form.errors[key];

function save() {
    form.transform((data) => ({
        ...data,
        // Blank rows are dropped rather than rejected.
        fabrics: data.fabrics.map(f => f.trim()).filter(Boolean),
        colours: data.colours.filter(c => c.name.trim()).map(c => ({ name: c.name.trim(), hex: c.hex })),
    })).put(route('admin.custom_order_options.update'), { preserveScroll: true });
}

const field = 'w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none';
const iconBtn = 'h-9 w-9 shrink-0 grid place-items-center rounded-lg bg-white/5 text-slate-400 hover:text-white disabled:opacity-30';
</script>

<template>
    <section class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-6 mt-6" aria-labelledby="custom-options-heading">
        <h2 id="custom-options-heading" class="text-white font-headline text-xl mb-1">Custom order options</h2>
        <p class="text-slate-500 text-sm mb-6">The choices customers see on the Custom Order form. Changes apply as soon as you save.</p>

        <!-- Garments -->
        <h3 class="text-sm font-semibold text-slate-200 mb-1">The garment</h3>
        <p class="text-xs text-slate-500 mb-3">Hoodie &amp; jogger sets and single pieces only for now. Rename them or switch one off.</p>
        <div class="space-y-2 mb-2">
            <div v-for="(g, i) in form.garments" :key="g.key" class="flex items-center gap-3 rounded-xl bg-slate-950/60 p-3">
                <label class="flex shrink-0 cursor-pointer items-center gap-2 text-xs text-slate-400">
                    <input v-model="g.enabled" type="checkbox" class="h-4 w-4 accent-[#57d5fc]" :aria-label="`Offer ${g.label}`" />
                    {{ g.enabled ? 'On' : 'Off' }}
                </label>
                <div class="min-w-0 flex-1">
                    <input v-model="g.label" :aria-label="`Name shown for ${garmentHints[g.key]}`" :class="field" maxlength="60" />
                    <span class="mt-1 block text-[11px] text-slate-600">{{ garmentHints[g.key] }}</span>
                    <span v-if="err(`garments.${i}.label`)" class="block text-xs text-rose-400">{{ err(`garments.${i}.label`) }}</span>
                </div>
            </div>
        </div>
        <p v-if="err('garments') || enabledCount === 0" class="text-xs text-rose-400 mb-2">{{ err('garments') || 'Keep at least one garment switched on.' }}</p>

        <!-- Fabrics -->
        <h3 class="text-sm font-semibold text-slate-200 mt-7 mb-3">Fabrics</h3>
        <div class="space-y-2">
            <div v-for="(f, i) in form.fabrics" :key="`f${i}`">
                <div class="flex items-center gap-2">
                    <input v-model="form.fabrics[i]" :aria-label="`Fabric ${i + 1}`" placeholder="e.g. Cotton Fleece" maxlength="60" :class="field" />
                    <button type="button" :class="iconBtn" :disabled="i === 0" @click="move(form.fabrics, i, -1)" :aria-label="`Move ${f || 'fabric'} up`"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">expand_less</span></button>
                    <button type="button" :class="iconBtn" :disabled="i === form.fabrics.length - 1" @click="move(form.fabrics, i, 1)" :aria-label="`Move ${f || 'fabric'} down`"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">expand_more</span></button>
                    <button type="button" :class="iconBtn" class="hover:!text-rose-400" @click="form.fabrics.splice(i, 1)" :aria-label="`Remove ${f || 'fabric'}`"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span></button>
                </div>
                <span v-if="err(`fabrics.${i}`)" class="block text-xs text-rose-400 mt-1">{{ err(`fabrics.${i}`) }}</span>
            </div>
            <p v-if="!form.fabrics.length" class="text-xs text-slate-500">No fabrics listed. Customers will be told you’ll suggest fabrics on WhatsApp.</p>
        </div>
        <button type="button" @click="addFabric" :disabled="form.fabrics.length >= 20" class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-white/5 px-3 py-2 text-sm text-slate-200 hover:bg-white/10 disabled:opacity-40">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span> Add fabric
        </button>

        <!-- Colours -->
        <h3 class="text-sm font-semibold text-slate-200 mt-7 mb-1">Colours</h3>
        <p class="text-xs text-slate-500 mb-3">Tap a swatch to pick a colour; its name fills in automatically. Type over the name to use your own.</p>
        <div class="space-y-2">
            <div v-for="(c, i) in form.colours" :key="`c${i}`">
                <div class="flex items-center gap-2">
                    <input :ref="el => (colourInputs[i] = el)" v-model="c.hex" @input="onPick(c)" type="color" :aria-label="`Pick colour for ${c.name || 'this swatch'}`" class="h-10 w-12 shrink-0 cursor-pointer rounded-lg border border-white/10 bg-slate-950 p-1" />
                    <div class="relative min-w-0 flex-1">
                        <input v-model="c.name" @input="onRename(c)" :aria-label="`Colour name ${i + 1}`" placeholder="Pick a colour to name it" maxlength="40" :class="field" class="pr-14" />
                        <span v-if="c._auto" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-[#57d5fc]/15 px-2 py-0.5 text-[10px] font-semibold text-[#57d5fc]">Auto</span>
                    </div>
                    <button type="button" :class="iconBtn" :disabled="i === 0" @click="move(form.colours, i, -1)" :aria-label="`Move ${c.name || 'colour'} up`"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">expand_less</span></button>
                    <button type="button" :class="iconBtn" :disabled="i === form.colours.length - 1" @click="move(form.colours, i, 1)" :aria-label="`Move ${c.name || 'colour'} down`"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">expand_more</span></button>
                    <button type="button" :class="iconBtn" class="hover:!text-rose-400" @click="form.colours.splice(i, 1)" :aria-label="`Remove ${c.name || 'colour'}`"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span></button>
                </div>
                <span v-if="err(`colours.${i}.name`) || err(`colours.${i}.hex`)" class="block text-xs text-rose-400 mt-1">{{ err(`colours.${i}.name`) || err(`colours.${i}.hex`) }}</span>
            </div>
            <p v-if="!form.colours.length" class="text-xs text-slate-500">No colours listed. Customers will describe the colour in their notes.</p>
        </div>
        <button type="button" @click="addColour" :disabled="form.colours.length >= 20" class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-white/5 px-3 py-2 text-sm text-slate-200 hover:bg-white/10 disabled:opacity-40">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span> Add colour
        </button>

        <div class="mt-7 flex items-center justify-end gap-4">
            <span v-if="form.recentlySuccessful" role="status" class="text-sm text-emerald-300">Saved</span>
            <button @click="save" :disabled="form.processing || enabledCount === 0"
                class="px-7 py-3 rounded-xl bg-[#57d5fc] text-slate-900 font-semibold text-sm hover:brightness-105 disabled:opacity-40 transition-all">
                {{ form.processing ? 'Saving…' : 'Save options' }}
            </button>
        </div>
    </section>
</template>
