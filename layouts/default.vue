<template>
  <main>
    <slot></slot>
  </main>
</template>

<style>
.page-enter-active,
.page-leave-active {
  @apply transition-all duration-500 ease-in-out;
}

.page-enter-from {
  @apply opacity-0 scale-0;
}

.page-enter-to {
  @apply opacity-100 scale-100;
}

.page-leave-from {
  @apply scale-100 opacity-100;
}

.page-leave-to {
  @apply scale-0 opacity-0;
}
</style>
