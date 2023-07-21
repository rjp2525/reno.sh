<script setup lang="ts">
  import HomeIcon from '@/images/icons/home.svg?component';
  import AboutIcon from '@/images/icons/about.svg?component';
  import ResumeIcon from '@/images/icons/resume.svg?component';
  import SoftwareIcon from '@/images/icons/software.svg?component';
  import PhotographyIcon from '@/images/icons/photography.svg?component';
  import ContactIcon from '@/images/icons/contact.svg?component';
  import GitHubIcon from '@/images/icons/github.svg?component';
</script>

<template>
  <div class="top-menu w-full text-[0] z-[155]">
    <ul>
      <li class="active block">
        <a href="#home" class="mx-0 my-3 relative block text-white text-center">
          <span class="icon flex items-center justify-center m-0 h-[32px] leading-[32px] text-[21px] text-[#a9a9a9] hover:text-accent-light rounded-[30px] transition-all duration-300">
            <HomeIcon width="30" />
          </span>
					<span class="link mx-0 mb-0 mt-[-11px] px-[8px] py-0 absolute left-[120%] top-1/2 h-[22px] leading-[22px] text-[12px] text-white font-light bg-[#181818] opacity-0 invisible pointer-events-none rounded-[4px] translate-x-[-10px] transition-all duration-300">Home</span>
        </a>
      </li>
      <li class="block">
        <a href="#about" class="mx-0 my-3 relative block text-white text-center">
          <span class="icon flex items-center justify-center m-0 h-[32px] leading-[32px] text-[21px] text-[#a9a9a9] hover:text-accent-light rounded-[30px] transition-all duration-300">
            <AboutIcon width="21" />
          </span>
					<span class="link mx-0 mb-0 mt-[-11px] px-[8px] py-0 absolute left-[120%] top-1/2 h-[22px] leading-[22px] text-[12px] text-white font-light bg-[#181818] opacity-0 invisible pointer-events-none rounded-[4px] translate-x-[-10px] transition-all duration-300">Home</span>
        </a>
      </li>
      <li class="block">
        <a href="#resume" class="mx-0 my-3 relative block text-white text-center">
          <span class="icon flex items-center justify-center m-0 h-[32px] leading-[32px] text-[21px] text-[#a9a9a9] hover:text-accent-light rounded-[30px] transition-all duration-300">
            <ResumeIcon width="21" />
          </span>
					<span class="link mx-0 mb-0 mt-[-11px] px-[8px] py-0 absolute left-[120%] top-1/2 h-[22px] leading-[22px] text-[12px] text-white font-light bg-[#181818] opacity-0 invisible pointer-events-none rounded-[4px] translate-x-[-10px] transition-all duration-300">Home</span>
        </a>
      </li>
      <li class="block">
        <a href="#software" class="mx-0 my-3 relative block text-white text-center">
          <span class="icon flex items-center justify-center m-0 h-[32px] leading-[32px] text-[21px] text-[#a9a9a9] hover:text-accent-light rounded-[30px] transition-all duration-300">
            <SoftwareIcon width="21" />
          </span>
					<span class="link mx-0 mb-0 mt-[-11px] px-[8px] py-0 absolute left-[120%] top-1/2 h-[22px] leading-[22px] text-[12px] text-white font-light bg-[#181818] opacity-0 invisible pointer-events-none rounded-[4px] translate-x-[-10px] transition-all duration-300">Home</span>
        </a>
      </li>
      <li class="block">
        <a href="#photography" class="mx-0 my-3 relative block text-white text-center">
          <span class="icon flex items-center justify-center m-0 h-[32px] leading-[32px] text-[21px] text-[#a9a9a9] hover:text-accent-light rounded-[30px] transition-all duration-300">
            <PhotographyIcon width="21" />
          </span>
					<span class="link mx-0 mb-0 mt-[-11px] px-[8px] py-0 absolute left-[120%] top-1/2 h-[22px] leading-[22px] text-[12px] text-white font-light bg-[#181818] opacity-0 invisible pointer-events-none rounded-[4px] translate-x-[-10px] transition-all duration-300">Home</span>
        </a>
      </li>
      <li class="block">
        <a href="#contact" class="mx-0 my-3 relative block text-white text-center">
          <span class="icon flex items-center justify-center m-0 h-[32px] leading-[32px] text-[21px] text-[#a9a9a9] hover:text-accent-light rounded-[30px] transition-all duration-300">
            <ContactIcon width="21" />
          </span>
					<span class="link mx-0 mb-0 mt-[-11px] px-[8px] py-0 absolute left-[120%] top-1/2 h-[22px] leading-[22px] text-[12px] text-white font-light bg-[#181818] opacity-0 invisible pointer-events-none rounded-[4px] translate-x-[-10px] transition-all duration-300">Home</span>
        </a>
      </li>
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
