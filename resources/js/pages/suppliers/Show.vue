<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { t as $t } from '@/i18n';

type ItemRow = {
    id: string;
    sku: string;
    name: string;
    supplier_sku: string | null;
    lead_time_days: number | null;
    is_preferred: boolean;
    last_purchase_price: string | null;
};
type Supplier = {
    id: string;
    name: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    notes: string | null;
};

defineProps<{ supplier: Supplier; items: ItemRow[]; canViewCost: boolean }>();

defineOptions({ layout: { breadcrumbs: [{ title: $t('nav.suppliers'), href: '/suppliers' }, { title: $t('common.details'), href: '#' }] } });

const { t } = useI18n();
</script>

<template>
    <Head :title="supplier.name" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4">
        <Heading variant="small" :title="supplier.name" :description="supplier.contact_name ?? ''" />

        <div class="rounded-xl border border-sidebar-border/70 p-4 text-sm dark:border-sidebar-border">
            <div v-if="supplier.email" class="text-muted-foreground">{{ supplier.email }}</div>
            <div v-if="supplier.phone" class="text-muted-foreground">{{ supplier.phone }}</div>
            <div v-if="supplier.address" class="mt-1 text-muted-foreground">{{ supplier.address }}</div>
        </div>

        <section class="flex flex-col gap-2">
            <h2 class="text-sm font-medium">{{ t('suppliers.supplied_items') }}</h2>
            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">{{ t('suppliers.item') }}</th>
                            <th class="px-3 py-2 font-medium">{{ t('suppliers.supplier_sku') }}</th>
                            <th class="px-3 py-2 font-medium">{{ t('suppliers.lead') }}</th>
                            <th v-if="canViewCost" class="px-3 py-2 text-right font-medium">{{ t('suppliers.last_cost') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="i in items" :key="i.id" class="border-b last:border-0">
                            <td class="px-3 py-2">
                                {{ i.name }}
                                <Badge v-if="i.is_preferred" variant="default" class="ml-1">{{ t('suppliers.preferred') }}</Badge>
                                <div class="font-mono text-xs text-muted-foreground">{{ i.sku }}</div>
                            </td>
                            <td class="px-3 py-2">{{ i.supplier_sku ?? '—' }}</td>
                            <td class="px-3 py-2">{{ i.lead_time_days != null ? i.lead_time_days + 'd' : '—' }}</td>
                            <td v-if="canViewCost" class="px-3 py-2 text-right">{{ i.last_purchase_price ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="!items.length" class="text-sm text-muted-foreground">{{ t('suppliers.no_linked_items') }}</p>
        </section>
    </div>
</template>
