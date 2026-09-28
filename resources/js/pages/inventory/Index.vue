<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { t as $t } from '@/i18n';
import { create, show } from '@/routes/tenant/inventory';

type Item = {
    id: string;
    sku: string;
    name: string;
    unit: string;
    status: string;
    sale_price: string | null;
    stock: number;
    low: boolean;
};

const props = defineProps<{
    items: Item[];
    lowOnly: boolean;
    threshold: number;
    canManage: boolean;
}>();

defineOptions({ layout: { breadcrumbs: [{ title: $t('nav.inventory'), href: '/inventory' }] } });

const { t } = useI18n();

// Stock health, in the shared palette: none stocked → red (critical), at/below
// the threshold → amber (warning), otherwise fine.
function stockLevel(item: Item): { color: string; key: string } | null {
    if (item.stock <= 0) {
        return { color: 'red', key: 'inventory.out' };
    }

    if (item.low) {
        return { color: 'yellow', key: 'inventory.low_stock' };
    }

    return null;
}

const rows = computed(() => props.items.map((item) => ({ item, level: stockLevel(item) })));

function toggleLow() {
    router.get('/inventory', props.lowOnly ? {} : { low: 1 }, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <Head :title="t('nav.inventory')" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-3">
            <Heading variant="small" :title="t('nav.inventory')" :description="t('inventory.desc')" />
            <div class="flex gap-2">
                <Button variant="outline" size="sm" @click="toggleLow">
                    {{ lowOnly ? t('inventory.all_items') : t('inventory.low_stock') }}
                </Button>
                <Button v-if="canManage" as-child size="sm">
                    <Link :href="create().url"><Plus class="size-4" /> {{ t('inventory.new_item') }}</Link>
                </Button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('inventory.col_sku') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('inventory.col_name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('inventory.col_status') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ t('inventory.col_stock') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ t('inventory.col_sale_price') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="{ item, level } in rows"
                        :key="item.id"
                        class="cursor-pointer border-b border-sidebar-border/40 last:border-0 hover:bg-muted/40"
                        @click="router.get(show(item.id).url)"
                    >
                        <td class="px-4 py-3 font-mono text-xs">{{ item.sku }}</td>
                        <td class="px-4 py-3 font-medium">{{ item.name }}</td>
                        <td class="px-4 py-3"><StatusBadge :status="item.status" /></td>
                        <td class="px-4 py-3 text-right">
                            <span class="inline-flex items-center justify-end gap-2">
                                <span :style="level ? { color: `var(--dv-${level.color})` } : {}" class="font-medium tabular-nums">
                                    {{ item.stock }} {{ item.unit }}
                                </span>
                                <span
                                    v-if="level"
                                    class="inline-flex items-center gap-1 whitespace-nowrap rounded-md px-1.5 py-0.5 text-[10px] font-medium"
                                    :style="{ backgroundColor: `color-mix(in srgb, var(--dv-${level.color}) 16%, transparent)` }"
                                >
                                    <span class="size-1.5 rounded-full" :style="{ backgroundColor: `var(--dv-${level.color})` }" />
                                    {{ t(level.key) }}
                                </span>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">{{ item.sale_price ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="!items.length" class="py-8 text-center text-sm text-muted-foreground">{{ t('inventory.no_items') }}</p>
    </div>
</template>
