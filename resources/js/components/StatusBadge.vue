<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { statusMeta } from '@/lib/statusLabels';

const props = defineProps<{
    status: string;
}>();

const { t } = useI18n();
const meta = computed(() => statusMeta(props.status));
</script>

<template>
    <span
        v-if="meta.color"
        class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-md px-2 py-0.5 text-xs font-medium"
        :style="{ backgroundColor: `color-mix(in srgb, var(--dv-${meta.color}) 16%, transparent)` }"
    >
        <span class="size-2 shrink-0 rounded-full" :style="{ backgroundColor: `var(--dv-${meta.color})` }" />
        {{ t(meta.key) }}
    </span>
    <Badge v-else :variant="meta.variant">{{ t(meta.key) }}</Badge>
</template>
