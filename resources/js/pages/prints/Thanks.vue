<script setup lang="ts">
import { SeoHead } from '@/components';
import { GuestLayout } from '@/layouts';
import { gallery } from '@/routes';
import { Link, router } from '@inertiajs/vue3';
import { CheckCircle2, Clock, Package, Truck } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted } from 'vue';

interface OrderItem {
    title: string;
    product: string;
    quantity: number;
    thumbnail_url: string | null;
    photo_url: string | null;
}

const props = defineProps<{
    order: {
        number: string;
        status: string;
        status_label: string;
        total: string;
        ship_to: string;
        tracking: Array<{ carrier: string | null; number: string | null; url: string | null }>;
        items: OrderItem[];
    };
}>();

const steps = [
    { key: 'paid', label: 'Paid', icon: CheckCircle2 },
    { key: 'in_production', label: 'Printing', icon: Clock },
    { key: 'shipped', label: 'Shipped', icon: Truck },
    { key: 'completed', label: 'Delivered', icon: Package },
];
const progression = ['pending_payment', 'paid', 'submitted', 'in_production', 'shipped', 'completed'];
const reached = (step: string) => {
    const at = progression.indexOf(props.order.status);
    const target = progression.indexOf(step === 'in_production' ? 'submitted' : step);
    return at >= target && at > -1;
};

const pending = computed(() => props.order.status === 'pending_payment');

// Stripe's webhook usually lands a second or two after redirect: poll briefly until it does.
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    if (!pending.value) return;
    let tries = 0;
    timer = setInterval(() => {
        if (++tries > 10 || !pending.value) return clearInterval(timer);
        router.reload({ only: ['order'] });
    }, 3000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <GuestLayout>
        <SeoHead :title="`Order ${order.number}`" description="Your print order status." />

        <main class="mx-auto flex w-full max-w-2xl flex-col gap-6 px-4 py-10">
            <header>
                <p class="text-sm text-[#607B96]">// thank you for supporting my photography</p>
                <h1 class="mt-2 text-2xl text-white sm:text-3xl">
                    <span class="text-[#4D5BCE]">order</span>
                    <span class="text-[#43D9AD]">.{{ order.number }}</span>
                </h1>
                <p class="mt-2 text-sm text-[#607B96]">
                    <template v-if="pending">Confirming your payment…</template>
                    <template v-else>Status: <span class="text-[#FEA55F]">{{ order.status_label }}</span> · {{ order.total }}<template v-if="order.ship_to"> · shipping to {{ order.ship_to }}</template></template>
                </p>
            </header>

            <ol class="grid grid-cols-4 gap-2" aria-label="Order progress">
                <li
                    v-for="step in steps"
                    :key="step.key"
                    class="flex flex-col items-center gap-2 rounded-lg border p-3 text-center text-xs transition-colors duration-500"
                    :class="reached(step.key) ? 'border-[#43D9AD]/60 bg-[#43D9AD]/10 text-white' : 'border-[#1E2D3D] text-[#607B96]'"
                >
                    <component :is="step.icon" class="h-4 w-4" />
                    {{ step.label }}
                </li>
            </ol>

            <section v-if="order.tracking.length" class="rounded-lg border border-[#1E2D3D] bg-[#011627] p-4">
                <h2 class="mb-2 text-xs uppercase text-[#607B96]">Tracking</h2>
                <ul class="space-y-1 text-sm">
                    <li v-for="(t, i) in order.tracking" :key="i">
                        <a v-if="t.url" :href="t.url" target="_blank" rel="noopener" class="text-[#43D9AD] underline underline-offset-4">
                            {{ t.carrier }} {{ t.number }}
                        </a>
                        <span v-else class="text-white">{{ t.carrier }} {{ t.number }}</span>
                    </li>
                </ul>
            </section>

            <ul class="divide-y divide-[#1E2D3D] rounded-lg border border-[#1E2D3D] bg-[#011627]">
                <li v-for="(item, i) in order.items" :key="i" class="flex items-center gap-4 p-4">
                    <img v-if="item.thumbnail_url" :src="item.thumbnail_url" :alt="item.title" class="h-16 w-20 rounded object-cover" />
                    <div class="min-w-0 flex-1">
                        <component :is="item.photo_url ? Link : 'span'" :href="item.photo_url ?? undefined" class="block truncate text-white hover:text-[#43D9AD]">
                            {{ item.title }}
                        </component>
                        <p class="text-xs text-[#607B96]">{{ item.quantity }} × {{ item.product }}</p>
                    </div>
                </li>
            </ul>

            <p class="text-sm text-[#607B96]">
                A receipt is on its way to your inbox, and you'll get tracking by email once it ships.
                <Link :href="gallery.url()" class="text-[#43D9AD] hover:underline">Back to the gallery →</Link>
            </p>
        </main>
    </GuestLayout>
</template>
