<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import {
    GripVertical,
    Lock,
    MessageCircle,
    Paperclip,
    Pencil,
    Plus,
    SendHorizontal,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import draggable from 'vuedraggable';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t as $t } from '@/i18n';
import { show } from '@/routes/tenant/boards';
import {
    reorder as reorderColumnsRoute,
    store as storeColumn,
} from '@/routes/tenant/boards/columns';
import { store as addMember } from '@/routes/tenant/boards/members';
import {
    destroy as destroyColumn,
    update as updateColumn,
} from '@/routes/tenant/columns';
import { move as moveTask, store as storeTask } from '@/routes/tenant/tasks';
import {
    download,
    store as storeAttachment,
} from '@/routes/tenant/tasks/attachments';
import { store as storeComment } from '@/routes/tenant/tasks/comments';

type Chip = { id: string; name: string };
type Abilities = {
    move: boolean;
    update: boolean;
    delete: boolean;
    comment_internal: boolean;
    comment_customer: boolean;
};
type Card = {
    id: string;
    title: string;
    status: string;
    column_id: string | null;
    priority: string | null;
    due_at: string | null;
    completed_at: string | null;
    assignees: Chip[];
    abilities: Abilities;
};
type Comment = {
    id: string;
    body: string;
    visibility: string;
    author: Chip | null;
    mine: boolean;
    created_at: string | null;
};
type Attachment = {
    id: string;
    name: string;
    size: number;
    visibility: string;
    uploaded_by: Chip | null;
    mine: boolean;
    created_at: string | null;
};
type History = {
    id: string;
    from: string | null;
    to: string;
    reason: string | null;
    actor: Chip | null;
    created_at: string | null;
};
type Detail = Card & {
    description: string | null;
    board_id: string;
    comments: Comment[];
    attachments: Attachment[];
    history: History[];
};
type Column = {
    id: string;
    name: string;
    category: string;
    position: number;
    move_in: string;
    move_out: string;
    can_drop: boolean;
    can_pull: boolean;
    tasks: Card[];
};
type Category = { value: string; label: string };
type Member = { id: string; name: string; role: string | null };
type BoardData = {
    id: string;
    name: string;
    description: string | null;
    type: string;
    customer: Chip | null;
    members: Member[];
    abilities: {
        update: boolean;
        delete: boolean;
        manage_members: boolean;
        manage_columns: boolean;
        create_task: boolean;
    };
};

const props = defineProps<{
    board: BoardData;
    columns: Column[];
    categories: Category[];
    accessOptions: { value: string; label: string }[];
    selectedTask: Detail | null;
    priorities: string[];
    assignableUsers: Chip[];
    memberCandidates: Chip[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: $t('nav.boards'), href: '/boards' }] },
});

const { t } = useI18n();
const addingTask = ref(false);

// Colour each step's top border by its workflow category, using the shared
// data-viz palette so boards match the dashboard charts.
const categoryColor: Record<string, string> = {
    todo: 'var(--dv-yellow)',
    in_progress: 'var(--dv-blue)',
    review: 'var(--dv-violet)',
    completed: 'var(--dv-green)',
};

// vuedraggable mutates the bound arrays, so we work on a local clone and resync
// it whenever the server sends fresh board data (after a move / column change).
const localColumns = ref<Column[]>(clone(props.columns));
watch(
    () => props.columns,
    (next) => (localColumns.value = clone(next)),
);

function clone(columns: Column[]): Column[] {
    return columns.map((c) => ({ ...c, tasks: [...c.tasks] }));
}

const canManageColumns = computed(() => props.board.abilities.manage_columns);

// -- Task movement ---------------------------------------------------------

type ChangeEvent = {
    added?: { element: Card; newIndex: number };
    moved?: { element: Card; newIndex: number };
};

function onTaskChange(column: Column, event: ChangeEvent) {
    const change = event.added ?? event.moved;

    if (!change) {
        return; // "removed" fires on the source list — the destination handles it.
    }

    router.post(
        moveTask(change.element.id).url,
        { column_id: column.id, position: change.newIndex },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['columns', 'selectedTask'],
        },
    );
}

function moveToStep(taskId: string, columnId: string) {
    router.post(
        moveTask(taskId).url,
        { column_id: columnId },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['columns', 'selectedTask'],
        },
    );
}

// -- Column management ------------------------------------------------------

