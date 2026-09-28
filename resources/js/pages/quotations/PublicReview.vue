<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Line = {
    name: string;
    description: string | null;
    quantity: string;
    unit: string;
    unit_price: string;
    line_total: string;
};
type Quotation = {
    number: string;
    status: string;
    currency: string;
    issue_date: string;
    valid_until: string | null;
    notes: string | null;
    terms: string | null;
    customer: string | null;
    decided_by_name: string | null;
    subtotal: string;
    discount_total: string;
    tax_total: string;
    grand_total: string;
    lines: Line[];
};

const props = defineProps<{
    quotation: Quotation;
    company: string;
    actions: { accept: string; reject: string };
}>();

const { t } = useI18n();

const name = ref('');
const reason = ref('');
const rejecting = ref(false);
const processing = ref(false);

const pending = computed(() => props.quotation.status === 'sent');

function money(amount: string): string {
    return `${props.quotation.currency} ${amount}`;
}

function submit(url: string) {
    if (name.value.trim() === '') {
        return;
    }

    processing.value = true;
    router.post(
        url,
        { name: name.value.trim(), reason: reason.value.trim() || null },
        { onFinish: () => (processing.value = false) },
    );
}
</script>

<template>
    <Head :title="`${t('quote_review.title')} · ${quotation.number}`" />

    <div class="min-h-screen bg-muted/30 px-4 py-10">
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
            <!-- Header -->
            <div class="flex flex-col gap-1 text-center">
                <span class="text-sm text-muted-foreground">{{ company }}</span>
                <h1 class="text-xl font-semibold">{{ t('quote_review.title') }} · {{ quotation.number }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('quote_review.intro', { company }) }}</p>
            </div>

            <!-- Decision banner -->
            <div
                v-if="!pending"
                class="rounded-xl border p-4 text-center text-sm"
                :class="quotation.status === 'accepted'
                    ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                    : 'border-sidebar-border/70 bg-muted/40 text-muted-foreground dark:border-sidebar-border'"
            >
                <p class="font-medium">{{ t('quote_review.status_' + quotation.status) }}</p>
                <p v-if="quotation.decided_by_name" class="mt-1 text-xs">
                    {{ t('quote_review.decided_by', { name: quotation.decided_by_name }) }}
                </p>
            </div>

            <!-- Quotation -->
            <div class="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-sm dark:border-sidebar-border">
                <div class="flex flex-wrap justify-between gap-2 text-sm">
                    <div v-if="quotation.customer">
                        <span class="text-muted-foreground">{{ t('quote_review.customer') }}:</span> {{ quotation.customer }}
                    </div>
                    <div v-if="quotation.valid_until">
                        <span class="text-muted-foreground">{{ t('quote_review.valid_until') }}:</span> {{ quotation.valid_until }}
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b text-xs text-muted-foreground">
                            <tr>
                                <th class="py-2 font-medium">{{ t('quote_review.item') }}</th>
                                <th class="py-2 text-right font-medium">{{ t('quote_review.qty') }}</th>
                                <th class="py-2 text-right font-medium">{{ t('quote_review.unit_price') }}</th>
                                <th class="py-2 text-right font-medium">{{ t('quote_review.total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(line, i) in quotation.lines" :key="i" class="border-b border-sidebar-border/40 last:border-0">
                                <td class="py-2">
                                    <div class="font-medium">{{ line.name }}</div>
                                    <div v-if="line.description" class="text-xs text-muted-foreground">{{ line.description }}</div>
                                </td>
                                <td class="py-2 text-right">{{ line.quantity }} {{ line.unit }}</td>
                                <td class="py-2 text-right">{{ money(line.unit_price) }}</td>
                                <td class="py-2 text-right">{{ money(line.line_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="ml-auto flex w-full max-w-xs flex-col gap-1 text-sm">
                    <div class="flex justify-between text-muted-foreground">
                        <span>{{ t('quote_review.subtotal') }}</span><span>{{ money(quotation.subtotal) }}</span>
                    </div>
                    <div v-if="quotation.discount_total !== '0.00'" class="flex justify-between text-muted-foreground">
                        <span>{{ t('quote_review.discount') }}</span><span>-{{ money(quotation.discount_total) }}</span>
                    </div>
                    <div class="flex justify-between text-muted-foreground">
                        <span>{{ t('quote_review.tax') }}</span><span>{{ money(quotation.tax_total) }}</span>
                    </div>
                    <div class="flex justify-between border-t pt-1 text-base font-semibold">
                        <span>{{ t('quote_review.grand_total') }}</span><span>{{ money(quotation.grand_total) }}</span>
                    </div>
                </div>

                <div v-if="quotation.terms" class="border-t pt-3 text-xs text-muted-foreground">
                    <span class="font-medium">{{ t('quote_review.terms') }}:</span> {{ quotation.terms }}
                </div>
            </div>

            <!-- Decision form -->
            <div
                v-if="pending"
                class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-sm dark:border-sidebar-border"
            >
                <div class="grid gap-2">
                    <Label for="name">{{ t('quote_review.your_name') }}</Label>
                    <Input id="name" v-model="name" :placeholder="t('quote_review.your_name')" />
                </div>

                <div v-if="rejecting" class="grid gap-2">
                    <Label for="reason">{{ t('quote_review.reject_reason') }}</Label>
                    <textarea
                        id="reason"
                        v-model="reason"
                        rows="2"
                        class="rounded-md border border-input bg-transparent p-2 text-sm"
                        :placeholder="t('quote_review.reject_reason')"
                    ></textarea>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="!rejecting"
                        class="flex-1"
                        :disabled="processing || name.trim() === ''"
                        @click="submit(actions.accept)"
                    >
                        <Check class="size-4" /> {{ t('quote_review.accept') }}
                    </Button>
                    <Button
                        v-if="!rejecting"
                        variant="outline"
                        class="flex-1"
                        :disabled="processing"
                        @click="rejecting = true"
                    >
                        <X class="size-4" /> {{ t('quote_review.reject') }}
                    </Button>

                    <template v-else>
                        <Button
                            variant="destructive"
                            class="flex-1"
                            :disabled="processing || name.trim() === ''"
                            @click="submit(actions.reject)"
                        >
                            {{ t('quote_review.confirm_reject') }}
                        </Button>
                        <Button variant="ghost" :disabled="processing" @click="rejecting = false">
                            {{ t('quote_review.cancel') }}
                        </Button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
