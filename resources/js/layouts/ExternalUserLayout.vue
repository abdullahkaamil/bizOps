<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import NavUser from '@/components/NavUser.vue';
import { index as boardsIndex } from '@/routes/tenant/boards';

/**
 * Minimal shell for external customer representatives: no internal sidebar —
 * only the workspace name, a link to their project boards, and the user menu.
 */
const tenantName = computed(() => usePage().props.tenant?.name ?? 'Workspace');
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <header
            class="flex items-center justify-between border-b border-sidebar-border/70 px-6 py-3 dark:border-sidebar-border"
        >
            <div class="flex items-center gap-6 font-semibold">
                <span class="flex items-center gap-2">
                    <AppLogoIcon class="size-5 fill-current" />
                    <span>{{ tenantName }}</span>
                </span>
                <Link
                    :href="boardsIndex().url"
                    class="text-sm font-normal text-muted-foreground hover:text-foreground"
                >
                    Boards
                </Link>
            </div>
            <div class="w-56">
                <NavUser />
            </div>
        </header>

        <main class="mx-auto w-full max-w-5xl flex-1 p-4">
            <slot />
        </main>
    </div>
</template>
