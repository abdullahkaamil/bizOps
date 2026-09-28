<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, Copy, FileText } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { t as $t } from '@/i18n';
import { accept, cancel, duplicate, edit, expire, reject, send } from '@/routes/tenant/quotations';

type Line = {
    id: string;
    item_id: string | null;
    internal_name: string | null;
    unit_cost: string | null;
    customer_alias: string;
    description: string | null;
    quantity: string;
    unit: string;
    unit_price: string;
    tax_rate: string;
    line_total: string;
};
type History = { id: string; from: string | null; to: string; reason: string | null; actor: string | null; created_at: string | null };
type Quotation = {
    id: string;
    number: string;
    status: string;
    currency: string;
    issue_date: string | null;
    valid_until: string | null;
    notes: string | null;
    terms: string | null;
    customer: { id: string; name: string } | null;
    subtotal: string;
    discount_total: string;
    tax_total: string;
    grand_total: string;
    lines: Line[];
    history: History[];
};
type Doc = { id: string; type: string; number: string | null; url: string };

defineProps<{
    quotation: Quotation;
    canViewCost: boolean;
    documents: Doc[];
    abilities: Record<string, boolean>;
    reviewLink: string | null;
}>();

defineOptions({ layout: { breadcrumbs: [{ title: $t('nav.quotations'), href: '/quotations' }, { title: $t('common.details'), href: '#' }] } });

const { t } = useI18n();
const rejecting = ref(false);
const reason = ref('');
const copied = ref(false);

async function copyLink(link: string) {
    try {
        await navigator.clipboard.writeText(link);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard unavailable — the link stays visible for manual copy.
    }
}

function post(url: string, data: Record<string, string> = {}) {
    router.post(url, data, { preserveScroll: true });
}
</script>

