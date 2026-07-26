<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { CalendarX } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    expiredAt: string | null;
}>();

const page = usePage();
const tenantName = computed(() => page.props.tenant?.name ?? 'This workspace');
const expiredOn = computed(() =>
    props.expiredAt ? new Date(props.expiredAt).toLocaleDateString() : null,
);
</script>

<template>
    <div
        class="flex min-h-screen items-center justify-center bg-background p-6"
    >
        <Head title="License expired" />

        <div
            class="w-full max-w-md rounded-xl border border-sidebar-border/70 p-8 text-center dark:border-sidebar-border"
        >
            <div
                class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-destructive/10"
            >
                <CalendarX class="size-6 text-destructive" />
            </div>

            <h1 class="text-lg font-semibold">License expired</h1>

            <p class="mt-2 text-sm text-muted-foreground">
                {{ tenantName }}'s access
                <template v-if="expiredOn"
                    >expired on {{ expiredOn }}.</template
                >
                <template v-else>has expired.</template>
                Please contact your platform administrator to renew the
                subscription.
            </p>
        </div>
    </div>
</template>
