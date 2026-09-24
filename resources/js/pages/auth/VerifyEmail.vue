<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t as $t } from '@/i18n';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: $t('auth.verify_title'),
        description: $t('auth.verify_description'),
    },
});

defineProps<{
    status?: string;
}>();

const { t } = useI18n();
</script>

<template>
    <Head :title="t('auth.verify_title')" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ t('auth.verification_link_sent') }}
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            {{ t('auth.resend_verification') }}
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">{{ t('auth.log_out') }}</TextLink>
    </Form>
</template>
