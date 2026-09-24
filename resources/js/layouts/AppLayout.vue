<script setup lang="ts">
import { useAuthorization } from '@/composables/useAuthorization';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import ExternalUserLayout from '@/layouts/ExternalUserLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();

// The layout is selected by user type: external customer representatives get the
// minimal ExternalUserLayout; central admins and internal users get the sidebar
// shell, whose navigation itself adapts to the actor (see AppSidebar).
const { isExternal } = useAuthorization();
</script>

<template>
    <ExternalUserLayout v-if="isExternal">
        <slot />
    </ExternalUserLayout>
    <AppSidebarLayout v-else :breadcrumbs="breadcrumbs">
        <slot />
    </AppSidebarLayout>
</template>
