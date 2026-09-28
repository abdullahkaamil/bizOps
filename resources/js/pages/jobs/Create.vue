<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { MapPin, Phone, Search, UserRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t as $t } from '@/i18n';
import { store } from '@/routes/tenant/jobs';

type Option = { id: string; name?: string; label?: string };
type CustomerOption = {
    id: string;
    name: string;
    email: string | null;
    phone: string | null;
    tax_number: string | null;
    contacts: {
        id: string;
        name: string;
        email: string | null;
        phone: string | null;
    }[];
    addresses: { id: string; label: string }[];
};

const props = defineProps<{
    customers: CustomerOption[];
    technicians: Option[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: $t('nav.jobs'), href: '/jobs' },
            { title: $t('jobs.new_job'), href: '/jobs/create' },
        ],
    },
});

const { t } = useI18n();
const selectedCustomer = ref<string>('');
const customerSearch = ref('');
const customerListOpen = ref(false);
const current = computed(() =>
    props.customers.find((c) => c.id === selectedCustomer.value),
);
const currentContact = computed(() => current.value?.contacts[0] ?? null);
const currentAddress = computed(() => current.value?.addresses[0] ?? null);
const filteredCustomers = computed(() => {
    const query = customerSearch.value.trim().toLocaleLowerCase();

    return props.customers
        .filter(
            (customer) =>
                !query || customer.name.toLocaleLowerCase().includes(query),
        )
        .slice(0, 8);
});

function searchCustomers() {
    if (current.value?.name !== customerSearch.value) {
        selectedCustomer.value = '';
    }

    customerListOpen.value = true;
}

function chooseCustomer(customer: CustomerOption) {
    selectedCustomer.value = customer.id;
    customerSearch.value = customer.name;
    customerListOpen.value = false;
}
</script>

<template>
    <Head :title="t('jobs.new_job')" />

    <div class="mx-auto w-full max-w-2xl p-4">
        <Heading
            variant="small"
            :title="t('jobs.new_job')"
            :description="t('jobs.new_job_desc')"
        />

        <Form
            v-bind="store.form()"
            class="mt-6 grid gap-4"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="title">{{ t('jobs.title_field') }}</Label>
                <Input id="title" name="title" required />
                <InputError :message="errors.title" />
            </div>

            <div class="grid gap-2">
                <Label for="customer_search">{{ t('jobs.customer') }}</Label>
                <input
                    type="hidden"
                    name="customer_id"
                    :value="selectedCustomer"
                />
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground"
                    />
                    <Input
                        id="customer_search"
                        v-model="customerSearch"
                        class="pl-9"
                        autocomplete="off"
                        :placeholder="t('jobs.search_customer')"
                        @focus="customerListOpen = true"
                        @input="searchCustomers"
                    />
                    <div
                        v-if="customerListOpen"
                        class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-input bg-popover p-1 text-popover-foreground shadow-lg"
                    >
                        <button
                            v-for="customer in filteredCustomers"
                            :key="customer.id"
                            type="button"
                            class="flex w-full rounded-sm px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                            @click="chooseCustomer(customer)"
                        >
                            {{ customer.name }}
                        </button>
                        <p
                            v-if="!filteredCustomers.length"
                            class="px-3 py-2 text-sm text-muted-foreground"
                        >
                            {{ t('jobs.customer_not_found') }}
                        </p>
                    </div>
                </div>
                <InputError :message="errors.customer_id" />
            </div>

            <div
                v-if="current"
                class="grid gap-3 rounded-xl border border-sidebar-border/70 bg-muted/30 p-4 dark:border-sidebar-border"
            >
                <input
                    type="hidden"
                    name="customer_contact_id"
                    :value="currentContact?.id ?? ''"
                />
                <input
                    type="hidden"
                    name="service_address_id"
                    :value="currentAddress?.id ?? ''"
                />
                <div>
                    <p class="font-semibold">{{ current.name }}</p>
                    <p
                        v-if="current.tax_number"
                        class="text-xs text-muted-foreground"
                    >
                        {{ t('jobs.tax_number') }}: {{ current.tax_number }}
                    </p>
                </div>
                <div class="grid gap-3 text-sm md:grid-cols-2">
                    <div class="flex gap-2">
                        <UserRound
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        />
                        <div>
                            <p class="font-medium">
                                {{
                                    currentContact?.name ?? t('jobs.no_contact')
                                }}
                            </p>
                            <p class="text-muted-foreground">
                                {{
                                    currentContact?.email ??
                                    current.email ??
                                    '—'
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Phone
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        />
                        <p>
                            {{ currentContact?.phone ?? current.phone ?? '—' }}
                        </p>
                    </div>
                    <div class="flex gap-2 md:col-span-2">
                        <MapPin
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        />
                        <p>
                            {{
                                currentAddress?.label ??
                                t('jobs.no_service_address')
                            }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="assigned_user_id">{{
                    t('jobs.assign_technician')
                }}</Label>
                <select
                    id="assigned_user_id"
                    name="assigned_user_id"
                    class="h-10 rounded-md border border-input bg-transparent px-3 text-sm"
                >
                    <option value="">{{ t('jobs.unassigned') }}</option>
                    <option
                        v-for="tech in technicians"
                        :key="tech.id"
                        :value="tech.id"
                    >
                        {{ tech.name }}
                    </option>
                </select>
                <p class="text-xs text-muted-foreground">
                    {{ t('jobs.assign_technician_help') }}
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="planned_at">{{ t('jobs.planned_time') }}</Label>
                <Input
                    id="planned_at"
                    name="planned_at"
                    type="datetime-local"
                />
            </div>

            <div class="grid gap-2">
                <Label for="description">{{ t('jobs.description') }}</Label>
                <textarea
                    id="description"
                    name="description"
                    rows="3"
                    class="rounded-md border border-input bg-transparent p-2 text-sm"
                ></textarea>
            </div>

            <Button type="submit" :disabled="processing" class="h-11">{{
                t('jobs.create_job')
            }}</Button>
        </Form>
    </div>
</template>
