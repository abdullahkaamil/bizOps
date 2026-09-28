<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t as $t } from '@/i18n';
import { destroy, store } from '@/routes/admin/announcements';

type Announcement = { id: string; title: string; body: string; level: string; is_active: boolean };

defineProps<{ announcements: Announcement[] }>();

defineOptions({ layout: { breadcrumbs: [{ title: $t('nav.announcements'), href: '/announcements' }] } });

const { t } = useI18n();

function remove(id: string) {
    router.delete(destroy(id).url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('nav.announcements')" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4">
        <Heading variant="small" :title="t('admin.system_announcements')" :description="t('admin.shown_to_all')" />

        <Form v-bind="store.form()" :reset-on-success="['title', 'body']" class="grid gap-3 rounded-xl border p-4 md:grid-cols-2" v-slot="{ errors }">
            <div class="grid gap-1 md:col-span-2">
                <Label for="title">{{ t('admin.title') }}</Label>
                <Input id="title" name="title" required />
                <InputError :message="errors.title" />
            </div>
            <div class="grid gap-1 md:col-span-2">
                <Label for="body">{{ t('admin.body') }}</Label>
                <textarea id="body" name="body" rows="2" required class="rounded-md border border-input bg-transparent p-2 text-sm"></textarea>
            </div>
            <div class="grid gap-1">
                <Label for="level">{{ t('admin.level') }}</Label>
                <select id="level" name="level" class="h-9 rounded-md border border-input bg-transparent px-2 text-sm">
                    <option value="info">{{ t('admin.level_info') }}</option>
                    <option value="warning">{{ t('admin.level_warning') }}</option>
                    <option value="critical">{{ t('admin.level_critical') }}</option>
                </select>
            </div>
            <div class="flex items-end"><Button type="submit" size="sm">{{ t('admin.publish') }}</Button></div>
        </Form>

        <div v-for="a in announcements" :key="a.id" class="flex items-start justify-between rounded-lg border p-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="font-medium">{{ a.title }}</span>
                    <Badge :variant="a.level === 'critical' ? 'destructive' : 'outline'">{{ t('admin.level_' + a.level) }}</Badge>
                    <Badge v-if="!a.is_active" variant="outline">{{ t('admin.inactive') }}</Badge>
                </div>
                <p class="text-sm text-muted-foreground">{{ a.body }}</p>
            </div>
            <Button variant="ghost" size="sm" @click="remove(a.id)"><Trash2 class="size-4" /></Button>
        </div>
        <p v-if="!announcements.length" class="text-sm text-muted-foreground">{{ t('admin.no_announcements') }}</p>
    </div>
</template>
