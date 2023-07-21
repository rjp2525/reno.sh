<script setup lang="ts">
import { computed, onMounted, onBeforeMount, ref, watchEffect, PropType } from 'vue';

const emit = defineEmits(['loading-complete', 'transition-complete']);
const props = defineProps({
  backgroundColor: {
    type: String as PropType<string>,
    default: '#242425'
  },
  loadingSpeed: {
    type: Number as PropType<number>,
    default: 15
  },
  overflow: {
    type: Boolean as PropType<boolean>,
    default: true
  },
  transitionSpeed: {
    type: Number as PropType<number>,
    default: 1400
  },
  transitionType: {
    type: String as PropType<string>,
    default: 'fade-left'
  }
});

const loadingBar = ref(null);
var percent = ref(0);

const preloaderBackgroundColor = computed(() => {
  return { backgroundColor: props.backgroundColor };
});

const preloaderTransitionSpeed = computed(() => {
  return { transition: `all ${props.transitionSpeed}ms ease-in-out` };
});

watchEffect(() => {
  if (percent.value < 100) {
    setTimeout(() => {
      percent.value += 1;
      if (loadingBar.value) {
        loadingBar.value.style.width = `${percent.value}%`;
      }
    }, props.loadingSpeed);
  } else {
    loadingComplete();
    transitionComplete();
  }
});

onBeforeMount(() => {
  if (props.overflow) setOverflowHidden();
});

onMounted(() => {
  percent.value = percent.value += 1;
});

function loadingComplete() {
  emit('loading-complete');
}

function setOverflowAuto() {
  document.body.style.overflow = 'auto';
}

function setOverflowHidden() {
  document.body.style.overflow = 'hidden';
}

function transitionComplete() {
  setTimeout(() => {
    emit('transition-complete')
    if (props.overflow) setOverflowAuto()
  }, props.transitionSpeed);
}
</script>

<template>
  <transition name="fade">
    <div class="preloader" v-if="percent < 100" :style="[
      preloaderBackgroundColor,
      preloaderTransitionSpeed
    ]">
      <slot v-bind="{ percent }">
        <div class="spinner">
          <div class="bounce-1" ></div>
          <div
            class="bounce-2"
            :style="{ height: percent + '%' }"
          ></div>
        </div>
      </slot>
    </div>
  </transition>
</template>

<style scoped>
  .fade-enter-active,
  .fade-leave-active {
    @apply transition-opacity duration-500;
  }

  .fade-enter,
  .fade-leave-to {
    @apply opacity-0;
  }
</style>
