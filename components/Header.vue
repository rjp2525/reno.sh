<script setup lang="ts">
import NavigationMenu from '@/components/Navigation/NavigationMenu.vue';
</script>

<template>
  <header class="header fixed top-0 left-0 w-[44px] z-[140] h-full text-center bg-[#181818]">
    <div class="logo relative p-0 bg-accent text-center">
      <a href="#" class="block h-[58px] leading-[58px] text-white no-underline w-[50px] before:bg-nav-gradient">
        <span class="relative font-cormorant text-[28px] font-bold uppercase z-[2]">R</span>
      </a>
    </div>
    <NavigationMenu />
  </header>
</template>
