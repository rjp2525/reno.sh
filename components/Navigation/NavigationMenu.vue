<script setup lang="ts">
  import { ref } from 'vue';
  import HomeIcon from '@/images/icons/home.svg?component';
  import AboutIcon from '@/images/icons/about.svg?component';
  import ResumeIcon from '@/images/icons/resume.svg?component';
  import SoftwareIcon from '@/images/icons/software.svg?component';
  import PhotographyIcon from '@/images/icons/photography.svg?component';
  import ContactIcon from '@/images/icons/contact.svg?component';
  import GitHubIcon from '@/images/icons/github.svg?component';
  import BlogIcon from '@/images/icons/blog.svg?component';
  import NavigationLink from '@/components/Navigation/NavigationLink';

  const menuOpen = ref(false);
</script>

<template>
  <div class="top-menu w-full text-[0] z-[155]" :class="{ 'visible opacity-100': menuOpen }">
    <ul>
      <NavigationLink to="/" text="Home" :icon="HomeIcon" @click="menuOpen = !menuOpen" />
      <NavigationLink to="/about" text="About" :icon="AboutIcon" @click="menuOpen = !menuOpen" />
      <NavigationLink to="/resume" text="Resume" :icon="ResumeIcon" @click="menuOpen = !menuOpen" />
      <NavigationLink to="/software" text="Software" :icon="SoftwareIcon" @click="menuOpen = !menuOpen" />
      <NavigationLink to="/photography" text="Photography" :icon="PhotographyIcon" @click="menuOpen = !menuOpen" />
      <NavigationLink to="/blog" text="Blog" :icon="BlogIcon" @click="menuOpen = !menuOpen" />
      <NavigationLink to="/contact" text="Contact" :icon="ContactIcon" @click="menuOpen = !menuOpen" />
    </ul>
  </div>
  <div class="absolute left-auto md:left-0 right-[70px] md:right-auto w-auto md:w-full bottom-auto md:bottom-2 top-0 md:top-auto">
    <a href="https://github.com/rjp2525" class="m-0 md:my-2 md:mx-0 flex items-center justify-center text-base text-[#a9a9a9] hover:text-accent-light transition-all duration-300" target="_blank">
      <span class="m-0 h-[58px] md:h-6 w-[30px] leading-[56px] md:leading-6 flex items-center justify-center">
        <GitHubIcon width="24" />
      </span>
    </a>
  </div>
  <div class="mobile-trigger absolute top-0 right-0 h-[58px] w-[58px] cursor-pointer bg-[#202020] md:hidden" @click="menuOpen = !menuOpen">
    <span class="line" :class="{ 'before:top-0 before:bottom-auto before:rotate-45 after:top-auto after:bottom-0 after:-rotate-45': menuOpen }"></span>
  </div>
</template>
