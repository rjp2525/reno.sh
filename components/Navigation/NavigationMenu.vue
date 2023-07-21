<script setup lang="ts">
  import HomeIcon from '@/images/icons/home.svg?component';
</script>

<template>
  <div class="top-menu mt-[12px] w-full text-[0] z-[155]">
    <ul>
      <li class="active block">
        <a href="#home" class="mx-0 my-[8px] relative block text-white text-center">
          <span class="icon flex items-center justify-center m-0 h-[32px] leading-[32px] text-[21px] text-[#a9a9a9] hover:text-accent rounded-[30px] transition-all duration-300">
            <HomeIcon height="24" width="24" />
          </span>
					<span class="link mx-0 mb-0 mt-[-11px] px-[8px] py-0 absolute left-[120%] top-1/2 h-[22px] leading-[22px] text-[12px] text-white font-light bg-[#181818] opacity-0 invisible pointer-events-none rounded-[4px] translate-x-[-10px] transition-all duration-300">Home</span>
        </a>
      </li>
    </ul>
  </div>
</template>