const addingStep = ref(false);
const newStepName = ref('');
const newStepCategory = ref('in_progress');
const newStepMoveIn = ref('both');
const newStepMoveOut = ref('both');
const editingStep = ref<string | null>(null);
const editStepName = ref('');
const editStepCategory = ref('in_progress');
const editStepMoveIn = ref('both');
const editStepMoveOut = ref('both');

function addStep() {
    if (newStepName.value.trim() === '') {
        return;
    }

    router.post(
        storeColumn(props.board.id).url,
        {
            name: newStepName.value.trim(),
            category: newStepCategory.value,
            move_in: newStepMoveIn.value,
            move_out: newStepMoveOut.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                addingStep.value = false;
                newStepName.value = '';
                newStepCategory.value = 'in_progress';
                newStepMoveIn.value = 'both';
                newStepMoveOut.value = 'both';
            },
        },
    );
}

function startEditStep(column: Column) {
    editingStep.value = column.id;
    editStepName.value = column.name;
    editStepCategory.value = column.category;
    editStepMoveIn.value = column.move_in;
    editStepMoveOut.value = column.move_out;
}

function saveStep(columnId: string) {
    if (editStepName.value.trim() === '') {
        return;
    }

    router.put(
        updateColumn(columnId).url,
        {
            name: editStepName.value.trim(),
            category: editStepCategory.value,
            move_in: editStepMoveIn.value,
            move_out: editStepMoveOut.value,
        },
        { preserveScroll: true, onSuccess: () => (editingStep.value = null) },
    );
}

function deleteStep(column: Column) {
    if (
        !window.confirm(t('tasks.confirm_delete_step', { name: column.name }))
    ) {
        return;
    }

    router.delete(destroyColumn(column.id).url, { preserveScroll: true });
}

function onColumnReorder() {
    router.post(
        reorderColumnsRoute(props.board.id).url,
        { columns: localColumns.value.map((c) => c.id) },
        { preserveScroll: true, preserveState: true, only: ['columns'] },
    );
}

// -- Task drawer -----------------------------------------------------------

const drawerOpen = computed(() => props.selectedTask !== null);

// The drawer's "move to step" dropdown only offers steps the viewer may move the
// card into (plus its current step, so the selection shows), and is disabled when
// the card cannot be pulled out of its current step at all.
const currentColumn = computed(() =>
    props.columns.find((c) => c.id === props.selectedTask?.column_id),
);
const canPullCurrent = computed(() => currentColumn.value?.can_pull ?? true);
const moveTargets = computed(() =>
    props.columns.filter(
        (c) => c.can_drop || c.id === props.selectedTask?.column_id,
    ),
);

// Comments and attachments woven into one chronological conversation — the active
// communication center, kept distinct from the immutable task definition.
type ChatItem = {
    kind: 'comment' | 'attachment';
    id: string;
    author: Chip | null;
    mine: boolean;
    visibility: string;
    created_at: string | null;
    body?: string;
    name?: string;
};
const conversation = computed<ChatItem[]>(() => {
    const task = props.selectedTask;

    if (!task) {
        return [];
    }

    const items: ChatItem[] = [
        ...task.comments.map((c): ChatItem => ({
            kind: 'comment',
            id: c.id,
            author: c.author,
            mine: c.mine,
            visibility: c.visibility,
            created_at: c.created_at,
            body: c.body,
        })),
        ...task.attachments.map((a): ChatItem => ({
            kind: 'attachment',
            id: a.id,
            author: a.uploaded_by,
            mine: a.mine,
            visibility: a.visibility,
            created_at: a.created_at,
            name: a.name,
        })),
    ];

    return items.sort((x, y) =>
        (x.created_at ?? '').localeCompare(y.created_at ?? ''),
    );
});

const canComment = computed(
    () =>
        props.selectedTask?.abilities.comment_internal ||
        props.selectedTask?.abilities.comment_customer,
);

function formatTime(iso: string | null): string {
    return iso ? new Date(iso).toLocaleString() : '';
}

// Visibility pill styling that stays legible on both bubble backgrounds: on the
// viewer's own (accent) bubble we tint with the bubble's foreground colour; on
// others' (surface) bubbles we use the theme secondary/outline. Internal stays
// filled, customer stays outlined, so the distinction survives either way.
function visibilityBadgeClass(mine: boolean, visibility: string): string {
    if (mine) {
        return visibility === 'internal'
            ? 'bg-primary-foreground/20 text-primary-foreground'
            : 'border border-primary-foreground/40 text-primary-foreground';
    }

    return visibility === 'internal'
        ? 'bg-secondary text-secondary-foreground'
        : 'border border-border text-foreground';
}

