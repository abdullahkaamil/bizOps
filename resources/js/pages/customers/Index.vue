<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Contact } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { t as $t } from '@/i18n';
import {
    index as customersIndex,
    create,
    show,
} from '@/routes/tenant/customers';

type CustomerRow = {
    id: string;
    company_name: string;
    email: string | null;
    phone: string | null;
    status: string;
    contacts_count: number;
    addresses_count: number;
};

type Paginator = {
    data: CustomerRow[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

const props = defineProps<{
    customers: Paginator;
    filters: { search: string; status: string | null };
    statuses: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: $t('nav.customers'), href: '/customers' }],
    },
});

const { t } = useI18n();
const search = ref(props.filters.search);
const status = ref(props.filters.status ?? '');

let timeout: ReturnType<typeof setTimeout>;
watch([search, status], () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            customersIndex().url,
            {
                search: search.value || undefined,
                status: status.value || undefined,
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    }, 250);
});
</script>

<template>
    <Head :title="t('nav.customers')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                :title="t('nav.customers')"
                :description="`${customers.total} ${t('crm.total')}`"
            />
            <Button as-child>
                <a :href="create().url">{{ t('crm.new_customer') }}</a>
            </Button>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <Input
                v-model="search"
                :placeholder="t('crm.search_placeholder')"
                class="max-w-sm"
            />
            <select
                v-model="status"
                class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground"
            >
                <option value="" class="bg-background text-foreground">
                    {{ t('crm.all_statuses') }}
                </option>
                <option
                    v-for="s in statuses"
                    :key="s"
                    :value="s"
                    class="bg-background text-foreground"
                >
                    {{ t('status.' + s) }}
                </option>
            </select>
        </div>

        <div
            v-if="customers.data.length === 0"
            class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed p-12 text-center"
        >
            <Contact class="size-8 text-muted-foreground" />
            <p class="text-sm text-muted-foreground">
                {{ t('crm.no_match') }}
            </p>
        </div>

        <div
            v-else
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">
                            {{ t('crm.col_company') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('crm.col_email') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('crm.col_phone') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('crm.col_contacts') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('crm.col_status') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="customer in customers.data"
                        :key="customer.id"
                        class="border-b border-sidebar-border/40 last:border-0 dark:border-sidebar-border/40"
                    >
                        <td class="px-4 py-3 font-medium">
                            <Link
                                :href="show(customer.id).url"
                                class="text-primary underline-offset-4 hover:underline"
                            >
                                {{ customer.company_name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ customer.email ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ customer.phone ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ customer.contacts_count }} /
                            {{ customer.addresses_count }} {{ t('crm.addr') }}
                        </td>
                        <td class="px-4 py-3">
                            <StatusBadge :status="customer.status" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="customers.links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="(link, i) in customers.links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    class="rounded-md border px-3 py-1 text-sm"
                    :class="
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'hover:bg-muted'
                    "
                >
                    <span v-html="link.label" />
                </Link>
            </template>
        </div>
    </div>
</template>
