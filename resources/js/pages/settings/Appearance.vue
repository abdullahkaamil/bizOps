<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { t as $t } from '@/i18n';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: $t('settings.appearance_title'),
                href: edit(),
            },
        ],
    },
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('settings.appearance_title')" />

    <h1 class="sr-only">{{ t('settings.appearance_title') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('settings.appearance_title')"
            :description="t('settings.appearance_desc')"
        />
        <AppearanceTabs />
    </div>
</template>
