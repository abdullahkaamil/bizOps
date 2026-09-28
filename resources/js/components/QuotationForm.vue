<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Plus, Trash2 } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const { t } = useI18n();

type ItemOption = { id: string; name: string; sku: string; unit: string; sale_price: string | null };
type CustomerOption = { id: string; name: string; contacts: { id: string; name: string }[] };
type Line = {
    inventory_item_id: string;
    customer_alias: string;
    description: string;
    quantity: string;
    unit: string;
    unit_price: string;
    tax_rate: string;
    discount_type: string;
    discount_value: string;
};
type ExistingQuotation = {
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
    customers: CustomerOption[];
    items: ItemOption[];
    discountTypes: string[];
    defaultTaxRate: number;
    currencies: { value: string; label: string }[];
    defaultCurrency: string;
    submitUrl: string;
    method?: 'post' | 'put';
    quotation?: ExistingQuotation;
}>();

function emptyLine(): Line {
    return {
        inventory_item_id: '',
        customer_alias: '',
        description: '',
        quantity: '1',
        unit: 'unit',
        unit_price: '0',
        tax_rate: String(props.defaultTaxRate),
        discount_type: '',
        discount_value: '',
    };
}

const customerId = ref(props.quotation?.customer?.id ?? '');
const contactId = ref(props.quotation?.contact_id ?? '');
const currency = ref(props.quotation?.currency ?? props.defaultCurrency);
const validUntil = ref(props.quotation?.valid_until ?? '');
const notes = ref(props.quotation?.notes ?? '');
const terms = ref(props.quotation?.terms ?? '');
const processing = ref(false);
const errorMsg = ref('');

const lines = reactive<Line[]>(
    props.quotation?.lines.map((l) => ({
        inventory_item_id: l.item_id ?? '',
        customer_alias: l.customer_alias,
        description: l.description ?? '',
        quantity: l.quantity,
        unit: l.unit,
        unit_price: l.unit_price,
        tax_rate: l.tax_rate,
        discount_type: l.discount_type ?? '',
        discount_value: l.discount_value ?? '',
    })) ?? [emptyLine()],
);

const currentCustomer = computed(() => props.customers.find((c) => c.id === customerId.value));

function onItemChange(line: Line) {
    const item = props.items.find((i) => i.id === line.inventory_item_id);

    if (item) {
        if (!line.customer_alias) {
line.customer_alias = item.name;
}

        line.unit = item.unit;
        line.unit_price = item.sale_price ?? '0';
    }
}

function lineTotal(line: Line): number {
    const sub = (parseFloat(line.quantity) || 0) * (parseFloat(line.unit_price) || 0);
    let discount = 0;

    if (line.discount_type === 'percent') {
discount = (sub * (parseFloat(line.discount_value) || 0)) / 100;
} else if (line.discount_type === 'fixed') {
discount = Math.min(parseFloat(line.discount_value) || 0, sub);
}

    const taxable = sub - discount;

    return taxable + (taxable * (parseFloat(line.tax_rate) || 0)) / 100;
}

// Client-side preview only — the server recomputes authoritatively.
const grandPreview = computed(() => lines.reduce((sum, l) => sum + lineTotal(l), 0).toFixed(2));

function addLine() {
    lines.push(emptyLine());
}
function removeLine(i: number) {
    lines.splice(i, 1);
}
function move(i: number, dir: -1 | 1) {
    const j = i + dir;

    if (j < 0 || j >= lines.length) {
return;
}

    const [item] = lines.splice(i, 1);
    lines.splice(j, 0, item);
}

