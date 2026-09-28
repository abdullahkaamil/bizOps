<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Camera, FileText, Trash2, Unlink } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { t as $t } from '@/i18n';
import { complete, deliver, repairNotes, unlink } from '@/routes/tenant/workshop';
import { destroy as destroyAttachment, store as storeAttachment } from '@/routes/tenant/workshop/attachments';
import { destroy as destroyPart, store as storePart } from '@/routes/tenant/workshop/parts';

type Attachment = { id: string; kind: string; url: string; thumb_url: string };
type History = { id: string; from: string | null; to: string; actor: string | null; created_at: string | null };
type Doc = { id: string; type: string; number: string | null; url: string };
type Part = {
    id: string;
    item: string | null;
    sku: string | null;
    quantity: number;
    sale_price: string | null;
    unit_cost: string | null;
};
type Ticket = {
    id: string;
    number: string;
    status: string;
    device: string | null;
    customer: string | null;
    assignee: string | null;
    issue_description: string;
    repair_notes: string | null;
    device_detail: { brand: string; model: string; serial: string | null; serial_unavailable: boolean } | null;
    job: { id: string; number: string } | null;
    attachments: Attachment[];
    parts: Part[];
    can_view_cost: boolean;
    history: History[];
    documents: Doc[];
    abilities: Record<string, boolean>;
};

const props = defineProps<{ ticket: Ticket; inventoryOptions: { id: string; label: string }[] }>();

const partItem = ref('');
const partQty = ref('1');

defineOptions({ layout: { breadcrumbs: [{ title: $t('nav.workshop'), href: '/workshop' }, { title: $t('workshop.ticket'), href: '#' }] } });

const { t } = useI18n();
const notes = ref(props.ticket.repair_notes ?? '');
const fileInput = ref<HTMLInputElement | null>(null);

