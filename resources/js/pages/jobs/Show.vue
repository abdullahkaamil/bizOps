<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Camera, FileText, MapPin, Phone, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import SignaturePad from '@/components/SignaturePad.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { t as $t } from '@/i18n';
import {
    cancel,
    complete,
    report,
    reopen,
    serviceData,
    start,
} from '@/routes/tenant/jobs';
import {
    destroy as destroyImage,
    store as storeImage,
} from '@/routes/tenant/jobs/images';
import { store as storeSignature } from '@/routes/tenant/jobs/signature';

type Chip = { id: string; name: string };
type Image = {
    id: string;
    name: string;
    processed: boolean;
    url: string;
    thumb_url: string;
};
type History = {
    id: string;
    from: string | null;
    to: string;
    metadata: Record<string, unknown> | null;
    actor: string | null;
    created_at: string | null;
};
type Document = {
    id: string;
    type: string;
    number: string | null;
    generated_at: string | null;
    url: string;
};
type JobDetail = {
    id: string;
    number: string;
    title: string;
    status: string;
    description: string | null;
    planned_at: string | null;
    service_notes: string | null;
    actual_start_at: string | null;
    actual_end_at: string | null;
    duration_minutes: number | null;
    customer: {
        name: string;
        email: string | null;
        phone: string | null;
    } | null;
    contact: {
        name: string;
        phone: string | null;
        email: string | null;
    } | null;
    address: { lines: string[] } | null;
    assignee: Chip | null;
    images: Image[];
    signature: {
        id: string;
        signed_by_name: string | null;
        url: string;
    } | null;
    documents: Document[];
    history: History[];
    abilities: Record<string, boolean>;
};

const props = defineProps<{
    job: JobDetail;
    requirements: {
        signature: boolean;
        photo: boolean;
        min_service_notes: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: $t('nav.jobs'), href: '/jobs' },
            { title: $t('common.details'), href: '#' },
        ],
    },
});

const { t } = useI18n();
const page = usePage();
const notes = ref(props.job.service_notes ?? '');
const signerName = ref('');
const fileInput = ref<HTMLInputElement | null>(null);

const completeError = computed(
    () => (page.props.errors as Record<string, string>)?.job ?? '',
);
const statusPanel = computed(() => {
    const panels: Record<
        string,
        { title: string; description: string; classes: string }
    > = {
        pending: {
            title: t('jobs.pending_title'),
            description: t('jobs.pending_description'),
            classes:
                'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100',
        },
        in_progress: {
            title: t('jobs.in_progress_title'),
            description: t('jobs.in_progress_description'),
            classes:
                'border-blue-300 bg-blue-50 text-blue-950 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-100',
        },
        completed: {
            title: t('jobs.completed_title'),
            description: t('jobs.completed_description'),
            classes:
                'border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-100',
        },
        canceled: {
            title: t('jobs.canceled_title'),
            description: t('jobs.canceled_description'),
            classes: 'border-muted bg-muted/40 text-foreground',
        },
    };

    return panels[props.job.status] ?? panels.pending;
});

const mapsUrl = computed(() =>
    props.job.address
        ? `https://maps.google.com/?q=${encodeURIComponent(props.job.address.lines.join(', '))}`
        : null,
);

function planned(value: string | null): string {
    return value ? new Date(value).toLocaleString() : t('jobs.unscheduled');
}

