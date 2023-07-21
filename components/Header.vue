<script setup lang="ts">
import NavigationMenu from '@/components/Navigation/NavigationMenu.vue';
</script>

<template>
  <header class="header fixed top-0 left-0 w-full md:w-auto h-[58px] md:h-full z-[140] text-center bg-[#181818]">
    <div class="logo float-left md:float-none w-[50px] md:w-full relative p-0 bg-accent text-center">
      <nuxt-link to="/" class="block h-[58px] leading-[58px] text-white no-underline w-[50px] before:bg-nav-gradient">
        <span class="relative font-cormorant text-[28px] font-bold uppercase z-[2]">R</span>
      </nuxt-link>
    </div>
    <NavigationMenu />
  </header>
</template>
