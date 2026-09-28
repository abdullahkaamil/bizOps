<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t as $t } from '@/i18n';
import { store } from '@/routes/tenant/inventory';

defineProps<{ statuses: string[] }>();

defineOptions({ layout: { breadcrumbs: [{ title: $t('nav.inventory'), href: '/inventory' }, { title: $t('inventory.new_item'), href: '/inventory/create' }] } });

const { t } = useI18n();
</script>

<template>
    <Head :title="t('inventory.new_item')" />

    <div class="mx-auto w-full max-w-xl p-4">
        <Heading variant="small" :title="t('inventory.new_item_title')" :description="t('inventory.new_item_desc')" />

        <Form v-bind="store.form()" class="mt-6 grid gap-4" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="sku">{{ t('inventory.sku') }}</Label>
                <Input id="sku" name="sku" required />
                <InputError :message="errors.sku" />
            </div>
            <div class="grid gap-2">
                <Label for="name">{{ t('inventory.name') }}</Label>
                <Input id="name" name="name" required />
                <InputError :message="errors.name" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="unit">{{ t('inventory.unit') }}</Label>
                    <Input id="unit" name="unit" value="unit" required />
                </div>
                <div class="grid gap-2">
                    <Label for="status">{{ t('inventory.status') }}</Label>
                    <select id="status" name="status" class="h-10 rounded-md border border-input bg-transparent px-3 text-sm">
                        <option v-for="s in statuses" :key="s" :value="s">{{ t('status.' + s) }}</option>
                    </select>
                </div>
            </div>
            <div class="grid gap-2">
                <Label for="current_sale_price">{{ t('inventory.sale_price') }}</Label>
                <Input id="current_sale_price" name="current_sale_price" type="number" step="0.01" min="0" />
                <InputError :message="errors.current_sale_price" />
            </div>
            <div class="grid gap-2">
                <Label for="description">{{ t('inventory.description') }}</Label>
                <textarea id="description" name="description" rows="2" class="rounded-md border border-input bg-transparent p-2 text-sm"></textarea>
            </div>
            <Button type="submit" :disabled="processing">{{ t('inventory.create_item') }}</Button>
        </Form>
    </div>
</template>
