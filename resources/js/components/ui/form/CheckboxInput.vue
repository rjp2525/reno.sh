<script setup lang="ts">
import { Check } from 'lucide-vue-next';

defineProps<{
    modelValue: boolean;
    label: string;
}>();

defineEmits<{
    'update:modelValue': [value: boolean];
}>();
</script>

<template>
    <label class="flex shrink-0 cursor-pointer items-center gap-2 whitespace-nowrap">
        <button
            type="button"
            role="checkbox"
            :aria-checked="modelValue"
            class="flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded border transition-colors"
            :class="
                modelValue
                    ? 'border-[#607B96] bg-[#1E2D3D]'
                    : 'border-[#1E2D3D] bg-[#011627] hover:border-[#607B96]'
            "
            @click="$emit('update:modelValue', !modelValue)"
        >
            <Check
                v-if="modelValue"
                class="h-3 w-3 text-white"
                :stroke-width="3"
            />
        </button>
        <span class="text-sm text-[#607B96]">{{ label }}</span>
    </label>
</template>