function doStart() {
    router.post(start(props.job.id).url, {}, { preserveScroll: true });
}
function doComplete() {
    router.post(complete(props.job.id).url, {}, { preserveScroll: true });
}
function doCancel() {
    router.post(cancel(props.job.id).url, {}, { preserveScroll: true });
}
function doReopen() {
    router.post(reopen(props.job.id).url, {}, { preserveScroll: true });
}
function generateReport() {
    router.post(report(props.job.id).url, {}, { preserveScroll: true });
}
function saveNotes() {
    router.put(
        serviceData(props.job.id).url,
        { service_notes: notes.value },
        { preserveScroll: true },
    );
}
function onPickPhoto(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    router.post(
        storeImage(props.job.id).url,
        { file },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => (input.value = ''),
        },
    );
}
function removePhoto(id: string) {
    router.delete(destroyImage(id).url, { preserveScroll: true });
}
function saveSignature(blob: Blob) {
    router.post(
        storeSignature(props.job.id).url,
        {
            file: new File([blob], 'signature.png', { type: 'image/png' }),
            signed_by_name: signerName.value,
        },
        { forceFormData: true, preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="job.number" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-5 p-4 pb-24">
        <div class="flex items-center justify-between gap-3">
            <Heading
                variant="small"
                :title="job.title"
                :description="job.number"
            />
            <StatusBadge :status="job.status" />
        </div>

        <div class="rounded-xl border p-4" :class="statusPanel.classes">
            <p class="font-semibold">{{ statusPanel.title }}</p>
            <p class="mt-1 text-sm opacity-80">{{ statusPanel.description }}</p>
        </div>

        <!-- Customer card -->
        <div
            class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div class="font-medium">
                {{ job.customer?.name ?? t('jobs.no_customer') }}
            </div>
            <div v-if="job.contact" class="text-sm text-muted-foreground">
                {{ job.contact.name }}
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="job.contact?.phone || job.customer?.phone"
                    as-child
                    size="sm"
                    variant="outline"
                >
                    <a
                        :href="`tel:${job.contact?.phone ?? job.customer?.phone}`"
                    >
                        <Phone class="size-4" /> {{ t('jobs.call') }}
                    </a>
                </Button>
                <Button v-if="mapsUrl" as-child size="sm" variant="outline">
                    <a :href="mapsUrl" target="_blank" rel="noopener">
                        <MapPin class="size-4" /> {{ t('jobs.directions') }}
                    </a>
                </Button>
            </div>
            <div v-if="job.address" class="text-sm text-muted-foreground">
                {{ job.address.lines.join(', ') }}
            </div>
            <div class="text-sm">
                <span class="text-muted-foreground"
                    >{{ t('jobs.planned') }}:</span
                >
                {{ planned(job.planned_at) }}
            </div>
            <div v-if="job.duration_minutes !== null" class="text-sm">
                <span class="text-muted-foreground"
                    >{{ t('jobs.duration') }}:</span
                >
                {{ job.duration_minutes }} {{ t('jobs.min') }}
            </div>
        </div>

        <div
            v-if="job.description"
            class="flex flex-col gap-1 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <Label>{{ t('jobs.description') }}</Label>
            <p class="text-sm whitespace-pre-line text-muted-foreground">
                {{ job.description }}
            </p>
        </div>

        <!-- Start -->
        <Button
            v-if="job.abilities.start"
            class="h-14 text-base"
            @click="doStart"
            >{{ t('jobs.start_job') }}</Button
        >

        <div v-if="job.status !== 'pending'" class="flex flex-col gap-5">
            <!-- Service notes -->
            <div v-if="job.abilities.service_data" class="flex flex-col gap-2">
                <Label for="notes">{{ t('jobs.service_notes') }}</Label>
                <textarea
                    id="notes"
                    v-model="notes"
                    rows="4"
                    class="rounded-md border border-input bg-transparent p-3 text-sm"
                    :placeholder="t('jobs.service_notes_placeholder')"
                ></textarea>
                <Button
                    size="sm"
                    variant="secondary"
                    class="self-start"
                    @click="saveNotes"
                    >{{ t('jobs.save_notes') }}</Button
                >
            </div>
            <div v-else-if="job.service_notes" class="flex flex-col gap-1">
                <Label>{{ t('jobs.service_notes') }}</Label>
                <p class="text-sm whitespace-pre-line text-muted-foreground">
                    {{ job.service_notes }}
                </p>
            </div>

            <!-- Photos -->
            <div class="flex flex-col gap-2">
                <Label>{{ t('jobs.photos') }}</Label>
                <div class="grid grid-cols-3 gap-2">
                    <div
                        v-for="img in job.images"
                        :key="img.id"
                        class="relative"
                    >
                        <img
                            :src="img.thumb_url"
                            :alt="img.name"
                            class="h-24 w-full rounded-lg border object-cover"
                        />
                        <button
                            v-if="job.abilities.service_data"
                            type="button"
                            class="absolute top-1 right-1 rounded-full bg-black/60 p-1 text-white"
                            @click="removePhoto(img.id)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </div>
                </div>
                <template v-if="job.abilities.service_data">
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/*"
                        capture="environment"
                        class="hidden"
                        @change="onPickPhoto"
                    />
                    <Button
                        size="sm"
                        variant="outline"
                        class="self-start"
                        @click="fileInput?.click()"
                    >
                        <Camera class="size-4" /> {{ t('jobs.add_photo') }}
                    </Button>
                </template>
            </div>

            <!-- Signature -->
            <div
                v-if="job.abilities.service_data || job.signature"
                class="flex flex-col gap-2"
            >
                <Label>{{ t('jobs.customer_signature') }}</Label>
                <img
                    v-if="job.signature"
                    :src="job.signature.url"
                    alt="signature"
                    class="h-32 w-full rounded-lg border bg-white object-contain"
                />
                <template v-else>
                    <input
                        v-model="signerName"
                        :placeholder="t('jobs.signed_by_placeholder')"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    />
                    <SignaturePad @save="saveSignature" />
                </template>
            </div>

            <!-- Requirements hint -->
            <p
                v-if="job.status === 'in_progress'"
                class="text-xs text-muted-foreground"
            >
                {{ t('jobs.to_complete') }}
                <span v-if="requirements.min_service_notes">{{
                    t('jobs.notes_chars', {
                        count: requirements.min_service_notes,
                    })
                }}</span>
                <span v-if="requirements.photo">
                    · {{ t('jobs.at_least_photo') }}</span
                >
                <span v-if="requirements.signature">
                    · {{ t('jobs.a_signature') }}</span
                >
            </p>
            <p v-if="completeError" class="text-sm text-destructive">
                {{ completeError }}
            </p>

            <!-- Complete -->
            <Button
                v-if="job.abilities.complete"
                class="h-14 text-base"
                @click="doComplete"
                >{{ t('jobs.complete_job') }}</Button
            >
        </div>

        <!-- Manager actions -->
        <div class="flex flex-wrap gap-2">
            <Button
                v-if="job.abilities.cancel"
                size="sm"
                variant="outline"
                @click="doCancel"
                >{{ t('jobs.cancel_job') }}</Button
            >
            <Button
                v-if="job.abilities.reopen"
                size="sm"
                variant="outline"
                @click="doReopen"
                >{{ t('jobs.reopen') }}</Button
            >
        </div>

        <!-- Documents -->
        <div
            v-if="job.documents.length || job.abilities.generate_report"
            class="flex flex-col gap-2"
        >
            <div class="flex items-center justify-between">
                <Label>{{ t('jobs.documents') }}</Label>
                <Button
                    v-if="job.abilities.generate_report"
                    size="sm"
                    variant="outline"
                    @click="generateReport"
                >
                    <FileText class="size-4" /> {{ t('jobs.generate_report') }}
                </Button>
            </div>
            <a
                v-for="doc in job.documents"
                :key="doc.id"
                :href="doc.url"
                class="inline-flex items-center gap-2 text-sm text-primary hover:underline"
            >
                <FileText class="size-3.5" />
                {{ doc.type }}<span v-if="doc.number"> · {{ doc.number }}</span>
            </a>
            <p
                v-if="!job.documents.length"
                class="text-xs text-muted-foreground"
            >
                {{ t('jobs.no_documents') }}
            </p>
        </div>

        <!-- Timeline -->
        <div class="flex flex-col gap-2">
            <Label>{{ t('jobs.timeline') }}</Label>
            <div
                v-for="h in job.history"
                :key="h.id"
                class="text-xs text-muted-foreground"
            >
                <span class="font-medium text-foreground">{{
                    h.actor ?? t('jobs.system')
                }}</span>
                {{ h.from ? `${h.from} → ${h.to}` : h.to }}
                <span v-if="h.created_at">
                    · {{ new Date(h.created_at).toLocaleString() }}</span
                >
            </div>
        </div>
    </div>
</template>
