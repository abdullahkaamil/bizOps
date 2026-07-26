<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TenantController from '@/actions/App/Http/Controllers/Admin/TenantController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as tenantsIndex } from '@/routes/admin/tenants';

const props = defineProps<{
    centralDomain: string;
    defaultLicenseExpiresAt: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Tenants', href: '/tenants' },
            { title: 'Create', href: '/tenants/create' },
        ],
    },
});

const subdomain = ref('');
const domainPreview = computed(() =>
    subdomain.value
        ? `${subdomain.value}.${props.centralDomain}`
        : `your-tenant.${props.centralDomain}`,
);
</script>

<template>
    <Head title="Create tenant" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Create tenant"
                description="Provision a new tenant with its own database, subdomain and administrator."
            />
            <Button as-child variant="outline">
                <a :href="tenantsIndex().url">Back to tenants</a>
            </Button>
        </div>

        <Form
            v-bind="TenantController.store.form()"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Tenant name</Label>
                <Input id="name" name="name" required placeholder="Acme Inc" />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="subdomain">Subdomain</Label>
                <Input
                    id="subdomain"
                    name="subdomain"
                    v-model="subdomain"
                    required
                    placeholder="acme"
                    autocapitalize="none"
                    autocomplete="off"
                />
                <p class="text-sm text-muted-foreground">
                    Reachable at
                    <span class="font-mono">{{ domainPreview }}</span>
                </p>
                <InputError :message="errors.subdomain" />
            </div>

            <div class="grid gap-2">
                <Label for="admin_name">Administrator name</Label>
                <Input
                    id="admin_name"
                    name="admin_name"
                    required
                    placeholder="Jane Doe"
                />
                <InputError :message="errors.admin_name" />
            </div>

            <div class="grid gap-2">
                <Label for="admin_email">Administrator email</Label>
                <Input
                    id="admin_email"
                    name="admin_email"
                    type="email"
                    required
                    placeholder="jane@acme.test"
                    autocomplete="off"
                />
                <InputError :message="errors.admin_email" />
            </div>

            <div class="grid gap-2">
                <Label for="admin_password">Administrator password</Label>
                <Input
                    id="admin_password"
                    name="admin_password"
                    type="password"
                    required
                    autocomplete="new-password"
                />
                <InputError :message="errors.admin_password" />
            </div>

            <div class="grid gap-2">
                <Label for="license_expires_at">License valid until</Label>
                <Input
                    id="license_expires_at"
                    name="license_expires_at"
                    type="date"
                    required
                    :default-value="defaultLicenseExpiresAt"
                />
                <p class="text-sm text-muted-foreground">
                    The tenant can access the platform until this date. You can
                    renew it later.
                </p>
                <InputError :message="errors.license_expires_at" />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing"
                    >Provision tenant</Button
                >
            </div>
        </Form>
    </div>
</template>
