<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import TenantController from '@/actions/App/Http/Controllers/Admin/TenantController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t as $t } from '@/i18n';
import { index as tenantsIndex } from '@/routes/admin/tenants';

const props = defineProps<{
    centralDomain: string;
    defaultLicenseExpiresAt: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: $t('nav.tenants'), href: '/admin/tenants' },
            { title: $t('admin.create_tenant'), href: '/admin/tenants/create' },
        ],
    },
});

const { t } = useI18n();
const subdomain = ref('');
const domainPreview = computed(() =>
    subdomain.value
        ? `${subdomain.value}.${props.centralDomain}`
        : `your-tenant.${props.centralDomain}`,
);
</script>

<template>
    <Head :title="t('admin.create_tenant')" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                :title="t('admin.create_tenant')"
                :description="t('admin.create_tenant_desc')"
            />
            <Button as-child variant="outline">
                <a :href="tenantsIndex().url">{{ t('admin.back_to_tenants') }}</a>
            </Button>
        </div>

        <Form
            v-bind="TenantController.store.form()"
            class="max-w-xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">{{ t('admin.tenant_name') }}</Label>
                <Input id="name" name="name" required placeholder="Acme Inc" />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="subdomain">{{ t('admin.subdomain') }}</Label>
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
                    {{ t('admin.reachable_at') }}
                    <span class="font-mono">{{ domainPreview }}</span>
                </p>
                <InputError :message="errors.subdomain" />
            </div>

            <div class="grid gap-2">
                <Label for="admin_name">{{ t('admin.admin_name') }}</Label>
                <Input
                    id="admin_name"
                    name="admin_name"
                    required
                    placeholder="Jane Doe"
                />
                <InputError :message="errors.admin_name" />
            </div>

            <div class="grid gap-2">
                <Label for="admin_email">{{ t('admin.admin_email') }}</Label>
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
                <Label for="admin_password">{{ t('admin.admin_password') }}</Label>
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
                <Label for="license_expires_at">{{ t('admin.license_valid_until') }}</Label>
                <Input
                    id="license_expires_at"
                    name="license_expires_at"
                    type="date"
                    required
                    :default-value="defaultLicenseExpiresAt"
                />
                <p class="text-sm text-muted-foreground">
                    {{ t('admin.license_hint') }}
                </p>
                <InputError :message="errors.license_expires_at" />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing"
                    >{{ t('admin.provision_tenant') }}</Button>
            </div>
        </Form>
    </div>
</template>
