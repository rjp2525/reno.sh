<script setup lang="ts">
  import HomeIcon from '@/images/icons/home.svg?component';
  import AboutIcon from '@/images/icons/about.svg?component';
  import ResumeIcon from '@/images/icons/resume.svg?component';
  import SoftwareIcon from '@/images/icons/software.svg?component';
  import PhotographyIcon from '@/images/icons/photography.svg?component';
  import ContactIcon from '@/images/icons/contact.svg?component';
  import GitHubIcon from '@/images/icons/github.svg?component';
  import BlogIcon from '@/images/icons/blog.svg?component';
  import NavigationLink from '@/components/Navigation/NavigationLink';
</script>

<template>
  <div class="top-menu w-full text-[0] z-[155]">
    <ul>
      <NavigationLink to="/" text="Home" :icon="HomeIcon" :size="30" />
      <NavigationLink to="/about" text="About" :icon="AboutIcon" />
      <NavigationLink to="/resume" text="Resume" :icon="ResumeIcon" />
      <NavigationLink to="/software" text="Software" :icon="SoftwareIcon" />
      <NavigationLink to="/photography" text="Photography" :icon="PhotographyIcon" />
      <NavigationLink to="/blog" text="Blog" :icon="BlogIcon" />
      <NavigationLink to="/contact" text="Contact" :icon="ContactIcon" />
    </ul>
  </div>
  <div class="absolute left-0 w-full bottom-3">
    <a href="https://github.com/rjp2525" class="my-2 mx-0 flex items-center justify-center text-base text-[#a9a9a9] hover:text-accent-light transition-all duration-300" target="_blank">
      <span class="m-0 block h-6 leading-6">
        <GitHubIcon width="24" />
      </span>
    </a>
  </div>
</template>
