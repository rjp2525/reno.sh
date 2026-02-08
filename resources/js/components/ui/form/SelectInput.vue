<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';

defineProps<{
    modelValue: string;
}>();

defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <div class="relative shrink-0">
        <select
            :value="modelValue"
            class="appearance-none rounded-lg border border-[#1E2D3D] bg-[#011627] py-2 pr-10 pl-4 text-sm text-white transition-colors focus:border-[#607B96] focus:outline-none [&>option]:bg-[#011627] [&>option]:text-white"
            @change="
                $emit(
                    'update:modelValue',
                    ($event.target as HTMLSelectElement).value,
                )
            "
        >
            <slot />
        </select>
        <ChevronDown
            class="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-[#607B96]"
        />
    </div>
</template>