function saveNotes() {
    router.put(repairNotes(props.ticket.id).url, { repair_notes: notes.value }, { preserveScroll: true });
}
function doComplete() {
    router.post(complete(props.ticket.id).url, { repair_notes: notes.value }, { preserveScroll: true });
}
function doDeliver() {
    router.post(deliver(props.ticket.id).url, {}, { preserveScroll: true });
}
function doUnlink() {
    router.post(unlink(props.ticket.id).url, {}, { preserveScroll: true });
}
function onPick(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
return;
}

    router.post(
        storeAttachment(props.ticket.id).url,
        { file, kind: 'repair' },
        { forceFormData: true, preserveScroll: true, onFinish: () => (input.value = '') },
    );
}
function removeAttachment(id: string) {
    router.delete(destroyAttachment(id).url, { preserveScroll: true });
}
function addPart() {
    if (!partItem.value) {
return;
}

    router.post(
        storePart(props.ticket.id).url,
        { inventory_item_id: partItem.value, quantity: partQty.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                partItem.value = '';
                partQty.value = '1';
            },
        },
    );
}
function removePart(id: string) {
    router.delete(destroyPart(id).url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="ticket.number" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-5 p-4 pb-24">
        <div class="flex items-start justify-between gap-3">
            <Heading variant="small" :title="ticket.device ?? t('workshop.device')" :description="ticket.number" />
            <StatusBadge :status="ticket.status" />
        </div>

        <!-- Device / customer -->
        <div class="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 p-4 text-sm dark:border-sidebar-border">
            <div class="font-medium">{{ ticket.customer ?? t('workshop.no_customer') }}</div>
            <div v-if="ticket.device_detail" class="text-muted-foreground">
                {{ ticket.device_detail.brand }} {{ ticket.device_detail.model }}
                · SN {{ ticket.device_detail.serial ?? t('workshop.not_available') }}
            </div>
            <div v-if="ticket.assignee" class="text-muted-foreground">{{ t('workshop.technician_label') }} {{ ticket.assignee }}</div>
            <div v-if="ticket.job" class="flex items-center gap-2">
                <Badge variant="outline">{{ t('workshop.job') }} {{ ticket.job.number }}</Badge>
                <Button v-if="ticket.abilities.update" variant="ghost" size="sm" @click="doUnlink">
                    <Unlink class="size-3.5" /> {{ t('workshop.unlink') }}
                </Button>
            </div>
        </div>

        <div class="flex flex-col gap-1">
            <Label>{{ t('workshop.reported_issue') }}</Label>
            <p class="whitespace-pre-line text-sm text-muted-foreground">{{ ticket.issue_description }}</p>
        </div>

        <!-- Repair notes -->
        <div v-if="ticket.abilities.update" class="flex flex-col gap-2">
            <Label for="notes">{{ t('workshop.repair_notes') }}</Label>
            <textarea
                id="notes"
                v-model="notes"
                rows="4"
                class="rounded-md border border-input bg-transparent p-3 text-sm"
                :placeholder="t('workshop.repair_notes_placeholder')"
            ></textarea>
            <Button size="sm" variant="secondary" class="self-start" @click="saveNotes">{{ t('workshop.save_notes') }}</Button>
        </div>
        <div v-else-if="ticket.repair_notes" class="flex flex-col gap-1">
            <Label>{{ t('workshop.repair_notes') }}</Label>
            <p class="whitespace-pre-line text-sm text-muted-foreground">{{ ticket.repair_notes }}</p>
        </div>

        <!-- Attachments -->
        <div class="flex flex-col gap-2">
            <Label>{{ t('workshop.images') }}</Label>
            <div class="grid grid-cols-3 gap-2">
                <div v-for="a in ticket.attachments" :key="a.id" class="relative">
                    <img :src="a.thumb_url" alt="attachment" class="h-24 w-full rounded-lg border object-cover" />
                    <button
                        v-if="ticket.abilities.update"
                        type="button"
                        class="absolute right-1 top-1 rounded-full bg-black/60 p-1 text-white"
                        @click="removeAttachment(a.id)"
                    >
                        <Trash2 class="size-3.5" />
                    </button>
                </div>
            </div>
            <template v-if="ticket.abilities.update">
                <input ref="fileInput" type="file" accept="image/*" capture="environment" class="hidden" @change="onPick" />
                <Button size="sm" variant="outline" class="self-start" @click="fileInput?.click()">
                    <Camera class="size-4" /> {{ t('workshop.add_image') }}
                </Button>
            </template>
        </div>

        <!-- Parts -->
        <div class="flex flex-col gap-2">
            <Label>{{ t('workshop.parts_used') }}</Label>
            <div v-for="p in ticket.parts" :key="p.id" class="flex items-center gap-2 rounded-lg border p-2 text-sm">
                <span class="font-medium">{{ p.item ?? p.sku }}</span>
                <span class="text-muted-foreground">×{{ p.quantity }}</span>
                <span v-if="ticket.can_view_cost && p.unit_cost" class="text-muted-foreground">@ {{ p.unit_cost }}</span>
                <Button
                    v-if="ticket.abilities.update"
                    variant="ghost"
                    size="sm"
                    class="ml-auto"
                    @click="removePart(p.id)"
                >
                    <Trash2 class="size-3.5" /> {{ t('workshop.return_part') }}
                </Button>
            </div>
            <p v-if="!ticket.parts.length" class="text-xs text-muted-foreground">{{ t('workshop.no_parts') }}</p>
            <div v-if="ticket.abilities.update" class="flex flex-wrap items-end gap-2">
                <select v-model="partItem" class="h-9 rounded-md border border-input bg-transparent px-2 text-sm">
                    <option value="">{{ t('workshop.select_part') }}</option>
                    <option v-for="o in inventoryOptions" :key="o.id" :value="o.id">{{ o.label }}</option>
                </select>
                <input
                    v-model="partQty"
                    type="number"
                    step="0.001"
                    min="0"
                    class="h-9 w-20 rounded-md border border-input bg-transparent px-2 text-sm"
                />
                <Button size="sm" variant="secondary" @click="addPart">{{ t('workshop.add_part') }}</Button>
            </div>
        </div>

        <!-- Actions -->
        <Button v-if="ticket.abilities.complete" class="h-12" @click="doComplete">{{ t('workshop.complete_repair') }}</Button>
        <Button v-if="ticket.abilities.deliver" class="h-12" @click="doDeliver">{{ t('workshop.deliver_email') }}</Button>

        <!-- Documents -->
        <div v-if="ticket.documents.length" class="flex flex-col gap-2">
            <Label>{{ t('workshop.documents') }}</Label>
            <a
                v-for="doc in ticket.documents"
                :key="doc.id"
                :href="doc.url"
                class="inline-flex items-center gap-2 text-sm text-primary hover:underline"
            >
                <FileText class="size-3.5" /> {{ doc.type }}<span v-if="doc.number"> · {{ doc.number }}</span>
            </a>
        </div>

        <!-- Timeline -->
        <div class="flex flex-col gap-2">
            <Label>{{ t('workshop.timeline') }}</Label>
            <div v-for="h in ticket.history" :key="h.id" class="text-xs text-muted-foreground">
                <span class="font-medium text-foreground">{{ h.actor ?? t('workshop.system') }}</span>
                {{ h.from ? `${h.from} → ${h.to}` : h.to }}
                <span v-if="h.created_at"> · {{ new Date(h.created_at).toLocaleString() }}</span>
            </div>
        </div>
    </div>
</template>