function submit() {
    processing.value = true;
    errorMsg.value = '';
    const payload = {
        customer_id: customerId.value,
        customer_contact_id: contactId.value || null,
        currency: currency.value,
        valid_until: validUntil.value || null,
        notes: notes.value || null,
        terms: terms.value || null,
        lines: lines.map((l, idx) => ({ ...l, sort_order: idx })),
    };
    const opts = {
        onError: (e: Record<string, string>) => {
            errorMsg.value = Object.values(e)[0] ?? t('quotations.check_form');
        },
        onFinish: () => (processing.value = false),
    };

    if (props.method === 'put') {
router.put(props.submitUrl, payload, opts);
} else {
router.post(props.submitUrl, payload, opts);
}
}
</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="grid gap-1">
                <Label for="customer">{{ t('quotations.customer') }}</Label>
                <select id="customer" v-model="customerId" class="h-10 rounded-md border border-input bg-transparent px-3 text-sm">
                    <option value="">{{ t('quotations.select') }}</option>
                    <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
            <div v-if="currentCustomer" class="grid gap-1">
                <Label for="contact">{{ t('quotations.contact') }}</Label>
                <select id="contact" v-model="contactId" class="h-10 rounded-md border border-input bg-transparent px-3 text-sm">
                    <option value="">—</option>
                    <option v-for="ct in currentCustomer.contacts" :key="ct.id" :value="ct.id">{{ ct.name }}</option>
                </select>
            </div>
            <div class="grid gap-1">
                <Label for="valid_until">{{ t('quotations.valid_until') }}</Label>
                <Input id="valid_until" v-model="validUntil" type="date" />
            </div>
            <div class="grid gap-1">
                <Label for="currency">{{ t('quotations.currency') }}</Label>
                <select id="currency" v-model="currency" class="h-10 rounded-md border border-input bg-transparent px-3 text-sm">
                    <option v-for="c in currencies" :key="c.value" :value="c.value">{{ c.label }}</option>
                </select>
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <Label>{{ t('quotations.lines') }}</Label>
                <Button size="sm" variant="outline" @click="addLine"><Plus class="size-4" /> {{ t('quotations.add_line') }}</Button>
            </div>

            <div
                v-for="(line, i) in lines"
                :key="i"
                class="grid gap-2 rounded-xl border border-sidebar-border/70 p-3 md:grid-cols-12 dark:border-sidebar-border"
            >
                <select v-model="line.inventory_item_id" class="h-9 rounded-md border border-input bg-transparent px-2 text-sm md:col-span-3" @change="onItemChange(line)">
                    <option value="">{{ t('quotations.custom_line') }}</option>
                    <option v-for="it in items" :key="it.id" :value="it.id">{{ it.sku }} — {{ it.name }}</option>
                </select>
                <Input v-model="line.customer_alias" :placeholder="t('quotations.customer_alias')" class="md:col-span-3" />
                <Input v-model="line.quantity" type="number" step="0.001" :placeholder="t('quotations.qty')" class="md:col-span-1" />
                <Input v-model="line.unit_price" type="number" step="0.01" :placeholder="t('quotations.price')" class="md:col-span-2" />
                <Input v-model="line.tax_rate" type="number" step="0.01" :placeholder="t('quotations.tax_pct')" class="md:col-span-1" />
                <div class="flex items-center justify-end gap-1 md:col-span-2">
                    <span class="mr-auto text-sm text-muted-foreground">{{ lineTotal(line).toFixed(2) }}</span>
                    <Button variant="ghost" size="icon" @click="move(i, -1)"><ArrowUp class="size-4" /></Button>
                    <Button variant="ghost" size="icon" @click="move(i, 1)"><ArrowDown class="size-4" /></Button>
                    <Button variant="ghost" size="icon" @click="removeLine(i)"><Trash2 class="size-4" /></Button>
                </div>
                <Input v-model="line.description" :placeholder="t('quotations.description_opt')" class="md:col-span-6" />
                <select v-model="line.discount_type" class="h-9 rounded-md border border-input bg-transparent px-2 text-sm md:col-span-3">
                    <option value="">{{ t('quotations.no_discount') }}</option>
                    <option v-for="d in discountTypes" :key="d" :value="d">{{ t('quotations.discount_' + d) }}</option>
                </select>
                <Input v-if="line.discount_type" v-model="line.discount_value" type="number" step="0.01" :placeholder="t('quotations.discount')" class="md:col-span-3" />
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-1">
                <Label for="notes">{{ t('quotations.notes') }}</Label>
                <textarea id="notes" v-model="notes" rows="2" class="rounded-md border border-input bg-transparent p-2 text-sm"></textarea>
            </div>
            <div class="grid gap-1">
                <Label for="terms">{{ t('quotations.terms') }}</Label>
                <textarea id="terms" v-model="terms" rows="2" class="rounded-md border border-input bg-transparent p-2 text-sm"></textarea>
            </div>
        </div>

        <div class="flex items-center justify-between border-t pt-4">
            <div class="text-sm text-muted-foreground">
                {{ t('quotations.preview_total') }} <span class="font-medium text-foreground">{{ grandPreview }}</span>
                <span class="ml-1 text-xs">{{ t('quotations.server_recalc') }}</span>
            </div>
            <div class="flex items-center gap-3">
                <span v-if="errorMsg" class="text-sm text-destructive">{{ errorMsg }}</span>
                <Button :disabled="processing || !customerId" @click="submit">{{ t('quotations.save_quotation') }}</Button>
            </div>
        </div>
    </div>
</template>
