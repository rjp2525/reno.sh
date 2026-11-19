<script setup lang="ts">
/**
 * Lightbox for content images, styled as an editor window: a file tab title bar,
 * a dot-grid canvas you can zoom and pan, and a status bar. Opens with a FLIP
 * animation from the thumbnail that was clicked.
 */
import { ChevronLeft, ChevronRight, ExternalLink, Maximize, Minus, Plus, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

export interface ViewerImage {
    src: string;
    alt?: string | null;
    caption?: string | null;
}

const props = defineProps<{
    images: ViewerImage[];
    /** Index of the open image, or null when closed. */
    index: number | null;
    /** The element that was clicked, to animate from and return focus to. */
    origin?: HTMLElement | null;
}>();

const emit = defineEmits<{
    close: [];
    'update:index': [index: number];
}>();

const MIN = 1;
const MAX = 5;

const open = computed(() => props.index !== null && props.images[props.index] !== undefined);
const current = computed(() => (props.index !== null ? props.images[props.index] : null));
const hasMany = computed(() => props.images.length > 1);

const dialog = ref<HTMLElement | null>(null);
const stage = ref<HTMLElement | null>(null);
const img = ref<HTMLImageElement | null>(null);
const closeButton = ref<HTMLButtonElement | null>(null);

const scale = ref(1);
const tx = ref(0);
const ty = ref(0);
const natural = ref<{ w: number; h: number } | null>(null);
const loaded = ref(false);
/** FLIP phases: 'start' pins the image over the thumbnail, 'run' animates it home. */
const flip = ref<{ phase: 'start' | 'run'; from: string } | null>(null);
/** Set after a drag/swipe so the trailing click doesn't close the viewer. */
let suppressClick = false;

const fileName = computed(() => {
    const src = current.value?.src ?? '';
    const name = decodeURIComponent(src.split('?')[0].split('/').pop() || 'image');
    return name.length > 42 ? `${name.slice(0, 20)}…${name.slice(-18)}` : name;
});
const zoomLabel = computed(() => `${Math.round(scale.value * 100)}%`);
const reducedMotion = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const transform = computed(() =>
    flip.value?.phase === 'start' ? flip.value.from : `translate3d(${tx.value}px, ${ty.value}px, 0) scale(${scale.value})`,
);

// ---- Zoom & pan -------------------------------------------------------------

function bounds() {
    const s = stage.value?.getBoundingClientRect();
    const i = img.value;
    if (!s || !i) return { x: 0, y: 0 };
    return {
        x: Math.max(0, (i.offsetWidth * scale.value - s.width) / 2),
        y: Math.max(0, (i.offsetHeight * scale.value - s.height) / 2),
    };
}

function clampPan() {
    const b = bounds();
    tx.value = Math.min(b.x, Math.max(-b.x, tx.value));
    ty.value = Math.min(b.y, Math.max(-b.y, ty.value));
}

/** Zoom to `next`, keeping the point at client (cx, cy) fixed under the cursor. */
function zoomTo(next: number, cx?: number, cy?: number) {
    next = Math.min(MAX, Math.max(MIN, next));
    const s = stage.value?.getBoundingClientRect();
    if (s && cx !== undefined && cy !== undefined) {
        const px = cx - (s.left + s.width / 2);
        const py = cy - (s.top + s.height / 2);
        tx.value = px - ((px - tx.value) * next) / scale.value;
        ty.value = py - ((py - ty.value) * next) / scale.value;
    }
    scale.value = next;
    if (next === MIN) {
        tx.value = 0;
        ty.value = 0;
    }
    clampPan();
}

function reset() {
    scale.value = 1;
    tx.value = 0;
    ty.value = 0;
}

/** Show the image at its real pixel size (or fit, if it's already there). */
function actualSize() {
    const i = img.value;
    if (!i || !natural.value) return;
    const oneToOne = natural.value.w / i.offsetWidth;
    if (Math.abs(scale.value - oneToOne) < 0.01 || oneToOne <= 1) reset();
    else zoomTo(oneToOne);
}

function onWheel(e: WheelEvent) {
    e.preventDefault();
    zoomTo(scale.value * Math.exp(-e.deltaY * (e.ctrlKey ? 0.01 : 0.0025)), e.clientX, e.clientY);
}

const pointers = new Map<number, { x: number; y: number }>();
let gesture: { startX: number; startY: number; tx: number; ty: number; dist: number; scale: number; moved: boolean } | null = null;

function onPointerDown(e: PointerEvent) {
    if (e.button !== 0) return;
    (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    const pts = [...pointers.values()];
    gesture = {
        startX: e.clientX,
        startY: e.clientY,
        tx: tx.value,
        ty: ty.value,
        dist: pts.length === 2 ? Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y) : 0,
        scale: scale.value,
        moved: gesture?.moved ?? false,
    };
}

function onPointerMove(e: PointerEvent) {
    if (!gesture || !pointers.has(e.pointerId)) return;
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    const pts = [...pointers.values()];

    if (pts.length === 2 && gesture.dist > 0) {
        const dist = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
        gesture.moved = true;
        zoomTo((gesture.scale * dist) / gesture.dist, (pts[0].x + pts[1].x) / 2, (pts[0].y + pts[1].y) / 2);
        return;
    }

    const dx = e.clientX - gesture.startX;
    const dy = e.clientY - gesture.startY;
    if (Math.hypot(dx, dy) > 4) gesture.moved = true;

    if (scale.value > 1) {
        tx.value = gesture.tx + dx;
        ty.value = gesture.ty + dy;
        clampPan();
    }
}

function onPointerUp(e: PointerEvent) {
    if (!gesture) return;
    const wasPinch = pointers.size > 1;
    pointers.delete(e.pointerId);
    if (pointers.size > 0) return;

    const dx = e.clientX - gesture.startX;
    const moved = gesture.moved;
    gesture = null;
    suppressClick = moved;
    if (wasPinch) return;

    // Swipe between gallery images when not zoomed in.
    if (moved && scale.value === 1 && hasMany.value && Math.abs(dx) > 60) {
        go(dx < 0 ? 1 : -1);
        return;
    }

    // A click toggles zoom at the clicked point.
    if (!moved && e.target === img.value) {
        if (scale.value > 1) reset();
        else zoomTo(2.5, e.clientX, e.clientY);
    }
}

// ---- Navigation & lifecycle -------------------------------------------------

function go(step: number) {
    if (props.index === null || !hasMany.value) return;
    const n = props.images.length;
    emit('update:index', (props.index + step + n) % n);
}

function close() {
    emit('close');
}

/** Clicking the empty canvas closes the viewer, unless that click ended a drag. */
function onBackgroundClick() {
    if (suppressClick) {
        suppressClick = false;
        return;
    }
    if (scale.value === 1) close();
}

function onKeydown(e: KeyboardEvent) {
    if (!open.value) return;
    switch (e.key) {
        case 'Escape':
            e.preventDefault();
            close();
            break;
        case 'ArrowLeft':
            if (scale.value === 1) go(-1);
            break;
        case 'ArrowRight':
            if (scale.value === 1) go(1);
            break;
        case '+':
        case '=':
            zoomTo(scale.value * 1.5);
            break;
        case '-':
            zoomTo(scale.value / 1.5);
            break;
        case '0':
            reset();
            break;
        case 'Tab': {
            // Keep focus inside the dialog.
            const focusable = dialog.value?.querySelectorAll<HTMLElement>('button, a[href]');
            if (!focusable?.length) break;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    }
}

/** Animate the image from the clicked thumbnail into place (FLIP). */
async function flipFromOrigin() {
    const el = img.value;
    const from = props.origin?.querySelector('img')?.getBoundingClientRect() ?? props.origin?.getBoundingClientRect();
    if (!el || !from || reducedMotion()) return;
    const to = el.getBoundingClientRect();
    if (!to.width || !to.height) return;

    const dx = from.left + from.width / 2 - (to.left + to.width / 2);
    const dy = from.top + from.height / 2 - (to.top + to.height / 2);
    flip.value = { phase: 'start', from: `translate3d(${dx}px, ${dy}px, 0) scale(${from.width / to.width})` };
    await nextTick();
    el.getBoundingClientRect(); // commit the start frame before animating
    requestAnimationFrame(() => {
        flip.value = { phase: 'run', from: '' };
        setTimeout(() => (flip.value = null), 450);
    });
}

let flipPending = false;

function onLoad() {
    const el = img.value;
    if (el) natural.value = { w: el.naturalWidth, h: el.naturalHeight };
    loaded.value = true;
    if (flipPending) {
        flipPending = false;
        nextTick(flipFromOrigin);
    }
}

watch(
    () => props.index,
    (index, previous) => {
        reset();
        if (index === null) return;
        if (previous === null || previous === undefined) {
            flipPending = true;
        }
        loaded.value = false;
        natural.value = null;
        nextTick(() => {
            // Cached images may already be complete before @load is attached.
            if (img.value?.complete && img.value.naturalWidth) onLoad();
        });
    },
);

watch(open, (isOpen) => {
    if (typeof document === 'undefined') return;
    if (isOpen) {
        document.documentElement.style.overflow = 'hidden';
        window.addEventListener('keydown', onKeydown);
        nextTick(() => closeButton.value?.focus({ preventScroll: true }));
    } else {
        document.documentElement.style.overflow = '';
        window.removeEventListener('keydown', onKeydown);
        props.origin?.focus({ preventScroll: true });
    }
});

onBeforeUnmount(() => {
    if (typeof document === 'undefined') return;
    document.documentElement.style.overflow = '';
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="viewer">
            <div
                v-if="open && current"
                class="viewer-backdrop fixed inset-0 z-[100] flex items-center justify-center p-2 sm:p-6"
                @click.self="close"
            >
                <div
                    ref="dialog"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="current.alt || current.caption || 'Image viewer'"
                    class="viewer-window flex h-full max-h-[min(100%,1100px)] w-full max-w-[1600px] flex-col overflow-hidden rounded-lg border border-[#1E2D3D] bg-[#011627] font-mono text-xs text-[#607B96] shadow-[0_30px_120px_-20px_rgba(0,0,0,0.9),0_0_0_1px_rgba(67,217,173,0.04)]"
                >
                    <!-- Title bar: a file tab plus controls -->
                    <div class="flex h-10 shrink-0 items-stretch border-b border-[#1E2D3D] bg-[#011221]">
                        <div class="flex items-center gap-1.5 px-3">
                            <button
                                type="button"
                                class="h-3 w-3 rounded-full bg-[#E99287] transition hover:brightness-125"
                                aria-label="Close"
                                @click="close"
                            />
                            <span class="h-3 w-3 rounded-full bg-[#FEA55F]/70" aria-hidden="true" />
                            <span class="h-3 w-3 rounded-full bg-[#43D9AD]/70" aria-hidden="true" />
                        </div>
                        <div class="relative flex min-w-0 items-center gap-2 border-x border-[#1E2D3D] bg-[#011627] px-4 text-white">
                            <span class="text-[#43D9AD]" aria-hidden="true">▣</span>
                            <span class="truncate">{{ fileName }}</span>
                            <span class="absolute inset-x-0 bottom-0 h-0.5 bg-[#FEA55F]" aria-hidden="true" />
                        </div>
                        <div class="ml-auto flex items-center gap-0.5 px-2">
                            <button type="button" class="viewer-btn" aria-label="Zoom out" :disabled="scale <= MIN" @click="zoomTo(scale / 1.5)">
                                <Minus class="h-3.5 w-3.5" />
                            </button>
                            <button type="button" class="viewer-btn w-14 tabular-nums" aria-label="Actual size" title="Actual size (1:1)" @click="actualSize">
                                {{ zoomLabel }}
                            </button>
                            <button type="button" class="viewer-btn" aria-label="Zoom in" :disabled="scale >= MAX" @click="zoomTo(scale * 1.5)">
                                <Plus class="h-3.5 w-3.5" />
                            </button>
                            <button type="button" class="viewer-btn" aria-label="Fit to window" title="Fit (0)" @click="reset">
                                <Maximize class="h-3.5 w-3.5" />
                            </button>
                            <a :href="current.src" target="_blank" rel="noopener" class="viewer-btn" aria-label="Open original in a new tab" title="Open original">
                                <ExternalLink class="h-3.5 w-3.5" />
                            </a>
                            <span class="mx-1 h-4 w-px bg-[#1E2D3D]" aria-hidden="true" />
                            <button ref="closeButton" type="button" class="viewer-btn hover:!text-[#E99287]" aria-label="Close viewer" title="Close (Esc)" @click="close">
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Canvas -->
                    <div
                        ref="stage"
                        class="viewer-stage relative min-h-0 flex-1 touch-none overflow-hidden select-none"
                        :class="scale > 1 ? 'cursor-grab active:cursor-grabbing' : 'cursor-zoom-in'"
                        @wheel="onWheel"
                        @pointerdown="onPointerDown"
                        @pointermove="onPointerMove"
                        @pointerup="onPointerUp"
                        @pointercancel="onPointerUp"
                        @dblclick="reset"
                        @click.self="onBackgroundClick"
                    >
                        <div class="absolute inset-0 flex items-center justify-center p-4 sm:p-10" @click.self="onBackgroundClick">
                            <img
                                ref="img"
                                :key="current.src"
                                :src="current.src"
                                :alt="current.alt || current.caption || ''"
                                draggable="false"
                                class="viewer-img max-h-full max-w-full rounded-sm object-contain shadow-[0_20px_60px_-15px_rgba(0,0,0,0.8)]"
                                :class="{
                                    'opacity-0': !loaded,
                                    'viewer-img--pinned': flip?.phase === 'start',
                                    'viewer-img--animating': flip?.phase === 'run',
                                }"
                                :style="{ transform }"
                                @load="onLoad"
                            />
                        </div>

                        <div v-if="!loaded" class="pointer-events-none absolute inset-0 flex items-center justify-center text-[#43D9AD]">
                            <span class="viewer-loading">loading<span>.</span><span>.</span><span>.</span></span>
                        </div>

                        <template v-if="hasMany">
                            <button type="button" class="viewer-nav left-3" aria-label="Previous image" @click.stop="go(-1)">
                                <ChevronLeft class="h-5 w-5" />
                            </button>
                            <button type="button" class="viewer-nav right-3" aria-label="Next image" @click.stop="go(1)">
                                <ChevronRight class="h-5 w-5" />
                            </button>
                        </template>
                    </div>

                    <!-- Filmstrip for galleries -->
                    <div v-if="hasMany" class="flex shrink-0 justify-center gap-2 overflow-x-auto border-t border-[#1E2D3D] bg-[#011221] px-3 py-2">
                        <button
                            v-for="(image, i) in images"
                            :key="image.src"
                            type="button"
                            class="h-12 w-16 shrink-0 overflow-hidden rounded border transition"
                            :class="i === index ? 'border-[#43D9AD] opacity-100' : 'border-[#1E2D3D] opacity-50 hover:opacity-90'"
                            :aria-label="`Show image ${i + 1}`"
                            :aria-current="i === index ? 'true' : undefined"
                            @click="emit('update:index', i)"
                        >
                            <img :src="image.src" alt="" class="h-full w-full object-cover" loading="lazy" />
                        </button>
                    </div>

                    <!-- Status bar -->
                    <div class="flex h-8 shrink-0 items-center gap-4 border-t border-[#1E2D3D] bg-[#011221] px-3">
                        <p class="min-w-0 flex-1 truncate">
                            <span v-if="current.caption" class="text-[#607B96]">// {{ current.caption }}</span>
                            <span v-else class="text-[#607B96]/50">// no caption</span>
                        </p>
                        <span v-if="natural" class="hidden shrink-0 tabular-nums sm:inline">{{ natural.w }}×{{ natural.h }}</span>
                        <span class="hidden shrink-0 tabular-nums sm:inline">{{ zoomLabel }}</span>
                        <span v-if="hasMany" class="shrink-0 tabular-nums text-[#43D9AD]">{{ (index ?? 0) + 1 }}/{{ images.length }}</span>
                        <span class="hidden shrink-0 text-[#607B96]/60 lg:inline">
                            <kbd>esc</kbd> close<template v-if="hasMany"> · <kbd>←</kbd><kbd>→</kbd> nav</template> · <kbd>scroll</kbd> zoom · <kbd>drag</kbd> pan
                        </span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.viewer-backdrop {
    background:
        radial-gradient(ellipse at 30% 20%, rgba(67, 217, 173, 0.08), transparent 55%),
        radial-gradient(ellipse at 75% 85%, rgba(77, 91, 206, 0.1), transparent 55%),
        rgba(1, 12, 21, 0.82);
    backdrop-filter: blur(10px) saturate(120%);
}

.viewer-stage {
    background-color: #010c15;
    background-image: radial-gradient(rgba(96, 123, 150, 0.18) 1px, transparent 1px);
    background-size: 20px 20px;
}

.viewer-img {
    transition:
        transform 220ms cubic-bezier(0.2, 0.8, 0.2, 1),
        opacity 200ms ease;
    will-change: transform;
}

.viewer-img--pinned {
    transition: none !important;
}

.viewer-img--animating {
    transition:
        transform 420ms cubic-bezier(0.2, 0.9, 0.1, 1),
        opacity 150ms ease;
}

.viewer-stage:active .viewer-img {
    transition: opacity 200ms ease;
}

.viewer-btn {
    display: inline-flex;
    height: 1.75rem;
    min-width: 1.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.25rem;
    color: #607b96;
    transition:
        color 150ms,
        background-color 150ms;
}

.viewer-btn:hover:not(:disabled) {
    background-color: #1e2d3d;
    color: #fff;
}

.viewer-btn:disabled {
    opacity: 0.35;
}

.viewer-btn:focus-visible,
.viewer-nav:focus-visible {
    outline: 1px solid #43d9ad;
    outline-offset: 1px;
}

.viewer-nav {
    position: absolute;
    top: 50%;
    display: inline-flex;
    height: 2.5rem;
    width: 2.5rem;
    transform: translateY(-50%);
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1px solid #1e2d3d;
    background: rgba(1, 18, 33, 0.8);
    color: #607b96;
    backdrop-filter: blur(4px);
    transition: all 150ms;
}

.viewer-nav:hover {
    border-color: #43d9ad;
    color: #43d9ad;
}

kbd {
    margin: 0 1px;
    border: 1px solid #1e2d3d;
    border-radius: 3px;
    padding: 0 4px;
    font-family: inherit;
    font-size: 10px;
    color: #607b96;
}

.viewer-loading span {
    animation: blink 1.2s infinite both;
}

.viewer-loading span:nth-child(2) {
    animation-delay: 0.2s;
}

.viewer-loading span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes blink {
    0%,
    80%,
    100% {
        opacity: 0;
    }
    40% {
        opacity: 1;
    }
}

.viewer-enter-active,
.viewer-leave-active {
    transition: opacity 220ms ease;
}

.viewer-enter-active .viewer-window,
.viewer-leave-active .viewer-window {
    transition:
        transform 260ms cubic-bezier(0.2, 0.8, 0.2, 1),
        opacity 220ms ease;
}

.viewer-enter-from,
.viewer-leave-to {
    opacity: 0;
}

.viewer-enter-from .viewer-window,
.viewer-leave-to .viewer-window {
    transform: scale(0.97) translateY(8px);
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .viewer-img,
    .viewer-img--animating,
    .viewer-enter-active,
    .viewer-leave-active,
    .viewer-enter-active .viewer-window,
    .viewer-leave-active .viewer-window {
        transition: none;
    }
}
</style>