<template>
    <Head :title="quotation.number" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading variant="small" :title="quotation.number" :description="quotation.customer?.name ?? ''" />
            <div class="flex items-center gap-2">
                <StatusBadge :status="quotation.status" />
                <Button v-if="abilities.edit" as-child variant="outline" size="sm"><Link :href="edit(quotation.id).url">{{ t('common.edit') }}</Link></Button>
                <Button v-if="abilities.send" size="sm" @click="post(send(quotation.id).url)">{{ t('quotations.send') }}</Button>
                <Button v-if="abilities.decide" size="sm" @click="post(accept(quotation.id).url)">{{ t('quotations.accept') }}</Button>
                <Button v-if="abilities.decide" size="sm" variant="destructive" @click="rejecting = true">{{ t('quotations.reject') }}</Button>
                <Button v-if="abilities.decide" size="sm" variant="outline" @click="post(expire(quotation.id).url)">{{ t('quotations.expire') }}</Button>
                <Button v-if="abilities.cancel" size="sm" variant="outline" @click="post(cancel(quotation.id).url)">{{ t('common.cancel') }}</Button>
                <Button v-if="abilities.duplicate" size="sm" variant="secondary" @click="post(duplicate(quotation.id).url)">{{ t('quotations.duplicate') }}</Button>
            </div>
        </div>

        <div v-if="rejecting" class="flex flex-col gap-2 rounded-lg border p-3">
            <Label>{{ t('quotations.rejection_reason') }}</Label>
            <textarea v-model="reason" rows="2" class="rounded-md border border-input bg-transparent p-2 text-sm"></textarea>
            <div class="flex gap-2">
                <Button size="sm" variant="destructive" @click="post(reject(quotation.id).url, { reason }); rejecting = false">{{ t('quotations.confirm_reject') }}</Button>
                <Button size="sm" variant="ghost" @click="rejecting = false">{{ t('common.cancel') }}</Button>
            </div>
        </div>

        <!-- Customer approval link (no mail server needed — copy & send manually) -->
        <div
            v-if="reviewLink"
            class="flex flex-col gap-2 rounded-xl border border-primary/40 bg-primary/5 p-4"
        >
            <h2 class="text-sm font-medium">{{ t('quotations.review_link_title') }}</h2>
            <p class="text-xs text-muted-foreground">{{ t('quotations.review_link_hint') }}</p>
            <div class="flex flex-wrap items-center gap-2">
                <input
                    :value="reviewLink"
                    readonly
                    class="h-9 min-w-0 flex-1 rounded-md border border-input bg-background px-3 font-mono text-xs"
                    @focus="($event.target as HTMLInputElement).select()"
                />
                <Button size="sm" variant="secondary" @click="copyLink(reviewLink)">
                    <component :is="copied ? Check : Copy" class="size-4" />
                    {{ copied ? t('quotations.copied') : t('quotations.copy') }}
                </Button>
            </div>
        </div>

        <!-- Lines -->
        <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <table class="w-full text-left text-sm">
                <thead class="border-b text-muted-foreground">
                    <tr>
                        <th class="px-3 py-2 font-medium">{{ t('quotations.col_item_alias') }}</th>
                        <th v-if="canViewCost" class="px-3 py-2 font-medium">{{ t('quotations.col_internal') }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ t('quotations.col_qty') }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ t('quotations.col_price') }}</th>
                        <th v-if="canViewCost" class="px-3 py-2 text-right font-medium">{{ t('quotations.col_cost') }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ t('quotations.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="l in quotation.lines" :key="l.id" class="border-b last:border-0">
                        <td class="px-3 py-2">
                            <div class="font-medium">{{ l.customer_alias }}</div>
                            <div v-if="l.description" class="text-xs text-muted-foreground">{{ l.description }}</div>
                        </td>
                        <td v-if="canViewCost" class="px-3 py-2 text-xs text-muted-foreground">{{ l.internal_name ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">{{ l.quantity }} {{ l.unit }}</td>
                        <td class="px-3 py-2 text-right">{{ l.unit_price }}</td>
                        <td v-if="canViewCost" class="px-3 py-2 text-right text-muted-foreground">{{ l.unit_cost ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">{{ l.line_total }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="ml-auto w-full max-w-xs text-sm">
            <div class="flex justify-between py-1"><span class="text-muted-foreground">{{ t('quotations.subtotal') }}</span><span>{{ quotation.currency }} {{ quotation.subtotal }}</span></div>
            <div class="flex justify-between py-1"><span class="text-muted-foreground">{{ t('quotations.discount') }}</span><span>-{{ quotation.currency }} {{ quotation.discount_total }}</span></div>
            <div class="flex justify-between py-1"><span class="text-muted-foreground">{{ t('quotations.tax') }}</span><span>{{ quotation.currency }} {{ quotation.tax_total }}</span></div>
            <div class="flex justify-between border-t py-2 font-semibold"><span>{{ t('quotations.total') }}</span><span>{{ quotation.currency }} {{ quotation.grand_total }}</span></div>
        </div>

        <div v-if="documents.length" class="flex flex-col gap-2">
            <Label>{{ t('quotations.documents') }}</Label>
            <a v-for="d in documents" :key="d.id" :href="d.url" class="inline-flex items-center gap-2 text-sm text-primary hover:underline">
                <FileText class="size-3.5" /> {{ d.type }}<span v-if="d.number"> · {{ d.number }}</span>
            </a>
        </div>

        <div class="flex flex-col gap-2">
            <Label>{{ t('quotations.timeline') }}</Label>
            <div v-for="h in quotation.history" :key="h.id" class="text-xs text-muted-foreground">
                <span class="font-medium text-foreground">{{ h.actor ?? t('quotations.system') }}</span>
                {{ h.from ? `${h.from} → ${h.to}` : h.to }}
                <span v-if="h.reason">· "{{ h.reason }}"</span>
                <span v-if="h.created_at"> · {{ new Date(h.created_at).toLocaleString() }}</span>
            </div>
        </div>
    </div>
</template>
