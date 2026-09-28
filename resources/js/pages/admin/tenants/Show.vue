<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { CheckCircle2, CircleDashed, ExternalLink, XCircle } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import TenantController from '@/actions/App/Http/Controllers/Admin/TenantController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t as $t } from '@/i18n';

type Step = {
    id: number;
    step: string;
    status: string;
    message: string | null;
    finished_at: string | null;
};

type TenantDetail = {
    id: string;
    name: string;
    slug: string | null;
    status: string;
    subdomain: string | null;
    url: string | null;
    license_expires_at: string | null;
    plan_code: string | null;
    trial_ends_at: string | null;
    subscription_ends_at: string | null;
    features: Record<string, boolean>;
    deletion_requested_at: string | null;
    purge_after: string | null;
    retention_elapsed: boolean;
};
type Health = {
    db_size: number | null;
    migration_version: string | null;
    last_activity: string | null;
    users: number | null;
    queue_failures: number;
};
type Support = { id: string; admin: string | null; reason: string; expires_at: string; active: boolean };

const props = defineProps<{
    tenant: TenantDetail;
    timeline: Step[];
    availableFeatures: string[];
    health: Health;
    supportSessions: Support[];
}>();

const { t } = useI18n();
const featureState = ref<Record<string, boolean>>(
    Object.fromEntries(props.availableFeatures.map((f) => [f, props.tenant.features[f] ?? true])),
);
const confirmName = ref('');
const supportReason = ref('');

function post(url: string) {
    router.post(url, {}, { preserveScroll: true });
}
function saveFeatures() {
    router.put(TenantController.updateFeatures(props.tenant.id).url, { features: featureState.value }, { preserveScroll: true });
}
function requestDeletion() {
    router.post(
        TenantController.requestDeletion(props.tenant.id).url,
        { confirm: true, confirm_name: confirmName.value },
        { preserveScroll: true, onSuccess: () => (confirmName.value = '') },
    );
}
function startSupport() {
    router.post(
        TenantController.support(props.tenant.id).url,
        { reason: supportReason.value },
        { preserveScroll: true, onSuccess: () => (supportReason.value = '') },
    );
}
function humanBytes(n: number | null): string {
    if (!n) {
return '—';
}

    const u = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let v = n;

    while (v >= 1024 && i < u.length - 1) {
        v /= 1024;
        i++;
    }

    return `${v.toFixed(1)} ${u[i]}`;
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: $t('nav.tenants'), href: '/admin/tenants' }],
    },
});

const statusVariant: Record<string, 'secondary' | 'destructive' | 'outline'> = {
    active: 'secondary',
    provisioning: 'outline',
    suspended: 'destructive',
    failed: 'destructive',
    archived: 'outline',
    deletion_pending: 'destructive',
    deleted: 'destructive',
};

function stepIcon(status: string) {
    if (status === 'completed') {
        return CheckCircle2;
    }

    if (status === 'failed') {
        return XCircle;
    }

    return CircleDashed;
}

function suspend() {
    router.post(
        TenantController.suspend(props.tenant.id).url,
        {},
        { preserveScroll: true },
    );
}

