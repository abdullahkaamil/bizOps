<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Check, Copy } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import DepartmentController from '@/actions/App/Http/Controllers/Tenant/DepartmentController';
import UserController from '@/actions/App/Http/Controllers/Tenant/UserController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuthorization } from '@/composables/useAuthorization';
import { t as $t } from '@/i18n';
import { roleLabel } from '@/lib/roleLabel';

type Member = {
    id: number;
    name: string;
    email: string;
    user_type: string;
    status: string;
    roles: string[];
    department: string | null;
    customer: string | null;
};
type Option = { id: string; name: string };
type Invite = {
    id: number;
    email: string;
    user_type: string;
    role: string;
    expired: boolean;
};
type InviteResult = {
    mode: 'link' | 'auto_accepted';
    email: string;
    link?: string;
    name?: string;
    password?: string;
};

type RoleOption = { name: string; user_type: string };

const props = defineProps<{
    internalUsers: Member[];
    externalUsers: Member[];
    roles: RoleOption[];
    departments: Option[];
    customers: Option[];
    invitations: Invite[];
    autoAcceptInvitations: boolean;
    inviteResult: InviteResult | null;
}>();

defineOptions({ layout: { breadcrumbs: [{ title: $t('nav.team'), href: '/users' }] } });

const { can } = useAuthorization();
const { t } = useI18n();
const inviteType = ref<'internal' | 'external'>('internal');
const copied = ref(false);

// Role dropdowns are scoped to the relevant user type: external reps only ever
// get the external role(s); internal users get every internal (system or custom)
// role. Server-side validation enforces the same rule.
const internalRoles = computed(() => props.roles.filter((r) => r.user_type === 'internal'));
const externalRoles = computed(() => props.roles.filter((r) => r.user_type === 'external'));
const inviteRoles = computed(() => (inviteType.value === 'external' ? externalRoles.value : internalRoles.value));

async function copyText(text: string) {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard unavailable (e.g. insecure context) — the value is still
        // visible for the user to select and copy manually.
    }
}

