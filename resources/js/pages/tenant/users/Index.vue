<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Users } from '@lucide/vue';
import UserController from '@/actions/App/Http/Controllers/Tenant/UserController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';

type TeamMember = {
    id: number;
    name: string;
    email: string;
    roles: string[];
};

defineProps<{
    users: TeamMember[];
    roles: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Team', href: '/users' }],
    },
});

const { can } = usePermissions();

function changeRole(user: TeamMember, event: Event) {
    const role = (event.target as HTMLSelectElement).value;
    router.patch(
        UserController.updateRole(user.id).url,
        { role },
        { preserveScroll: true },
    );
}

function removeUser(user: TeamMember) {
    if (!window.confirm(`Remove ${user.name} from the team?`)) {
        return;
    }

    router.delete(UserController.destroy(user.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Team" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            variant="small"
            title="Team"
            description="Manage the people in this workspace and their roles."
        />

        <div
            v-if="can('users.create')"
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <h2 class="mb-4 text-sm font-medium">Add a team member</h2>
            <Form
                v-bind="UserController.store.form()"
                class="grid gap-4 md:grid-cols-2"
                :reset-on-success="['name', 'email', 'password']"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        placeholder="Jane Doe"
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        required
                        placeholder="jane@example.com"
                        autocomplete="off"
                    />
                    <InputError :message="errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="password">Temporary password</Label>
                    <Input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="new-password"
                    />
                    <InputError :message="errors.password" />
                </div>
                <div class="grid gap-2">
                    <Label for="role">Role</Label>
                    <select
                        id="role"
                        name="role"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                    >
                        <option v-for="role in roles" :key="role" :value="role">
                            {{ role }}
                        </option>
                    </select>
                    <InputError :message="errors.role" />
                </div>
                <div class="md:col-span-2">
                    <Button type="submit" :disabled="processing"
                        >Add member</Button
                    >
                </div>
            </Form>
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Role</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in users"
                        :key="user.id"
                        class="border-b border-sidebar-border/40 last:border-0 dark:border-sidebar-border/40"
                    >
                        <td class="px-4 py-3 font-medium">
                            <span class="flex items-center gap-2">
                                <Users class="size-4 text-muted-foreground" />
                                {{ user.name }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ user.email }}
                        </td>
                        <td class="px-4 py-3">
                            <select
                                v-if="can('roles.manage')"
                                class="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                                :value="user.roles[0] ?? ''"
                                @change="changeRole(user, $event)"
                            >
                                <option
                                    v-for="role in roles"
                                    :key="role"
                                    :value="role"
                                >
                                    {{ role }}
                                </option>
                            </select>
                            <span v-else class="flex gap-1">
                                <Badge
                                    v-for="role in user.roles"
                                    :key="role"
                                    variant="secondary"
                                    >{{ role }}</Badge
                                >
                                <span
                                    v-if="user.roles.length === 0"
                                    class="text-muted-foreground"
                                    >—</span
                                >
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button
                                v-if="can('users.delete')"
                                variant="destructive"
                                size="sm"
                                @click="removeUser(user)"
                            >
                                Remove
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
