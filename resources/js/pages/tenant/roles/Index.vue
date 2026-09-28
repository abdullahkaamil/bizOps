<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, X } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t as $t } from '@/i18n';
import { roleLabel } from '@/lib/roleLabel';
import { destroy as destroyRole, store as storeRole, update as updateRole } from '@/routes/tenant/roles';

type Role = {
    name: string;
    is_system: boolean;
    user_type: string;
    permissions: string[];
    users_count: number;
};
type PermissionGroup = { group: string; permissions: { value: string; action: string }[] };

defineProps<{
    roles: Role[];
    permissionGroups: PermissionGroup[];
}>();

defineOptions({ layout: { breadcrumbs: [{ title: $t('nav.roles'), href: '/roles' }] } });

const { t, te } = useI18n();

const creating = ref(false);
const newName = ref('');
const newPerms = ref<string[]>([]);
const editing = ref<string | null>(null);
const editPerms = ref<string[]>([]);

function groupLabel(group: string): string {
    const key = `permissions.${group}`;

    return te(key) ? t(key) : group;
}
function permLabel(value: string): string {
    // Permission values are `resource.action`; vue-i18n treats dots as path
    // separators, so build the nested key from the split parts.
    const dot = value.indexOf('.');
    const key = `permission_labels.${value.slice(0, dot)}.${value.slice(dot + 1)}`;

    return te(key) ? t(key) : value;
}

function resetCreate() {
    creating.value = false;
    newName.value = '';
    newPerms.value = [];
}
function submitCreate() {
    if (newName.value.trim() === '') {
        return;
    }

    router.post(
        storeRole().url,
        { name: newName.value.trim(), permissions: newPerms.value },
        { preserveScroll: true, onSuccess: resetCreate },
    );
}

function startEdit(role: Role) {
    editing.value = role.name;
    editPerms.value = [...role.permissions];
    creating.value = false;
}
function saveEdit(name: string) {
    router.put(
        updateRole(name).url,
        { permissions: editPerms.value },
        { preserveScroll: true, onSuccess: () => (editing.value = null) },
    );
}
function remove(role: Role) {
    if (!window.confirm(t('roles_admin.delete_confirm', { name: roleLabel(role.name) }))) {
        return;
    }

    router.delete(destroyRole(role.name).url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('nav.roles')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading variant="small" :title="t('nav.roles')" :description="t('roles_admin.desc')" />
            <Button v-if="!creating" @click="creating = true">
                <Plus class="size-4" /> {{ t('roles_admin.create') }}
            </Button>
        </div>

        <!-- Create role -->
        <div
            v-if="creating"
            class="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-medium">{{ t('roles_admin.create') }}</h2>
                <Button variant="ghost" size="icon" @click="resetCreate"><X class="size-4" /></Button>
            </div>
            <div class="grid max-w-sm gap-2">
                <Label for="role_name">{{ t('roles_admin.name') }}</Label>
                <Input id="role_name" v-model="newName" :placeholder="t('roles_admin.name_placeholder')" />
            </div>

            <div class="flex flex-col gap-4">
                <Label>{{ t('roles_admin.permissions') }}</Label>
                <div
                    v-for="grp in permissionGroups"
                    :key="grp.group"
                    class="flex flex-col gap-2"
                >
                    <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ groupLabel(grp.group) }}</span>
                    <div class="flex flex-wrap gap-x-4 gap-y-1">
                        <label
                            v-for="p in grp.permissions"
                            :key="p.value"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input type="checkbox" :value="p.value" v-model="newPerms" class="size-4 rounded border-input" />
                            {{ permLabel(p.value) }}
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex gap-2">
                <Button :disabled="newName.trim() === ''" @click="submitCreate">{{ t('roles_admin.create_role') }}</Button>
                <Button variant="ghost" @click="resetCreate">{{ t('common.cancel') }}</Button>
            </div>
        </div>

        <!-- Roles list -->
        <div class="flex flex-col gap-3">
            <div
                v-for="role in roles"
                :key="role.name"
                class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="font-medium">{{ roleLabel(role.name) }}</span>
                        <Badge :variant="role.is_system ? 'secondary' : 'outline'">
                            {{ role.is_system ? t('roles_admin.system_badge') : t('roles_admin.custom_badge') }}
                        </Badge>
                        <Badge variant="outline">{{ t('roles_admin.users_count', { count: role.users_count }) }}</Badge>
                    </div>
                    <div v-if="!role.is_system" class="flex items-center gap-1">
                        <Button variant="ghost" size="sm" @click="startEdit(role)">
                            <Pencil class="size-3.5" /> {{ t('common.edit') }}
                        </Button>
                        <Button variant="ghost" size="sm" class="text-destructive" @click="remove(role)">
                            <Trash2 class="size-3.5" /> {{ t('common.delete') }}
                        </Button>
                    </div>
                    <Badge v-else variant="secondary" class="opacity-70">{{ t('roles_admin.locked') }}</Badge>
                </div>

                <!-- Edit permissions -->
                <div v-if="editing === role.name" class="flex flex-col gap-4 border-t pt-3">
                    <div
                        v-for="grp in permissionGroups"
                        :key="grp.group"
                        class="flex flex-col gap-2"
                    >
                        <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ groupLabel(grp.group) }}</span>
                        <div class="flex flex-wrap gap-x-4 gap-y-1">
                            <label
                                v-for="p in grp.permissions"
                                :key="p.value"
                                class="flex items-center gap-2 text-sm"
                            >
                                <input type="checkbox" :value="p.value" v-model="editPerms" class="size-4 rounded border-input" />
                                {{ permLabel(p.value) }}
                            </label>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button size="sm" @click="saveEdit(role.name)">{{ t('common.save') }}</Button>
                        <Button size="sm" variant="ghost" @click="editing = null">{{ t('common.cancel') }}</Button>
                    </div>
                </div>

                <!-- Permission summary -->
                <div v-else class="flex flex-wrap gap-1">
                    <Badge v-for="p in role.permissions" :key="p" variant="secondary" class="font-normal">
                        {{ permLabel(p) }}
                    </Badge>
                    <span v-if="!role.permissions.length" class="text-xs text-muted-foreground">
                        {{ t('roles_admin.no_permissions') }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