function changeRole(member: Member, event: Event) {
    router.patch(
        UserController.updateRole(member.id).url,
        { role: (event.target as HTMLSelectElement).value },
        { preserveScroll: true },
    );
}
function changeDepartment(member: Member, event: Event) {
    router.post(
        UserController.assignDepartment(member.id).url,
        { department_id: (event.target as HTMLSelectElement).value || null },
        { preserveScroll: true },
    );
}
function toggleSuspend(member: Member) {
    const url =
        member.status === 'suspended'
            ? UserController.reactivate(member.id).url
            : UserController.suspend(member.id).url;
    router.post(url, {}, { preserveScroll: true });
}
function resend(invite: Invite) {
    router.post(
        UserController.resendInvitation(invite.id).url,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('nav.team')" />

    <div class="flex flex-col gap-8 p-4">
        <Heading
            variant="small"
            :title="t('nav.team')"
            :description="t('users.team_desc')"
        />

        <!-- Invite result: copyable link or temporary credentials -->
        <div
            v-if="inviteResult"
            class="flex flex-col gap-3 rounded-xl border border-primary/40 bg-primary/5 p-4"
        >
            <h2 class="text-sm font-medium">
                {{ inviteResult.mode === 'auto_accepted' ? t('users.auto_accepted_title') : t('users.invite_link_title') }}
            </h2>
            <p class="text-xs text-muted-foreground">
                {{ inviteResult.mode === 'auto_accepted' ? t('users.auto_accepted_hint') : t('users.invite_link_hint') }}
            </p>

            <div v-if="inviteResult.mode === 'link'" class="flex flex-wrap items-center gap-2">
                <input
                    :value="inviteResult.link"
                    readonly
                    class="h-9 min-w-0 flex-1 rounded-md border border-input bg-background px-3 font-mono text-xs"
                    @focus="($event.target as HTMLInputElement).select()"
                />
                <Button size="sm" variant="secondary" @click="copyText(inviteResult.link!)">
                    <component :is="copied ? Check : Copy" class="size-4" />
                    {{ copied ? t('users.copied') : t('users.copy') }}
                </Button>
            </div>

            <div v-else class="flex flex-col gap-2 text-sm">
                <div>
                    <span class="text-muted-foreground">{{ t('users.email') }}:</span>
                    {{ inviteResult.email }}
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-muted-foreground">{{ t('users.temp_password') }}:</span>
                    <code class="rounded bg-muted px-2 py-0.5 font-mono">{{ inviteResult.password }}</code>
                    <Button size="sm" variant="secondary" @click="copyText(inviteResult.password!)">
                        <component :is="copied ? Check : Copy" class="size-4" />
                        {{ copied ? t('users.copied') : t('users.copy') }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Invite -->
        <div
            v-if="can('users.create')"
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <h2 class="text-sm font-medium">{{ t("users.invite_user") }}</h2>
            <p class="mb-4 mt-1 text-xs text-muted-foreground">
                {{ autoAcceptInvitations ? t('users.mode_auto_accept') : t('users.mode_link') }}
            </p>
            <Form
                v-bind="UserController.invite.form()"
                :reset-on-success="['email']"
                class="grid gap-4 md:grid-cols-3"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="email">{{ t("users.email") }}</Label>
                    <Input id="email" name="email" type="email" required />
                    <InputError :message="errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="user_type">{{ t("users.type") }}</Label>
                    <select
                        id="user_type"
                        name="user_type"
                        v-model="inviteType"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="internal">{{ t("users.type_internal") }}</option>
                        <option value="external">{{ t("users.type_external") }}</option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="role">{{ t("users.role") }}</Label>
                    <select
                        id="role"
                        name="role"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option v-for="r in inviteRoles" :key="r.name" :value="r.name">{{ roleLabel(r.name) }}</option>
                    </select>
                    <InputError :message="errors.role" />
                </div>
                <div v-if="inviteType === 'external'" class="grid gap-2">
                    <Label for="customer_id">{{ t("users.customer") }}</Label>
                    <select
                        id="customer_id"
                        name="customer_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">{{ t("users.select_customer") }}</option>
                        <option
                            v-for="c in customers"
                            :key="c.id"
                            :value="c.id"
                        >
                            {{ c.name }}
                        </option>
                    </select>
                    <InputError :message="errors.customer_id" />
                </div>
                <div v-else class="grid gap-2">
                    <Label for="department_id">{{ t("users.department") }}</Label>
                    <select
                        id="department_id"
                        name="department_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">{{ t("users.none") }}</option>
                        <option
                            v-for="d in departments"
                            :key="d.id"
                            :value="d.id"
                        >
                            {{ d.name }}
                        </option>
                    </select>
                </div>
                <div class="flex items-end">
                    <Button type="submit" :disabled="processing"
                        >{{ t("users.send_invitation") }}</Button>
                </div>
            </Form>
        </div>

        <!-- Pending invitations -->
        <div v-if="invitations.length" class="flex flex-col gap-2">
            <h2 class="text-sm font-medium">{{ t("users.pending_invitations") }}</h2>
            <div
                v-for="invite in invitations"
                :key="invite.id"
                class="flex items-center justify-between rounded-lg border p-3 text-sm"
            >
                <span
                    >{{ invite.email }} · {{ roleLabel(invite.role) }}
                    <Badge v-if="invite.expired" variant="destructive"
                        >{{ t("users.expired") }}</Badge
                    ></span
                >
                <Button
                    v-if="can('users.create')"
                    variant="ghost"
                    size="sm"
                    @click="resend(invite)"
                    >{{ t("users.copy_link") }}</Button>
            </div>
        </div>

        <!-- Internal users -->
        <section class="flex flex-col gap-3">
            <h2 class="text-sm font-medium">{{ t("users.internal_employees") }}</h2>
            <div
                class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t("users.col_name") }}</th>
                            <th class="px-4 py-3 font-medium">{{ t("users.col_role") }}</th>
                            <th class="px-4 py-3 font-medium">{{ t("users.col_department") }}</th>
                            <th class="px-4 py-3 font-medium">{{ t("users.col_status") }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ t("users.col_actions") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in internalUsers"
                            :key="user.id"
                            class="border-b border-sidebar-border/40 last:border-0 dark:border-sidebar-border/40"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ user.name }}
                                <div class="text-xs text-muted-foreground">
                                    {{ user.email }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <select
                                    v-if="can('roles.manage')"
                                    class="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                                    :value="user.roles[0] ?? ''"
                                    @change="changeRole(user, $event)"
                                >
                                    <option
                                        v-for="r in internalRoles"
                                        :key="r.name"
                                        :value="r.name"
                                    >
                                        {{ roleLabel(r.name) }}
                                    </option>
                                </select>
                                <span v-else>{{ user.roles[0] ? roleLabel(user.roles[0]) : '—' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <select
                                    v-if="can('users.update')"
                                    class="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                                    :value="
                                        departments.find(
                                            (d) => d.name === user.department,
                                        )?.id ?? ''
                                    "
                                    @change="changeDepartment(user, $event)"
                                >
                                    <option value="">{{ t("users.none") }}</option>
                                    <option
                                        v-for="d in departments"
                                        :key="d.id"
                                        :value="d.id"
                                    >
                                        {{ d.name }}
                                    </option>
                                </select>
                                <span v-else>{{ user.department ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge :status="user.status" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    v-if="can('users.suspend')"
                                    variant="ghost"
                                    size="sm"
                                    @click="toggleSuspend(user)"
                                >
                                    {{
                                        user.status === "suspended"
                                            ? t("users.reactivate")
                                            : t("users.suspend")
                                    }}
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- External users -->
        <section v-if="externalUsers.length" class="flex flex-col gap-3">
            <h2 class="text-sm font-medium">{{ t("users.external_representatives") }}</h2>
            <div
                class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t("users.col_name") }}</th>
                            <th class="px-4 py-3 font-medium">{{ t("users.col_customer") }}</th>
                            <th class="px-4 py-3 font-medium">{{ t("users.col_status") }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ t("users.col_actions") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in externalUsers"
                            :key="user.id"
                            class="border-b border-sidebar-border/40 last:border-0 dark:border-sidebar-border/40"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ user.name }}
                                <div class="text-xs text-muted-foreground">
                                    {{ user.email }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ user.customer ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge :status="user.status" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    v-if="can('users.suspend')"
                                    variant="ghost"
                                    size="sm"
                                    @click="toggleSuspend(user)"
                                >
                                    {{
                                        user.status === "suspended"
                                            ? t("users.reactivate")
                                            : t("users.suspend")
                                    }}
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Departments -->
        <section v-if="can('users.create')" class="flex flex-col gap-3">
            <h2 class="text-sm font-medium">{{ t("users.departments") }}</h2>
            <div class="flex flex-wrap gap-2">
                <Badge v-for="d in departments" :key="d.id" variant="outline">{{
                    d.name
                }}</Badge>
            </div>
            <Form
                v-bind="DepartmentController.store.form()"
                :reset-on-success="['name', 'code']"
                class="flex flex-wrap items-end gap-2"
            >
                <Input
                    name="name"
                    :placeholder="t('users.department_name')"
                    required
                    class="max-w-xs"
                />
                <Input name="code" :placeholder="t('users.code')" class="w-28" />
                <Button type="submit" size="sm">{{ t("users.add_department") }}</Button>
            </Form>
        </section>
    </div>
</template>
