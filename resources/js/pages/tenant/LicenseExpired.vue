<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { CalendarX } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    expiredAt: string | null;
}>();

const { t } = useI18n();
const page = usePage();
const tenantName = computed(() => page.props.tenant?.name ?? t('license.this_workspace'));
const expiredOn = computed(() =>
    props.expiredAt ? new Date(props.expiredAt).toLocaleDateString() : null,
);
</script>

<template>
    <div
        class="flex min-h-screen items-center justify-center bg-background p-6"
    >
        <Head :title="t('license.expired_title')" />

        <div
            class="w-full max-w-md rounded-xl border border-sidebar-border/70 p-8 text-center dark:border-sidebar-border"
        >
            <div
                class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-destructive/10"
            >
                <CalendarX class="size-6 text-destructive" />
            </div>

            <h1 class="text-lg font-semibold">{{ t('license.expired_title') }}</h1>

            <p class="mt-2 text-sm text-muted-foreground">
                <template v-if="expiredOn">{{ t('license.expired_on', { name: tenantName, date: expiredOn }) }}</template>
                <template v-else>{{ t('license.expired_generic', { name: tenantName }) }}</template>
                {{ t('license.contact_admin') }}
            </p>
        </div>
    </div>
</template>
