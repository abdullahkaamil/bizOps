<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from "vue-i18n";
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { t as $t } from "@/i18n";
import { search as searchRoute } from "@/routes";

type Result = { id: string; title: string; subtitle: string | null; link: string | null };
type Group = { type: string; label: string; results: Result[] };

const props = defineProps<{ query: string; groups: Group[] }>();

defineOptions({ layout: { breadcrumbs: [{ title: $t('nav.search'), href: '/search' }] } });

const { t } = useI18n();
const term = ref(props.query);

function run() {
    router.get(searchRoute().url, term.value ? { q: term.value } : {}, { preserveState: true });
}
</script>

<template>
    <Head :title="t('nav.search')" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4">
        <Heading variant="small" :title="t('nav.search')" :description="t('search.desc')" />

        <div class="relative">
            <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="term" :placeholder="t('search.placeholder')" class="pl-9" @keyup.enter="run" />
        </div>

        <div v-for="group in groups" :key="group.type" class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-medium">{{ group.label }}</h2>
                <Badge variant="secondary">{{ group.results.length }}</Badge>
            </div>
            <component
                :is="r.link ? Link : 'div'"
                v-for="r in group.results"
                :key="r.id"
                :href="r.link ?? undefined"
                class="flex items-center justify-between rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border"
                :class="r.link ? 'hover:border-primary/50' : ''"
            >
                <span class="font-medium">{{ r.title }}</span>
                <span class="text-xs text-muted-foreground">{{ r.subtitle }}</span>
            </component>
        </div>

        <p v-if="query && !groups.length" class="text-sm text-muted-foreground">{{ t("search.no_results", { query }) }}</p>
    </div>
</template>
