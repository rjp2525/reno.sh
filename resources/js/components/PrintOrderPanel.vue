<script setup lang="ts">
import { checkout } from '@/routes/prints';
import { useForm } from '@inertiajs/vue3';
import { Frame, Minus, Plus, ShoppingBag, Truck } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    photoId: string;
    options: PrintOption[];
}>();

// Group sizes under each product line (fine art, canvas, framed…).
const lines = computed(() => {
    const map = new Map<string, { label: string; name: string; description: string | null; options: PrintOption[] }>();
    for (const option of props.options) {
        const line = map.get(option.type) ?? { label: option.type_label, name: option.name, description: option.description, options: [] };
        line.options.push(option);
        map.set(option.type, line);
    }
    return [...map.entries()].map(([type, line]) => ({ type, ...line }));
});

const activeType = ref(lines.value[0]?.type ?? '');
const activeLine = computed(() => lines.value.find((l) => l.type === activeType.value) ?? lines.value[0]);

const form = useForm({
    photo_id: props.photoId,
    product_id: activeLine.value?.options[0]?.product_id ?? '',
    quantity: 1,
});

watch(activeType, () => {
    form.product_id = activeLine.value?.options[0]?.product_id ?? '';
});

const selected = computed(() => props.options.find((o) => o.product_id === form.product_id));

const money = (cents: number, currency: string) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency, maximumFractionDigits: cents % 100 === 0 ? 0 : 2 }).format(cents / 100);

const total = computed(() => (selected.value ? money(selected.value.price * form.quantity, selected.value.currency) : ''));

const submit = () => form.post(checkout.url(), { preserveScroll: true });
</script>

<template>
    <section
        v-if="options.length"
        class="rounded-lg border border-[#1E2D3D] bg-[#011627]"
        aria-labelledby="print-heading"
    >
        <div class="flex items-center gap-2 border-b border-[#1E2D3D] px-3 py-2">
            <ShoppingBag class="h-3 w-3 text-[#43D9AD]" />
            <h2 id="print-heading" class="text-xs font-medium text-white">order_a_print()</h2>
        </div>

        <form class="space-y-4 p-3" @submit.prevent="submit">
            <div v-if="lines.length > 1" class="flex gap-1 rounded-md bg-[#011221] p-1" role="tablist">
                <button
                    v-for="line in lines"
                    :key="line.type"
                    type="button"
                    role="tab"
                    :aria-selected="activeType === line.type"
                    class="flex-1 rounded px-2 py-1.5 text-xs transition-all duration-200"
                    :class="activeType === line.type ? 'bg-[#1E2D3D] text-white shadow' : 'text-[#607B96] hover:text-white'"
                    @click="activeType = line.type"
                >
                    {{ line.label }}
                </button>
            </div>

            <Transition
                mode="out-in"
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 translate-y-1"
                leave-active-class="transition duration-150 ease-in"
                leave-to-class="opacity-0"
            >
                <div :key="activeType" class="space-y-3">
                    <p v-if="activeLine?.description" class="text-xs leading-relaxed text-[#607B96]">
                        // {{ activeLine.description }}
                    </p>

                    <fieldset>
                        <legend class="mb-2 text-[11px] uppercase text-[#607B96]">Size</legend>
                        <div class="grid grid-cols-2 gap-2">
                            <label
                                v-for="option in activeLine?.options"
                                :key="option.product_id"
                                class="group flex cursor-pointer flex-col rounded-md border px-3 py-2 transition-all duration-200 focus-within:ring-2 focus-within:ring-[#43D9AD]/50"
                                :class="form.product_id === option.product_id
                                    ? 'border-[#43D9AD] bg-[#43D9AD]/10'
                                    : 'border-[#1E2D3D] hover:border-[#607B96]'"
                            >
                                <input v-model="form.product_id" type="radio" class="sr-only" :value="option.product_id" name="product_id" />
                                <span class="flex items-center gap-1.5 text-sm text-white">
                                    <Frame class="h-3 w-3 text-[#607B96] group-hover:text-[#43D9AD]" />
                                    {{ option.size_label }}
                                </span>
                                <span class="text-xs text-[#FEA55F]">{{ money(option.price, option.currency) }}</span>
                            </label>
                        </div>
                    </fieldset>
                </div>
            </Transition>

            <div class="flex items-center justify-between">
                <span class="text-[11px] uppercase text-[#607B96]">Quantity</span>
                <div class="flex items-center rounded-md border border-[#1E2D3D]">
                    <button type="button" class="p-2 text-[#607B96] hover:text-white disabled:opacity-40" :disabled="form.quantity <= 1" aria-label="Fewer" @click="form.quantity--">
                        <Minus class="h-3 w-3" />
                    </button>
                    <span class="w-8 text-center text-sm tabular-nums text-white" aria-live="polite">{{ form.quantity }}</span>
                    <button type="button" class="p-2 text-[#607B96] hover:text-white disabled:opacity-40" :disabled="form.quantity >= 10" aria-label="More" @click="form.quantity++">
                        <Plus class="h-3 w-3" />
                    </button>
                </div>
            </div>

            <p v-if="form.errors.product_id || form.errors.photo_id" class="text-xs text-[#E99287]" role="alert">
                {{ form.errors.product_id || form.errors.photo_id }}
            </p>

            <button
                type="submit"
                :disabled="form.processing || !selected"
                class="flex w-full items-center justify-center gap-2 rounded-md bg-[#FEA55F] px-4 py-2.5 text-sm font-medium text-[#01080E] transition-all duration-200 hover:bg-[#FFAC6B] active:scale-[0.98] disabled:cursor-wait disabled:opacity-70"
            >
                <span v-if="form.processing">opening checkout…</span>
                <span v-else>Order print · {{ total }}</span>
            </button>

            <p class="flex items-center gap-1.5 text-[11px] text-[#607B96]">
                <Truck class="h-3 w-3" />
                Printed on demand by a fine-art lab and shipped to your door. Secure checkout with Stripe.
            </p>
        </form>
    </section>
</template>
