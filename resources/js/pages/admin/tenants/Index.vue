<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';
import { reactive } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, destroy, renew } from '@/routes/admin/tenants';

type TenantRow = {
    id: string;
    name: string;
    subdomain: string | null;
    url: string | null;
    created_at: string | null;
    license_expires_at: string | null;
    license_active: boolean;
    license_days_remaining: number | null;
};

const props = defineProps<{
    tenants: TenantRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Tenants', href: '/tenants' }],
    },
});

// Per-row renewal date inputs, seeded with each tenant's current expiry date.
const renewalDates = reactive<Record<string, string>>(
    Object.fromEntries(
        props.tenants.map((t) => [
            t.id,
            t.license_expires_at?.slice(0, 10) ?? '',
        ]),
    ),
);

function deleteTenant(tenant: TenantRow) {
    if (
        !window.confirm(
            `Delete "${tenant.name}"? This permanently drops its database.`,
        )
    ) {
        return;
    }

    router.delete(destroy(tenant.id).url, { preserveScroll: true });
}

function renewTenant(tenant: TenantRow) {
    router.post(
        renew(tenant.id).url,
        { license_expires_at: renewalDates[tenant.id] },
        { preserveScroll: true },
    );
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString() : '—';
}

function licenseLabel(tenant: TenantRow): string {
    if (tenant.license_days_remaining === null) {
        return 'No license';
    }

    if (!tenant.license_active) {
        return 'Expired';
    }

    return `${tenant.license_days_remaining} day${tenant.license_days_remaining === 1 ? '' : 's'} left`;
}
</script>

<template>
    <Head title="Tenants" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Tenants"
                description="Provision organizations, manage their subdomain and license."
            />
            <Button as-child>
                <a :href="create().url">Create tenant</a>
            </Button>
        </div>

        <div
            v-if="tenants.length === 0"
            class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed p-12 text-center"
        >
            <Building2 class="size-8 text-muted-foreground" />
            <p class="text-sm text-muted-foreground">
                No tenants yet. Create your first one to get started.
            </p>
            <Button as-child variant="outline">
                <a :href="create().url">Create tenant</a>
            </Button>
        </div>

        <div
            v-else
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Domain</th>
                        <th class="px-4 py-3 font-medium">License</th>
                        <th class="px-4 py-3 font-medium">Renew until</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="tenant in tenants"
                        :key="tenant.id"
                        class="border-b border-sidebar-border/40 last:border-0 dark:border-sidebar-border/40"
                    >
                        <td class="px-4 py-3 font-medium">{{ tenant.name }}</td>
                        <td class="px-4 py-3">
                            <a
                                v-if="tenant.url"
                                :href="tenant.url"
                                target="_blank"
                                rel="noopener"
                                class="font-mono text-primary underline-offset-4 hover:underline"
                            >
                                {{ tenant.subdomain }}
                            </a>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-col gap-0.5">
                                <Badge
                                    :variant="
                                        tenant.license_active
                                            ? 'secondary'
                                            : 'destructive'
                                    "
                                    class="w-fit"
                                >
                                    {{ licenseLabel(tenant) }}
                                </Badge>
                                <span class="text-xs text-muted-foreground">
                                    until
                                    {{ formatDate(tenant.license_expires_at) }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <Input
                                    v-model="renewalDates[tenant.id]"
                                    type="date"
                                    class="h-8 w-40"
                                    :aria-label="`New license date for ${tenant.name}`"
                                />
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="renewTenant(tenant)"
                                    >Renew</Button
                                >
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button
                                variant="destructive"
                                size="sm"
                                @click="deleteTenant(tenant)"
                                >Delete</Button
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
