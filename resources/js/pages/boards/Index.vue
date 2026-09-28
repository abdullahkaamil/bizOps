<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { LayoutGrid, Users } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t as $t } from '@/i18n';
import { store as storeBoard, show } from '@/routes/tenant/boards';

type BoardRow = {
    id: string;
    name: string;
    description: string | null;
    type: string;
    is_active: boolean;
    customer: { id: string; name: string } | null;
    tasks_count: number | null;
    members_count: number | null;
};
type Option = { id: string; name: string };

defineProps<{
    boards: BoardRow[];
    boardTypes: string[];
    customers: Option[];
    canCreate: boolean;
}>();

defineOptions({ layout: { breadcrumbs: [{ title: $t('nav.boards'), href: '/boards' }] } });

const { t } = useI18n();
const newType = ref<'internal' | 'project'>('internal');
const creating = ref(false);

// Shared data-viz palette accent, so boards match the dashboard colours.
const typeColor: Record<string, string> = {
    project: 'var(--dv-blue)',
    internal: 'var(--dv-violet)',
};
</script>

<template>
    <Head :title="t('nav.boards')" />

    <div class="flex flex-col gap-8 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="t('nav.boards')"
                :description="t('boards.desc')"
            />
            <Button v-if="canCreate" @click="creating = !creating">
                {{ creating ? t('boards.close') : t('boards.new_board') }}
            </Button>
        </div>

        <div
            v-if="canCreate && creating"
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <Form
                v-bind="storeBoard.form()"
                class="grid gap-4 md:grid-cols-4"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">{{ t('boards.name') }}</Label>
                    <Input id="name" name="name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="type">{{ t('boards.type') }}</Label>
                    <select
                        id="type"
                        name="type"
                        v-model="newType"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option v-for="bt in boardTypes" :key="bt" :value="bt">{{ t('boards.type_' + bt) }}</option>
                    </select>
                </div>
                <div v-if="newType === 'project'" class="grid gap-2">
                    <Label for="customer_id">{{ t('boards.customer') }}</Label>
                    <select
                        id="customer_id"
                        name="customer_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">{{ t('boards.select_customer') }}</option>
                        <option v-for="c in customers" :key="c.id" :value="c.id">
                            {{ c.name }}
                        </option>
                    </select>
                    <InputError :message="errors.customer_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="description">{{ t('boards.description') }}</Label>
                    <Input id="description" name="description" />
                </div>
                <div class="flex items-end md:col-span-4">
                    <Button type="submit" :disabled="processing">{{ t('boards.create_board') }}</Button>
                </div>
            </Form>
        </div>

        <div v-if="boards.length" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="board in boards"
                :key="board.id"
                :href="show(board.id).url"
                class="relative flex flex-col gap-3 overflow-hidden rounded-xl border border-sidebar-border/70 p-4 pl-5 transition hover:border-primary/50 dark:border-sidebar-border"
            >
                <span class="absolute inset-y-0 left-0 w-1.5" :style="{ background: typeColor[board.type] ?? 'var(--dv-blue)' }" />
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium">{{ board.name }}</span>
                    <Badge :variant="board.type === 'project' ? 'default' : 'secondary'">
                        {{ t('boards.type_' + board.type) }}
                    </Badge>
                </div>
                <p
                    v-if="board.customer"
                    class="text-xs text-muted-foreground"
                >
                    {{ board.customer.name }}
                </p>
                <p
                    v-else-if="board.description"
                    class="line-clamp-2 text-xs text-muted-foreground"
                >
                    {{ board.description }}
                </p>
                <div class="mt-auto flex gap-4 text-xs text-muted-foreground">
                    <span class="inline-flex items-center gap-1">
                        <LayoutGrid class="size-3.5" />
                        {{ board.tasks_count ?? 0 }} {{ t('boards.tasks') }}
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <Users class="size-3.5" />
                        {{ board.members_count ?? 0 }}
                    </span>
                </div>
            </Link>
        </div>

        <p v-else class="text-sm text-muted-foreground">{{ t('boards.no_boards') }}</p>
    </div>
</template>
