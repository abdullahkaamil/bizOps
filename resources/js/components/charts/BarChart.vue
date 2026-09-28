<script setup lang="ts">
import { computed } from 'vue';

type Point = { label: string; value: number };

const props = defineProps<{
    label: string;
    color: string;
    series: Point[];
}>();

const max = computed(() => Math.max(1, ...props.series.map((s) => s.value)));

// Bar height in px, scaled to the tallest bar (rows are bottom-aligned so the
// value labels sit just above each bar).
function barHeight(value: number): string {
    return `${Math.max((value / max.value) * 130, 3)}px`;
}
</script>

<template>
    <div class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
        <span class="text-sm font-medium">{{ label }}</span>
        <div class="flex items-end gap-3 px-1">
            <div v-for="(point, i) in series" :key="i" class="flex flex-1 flex-col items-center gap-1">
                <span class="text-[11px] font-medium tabular-nums">{{ point.value }}</span>
                <div
                    class="w-full rounded-t-md transition-all"
                    :style="{ height: barHeight(point.value), background: `var(--dv-${color})` }"
                />
            </div>
        </div>
        <div class="flex gap-3 px-1">
            <span v-for="(point, i) in series" :key="i" class="flex-1 text-center text-[11px] text-muted-foreground">
                {{ point.label }}
            </span>
        </div>
    </div>
</template>
