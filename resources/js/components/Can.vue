<script setup lang="ts">
import { computed } from 'vue';
import { useAuthorization } from '@/composables/useAuthorization';

/**
 * Permission-aware wrapper. Renders its slot only when the current user passes
 * the given check. Presentation only — the server still authorizes every action.
 */
const props = defineProps<{
    permission?: string;
    anyOf?: string[];
    role?: string;
}>();

const { can, canAny, hasRole } = useAuthorization();

const allowed = computed<boolean>(() => {
    if (props.permission && !can(props.permission)) {
        return false;
    }

    if (props.anyOf && !canAny(props.anyOf)) {
        return false;
    }

    if (props.role && !hasRole(props.role)) {
        return false;
    }

    return true;
});
</script>

<template>
    <slot v-if="allowed" />
</template>
