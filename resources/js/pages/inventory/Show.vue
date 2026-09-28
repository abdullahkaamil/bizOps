<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t as $t } from '@/i18n';
import { adjust, update } from '@/routes/tenant/inventory';
import { attach } from '@/routes/tenant/inventory/suppliers';

type Balance = { warehouse: string | null; quantity: number };
type Movement = {
    id: string;
    type: string;
    quantity: number;
    warehouse: string | null;
    unit_cost: string | null;
    reason: string | null;
    actor: string | null;
    occurred_at: string | null;
};
type SupplierRow = {
    id: string;
    name: string;
    supplier_sku: string | null;
    is_preferred: boolean;
    lead_time_days: number | null;
    last_purchase_price: string | null;
};
type PriceRow = { type: string; amount: string; currency: string; effective_at: string | null };
type Item = {
    id: string;
    sku: string;
    name: string;
    description: string | null;
    unit: string;
    status: string;
    sale_price: string | null;
};
type Option = { id: string; name: string };

defineProps<{
    item: Item;
    balances: Balance[];
    movements: Movement[];
    suppliers: SupplierRow[];
    priceHistory: PriceRow[];
    canViewCost: boolean;
    canManage: boolean;
    movementTypes: string[];
    allSuppliers: Option[];
}>();

defineOptions({ layout: { breadcrumbs: [{ title: $t('nav.inventory'), href: '/inventory' }, { title: $t('inventory.edit_item'), href: '#' }] } });

const { t } = useI18n();
</script>

