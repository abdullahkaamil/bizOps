<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { t as $t } from '@/i18n';
import { create, show } from '@/routes/tenant/quotations';

type Row = {
    id: string;
    number: string;
    customer: string | null;
    status: string;
    grand_total: string;
    currency: string;
    issue_date: string | null;
    valid_until: string | null;
};

defineProps<{ quotations: Row[]; canCreate: boolean }>();

defineOptions({ layout: { breadcrumbs: [{ title: $t('nav.quotations'), href: '/quotations' }] } });

const { t } = useI18n();
</script>

<template>
    <Head :title="t('nav.quotations')" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-3">
            <Heading variant="small" :title="t('nav.quotations')" :description="t('quotations.desc')" />
            <Button v-if="canCreate" as-child size="sm">
                <Link :href="create().url"><Plus class="size-4" /> {{ t('quotations.new_quote') }}</Link>
            </Button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <table class="w-full text-left text-sm">
                <thead class="border-b text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('quotations.col_number') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('quotations.col_customer') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('common.status') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('quotations.col_valid_until') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ t('quotations.col_total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="q in quotations"
                        :key="q.id"
                        class="cursor-pointer border-b last:border-0 hover:bg-muted/40"
                        @click="router.get(show(q.id).url)"
                    >
                        <td class="px-4 py-3 font-mono text-xs">{{ q.number }}</td>
                        <td class="px-4 py-3">{{ q.customer ?? '—' }}</td>
                        <td class="px-4 py-3"><StatusBadge :status="q.status" /></td>
                        <td class="px-4 py-3 text-muted-foreground">{{ q.valid_until ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ q.currency }} {{ q.grand_total }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="!quotations.length" class="py-8 text-center text-sm text-muted-foreground">{{ t('quotations.no_quotations') }}</p>
    </div>
</template>
