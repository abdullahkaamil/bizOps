<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CompanySettingsController from '@/actions/App/Http/Controllers/Tenant/CompanySettingsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t as $t } from '@/i18n';

const props = defineProps<{
    general: {
        company_name: string | null;
        legal_name: string | null;
        email: string | null;
        phone: string | null;
        address: string | null;
        tax_number: string | null;
    };
    localization: {
        timezone: string;
        locale: string;
        currency: string;
        date_format: string;
        time_format: string;
    };
    invitations: {
        auto_accept: boolean;
    };
    currencies: { value: string; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: $t('settings.company_title'), href: '/settings/company' }],
    },
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('settings.company_title')" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            variant="small"
            :title="t('settings.company_title')"
            :description="t('settings.company_desc')"
        />

        <Form
            v-bind="CompanySettingsController.update.form()"
            class="max-w-2xl space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="company_name">{{ t('settings.company_name') }}</Label>
                    <Input
                        id="company_name"
                        name="company_name"
                        :default-value="props.general.company_name ?? ''"
                    />
                    <InputError :message="errors.company_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="legal_name">{{ t('settings.legal_name') }}</Label>
                    <Input
                        id="legal_name"
                        name="legal_name"
                        :default-value="props.general.legal_name ?? ''"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="email">{{ t('settings.email') }}</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        :default-value="props.general.email ?? ''"
                    />
                    <InputError :message="errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="phone">{{ t('settings.phone') }}</Label>
                    <Input
                        id="phone"
                        name="phone"
                        :default-value="props.general.phone ?? ''"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="tax_number">{{ t('settings.tax_number') }}</Label>
                    <Input
                        id="tax_number"
                        name="tax_number"
                        :default-value="props.general.tax_number ?? ''"
                    />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label for="address">{{ t('settings.address') }}</Label>
                    <Input
                        id="address"
                        name="address"
                        :default-value="props.general.address ?? ''"
                    />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="timezone">{{ t('settings.timezone') }}</Label>
                    <Input
                        id="timezone"
                        name="timezone"
                        :default-value="props.localization.timezone"
                        required
                    />
                    <InputError :message="errors.timezone" />
                </div>
                <div class="grid gap-2">
                    <Label for="currency">{{ t('settings.currency') }}</Label>
                    <select
                        id="currency"
                        name="currency"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        required
                    >
                        <option
                            v-for="c in currencies"
                            :key="c.value"
                            :value="c.value"
                            :selected="c.value === props.localization.currency"
                        >
                            {{ c.label }}
                        </option>
                    </select>
                    <InputError :message="errors.currency" />
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t pt-6">
                <h3 class="text-sm font-medium">{{ t('settings.invitations_section') }}</h3>
                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        name="auto_accept_invitations"
                        value="1"
                        :checked="props.invitations.auto_accept"
                        class="mt-0.5 size-4 rounded border-input"
                    />
                    <span class="flex flex-col gap-0.5">
                        <span class="text-sm font-medium">{{ t('settings.auto_accept_invitations') }}</span>
                        <span class="text-xs text-muted-foreground">{{ t('settings.auto_accept_desc') }}</span>
                    </span>
                </label>
            </div>

            <Button type="submit" :disabled="processing">{{ t('settings.save_settings') }}</Button>
        </Form>
    </div>
</template>