<template>
    <Head :title="item.sku" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4">
        <Heading variant="small" :title="item.name" :description="item.sku" />

        <!-- Balances -->
        <section class="flex flex-col gap-2">
            <h2 class="text-sm font-medium">{{ t('inventory.warehouse_balances') }}</h2>
            <div class="flex flex-wrap gap-2">
                <Badge v-for="b in balances" :key="b.warehouse ?? 'w'" variant="outline">
                    {{ b.warehouse ?? t('inventory.main') }}: {{ b.quantity }} {{ item.unit }}
                </Badge>
                <span v-if="!balances.length" class="text-sm text-muted-foreground">{{ t('inventory.no_stock') }}</span>
            </div>
        </section>

        <!-- Adjust -->
        <section v-if="canManage" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <h2 class="mb-3 text-sm font-medium">{{ t('inventory.adjust_stock') }}</h2>
            <Form v-bind="adjust.form(item.id)" :reset-on-success="['quantity', 'reason', 'unit_cost']" class="grid gap-3 md:grid-cols-4" v-slot="{ errors }">
                <select name="type" class="h-9 rounded-md border border-input bg-transparent px-2 text-sm">
                    <option v-for="mt in movementTypes" :key="mt" :value="mt">{{ t('movement.' + mt) }}</option>
                </select>
                <Input name="quantity" type="number" step="0.001" min="0" :placeholder="t('inventory.qty')" required />
                <Input v-if="canViewCost" name="unit_cost" type="number" step="0.01" min="0" :placeholder="t('inventory.unit_cost')" />
                <Input name="reason" :placeholder="t('inventory.reason')" />
                <div class="md:col-span-4">
                    <InputError :message="errors.stock ?? errors.quantity" />
                    <Button type="submit" size="sm">{{ t('inventory.apply') }}</Button>
                </div>
            </Form>
        </section>

        <!-- Ledger -->
        <section class="flex flex-col gap-2">
            <h2 class="text-sm font-medium">{{ t('inventory.stock_ledger') }}</h2>
            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-left text-sm">
                    <thead class="border-b text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">{{ t('inventory.type') }}</th>
                            <th class="px-3 py-2 text-right font-medium">{{ t('inventory.qty') }}</th>
                            <th v-if="canViewCost" class="px-3 py-2 text-right font-medium">{{ t('inventory.unit_cost') }}</th>
                            <th class="px-3 py-2 font-medium">{{ t('inventory.by') }}</th>
                            <th class="px-3 py-2 font-medium">{{ t('inventory.when') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in movements" :key="m.id" class="border-b last:border-0">
                            <td class="px-3 py-2">{{ t('movement.' + m.type) }}</td>
                            <td class="px-3 py-2 text-right" :class="m.quantity < 0 ? 'text-destructive' : 'text-emerald-600'">
                                {{ m.quantity }}
                            </td>
                            <td v-if="canViewCost" class="px-3 py-2 text-right">{{ m.unit_cost ?? '—' }}</td>
                            <td class="px-3 py-2 text-muted-foreground">{{ m.actor ?? t('inventory.system') }}</td>
                            <td class="px-3 py-2 text-muted-foreground">
                                {{ m.occurred_at ? new Date(m.occurred_at).toLocaleString() : '' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="!movements.length" class="text-sm text-muted-foreground">{{ t('inventory.no_movements') }}</p>
        </section>

        <!-- Suppliers -->
        <section class="flex flex-col gap-2">
            <h2 class="text-sm font-medium">{{ t('inventory.suppliers') }}</h2>
            <div v-for="s in suppliers" :key="s.id" class="flex flex-wrap items-center gap-2 rounded-lg border p-2 text-sm">
                <span class="font-medium">{{ s.name }}</span>
                <Badge v-if="s.is_preferred" variant="default">{{ t('inventory.preferred') }}</Badge>
                <span v-if="s.supplier_sku" class="text-muted-foreground">{{ t('inventory.supplier_sku') }} {{ s.supplier_sku }}</span>
                <span v-if="s.lead_time_days != null" class="text-muted-foreground">{{ s.lead_time_days }}{{ t('inventory.lead_days') }}</span>
                <span v-if="canViewCost && s.last_purchase_price" class="ml-auto">{{ s.last_purchase_price }}</span>
            </div>
            <Form v-if="canManage" v-bind="attach.form(item.id)" :reset-on-success="['supplier_sku', 'last_purchase_price', 'lead_time_days']" class="flex flex-wrap items-end gap-2">
                <select name="supplier_id" required class="h-9 rounded-md border border-input bg-transparent px-2 text-sm">
                    <option value="">{{ t('inventory.link_supplier') }}</option>
                    <option v-for="s in allSuppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <Input name="supplier_sku" :placeholder="t('inventory.supplier_sku')" class="w-36" />
                <Input v-if="canViewCost" name="last_purchase_price" type="number" step="0.01" :placeholder="t('inventory.last_cost')" class="w-28" />
                <Input name="lead_time_days" type="number" min="0" :placeholder="t('inventory.lead_days_ph')" class="w-24" />
                <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_preferred" value="1" /> {{ t('inventory.preferred') }}</label>
                <Button type="submit" size="sm" variant="secondary">{{ t('inventory.link') }}</Button>
            </Form>
        </section>

        <!-- Price history (cost-gated) -->
        <section v-if="canViewCost && priceHistory.length" class="flex flex-col gap-2">
            <h2 class="text-sm font-medium">{{ t('inventory.price_history') }}</h2>
            <div v-for="(p, i) in priceHistory" :key="i" class="text-xs text-muted-foreground">
                {{ p.type }} · {{ p.amount }} {{ p.currency }}
                <span v-if="p.effective_at"> · {{ new Date(p.effective_at).toLocaleDateString() }}</span>
            </div>
        </section>

        <!-- Edit -->
        <section v-if="canManage" class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <h2 class="mb-3 text-sm font-medium">{{ t('inventory.edit_item') }}</h2>
            <Form v-bind="update.form(item.id)" class="grid gap-3 md:grid-cols-2" v-slot="{ errors }">
                <input type="hidden" name="sku" :value="item.sku" />
                <input type="hidden" name="unit" :value="item.unit" />
                <div class="grid gap-1">
                    <Label for="name">{{ t('inventory.name') }}</Label>
                    <Input id="name" name="name" :default-value="item.name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-1">
                    <Label for="status">{{ t('inventory.status') }}</Label>
                    <select id="status" name="status" class="h-9 rounded-md border border-input bg-transparent px-2 text-sm">
                        <option :value="item.status">{{ t('status.' + item.status) }}</option>
                        <option value="active">{{ t('status.active') }}</option>
                        <option value="inactive">{{ t('status.inactive') }}</option>
                        <option value="archived">{{ t('status.archived') }}</option>
                    </select>
                </div>
                <div class="grid gap-1">
                    <Label for="price">{{ t('inventory.sale_price') }}</Label>
                    <Input id="price" name="current_sale_price" type="number" step="0.01" min="0" :default-value="item.sale_price ?? ''" />
                </div>
                <div class="flex items-end">
                    <Button type="submit" size="sm">{{ t('common.save') }}</Button>
                </div>
            </Form>
        </section>
    </div>
</template>
