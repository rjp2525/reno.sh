<script setup lang="ts">
  useHead({
    title: 'Reno Philibert :: Software Engineer',
    meta: []
  });
</script>

<template>
    <div class="card active card-home">
      <div class="slide absolute overflow-hidden left-0 top-0 w-full h-full bg-[#262628] bg-no-repeat bg-cover bg-home-bg bg-center">
        <div class="w-full h-full flex justify-center items-center backdrop-blur-sm bg-[#181818]/80"></div>
      </div>
      <div class="flex items-center justify-center h-full flex-col px-6 md:px-0">
        <h1 class="m-0 md:mb-11 text-5xl leading-tight md:leading-normal md:text-[72px] text-white font-semibold md:tracking-[-0.035em] z-[114]">
          <span class="md:pr-[15px] opacity-60">Reno</span> Philibert
        </h1>
        <div class="my-2 md:m-0 text-[20px] md:leading-[20px] text-white/80 font-extralight z-[114]">
          A marvelous <strong class="text-white font-semibold">software engineer</strong> and <strong class="text-white font-semibold">photographer</strong>
        </div>
        <div class="mt-2 md:my-4 z-[114] text-md text-white/70 font-extralight flex items-center">
          based in Denver, Colorado
        </div>
        <div class="z-[114]">
          <nuxt-link to="/about" class="relative inline-block py-3 px-5 text-accent-light text-base uppercase overflow-hidden duration-500 mt-6 tracking-wide hover:bg-accent-light hover:text-white hover:rounded-md hover:shadow-special-button border-2 border-solid border-accent-light/30">
            About me
          </nuxt-link>
        </div>
      </div>
    </div>
</template>
