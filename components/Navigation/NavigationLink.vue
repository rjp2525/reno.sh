<script setup lang="ts">
defineProps({
  to: {
    type: String,
    required: true
  },
  text: {
    type: String,
    required: true
  },
  icon: {
    type: Object,
    required: true
  },
  size: {
    type: Number,
    default: 21
  }
});
</script>

<template>
  <li class="block text-[#a9a9a9] hover:text-accent-light group">
    <nuxt-link :to="to" class="mx-0 my-3 relative block text-center" aria-label="{{ text }}">
      <span class="icon flex items-center justify-center m-0 h-[32px] leading-[32px] text-[21px] rounded-[30px] transition-all duration-300">
        <component :is="icon" :width="size" />
      </span>
      <span class="link mx-0 mb-0 mt-[-11px] px-[8px] py-0 absolute left-[120%] top-1/2 h-[22px] leading-[22px] text-[12px] text-white font-light bg-[#181818] opacity-0 invisible pointer-events-none rounded-[4px] -translate-x-[4px] transition-all duration-300 group-hover:opacity-100 group-hover:visible">{{ text }}</span>
    </nuxt-link>
  </li>
</template>

<style scoped>
  .router-link-active {
    @apply text-accent-light;
  }
  .router-link-active:hover {
    @apply text-accent;
  }
</style>
