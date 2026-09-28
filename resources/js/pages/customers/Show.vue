<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import CustomerAddressController from '@/actions/App/Http/Controllers/Tenant/CustomerAddressController';
import CustomerContactController from '@/actions/App/Http/Controllers/Tenant/CustomerContactController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuthorization } from '@/composables/useAuthorization';
import { t as $t } from '@/i18n';
import { edit } from '@/routes/tenant/customers';

type Contact = {
    id: string;
    first_name: string;
    last_name: string;
    email: string | null;
    phone: string | null;
    job_title: string | null;
    is_primary: boolean;
};
type Address = {
    id: string;
    type: string;
    label: string | null;
    address_line_1: string;
    city: string;
    country_code: string;
    is_primary: boolean;
};
type Customer = {
    id: string;
    company_name: string;
    email: string | null;
    phone: string | null;
    status: string;
    tax_number: string | null;
    notes: string | null;
};
type Counts = {
    open_jobs: number;
    active_workshop: number;
    devices: number;
    draft_quotations: number;
    open_tasks: number;
};
type PageLink = { url: string | null; label: string; active: boolean };
type Paginator<T> = { data: T[]; links: PageLink[]; total: number };

const props = defineProps<{
    customer: Customer;
    contacts: Contact[];
    addresses: Address[];
    addressTypes: string[];
    counts: Counts;
    jobs?: Paginator<Record<string, string | null>>;
    devices?: Paginator<Record<string, string | number | null>>;
    workshop?: Paginator<Record<string, string | null>>;
    quotations?: Paginator<Record<string, string | null>>;
    boards?: Paginator<Record<string, string | number | boolean | null>>;
    documents?: Paginator<Record<string, string | null>>;
    activities?: Paginator<Record<string, string | null>>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: $t('nav.customers'), href: '/customers' }],
    },
});

const { can } = useAuthorization();
const { t } = useI18n();
const page = usePage();

const tabs = [
    'contacts',
    'addresses',
    'jobs',
    'devices',
    'workshop',
    'quotations',
    'boards',
    'documents',
    'activity',
] as const;

const activeTab = ref<string>('contacts');
const loaded = ref<Set<string>>(new Set());

function propKeyFor(tab: string): string {
    return tab === 'activity' ? 'activities' : tab;
}

// Lazy tabs fetch their (paginated) data only when first opened.
watch(activeTab, (tab) => {
    if (tab === 'contacts' || tab === 'addresses') {
        return;
    }

    if (loaded.value.has(tab)) {
        return;
    }

    router.reload({
        only: [propKeyFor(tab)],
        onSuccess: () => loaded.value.add(tab),
    });
});

function paginator(
    tab: string,
): Paginator<Record<string, unknown>> | undefined {
    if (tab === 'contacts' || tab === 'addresses') {
        return undefined;
    }

    return page.props[propKeyFor(tab)] as never;
}

function goTo(url: string | null, tab: string) {
    if (!url) {
        return;
    }

    router.get(
        url,
        {},
        { only: [propKeyFor(tab)], preserveState: true, preserveScroll: true },
    );
}

function makeContactPrimary(contact: Contact) {
    router.post(
        CustomerContactController.setPrimary({
            customer: props.customer.id,
            contact: contact.id,
        }).url,
        {},
        { preserveScroll: true },
    );
}
function removeContact(contact: Contact) {
    if (
        !window.confirm(
            t('crm.confirm_remove_contact', {
                name: `${contact.first_name} ${contact.last_name}`,
            }),
        )
    ) {
        return;
    }

    router.delete(
        CustomerContactController.destroy({
            customer: props.customer.id,
            contact: contact.id,
        }).url,
        { preserveScroll: true },
    );
}
function makeAddressPrimary(address: Address) {
    router.post(
        CustomerAddressController.setPrimary({
            customer: props.customer.id,
            address: address.id,
        }).url,
        {},
        { preserveScroll: true },
    );
}
function removeAddress(address: Address) {
    if (!window.confirm(t('crm.confirm_remove_address'))) {
        return;
    }

    router.delete(
        CustomerAddressController.destroy({
            customer: props.customer.id,
            address: address.id,
        }).url,
        { preserveScroll: true },
    );
}

