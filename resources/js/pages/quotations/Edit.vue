<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import QuotationForm from '@/components/QuotationForm.vue';
import { t as $t } from '@/i18n';
import { update } from '@/routes/tenant/quotations';

type Quotation = {
    id: string;
    number: string;
    currency: string;
    valid_until: string | null;
    notes: string | null;
    terms: string | null;
    customer: { id: string; name: string } | null;
    contact_id: string | null;
    lines: Array<{
        item_id: string | null;
        customer_alias: string;
        description: string | null;
        quantity: string;
        unit: string;
        unit_price: string;
        tax_rate: string;
        discount_type: string | null;
        discount_value: string | null;
    }>;
};

const props = defineProps<{
    quotation: Quotation;
    customers: { id: string; name: string; contacts: { id: string; name: string }[] }[];
    items: { id: string; name: string; sku: string; unit: string; sale_price: string | null }[];
    discountTypes: string[];
    defaultTaxRate: number;
    currencies: { value: string; label: string }[];
    defaultCurrency: string;
}>();

defineOptions({ layout: { breadcrumbs: [{ title: $t('nav.quotations'), href: '/quotations' }, { title: $t('common.edit'), href: '#' }] } });

const { t } = useI18n();
</script>

<template>
    <Head :title="`${t('common.edit')} ${quotation.number}`" />

    <div class="mx-auto w-full max-w-4xl p-4">
        <Heading variant="small" :title="`${t('common.edit')} ${quotation.number}`" :description="t('quotations.draft_quotation')" />
        <div class="mt-6">
            <QuotationForm
                :customers="customers"
                :items="items"
                :discount-types="discountTypes"
                :default-tax-rate="defaultTaxRate"
                :currencies="currencies"
                :default-currency="defaultCurrency"
                :quotation="quotation"
                :submit-url="update(props.quotation.id).url"
                method="put"
            />
        </div>
    </div>
</template>
