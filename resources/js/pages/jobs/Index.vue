<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Building2, ChevronRight, Plus } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { t as $t } from '@/i18n';
import { create, index, show } from '@/routes/tenant/jobs';

type JobRow = {
    id: string;
    number: string;
    title: string;
    status: string;
    planned_at: string | null;
    customer: string | null;
    assignee: string | null;
};

const props = defineProps<{
    jobs: JobRow[];
    filters: { status: string | null; customer: string | null };
    statuses: string[];
    customers: { id: string; name: string }[];
    canCreate: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: $t('nav.jobs'), href: '/jobs' }] },
});

const { t } = useI18n();

function filterStatus(status: string | null) {
    router.get(
        index().url,
        {
            status: status || undefined,
            customer: props.filters.customer || undefined,
        },
        { preserveScroll: true, preserveState: true },
    );
}

function filterCustomer(event: Event) {
    const customer = (event.target as HTMLSelectElement).value;

    router.get(
        index().url,
        {
            status: props.filters.status || undefined,
            customer: customer || undefined,
        },
        { preserveScroll: true, preserveState: true },
    );
}

function planned(value: string | null): string {
    if (!value) {
        return t('jobs.unscheduled');
    }

    return new Date(value).toLocaleString([], {
        weekday: 'short',
        hour: '2-digit',
        minute: '2-digit',
        day: 'numeric',
        month: 'short',
    });
}
</script>

<template>
    <Head :title="t('nav.jobs')" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-3">
            <Heading
                variant="small"
                :title="t('nav.jobs')"
                :description="t('jobs.schedule_desc')"
            />
            <Button v-if="canCreate" as-child size="sm">
                <Link :href="create().url"
                    ><Plus class="size-4" /> {{ t('jobs.new') }}</Link
                >
            </Button>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-full border px-3 py-1 text-xs"
                :class="
                    !filters.status
                        ? 'bg-primary text-primary-foreground'
                        : 'border-input'
                "
                @click="filterStatus(null)"
            >
                {{ t('common.all') }}
            </button>
            <button
                v-for="s in statuses"
                :key="s"
                type="button"
                class="rounded-full border px-3 py-1 text-xs capitalize"
                :class="
                    filters.status === s
                        ? 'bg-primary text-primary-foreground'
                        : 'border-input'
                "
                @click="filterStatus(s)"
            >
                {{ t('status.' + s) }}
            </button>
        </div>

        <select
            :value="filters.customer ?? ''"
            class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground"
            @change="filterCustomer"
        >
            <option value="">{{ t('jobs.all_customers') }}</option>
            <option
                v-for="customer in customers"
                :key="customer.id"
                :value="customer.id"
            >
                {{ customer.name }}
            </option>
        </select>

        <Link
            v-for="job in props.jobs"
            :key="job.id"
            :href="show(job.id).url"
            class="flex items-center gap-3 rounded-xl border border-sidebar-border/70 p-4 transition active:bg-muted/50 dark:border-sidebar-border"
        >
            <div class="min-w-0 flex-1">
                <div
                    class="mb-1 flex items-center gap-1.5 text-sm font-semibold text-primary"
                >
                    <Building2 class="size-4 shrink-0" />
                    <span class="truncate">{{
                        job.customer ?? t('jobs.no_customer')
                    }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="truncate font-medium">{{ job.title }}</span>
                    <StatusBadge :status="job.status" />
                </div>
                <div class="mt-0.5 truncate text-xs text-muted-foreground">
                    {{ job.number }}
                </div>
                <div class="mt-0.5 text-xs text-muted-foreground">
                    {{ planned(job.planned_at) }}
                </div>
            </div>
            <ChevronRight class="size-5 shrink-0 text-muted-foreground" />
        </Link>

        <p
            v-if="!props.jobs.length"
            class="py-8 text-center text-sm text-muted-foreground"
        >
            {{ t('jobs.no_jobs_here') }}
        </p>
    </div>
</template>
