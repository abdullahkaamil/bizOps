<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t as $t } from '@/i18n';
import { deviceLookup, store } from '@/routes/tenant/workshop';

type Option = { id: string; name?: string; label?: string };
type Device = {
    id: string;
    brand: string;
    model: string;
    serial: string;
    belongs_to_current_customer: boolean;
};
type HistoryRow = {
    number: string;
    status: string;
    received_at: string | null;
};
type ActiveJob = { id: string; customer_id: string; label: string };

const props = defineProps<{
    customers: Option[];
    technicians: Option[];
    activeJobs: ActiveJob[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: $t('nav.workshop'), href: '/workshop' },
            { title: $t('workshop.intake'), href: '/workshop/create' },
        ],
    },
});

const { t } = useI18n();
const customer = ref('');
const linkedJob = ref('');
const serial = ref('');
const serialUnavailable = ref(false);
const brand = ref('');
const model = ref('');
const matchedDeviceId = ref('');
const history = ref<HistoryRow[]>([]);
const warning = ref<string | null>(null);
let lookupTimer: ReturnType<typeof setTimeout> | undefined;
const customerJobs = computed(() =>
    props.activeJobs.filter((job) => job.customer_id === customer.value),
);

async function runLookup() {
    matchedDeviceId.value = '';
    warning.value = null;
    history.value = [];

    if (!customer.value || !serial.value.trim() || serialUnavailable.value) {
        return;
    }

    const url = deviceLookup({
        query: { customer: customer.value, serial: serial.value.trim() },
    }).url;
    const res = await fetch(url, { headers: { Accept: 'application/json' } });

    if (!res.ok) {
        return;
    }

    const data = (await res.json()) as {
        device: Device | null;
        history: HistoryRow[];
        warning: string | null;
    };

    if (data.device) {
        warning.value = data.warning;

        if (data.device.belongs_to_current_customer) {
            brand.value = data.device.brand;
            model.value = data.device.model;
            matchedDeviceId.value = data.device.id;
            history.value = data.history;
        }
    }
}

watch([serial, customer], () => {
    clearTimeout(lookupTimer);
    lookupTimer = setTimeout(runLookup, 350);
});
watch(customer, () => {
    linkedJob.value = '';
});
watch(serialUnavailable, (v) => {
    if (v) {
        serial.value = '';
        matchedDeviceId.value = '';
        warning.value = null;
        history.value = [];
    }
});
</script>

<template>
    <Head :title="t('workshop.intake_title')" />

    <div class="mx-auto w-full max-w-2xl p-4">
        <Heading
            variant="small"
            :title="t('workshop.intake_title')"
            :description="t('workshop.intake_desc')"
        />

        <Form
            v-bind="store.form()"
            class="mt-6 grid gap-4"
            v-slot="{ errors, processing }"
        >
            <input type="hidden" name="device_id" :value="matchedDeviceId" />

            <div class="grid gap-2">
                <Label for="customer_id">{{ t('workshop.customer') }}</Label>
                <select
                    id="customer_id"
                    name="customer_id"
                    v-model="customer"
                    required
                    class="h-10 rounded-md border border-input bg-transparent px-3 text-sm"
                >
                    <option value="">
                        {{ t('workshop.select_customer') }}
                    </option>
                    <option v-for="c in customers" :key="c.id" :value="c.id">
                        {{ c.name }}
                    </option>
                </select>
                <InputError :message="errors.customer_id" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="serial_number">{{
                        t('workshop.serial_number')
                    }}</Label>
                    <label
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <input
                            type="checkbox"
                            name="serial_number_unavailable"
                            value="1"
                            v-model="serialUnavailable"
                        />
                        {{ t('workshop.not_available') }}
                    </label>
                </div>
                <Input
                    id="serial_number"
                    name="serial_number"
                    v-model="serial"
                    :disabled="serialUnavailable"
                    :placeholder="t('workshop.scan_serial')"
                />
                <InputError :message="errors.serial_number" />
                <p v-if="warning" class="text-sm text-amber-600">
                    {{ warning }}
                </p>
                <p v-else-if="matchedDeviceId" class="text-sm text-emerald-600">
                    {{ t('workshop.existing_device') }}
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="brand">{{ t('workshop.brand') }}</Label>
                    <Input id="brand" name="brand" v-model="brand" />
                    <InputError :message="errors.brand" />
                </div>
                <div class="grid gap-2">
                    <Label for="model">{{ t('workshop.model') }}</Label>
                    <Input id="model" name="model" v-model="model" />
                    <InputError :message="errors.model" />
                </div>
            </div>

            <div
                v-if="history.length"
                class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
            >
                <p class="mb-2 text-xs font-medium">
                    {{ t('workshop.repair_history') }}
                </p>
                <div
                    v-for="h in history"
                    :key="h.number"
                    class="flex items-center justify-between text-xs text-muted-foreground"
                >
                    <span>{{ h.number }}</span>
                    <Badge variant="outline">{{
                        t('status.' + h.status)
                    }}</Badge>
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="issue_description">{{
                    t('workshop.issue_description')
                }}</Label>
                <textarea
                    id="issue_description"
                    name="issue_description"
                    rows="3"
                    required
                    class="rounded-md border border-input bg-transparent p-2 text-sm"
                ></textarea>
                <InputError :message="errors.issue_description" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="assigned_user_id">{{
                        t('workshop.technician')
                    }}</Label>
                    <select
                        id="assigned_user_id"
                        name="assigned_user_id"
                        class="h-10 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">{{ t('workshop.unassigned') }}</option>
                        <option
                            v-for="tech in technicians"
                            :key="tech.id"
                            :value="tech.id"
                        >
                            {{ tech.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="job_id">{{ t('workshop.link_job') }}</Label>
                    <select
                        id="job_id"
                        name="job_id"
                        v-model="linkedJob"
                        :disabled="!customer"
                        class="h-10 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">{{ t('workshop.none') }}</option>
                        <option
                            v-for="job in customerJobs"
                            :key="job.id"
                            :value="job.id"
                        >
                            {{ job.label }}
                        </option>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        {{ t('workshop.link_job_help') }}
                    </p>
                </div>
            </div>

            <Button type="submit" :disabled="processing" class="h-11">{{
                t('workshop.create_ticket')
            }}</Button>
        </Form>
    </div>
</template>
