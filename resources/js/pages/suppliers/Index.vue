<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t as $t } from '@/i18n';
import { show, store } from '@/routes/tenant/suppliers';

type Supplier = {
    id: string;
    name: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    items_count: number;
};

defineProps<{ suppliers: Supplier[]; canManage: boolean }>();

defineOptions({ layout: { breadcrumbs: [{ title: $t('nav.suppliers'), href: '/suppliers' }] } });

const { t } = useI18n();
const adding = ref(false);
</script>

<template>
    <Head :title="t('nav.suppliers')" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4">
        <div class="flex items-center justify-between gap-3">
            <Heading variant="small" :title="t('nav.suppliers')" :description="t('suppliers.desc')" />
            <Button v-if="canManage" size="sm" @click="adding = !adding">{{ adding ? t('common.close') : t('suppliers.new_supplier') }}</Button>
        </div>

        <Form v-if="canManage && adding" v-bind="store.form()" :reset-on-success="['name', 'contact_name', 'email', 'phone']" class="grid gap-3 rounded-xl border border-sidebar-border/70 p-4 md:grid-cols-2 dark:border-sidebar-border" v-slot="{ errors }">
            <div class="grid gap-1">
                <Label for="name">{{ t('suppliers.name') }}</Label>
                <Input id="name" name="name" required />
                <InputError :message="errors.name" />
            </div>
            <div class="grid gap-1"><Label for="contact_name">{{ t('suppliers.contact') }}</Label><Input id="contact_name" name="contact_name" /></div>
            <div class="grid gap-1"><Label for="email">{{ t('suppliers.email') }}</Label><Input id="email" name="email" type="email" /></div>
            <div class="grid gap-1"><Label for="phone">{{ t('suppliers.phone') }}</Label><Input id="phone" name="phone" /></div>
            <div class="md:col-span-2"><Button type="submit" size="sm">{{ t('common.create') }}</Button></div>
        </Form>

        <Link
            v-for="s in suppliers"
            :key="s.id"
            :href="show(s.id).url"
            class="flex items-center justify-between rounded-xl border border-sidebar-border/70 p-4 hover:border-primary/50 dark:border-sidebar-border"
        >
            <div>
                <div class="font-medium">{{ s.name }}</div>
                <div class="text-xs text-muted-foreground">{{ s.contact_name ?? s.email ?? '—' }}</div>
            </div>
            <span class="text-xs text-muted-foreground">{{ s.items_count }} {{ t('suppliers.items') }}</span>
        </Link>
        <p v-if="!suppliers.length" class="py-8 text-center text-sm text-muted-foreground">{{ t('suppliers.no_suppliers') }}</p>
    </div>
</template>
