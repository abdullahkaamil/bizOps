<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';

const { t } = useI18n();

/**
 * A dependency-free canvas signature pad. Captures strokes with pointer events
 * and emits a compressed PNG blob for private upload (never a raw Base64 blob in
 * a text column).
 */
const emit = defineEmits<{ save: [blob: Blob]; clear: [] }>();

const canvas = ref<HTMLCanvasElement | null>(null);
const hasInk = ref(false);
let ctx: CanvasRenderingContext2D | null = null;
let drawing = false;

function resize() {
    const el = canvas.value;

    if (!el) {
return;
}

    const ratio = window.devicePixelRatio || 1;
    const rect = el.getBoundingClientRect();
    el.width = rect.width * ratio;
    el.height = rect.height * ratio;
    ctx = el.getContext('2d');

    if (ctx) {
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2.2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#111827';
    }
}

function pos(e: PointerEvent) {
    const rect = canvas.value!.getBoundingClientRect();

    return { x: e.clientX - rect.left, y: e.clientY - rect.top };
}

function down(e: PointerEvent) {
    drawing = true;
    hasInk.value = true;
    const p = pos(e);
    ctx?.beginPath();
    ctx?.moveTo(p.x, p.y);
    canvas.value?.setPointerCapture(e.pointerId);
}
function move(e: PointerEvent) {
    if (!drawing || !ctx) {
return;
}

    const p = pos(e);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
}
function up() {
    drawing = false;
}

function clear() {
    const el = canvas.value;

    if (el && ctx) {
ctx.clearRect(0, 0, el.width, el.height);
}

    hasInk.value = false;
    emit('clear');
}

function save() {
    if (!canvas.value || !hasInk.value) {
return;
}

    canvas.value.toBlob(
        (blob) => {
            if (blob) {
emit('save', blob);
}
        },
        'image/png',
    );
}

onMounted(() => {
    resize();
    window.addEventListener('resize', resize);
});
onBeforeUnmount(() => window.removeEventListener('resize', resize));

defineExpose({ clear });
</script>

<template>
    <div class="flex flex-col gap-2">
        <canvas
            ref="canvas"
            class="h-40 w-full touch-none rounded-lg border border-input bg-white"
            @pointerdown="down"
            @pointermove="move"
            @pointerup="up"
            @pointerleave="up"
        />
        <div class="flex gap-2">
            <Button type="button" size="sm" variant="outline" @click="clear">{{ t('jobs.sig_clear') }}</Button>
            <Button type="button" size="sm" :disabled="!hasInk" @click="save">{{ t('jobs.sig_save') }}</Button>
        </div>
    </div>
</template>