function reactivate() {
    router.post(
        TenantController.reactivate(props.tenant.id).url,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="tenant.name" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                :title="tenant.name"
                :description="tenant.subdomain ?? '—'"
            />
            <div class="flex items-center gap-2">
                <Badge :variant="statusVariant[tenant.status] ?? 'outline'">{{ t('status.' + tenant.status) }}</Badge>
                <Button
                    v-if="tenant.status === 'active' && tenant.url"
                    as-child
                    variant="outline"
                    size="sm"
                >
                    <a :href="tenant.url" target="_blank" rel="noopener">
                        {{ t('admin.open') }} <ExternalLink class="ml-1 size-3.5" />
                    </a>
                </Button>
                <Button
                    v-if="tenant.status === 'active'"
                    variant="outline"
                    size="sm"
                    @click="suspend"
                >{{ t('admin.suspend') }}</Button>
                <Button
                    v-if="tenant.status === 'suspended'"
                    size="sm"
                    @click="reactivate"
                >{{ t('admin.reactivate') }}</Button>
                <Button
                    v-if="tenant.status === 'suspended'"
                    variant="outline"
                    size="sm"
                    @click="post(TenantController.archive(tenant.id).url)"
                >{{ t('admin.archive') }}</Button>
            </div>
        </div>

        <!-- Health -->
        <div class="grid gap-3 md:grid-cols-5">
            <div class="rounded-lg border p-3">
                <div class="text-xs text-muted-foreground">{{ t('admin.db_size') }}</div>
                <div class="text-sm font-medium">{{ humanBytes(health.db_size) }}</div>
            </div>
            <div class="rounded-lg border p-3">
                <div class="text-xs text-muted-foreground">{{ t('admin.users') }}</div>
                <div class="text-sm font-medium">{{ health.users ?? '—' }}</div>
            </div>
            <div class="rounded-lg border p-3">
                <div class="text-xs text-muted-foreground">{{ t('admin.queue_failures') }}</div>
                <div class="text-sm font-medium" :class="health.queue_failures ? 'text-destructive' : ''">{{ health.queue_failures }}</div>
            </div>
            <div class="rounded-lg border p-3">
                <div class="text-xs text-muted-foreground">{{ t('admin.last_activity') }}</div>
                <div class="text-sm font-medium">{{ health.last_activity ? new Date(health.last_activity).toLocaleDateString() : '—' }}</div>
            </div>
            <div class="rounded-lg border p-3">
                <div class="text-xs text-muted-foreground">{{ t('admin.migration') }}</div>
                <div class="truncate text-xs font-medium">{{ health.migration_version ?? '—' }}</div>
            </div>
        </div>

        <!-- Provisioning timeline -->
        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <h2 class="mb-4 text-sm font-medium">{{ t('admin.provisioning_timeline') }}</h2>
            <ol v-if="timeline.length" class="flex flex-col gap-3">
                <li
                    v-for="step in timeline"
                    :key="step.id"
                    class="flex items-start gap-3"
                >
                    <component
                        :is="stepIcon(step.status)"
                        class="mt-0.5 size-4"
                        :class="{
                            'text-green-600': step.status === 'completed',
                            'text-destructive': step.status === 'failed',
                            'text-muted-foreground': step.status === 'running',
                        }"
                    />
                    <div class="flex flex-col">
                        <span class="text-sm font-medium capitalize">{{
                            step.step
                        }}</span>
                        <span
                            v-if="step.message"
                            class="text-xs text-muted-foreground"
                            >{{ step.message }}</span
                        >
                    </div>
                    <span
                        class="ml-auto text-xs text-muted-foreground capitalize"
                        >{{ step.status }}</span
                    >
                </li>
            </ol>
            <p v-else class="text-sm text-muted-foreground">
                {{ t('admin.no_provisioning_steps') }}
            </p>
        </div>

        <!-- Retry failed provisioning -->
        <div
            v-if="tenant.status === 'failed'"
            class="rounded-xl border border-destructive/40 p-4"
        >
            <h2 class="mb-1 text-sm font-medium">{{ t('admin.retry_provisioning') }}</h2>
            <p class="mb-4 text-sm text-muted-foreground">
                {{ t('admin.retry_desc') }}
            </p>
            <Form
                v-bind="TenantController.retry.form(tenant.id)"
                class="grid max-w-xl gap-4 md:grid-cols-2"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="admin_name">{{ t('admin.owner_name') }}</Label>
                    <Input id="admin_name" name="admin_name" required />
                    <InputError :message="errors.admin_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="admin_email">{{ t('admin.owner_email') }}</Label>
                    <Input
                        id="admin_email"
                        name="admin_email"
                        type="email"
                        required
                        autocomplete="off"
                    />
                    <InputError :message="errors.admin_email" />
                </div>
                <div class="grid gap-2">
                    <Label for="admin_password">{{ t('admin.owner_password') }}</Label>
                    <Input
                        id="admin_password"
                        name="admin_password"
                        type="password"
                        required
                        autocomplete="new-password"
                    />
                    <InputError :message="errors.admin_password" />
                </div>
                <div class="flex items-end">
                    <Button type="submit" :disabled="processing"
                        >{{ t('admin.retry_provisioning') }}</Button>
                </div>
            </Form>
        </div>

        <!-- Plan & subscription -->
        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <h2 class="mb-3 text-sm font-medium">{{ t('admin.plan_subscription') }}</h2>
            <Form v-bind="TenantController.updatePlan.form(tenant.id)" class="grid gap-4 md:grid-cols-3" v-slot="{ processing }">
                <div class="grid gap-1">
                    <Label for="plan_code">{{ t("admin.plan_code") }}</Label>
                    <Input id="plan_code" name="plan_code" :default-value="tenant.plan_code ?? ''" />
                </div>
                <div class="grid gap-1">
                    <Label for="trial_ends_at">{{ t("admin.trial_ends") }}</Label>
                    <Input id="trial_ends_at" name="trial_ends_at" type="date" :default-value="tenant.trial_ends_at ?? ''" />
                </div>
                <div class="grid gap-1">
                    <Label for="subscription_ends_at">{{ t("admin.subscription_ends") }}</Label>
                    <Input id="subscription_ends_at" name="subscription_ends_at" type="date" :default-value="tenant.subscription_ends_at ?? ''" />
                </div>
                <div class="md:col-span-3"><Button type="submit" size="sm" :disabled="processing">{{ t("admin.save_plan") }}</Button></div>
            </Form>
        </div>

        <!-- Feature flags -->
        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <h2 class="mb-3 text-sm font-medium">{{ t("admin.feature_flags") }}</h2>
            <div class="flex flex-wrap gap-4">
                <label v-for="f in availableFeatures" :key="f" class="flex items-center gap-2 text-sm capitalize">
                    <input type="checkbox" v-model="featureState[f]" /> {{ f }}
                </label>
            </div>
            <Button size="sm" class="mt-3" @click="saveFeatures">{{ t("admin.save_features") }}</Button>
        </div>

        <!-- Support access -->
        <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <h2 class="mb-1 text-sm font-medium">{{ t("admin.support_access") }}</h2>
            <p class="mb-3 text-xs text-muted-foreground">{{ t("admin.support_desc") }}</p>
            <div class="flex flex-wrap items-end gap-2">
                <Input v-model="supportReason" :placeholder="t('admin.support_reason_ph')" class="max-w-md" />
                <Button size="sm" variant="secondary" :disabled="supportReason.trim().length < 5" @click="startSupport">{{ t("admin.start_session") }}</Button>
            </div>
            <div v-if="supportSessions.length" class="mt-3 flex flex-col gap-1">
                <div v-for="s in supportSessions" :key="s.id" class="text-xs text-muted-foreground">
                    <Badge :variant="s.active ? 'secondary' : 'outline'" class="mr-2">{{ s.active ? t("admin.active") : t("admin.ended") }}</Badge>
                    {{ s.admin }} · "{{ s.reason }}" · {{ t("admin.expires") }} {{ new Date(s.expires_at).toLocaleString() }}
                </div>
            </div>
        </div>

        <!-- Danger zone: staged deletion -->
        <div class="rounded-xl border border-destructive/40 p-4">
            <h2 class="mb-1 text-sm font-medium text-destructive">{{ t("admin.danger_zone") }}</h2>
            <p class="mb-3 text-xs text-muted-foreground">
                Deletion is staged: active → suspended → archived → deletion pending → deleted, with a retention window for export/backup.
            </p>

            <div v-if="tenant.status === 'archived'" class="flex flex-col gap-2">
                <p class="text-sm">{{ t('admin.type_name_to_delete', { name: tenant.name }) }}</p>
                <div class="flex flex-wrap items-end gap-2">
                    <Input v-model="confirmName" :placeholder="tenant.name" class="max-w-xs" />
                    <Button size="sm" variant="destructive" :disabled="confirmName !== tenant.name" @click="requestDeletion">{{ t("admin.schedule_deletion") }}</Button>
                </div>
            </div>

            <div v-else-if="tenant.status === 'deletion_pending'" class="flex flex-col gap-2">
                <p class="text-sm">
                    {{ t('admin.purge_scheduled', { date: tenant.purge_after ? new Date(tenant.purge_after).toLocaleString() : '—' }) }}
                </p>
                <Button
                    size="sm"
                    variant="destructive"
                    :disabled="!tenant.retention_elapsed"
                    @click="post(TenantController.purge(tenant.id).url)"
                >
                    {{ tenant.retention_elapsed ? t("admin.purge_now") : t("admin.retention_not_elapsed") }}
                </Button>
            </div>

            <p v-else-if="tenant.status === 'deleted'" class="text-sm text-muted-foreground">{{ t("admin.tenant_purged") }}</p>
            <p v-else class="text-sm text-muted-foreground">{{ t("admin.suspend_first") }}</p>
        </div>
    </div>
</template>
