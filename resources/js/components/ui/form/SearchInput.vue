<script setup lang="ts">
import { Search } from 'lucide-vue-next';

defineProps<{
    modelValue: string;
    placeholder?: string;
}>();

defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <div class="relative">
        <Search
            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-[#607B96]"
        />
        <input
            :value="modelValue"
            type="text"
            :placeholder="placeholder"
            class="w-full rounded-lg border border-[#1E2D3D] bg-[#011627] py-2 pr-4 pl-10 text-sm text-white placeholder-[#607B96] transition-colors focus:border-[#607B96] focus:outline-none"
            @input="
                $emit(
                    'update:modelValue',
                    ($event.target as HTMLInputElement).value,
                )
            "
        />
    </div>
</template>
