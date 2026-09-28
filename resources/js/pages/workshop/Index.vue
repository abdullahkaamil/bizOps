<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Plus } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { t as $t } from '@/i18n';
import { create, show } from '@/routes/tenant/workshop';

type TicketRow = {
    id: string;
    number: string;
    status: string;
    device: string | null;
    customer: string | null;
    assignee: string | null;
    received_at: string | null;
};

const props = defineProps<{
    inProgress: TicketRow[];
    completed: TicketRow[];
    delivered: TicketRow[];
    canCreate: boolean;
}>();

defineOptions({ layout: { breadcrumbs: [{ title: $t('nav.workshop'), href: '/workshop' }] } });

const { t } = useI18n();
const tab = ref<'inProgress' | 'completed' | 'delivered'>('inProgress');
const tabs = [
    { key: 'inProgress', label: 'tab_in_progress' },
    { key: 'completed', label: 'tab_completed' },
    { key: 'delivered', label: 'tab_delivered' },
] as const;

const lists = { inProgress: props.inProgress, completed: props.completed, delivered: props.delivered };
</script>

<template>
    <Head :title="t('nav.workshop')" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-3">
            <Heading variant="small" :title="t('nav.workshop')" :description="t('workshop.desc')" />
            <Button v-if="canCreate" as-child size="sm">
                <Link :href="create().url"><Plus class="size-4" /> {{ t('workshop.intake') }}</Link>
            </Button>
        </div>

        <div class="flex gap-2">
            <button
                v-for="wt in tabs"
                :key="wt.key"
                type="button"
                class="rounded-full border px-3 py-1 text-xs"
                :class="tab === wt.key ? 'bg-primary text-primary-foreground' : 'border-input'"
                @click="tab = wt.key"
            >
                {{ t('workshop.' + wt.label) }}
                <Badge variant="secondary" class="ml-1">{{ lists[wt.key].length }}</Badge>
            </button>
        </div>

        <Link
            v-for="ticket in lists[tab]"
            :key="ticket.id"
            :href="show(ticket.id).url"
            class="flex items-center gap-3 rounded-xl border border-sidebar-border/70 p-4 transition active:bg-muted/50 dark:border-sidebar-border"
        >
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="truncate font-medium">{{ ticket.device ?? t('workshop.device') }}</span>
                    <StatusBadge :status="ticket.status" />
                </div>
                <div class="mt-0.5 truncate text-xs text-muted-foreground">
                    {{ ticket.number }} · {{ ticket.customer ?? t('workshop.no_customer') }}
                </div>
                <div v-if="ticket.assignee" class="text-xs text-muted-foreground">{{ ticket.assignee }}</div>
            </div>
            <ChevronRight class="size-5 shrink-0 text-muted-foreground" />
        </Link>

        <p v-if="!lists[tab].length" class="py-8 text-center text-sm text-muted-foreground">{{ t('workshop.nothing_here') }}</p>
    </div>
</template>
