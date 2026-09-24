<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BarChart from '@/components/charts/BarChart.vue';
import DonutChart from '@/components/charts/DonutChart.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { t as $t } from '@/i18n';

type Item = { title: string; meta: string | null; link: string | null };
type Widget = { label: string; count: number; items: Item[] };
type Stat = { label: string; value: number; color: string; link: string | null };
type Segment = { label: string; value: number; color: string };
type Chart =
    | { type: 'donut'; label: string; total: number; segments: Segment[] }
    | { type: 'bars'; label: string; color: string; series: { label: string; value: number }[] };
type Dashboard = {
    scope: string;
    widgets: Record<string, Widget>;
    stats: Stat[];
    charts: Chart[];
};

const props = defineProps<{ dashboard: Dashboard }>();

defineOptions({ layout: { breadcrumbs: [{ title: $t('nav.dashboard'), href: '/dashboard' }] } });

const { t } = useI18n();
const widgets = Object.values(props.dashboard.widgets ?? {});
const stats = props.dashboard.stats ?? [];
const charts = props.dashboard.charts ?? [];
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <div class="flex flex-col gap-6 p-4">
        <Heading variant="small" :title="t('dashboard.title')" :description="t('dashboard.summary')" />

        <!-- Stat tiles -->
        <div v-if="stats.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <component
                :is="s.link ? Link : 'div'"
                v-for="(s, i) in stats"
                :key="i"
                :href="s.link ?? undefined"
                class="relative flex flex-col gap-1 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                :class="s.link ? 'transition hover:border-primary/50' : ''"
            >
                <span class="absolute inset-y-0 left-0 w-1.5" :style="{ background: `var(--dv-${s.color})` }" />
                <span class="pl-2 text-3xl font-semibold tabular-nums" :style="{ color: `var(--dv-${s.color})` }">{{ s.value }}</span>
                <span class="pl-2 text-sm text-muted-foreground">{{ s.label }}</span>
            </component>
        </div>

        <!-- Charts -->
        <div v-if="charts.length" class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            <template v-for="(c, i) in charts" :key="i">
                <DonutChart v-if="c.type === 'donut'" :label="c.label" :total="c.total" :segments="c.segments" />
                <BarChart v-else :label="c.label" :color="c.color" :series="c.series" />
            </template>
        </div>

        <!-- Lists -->
        <div v-if="widgets.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="(w, i) in widgets"
                :key="i"
                class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium">{{ w.label }}</span>
                    <Badge variant="secondary">{{ w.count }}</Badge>
                </div>
                <div class="flex flex-col gap-1.5">
                    <component
                        :is="item.link ? Link : 'div'"
                        v-for="(item, j) in w.items"
                        :key="j"
                        :href="item.link ?? undefined"
                        class="flex items-center justify-between gap-2 rounded-md px-2 py-1 text-sm"
                        :class="item.link ? 'hover:bg-muted/50' : ''"
                    >
                        <span class="truncate">{{ item.title }}</span>
                        <span class="shrink-0 text-xs text-muted-foreground">{{ item.meta }}</span>
                    </component>
                    <p v-if="!w.items.length && !w.count" class="px-2 text-xs text-muted-foreground">{{ t('dashboard.nothing_here') }}</p>
                </div>
            </div>
        </div>

        <div v-if="!stats.length && !charts.length && !widgets.length" class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
            {{ t('dashboard.nothing_yet') }}
        </div>
    </div>
</template>
