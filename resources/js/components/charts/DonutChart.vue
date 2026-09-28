<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type Segment = { label: string; value: number; color: string };

const props = defineProps<{
    label: string;
    total: number;
    segments: Segment[];
}>();

const { t } = useI18n();

// Donut geometry: r chosen so the circumference is ~100, letting each segment's
// length be its exact percentage. Segments start at 12 o'clock and run clockwise.
const R = 15.9155;

const arcs = computed(() => {
    let before = 0;

    return props.segments.map((s) => {
        const pct = props.total > 0 ? (s.value / props.total) * 100 : 0;
        const gap = props.segments.length > 1 ? 1 : 0; // 1% surface gap between slices
        const len = Math.max(pct - gap, 0.5);
        const arc = { ...s, dash: `${len} ${100 - len}`, offset: 25 - before };
        before += pct;

        return arc;
    });
});
</script>

<template>
    <div class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
        <span class="text-sm font-medium">{{ label }}</span>
        <div class="flex items-center gap-4">
            <div class="relative size-28 shrink-0">
                <svg viewBox="0 0 42 42" class="size-full">
                    <circle
                        v-for="(arc, i) in arcs"
                        :key="i"
                        cx="21"
                        cy="21"
                        :r="R"
                        fill="none"
                        :stroke="`var(--dv-${arc.color})`"
                        stroke-width="5"
                        :stroke-dasharray="arc.dash"
                        :stroke-dashoffset="arc.offset"
                    />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-xl font-semibold">{{ total }}</span>
                    <span class="text-[10px] uppercase tracking-wide text-muted-foreground">{{ t('dashboard.total') }}</span>
                </div>
            </div>

            <ul class="flex flex-1 flex-col gap-1.5">
                <li v-for="(seg, i) in segments" :key="i" class="flex items-center justify-between gap-2 text-sm">
                    <span class="flex items-center gap-2 truncate">
                        <span class="size-2.5 shrink-0 rounded-full" :style="{ background: `var(--dv-${seg.color})` }" />
                        <span class="truncate">{{ seg.label }}</span>
                    </span>
                    <span class="shrink-0 font-medium tabular-nums">{{ seg.value }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
