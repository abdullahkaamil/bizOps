<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import QuotationForm from '@/components/QuotationForm.vue';
import { t as $t } from '@/i18n';
import { store } from '@/routes/tenant/quotations';

defineProps<{
    customers: { id: string; name: string; contacts: { id: string; name: string }[] }[];
    items: { id: string; name: string; sku: string; unit: string; sale_price: string | null }[];
    discountTypes: string[];
    defaultTaxRate: number;
    currencies: { value: string; label: string }[];
    defaultCurrency: string;
}>();

defineOptions({ layout: { breadcrumbs: [{ title: $t('nav.quotations'), href: '/quotations' }, { title: $t('quotations.new_quote'), href: '/quotations/create' }] } });

const { t } = useI18n();
</script>

<template>
    <Head :title="t('quotations.new_quotation')" />

    <div class="mx-auto w-full max-w-4xl p-4">
        <Heading variant="small" :title="t('quotations.new_quotation')" :description="t('quotations.new_desc')" />
        <div class="mt-6">
            <QuotationForm
                :customers="customers"
                :items="items"
                :discount-types="discountTypes"
                :default-tax-rate="defaultTaxRate"
                :currencies="currencies"
                :default-currency="defaultCurrency"
                :submit-url="store().url"
                method="post"
            />
        </div>
    </div>
</template>
