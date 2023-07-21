<script>
  export default {
    props: {}
  }
</script>

<template>
  <div class="top-menu mt-[12px] w-full text-[0]">
    <ul>
      <li class="active block">
        <a href="#home" class="mx-0 my-[8px] relative block text-white text-center">
          <span class="icon la la-home m-0 block h-[32px] leading-[32px] text-[21px] text-[#a9a9a9] rounded-[30px] transition-all duration-300"></span>
					<span class="link mx-0 mb-0 mt-[-11px] px-[8px] py-0 absolute left-[120%] top-1/2 h-[22px] leading-[22px] text-[12px] text-white font-light bg-[#181818] opacity-0 invisible pointer-events-none rounded-[4px] translate-x-[-10px] transition-all duration-300">Home</span>
        </a>
      </li>
    </ul>
  </div>
</template>