function openTask(id: string) {
    router.get(
        show(props.board.id).url,
        { task: id },
        { only: ['selectedTask'], preserveScroll: true, preserveState: true },
    );
}
function closeTask() {
    router.get(
        show(props.board.id).url,
        {},
        { only: ['selectedTask'], preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Head :title="board.name" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="board.name"
                :description="
                    board.customer
                        ? `${t('boards.project_prefix')} · ${board.customer.name}`
                        : t('boards.internal_board')
                "
            />
            <Button
                v-if="board.abilities.create_task"
                @click="addingTask = !addingTask"
            >
                <Plus class="size-4" /> {{ t('tasks.new_task') }}
            </Button>
        </div>

        <!-- New task -->
        <div
            v-if="board.abilities.create_task && addingTask"
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <Form
                v-bind="storeTask.form(board.id)"
                :reset-on-success="['title']"
                class="grid gap-4 md:grid-cols-4"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2 md:col-span-2">
                    <Label for="title">{{ t('tasks.title') }}</Label>
                    <Input id="title" name="title" required />
                    <InputError :message="errors.title" />
                </div>
                <div class="grid gap-2">
                    <Label for="priority">{{ t('tasks.priority') }}</Label>
                    <select
                        id="priority"
                        name="priority"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">—</option>
                        <option v-for="p in priorities" :key="p" :value="p">
                            {{ t('priority.' + p) }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="assignees">{{ t('tasks.assignees') }}</Label>
                    <select
                        id="assignees"
                        name="assignee_ids[]"
                        multiple
                        class="min-h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                    >
                        <option
                            v-for="u in assignableUsers"
                            :key="u.id"
                            :value="u.id"
                        >
                            {{ u.name }}
                        </option>
                    </select>
                </div>
                <div class="flex items-end md:col-span-4">
                    <Button type="submit" :disabled="processing">{{
                        t('tasks.add_task')
                    }}</Button>
                </div>
            </Form>
        </div>

        <!-- Kanban -->
        <draggable
            v-model="localColumns"
            item-key="id"
            :group="{ name: 'columns' }"
            :disabled="!canManageColumns"
            handle=".step-drag"
            class="grid items-start gap-4 md:grid-cols-2 xl:grid-cols-4"
            @change="onColumnReorder"
        >
            <template #item="{ element: column }">
                <div
                    class="flex flex-col gap-3 rounded-xl border-t-4 bg-muted/30 p-3"
                    :style="{
                        borderTopColor:
                            categoryColor[column.category] ?? 'var(--dv-aqua)',
                    }"
                >
                    <!-- Column header -->
                    <div
                        v-if="editingStep === column.id"
                        class="flex flex-col gap-2"
                    >
                        <Input
                            v-model="editStepName"
                            class="h-8"
                            @keyup.enter="saveStep(column.id)"
                        />
                        <select
                            v-model="editStepCategory"
                            class="h-8 rounded-md border border-input bg-transparent px-2 text-xs"
                        >
                            <option
                                v-for="c in categories"
                                :key="c.value"
                                :value="c.value"
                            >
                                {{ c.label }}
                            </option>
                        </select>
                        <label class="text-[11px] text-muted-foreground">{{
                            t('boards.move_in')
                        }}</label>
                        <select
                            v-model="editStepMoveIn"
                            class="h-8 rounded-md border border-input bg-transparent px-2 text-xs"
                        >
                            <option
                                v-for="a in accessOptions"
                                :key="a.value"
                                :value="a.value"
                            >
                                {{ t('access.' + a.value) }}
                            </option>
                        </select>
                        <label class="text-[11px] text-muted-foreground">{{
                            t('boards.move_out')
                        }}</label>
                        <select
                            v-model="editStepMoveOut"
                            class="h-8 rounded-md border border-input bg-transparent px-2 text-xs"
                        >
                            <option
                                v-for="a in accessOptions"
                                :key="a.value"
                                :value="a.value"
                            >
                                {{ t('access.' + a.value) }}
                            </option>
                        </select>
                        <div class="flex gap-1">
                            <Button size="sm" @click="saveStep(column.id)">{{
                                t('common.save')
                            }}</Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                @click="editingStep = null"
                                >{{ t('common.cancel') }}</Button
                            >
                            <Button
                                size="sm"
                                variant="ghost"
                                class="ml-auto text-destructive"
                                @click="deleteStep(column)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                    <div v-else class="flex items-center justify-between px-1">
                        <div class="flex items-center gap-1">
                            <GripVertical
                                v-if="canManageColumns"
                                class="step-drag size-4 cursor-grab text-muted-foreground"
                            />
                            <span class="text-sm font-medium">{{
                                column.name
                            }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <Badge variant="secondary">{{
                                column.tasks.length
                            }}</Badge>
                            <button
                                v-if="canManageColumns"
                                type="button"
                                class="text-muted-foreground hover:text-foreground"
                                :aria-label="t('tasks.edit_step')"
                                @click="startEditStep(column)"
                            >
                                <Pencil class="size-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- Tasks (draggable). Per-column authority: cards can only be
                         pulled out of a column the viewer may move out of, and
                         dropped into a column the viewer may move into. -->
                    <draggable
                        :list="column.tasks"
                        item-key="id"
                        :group="{
                            name: 'tasks',
                            pull: column.can_pull,
                            put: column.can_drop,
                        }"
                        class="flex min-h-2 flex-col gap-2"
                        @change="onTaskChange(column, $event)"
                    >
                        <template #item="{ element: task }">
                            <button
                                type="button"
                                class="flex w-full cursor-grab flex-col gap-2 rounded-lg border border-sidebar-border/70 bg-background p-3 text-left transition hover:border-primary/50 dark:border-sidebar-border"
                                @click="openTask(task.id)"
                            >
                                <span class="text-sm font-medium">{{
                                    task.title
                                }}</span>
                                <div class="flex flex-wrap items-center gap-1">
                                    <Badge
                                        v-if="task.priority"
                                        variant="outline"
                                    >
                                        {{ t('priority.' + task.priority) }}
                                    </Badge>
                                    <Badge
                                        v-for="a in task.assignees"
                                        :key="a.id"
                                        variant="secondary"
                                        class="font-normal"
                                    >
                                        {{ a.name }}
                                    </Badge>
                                </div>
                            </button>
                        </template>
                    </draggable>

                    <p
                        v-if="!column.tasks.length"
                        class="px-1 py-4 text-center text-xs text-muted-foreground"
                    >
                        {{ t('tasks.nothing_here') }}
                    </p>
                </div>
            </template>

            <!-- Add step -->
            <template v-if="canManageColumns" #footer>
                <div
                    class="flex flex-col gap-2 rounded-xl border border-dashed border-sidebar-border/70 p-3 dark:border-sidebar-border"
                >
                    <div v-if="addingStep" class="flex flex-col gap-2">
                        <Input
                            v-model="newStepName"
                            :placeholder="t('tasks.step_name')"
                            class="h-8"
                            @keyup.enter="addStep"
                        />
                        <select
                            v-model="newStepCategory"
                            class="h-8 rounded-md border border-input bg-transparent px-2 text-xs"
                        >
                            <option
                                v-for="c in categories"
                                :key="c.value"
                                :value="c.value"
                            >
                                {{ c.label }}
                            </option>
                        </select>
                        <label class="text-[11px] text-muted-foreground">{{
                            t('boards.move_in')
                        }}</label>
                        <select
                            v-model="newStepMoveIn"
                            class="h-8 rounded-md border border-input bg-transparent px-2 text-xs"
                        >
                            <option
                                v-for="a in accessOptions"
                                :key="a.value"
                                :value="a.value"
                            >
                                {{ t('access.' + a.value) }}
                            </option>
                        </select>
                        <label class="text-[11px] text-muted-foreground">{{
                            t('boards.move_out')
                        }}</label>
                        <select
                            v-model="newStepMoveOut"
                            class="h-8 rounded-md border border-input bg-transparent px-2 text-xs"
                        >
                            <option
                                v-for="a in accessOptions"
                                :key="a.value"
                                :value="a.value"
                            >
                                {{ t('access.' + a.value) }}
                            </option>
                        </select>
                        <div class="flex gap-1">
                            <Button size="sm" @click="addStep">{{
                                t('common.add')
                            }}</Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                @click="addingStep = false"
                                >{{ t('common.cancel') }}</Button
                            >
                        </div>
                    </div>
                    <Button
                        v-else
                        variant="ghost"
                        class="justify-start text-muted-foreground"
                        @click="addingStep = true"
                    >
                        <Plus class="size-4" /> {{ t('tasks.add_step') }}
                    </Button>
                </div>
            </template>
        </draggable>

        <!-- Members -->
        <section class="flex flex-col gap-3">
            <h2 class="text-sm font-medium">{{ t('tasks.members') }}</h2>
            <div class="flex flex-wrap gap-2">
                <Badge v-for="m in board.members" :key="m.id" variant="outline">
                    {{ m.name
                    }}<span v-if="m.role" class="ml-1 opacity-60"
                        >· {{ m.role }}</span
                    >
                </Badge>
            </div>
            <Form
                v-if="board.abilities.manage_members"
                v-bind="addMember.form(board.id)"
                class="flex flex-wrap items-end gap-2"
                v-slot="{ errors }"
            >
                <div class="grid gap-1">
                    <select
                        name="user_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">{{ t('tasks.add_member') }}</option>
                        <option
                            v-for="u in memberCandidates"
                            :key="u.id"
                            :value="u.id"
                        >
                            {{ u.name }}
                        </option>
                    </select>
                    <InputError :message="errors.user_id" />
                </div>
                <Button type="submit" size="sm" variant="secondary">{{
                    t('common.add')
                }}</Button>
            </Form>
        </section>
    </div>

    <!-- Task drawer -->
    <div
        v-if="drawerOpen && selectedTask"
        class="fixed inset-0 z-50 flex justify-end bg-black/40"
        @click.self="closeTask"
    >
        <div
            class="flex h-full w-full max-w-xl flex-col overflow-y-auto bg-background p-6 shadow-xl"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">
                        {{ selectedTask.title }}
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        {{ t('status.' + selectedTask.status) }}
                    </p>
                </div>
                <Button variant="ghost" size="icon" @click="closeTask">
                    <X class="size-4" />
                </Button>
            </div>

            <!-- Immutable task definition (the agreed contract) -->
            <div
                class="mt-4 rounded-lg border border-sidebar-border/70 bg-muted/40 p-3 dark:border-sidebar-border"
            >
                <div
                    class="mb-1 flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                >
                    <Lock class="size-3" /> {{ t('tasks.definition') }}
                </div>
                <p class="text-sm whitespace-pre-line">
                    {{ selectedTask.description || t('tasks.no_description') }}
                </p>
            </div>

            <!-- Move to step -->
            <div
                v-if="selectedTask.abilities.move"
                class="mt-6 flex flex-col gap-2"
            >
                <Label for="move_step">{{ t('tasks.move_to_step') }}</Label>
                <select
                    id="move_step"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm disabled:opacity-60"
                    :value="selectedTask.column_id ?? ''"
                    :disabled="!canPullCurrent"
                    @change="
                        moveToStep(
                            selectedTask.id,
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option v-for="c in moveTargets" :key="c.id" :value="c.id">
                        {{ c.name }}
                    </option>
                </select>
                <p v-if="!canPullCurrent" class="text-xs text-muted-foreground">
                    {{ t('tasks.move_locked') }}
                </p>
            </div>

            <!-- Conversation center (the active communication hub) -->
            <div class="mt-6 flex flex-1 flex-col gap-3">
                <div class="flex items-center gap-2">
                    <MessageCircle class="size-4 text-primary" />
                    <h3 class="text-sm font-semibold">
                        {{ t('tasks.conversation') }}
                    </h3>
                </div>

                <div
                    class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-muted/20 p-3 dark:border-sidebar-border"
                >
                    <div
                        v-for="item in conversation"
                        :key="item.kind + item.id"
                        class="flex flex-col"
                        :class="item.mine ? 'items-end' : 'items-start'"
                    >
                        <div
                            class="flex max-w-[85%] flex-col gap-1 rounded-2xl px-3 py-2 text-sm shadow-sm"
                            :class="
                                item.mine
                                    ? 'rounded-br-sm bg-primary text-primary-foreground'
                                    : 'rounded-bl-sm border border-sidebar-border/70 bg-background dark:border-sidebar-border'
                            "
                        >
                            <div
                                class="flex items-center gap-2 text-xs"
                                :class="
                                    item.mine
                                        ? 'text-primary-foreground/80'
                                        : 'text-muted-foreground'
                                "
                            >
                                <span class="font-medium">{{
                                    item.mine
                                        ? t('tasks.you')
                                        : (item.author?.name ??
                                          t('tasks.unknown'))
                                }}</span>
                                <span
                                    class="rounded-full px-1.5 py-[1px] text-[10px] font-medium"
                                    :class="
                                        visibilityBadgeClass(
                                            item.mine,
                                            item.visibility,
                                        )
                                    "
                                    >{{
                                        t('visibility.' + item.visibility)
                                    }}</span
                                >
                            </div>

                            <p
                                v-if="item.kind === 'comment'"
                                class="whitespace-pre-line"
                            >
                                {{ item.body }}
                            </p>
                            <a
                                v-else
                                :href="download(item.id).url"
                                class="inline-flex items-center gap-1.5 font-medium underline underline-offset-2"
                            >
                                <Paperclip class="size-3.5" /> {{ item.name }}
                            </a>

                            <span
                                class="text-[10px]"
                                :class="
                                    item.mine
                                        ? 'text-primary-foreground/70'
                                        : 'text-muted-foreground'
                                "
                                >{{ formatTime(item.created_at) }}</span
                            >
                        </div>
                    </div>

                    <p
                        v-if="!conversation.length"
                        class="py-6 text-center text-xs text-muted-foreground"
                    >
                        {{ t('tasks.conversation_empty') }}
                    </p>
                </div>

                <!-- Composer -->
                <div
                    v-if="canComment"
                    class="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                >
                    <Form
                        v-bind="storeComment.form(selectedTask.id)"
                        :reset-on-success="['body']"
                        class="flex flex-col gap-2"
                        v-slot="{ processing }"
                    >
                        <textarea
                            name="body"
                            rows="2"
                            required
                            class="rounded-md border border-input bg-transparent p-2 text-sm"
                            :placeholder="t('tasks.add_comment_placeholder')"
                        ></textarea>
                        <div class="flex items-center gap-2">
                            <select
                                v-if="board.type === 'project'"
                                name="visibility"
                                class="h-8 rounded-md border border-input bg-transparent px-2 text-xs"
                            >
                                <option
                                    v-if="
                                        selectedTask.abilities.comment_internal
                                    "
                                    value="internal"
                                >
                                    {{ t('visibility.internal') }}
                                </option>
                                <option
                                    v-if="
                                        selectedTask.abilities.comment_customer
                                    "
                                    value="customer"
                                >
                                    {{ t('visibility.customer') }}
                                </option>
                            </select>
                            <Button
                                type="submit"
                                size="sm"
                                class="ml-auto"
                                :disabled="processing"
                            >
                                <SendHorizontal class="size-4" />
                                {{ t('tasks.comment') }}
                            </Button>
                        </div>
                    </Form>

                    <Form
                        v-bind="storeAttachment.form(selectedTask.id)"
                        class="flex flex-wrap items-center gap-2 border-t border-sidebar-border/50 pt-2"
                        v-slot="{ processing }"
                    >
                        <Paperclip class="size-3.5 text-muted-foreground" />
                        <input
                            type="file"
                            name="file"
                            required
                            class="min-w-0 flex-1 text-xs"
                        />
                        <select
                            v-if="board.type === 'project'"
                            name="visibility"
                            class="h-8 rounded-md border border-input bg-transparent px-2 text-xs"
                        >
                            <option
                                v-if="selectedTask.abilities.comment_internal"
                                value="internal"
                            >
                                {{ t('visibility.internal') }}
                            </option>
                            <option
                                v-if="selectedTask.abilities.comment_customer"
                                value="customer"
                            >
                                {{ t('visibility.customer') }}
                            </option>
                        </select>
                        <Button
                            type="submit"
                            size="sm"
                            variant="secondary"
                            :disabled="processing"
                            >{{ t('tasks.upload') }}</Button
                        >
                    </Form>
                </div>
            </div>

            <!-- History (de-emphasised) -->
            <details v-if="selectedTask.history.length" class="mt-6">
                <summary
                    class="cursor-pointer text-xs font-medium text-muted-foreground"
                >
                    {{ t('tasks.history') }}
                </summary>
                <div class="mt-2 flex flex-col gap-1">
                    <div
                        v-for="h in selectedTask.history"
                        :key="h.id"
                        class="text-xs text-muted-foreground"
                    >
                        <span class="font-medium text-foreground">{{
                            h.actor?.name ?? t('tasks.system')
                        }}</span>
                        {{
                            h.from
                                ? `${t('status.' + h.from)} → ${t('status.' + h.to)}`
                                : t('status.' + h.to)
                        }}
                        <span v-if="h.reason">· "{{ h.reason }}"</span>
                    </div>
                </div>
            </details>
        </div>
    </div>
</template>
