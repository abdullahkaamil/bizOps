<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CustomerController from '@/actions/App/Http/Controllers/Tenant/CustomerController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t as $t } from '@/i18n';

defineProps<{ statuses: string[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: $t('nav.customers'), href: '/customers' },
            { title: $t('crm.new_customer'), href: '/customers/create' },
        ],
    },
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('crm.new_customer')" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            variant="small"
            :title="t('crm.new_customer')"
            :description="t('crm.new_customer_desc')"
        />

        <Form
            v-bind="CustomerController.store.form()"
            class="max-w-2xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2 md:col-span-2">
                    <Label for="company_name">{{ t('crm.company_name') }}</Label>
                    <Input id="company_name" name="company_name" required />
                    <InputError :message="errors.company_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="email">{{ t('crm.email') }}</Label>
                    <Input id="email" name="email" type="email" />
                    <InputError :message="errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="phone">{{ t('crm.phone') }}</Label>
                    <Input id="phone" name="phone" />
                </div>
                <div class="grid gap-2">
                    <Label for="status">{{ t('crm.status') }}</Label>
                    <select
                        id="status"
                        name="status"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option v-for="s in statuses" :key="s" :value="s">{{ t('status.' + s) }}</option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="tax_number">{{ t('crm.tax_number') }}</Label>
                    <Input id="tax_number" name="tax_number" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label for="notes">{{ t('crm.notes') }}</Label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="3"
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                    />
                </div>
            </div>

            <Button type="submit" :disabled="processing"
                >{{ t('crm.create_customer') }}</Button>
        </Form>
    </div>
</template>
