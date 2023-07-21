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
  <li class="inline-block md:block text-[#a9a9a9] hover:text-accent-light group">
    <nuxt-link :to="to" class="m-0 md:mx-0 md:my-3 relative block text-center" aria-label="{{ text }}">
      <span class="icon flex items-center justify-center m-0 h-[58px] w-[50px] md:h-[32px] leading-[50px] md:leading-[32px] text-[21px] rounded-[30px] transition-all duration-300">
        <component :is="icon" :width="size" />
      </span>
      <span class="link mx-0 mb-0 mt-[-11px] px-[8px] py-0 absolute left-[120%] top-1/2 h-[22px] leading-[22px] text-[12px] text-white font-light bg-[#181818] opacity-0 invisible pointer-events-none rounded-[4px] -translate-x-[4px] transition-all duration-300 md:group-hover:opacity-100 md:group-hover:visible">{{ text }}</span>
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