const countBadges = computed(() => [
    { label: t('crm.count_open_jobs'), value: props.counts.open_jobs },
    {
        label: t('crm.count_active_workshop'),
        value: props.counts.active_workshop,
    },
    { label: t('crm.count_devices'), value: props.counts.devices },
    {
        label: t('crm.count_draft_quotes'),
        value: props.counts.draft_quotations,
    },
    { label: t('crm.count_open_tasks'), value: props.counts.open_tasks },
]);
</script>

<template>
    <Head :title="customer.company_name" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-2">
                    <Heading variant="small" :title="customer.company_name" />
                    <StatusBadge :status="customer.status" />
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ customer.email ?? '—' }} · {{ customer.phone ?? '—' }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-for="c in countBadges"
                        :key="c.label"
                        variant="outline"
                        >{{ c.label }}: {{ c.value }}</Badge
                    >
                </div>
            </div>
            <Button
                v-if="can('customers.update')"
                as-child
                variant="outline"
                size="sm"
            >
                <a :href="edit(customer.id).url">{{ t('common.edit') }}</a>
            </Button>
        </div>

        <!-- Tabs -->
        <div
            class="flex flex-wrap gap-1 border-b border-sidebar-border/70 dark:border-sidebar-border"
        >
            <button
                v-for="tab in tabs"
                :key="tab"
                class="border-b-2 px-3 py-2 text-sm"
                :class="
                    activeTab === tab
                        ? 'border-primary font-medium'
                        : 'border-transparent text-muted-foreground'
                "
                @click="activeTab = tab"
            >
                {{ t('crm.tab_' + tab) }}
            </button>
        </div>

        <!-- Contacts -->
        <div v-show="activeTab === 'contacts'" class="flex flex-col gap-4">
            <div
                v-for="contact in contacts"
                :key="contact.id"
                class="flex items-center justify-between rounded-lg border p-3"
            >
                <div>
                    <span class="font-medium"
                        >{{ contact.first_name }} {{ contact.last_name }}</span
                    >
                    <Badge
                        v-if="contact.is_primary"
                        variant="secondary"
                        class="ml-2"
                        >{{ t('crm.primary') }}</Badge
                    >
                    <p class="text-sm text-muted-foreground">
                        {{ contact.job_title ?? '' }} {{ contact.email ?? '' }}
                        {{ contact.phone ?? '' }}
                    </p>
                </div>
                <div v-if="can('customers.update')" class="flex gap-2">
                    <Button
                        v-if="!contact.is_primary"
                        variant="ghost"
                        size="sm"
                        @click="makeContactPrimary(contact)"
                        ><Star class="size-4"
                    /></Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="removeContact(contact)"
                        >{{ t('common.remove') }}</Button
                    >
                </div>
            </div>
            <Form
                v-if="can('customers.update')"
                v-bind="CustomerContactController.store.form(customer.id)"
                :reset-on-success="[
                    'first_name',
                    'last_name',
                    'email',
                    'phone',
                    'job_title',
                ]"
                class="grid gap-3 rounded-lg border border-dashed p-3 md:grid-cols-3"
                v-slot="{ errors }"
            >
                <Input
                    name="first_name"
                    :placeholder="t('crm.first_name')"
                    required
                />
                <Input
                    name="last_name"
                    :placeholder="t('crm.last_name')"
                    required
                />
                <Input name="job_title" :placeholder="t('crm.job_title')" />
                <Input
                    name="email"
                    type="email"
                    :placeholder="t('crm.email')"
                />
                <Input name="phone" :placeholder="t('crm.phone')" />
                <div class="flex items-center gap-2">
                    <Label class="flex items-center gap-2 text-sm"
                        ><input type="checkbox" name="is_primary" value="1" />
                        {{ t('crm.primary') }}</Label
                    >
                    <Button type="submit" size="sm">{{
                        t('crm.add_contact')
                    }}</Button>
                </div>
                <InputError
                    class="md:col-span-3"
                    :message="errors.first_name"
                />
            </Form>
        </div>

        <!-- Addresses -->
        <div v-show="activeTab === 'addresses'" class="flex flex-col gap-4">
            <div
                v-for="address in addresses"
                :key="address.id"
                class="flex items-center justify-between rounded-lg border p-3"
            >
                <div>
                    <span class="font-medium capitalize">{{
                        address.type
                    }}</span>
                    <Badge
                        v-if="address.is_primary"
                        variant="secondary"
                        class="ml-2"
                        >{{ t('crm.primary') }}</Badge
                    >
                    <p class="text-sm text-muted-foreground">
                        {{ address.address_line_1 }}, {{ address.city }} ({{
                            address.country_code
                        }})
                    </p>
                </div>
                <div v-if="can('customers.update')" class="flex gap-2">
                    <Button
                        v-if="!address.is_primary"
                        variant="ghost"
                        size="sm"
                        @click="makeAddressPrimary(address)"
                        ><Star class="size-4"
                    /></Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="removeAddress(address)"
                        >{{ t('common.remove') }}</Button
                    >
                </div>
            </div>
            <Form
                v-if="can('customers.update')"
                v-bind="CustomerAddressController.store.form(customer.id)"
                :reset-on-success="['address_line_1', 'city', 'postal_code']"
                class="grid gap-3 rounded-lg border border-dashed p-3 md:grid-cols-3"
                v-slot="{ errors }"
            >
                <select
                    name="type"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                >
                    <option v-for="at in addressTypes" :key="at" :value="at">
                        {{ at }}
                    </option>
                </select>
                <Input
                    name="address_line_1"
                    :placeholder="t('crm.address_line_1')"
                    required
                />
                <Input name="city" :placeholder="t('crm.city')" required />
                <Input name="postal_code" :placeholder="t('crm.postal_code')" />
                <Input
                    name="country_code"
                    :placeholder="t('crm.country_2')"
                    maxlength="2"
                    required
                />
                <div class="flex items-center gap-2">
                    <Label class="flex items-center gap-2 text-sm"
                        ><input type="checkbox" name="is_primary" value="1" />
                        {{ t('crm.primary') }}</Label
                    >
                    <Button type="submit" size="sm">{{
                        t('crm.add_address')
                    }}</Button>
                </div>
                <InputError
                    class="md:col-span-3"
                    :message="errors.address_line_1"
                />
            </Form>
        </div>

        <!-- Jobs -->
        <div v-show="activeTab === 'jobs'" class="flex flex-col gap-2">
            <div
                v-for="j in jobs?.data ?? []"
                :key="String(j.id)"
                class="flex items-center justify-between rounded-lg border p-3 text-sm"
            >
                <div>
                    <span class="font-medium">{{ j.title }}</span>
                    <span class="text-xs text-muted-foreground">{{
                        j.number
                    }}</span>
                </div>
                <StatusBadge :status="String(j.status)" />
            </div>
            <p
                v-if="loaded.has('jobs') && !jobs?.data.length"
                class="text-sm text-muted-foreground"
            >
                {{ t('crm.no_jobs') }}
            </p>
        </div>

        <!-- Devices -->
        <div v-show="activeTab === 'devices'" class="flex flex-col gap-2">
            <div
                v-for="d in devices?.data ?? []"
                :key="String(d.id)"
                class="flex items-center justify-between rounded-lg border p-3 text-sm"
            >
                <span class="font-medium">{{ d.brand }} {{ d.model }}</span>
                <span class="text-xs text-muted-foreground"
                    >SN {{ d.serial ?? 'N/A' }} · {{ d.tickets_count }}
                    {{ t('crm.tickets') }}</span
                >
            </div>
            <p
                v-if="loaded.has('devices') && !devices?.data.length"
                class="text-sm text-muted-foreground"
            >
                {{ t('crm.no_devices') }}
            </p>
        </div>

        <!-- Workshop -->
        <div v-show="activeTab === 'workshop'" class="flex flex-col gap-2">
            <div
                v-for="w in workshop?.data ?? []"
                :key="String(w.id)"
                class="flex items-center justify-between rounded-lg border p-3 text-sm"
            >
                <div>
                    <span class="font-medium">{{
                        w.device ?? t('crm.device')
                    }}</span>
                    <span class="text-xs text-muted-foreground">{{
                        w.number
                    }}</span>
                </div>
                <StatusBadge :status="String(w.status)" />
            </div>
            <p
                v-if="loaded.has('workshop') && !workshop?.data.length"
                class="text-sm text-muted-foreground"
            >
                {{ t('crm.no_workshop') }}
            </p>
        </div>

        <!-- Quotations -->
        <div v-show="activeTab === 'quotations'" class="flex flex-col gap-2">
            <div
                v-for="q in quotations?.data ?? []"
                :key="String(q.id)"
                class="flex items-center justify-between rounded-lg border p-3 text-sm"
            >
                <div>
                    <span class="font-mono text-xs">{{ q.number }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span>{{ q.currency }} {{ q.grand_total }}</span>
                    <StatusBadge :status="String(q.status)" />
                </div>
            </div>
            <p
                v-if="loaded.has('quotations') && !quotations?.data.length"
                class="text-sm text-muted-foreground"
            >
                {{ t('crm.no_quotations') }}
            </p>
        </div>

        <!-- Project boards -->
        <div v-show="activeTab === 'boards'" class="flex flex-col gap-2">
            <div
                v-for="b in boards?.data ?? []"
                :key="String(b.id)"
                class="flex items-center justify-between rounded-lg border p-3 text-sm"
            >
                <span class="font-medium">{{ b.name }}</span>
                <span class="text-xs text-muted-foreground"
                    >{{ b.tasks_count }} {{ t('crm.tasks') }}</span
                >
            </div>
            <p
                v-if="loaded.has('boards') && !boards?.data.length"
                class="text-sm text-muted-foreground"
            >
                {{ t('crm.no_boards') }}
            </p>
        </div>

        <!-- Documents -->
        <div v-show="activeTab === 'documents'" class="flex flex-col gap-2">
            <a
                v-for="doc in documents?.data ?? []"
                :key="String(doc.id)"
                :href="String(doc.url)"
                class="flex items-center justify-between rounded-lg border p-3 text-sm text-primary hover:underline"
            >
                <span
                    >{{ doc.type
                    }}<span v-if="doc.number"> · {{ doc.number }}</span></span
                >
            </a>
            <p
                v-if="loaded.has('documents') && !documents?.data.length"
                class="text-sm text-muted-foreground"
            >
                {{ t('crm.no_documents') }}
            </p>
        </div>

        <!-- Activity -->
        <div v-show="activeTab === 'activity'" class="flex flex-col gap-2">
            <div
                v-for="(entry, i) in activities?.data ?? []"
                :key="i"
                class="rounded-lg border p-3 text-sm"
            >
                <span>{{ entry.description }}</span>
                <span class="ml-2 text-xs text-muted-foreground">{{
                    entry.created_at
                }}</span>
            </div>
            <p
                v-if="loaded.has('activity') && !activities?.data.length"
                class="text-sm text-muted-foreground"
            >
                {{ t('crm.no_activity') }}
            </p>
        </div>

        <!-- Pagination for the active history tab -->
        <div
            v-if="
                paginator(activeTab) &&
                (paginator(activeTab)?.links.length ?? 0) > 3
            "
            class="flex flex-wrap gap-1"
        >
            <button
                v-for="(link, i) in paginator(activeTab)?.links ?? []"
                :key="i"
                class="rounded-md border px-2.5 py-1 text-xs"
                :class="
                    link.active
                        ? 'bg-primary text-primary-foreground'
                        : link.url
                          ? ''
                          : 'opacity-40'
                "
                :disabled="!link.url"
                @click="goTo(link.url, activeTab)"
                v-html="link.label"
            />
        </div>
    </div>
</template>
