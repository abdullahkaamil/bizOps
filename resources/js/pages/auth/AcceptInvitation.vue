<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AcceptInvitationController from '@/actions/App/Http/Controllers/Tenant/AcceptInvitationController';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t as $t } from '@/i18n';
import { login } from '@/routes';

const props = defineProps<{
    token: string;
    valid: boolean;
    email: string | null;
}>();

defineOptions({
    layout: {
        title: $t('auth.invite_title'),
        description: $t('auth.invite_description'),
    },
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('auth.invite_head')" />

    <div v-if="!valid" class="flex flex-col gap-4 text-center">
        <p class="text-sm text-muted-foreground">
            {{ t('auth.invitation_invalid') }}
        </p>
        <TextLink :href="login()">{{ t('auth.back_to_sign_in') }}</TextLink>
    </div>

    <Form
        v-else
        v-bind="AcceptInvitationController.store.form(props.token)"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-2">
            <Label for="email">{{ t('auth.email_address') }}</Label>
            <Input
                id="email"
                type="email"
                :model-value="email ?? ''"
                disabled
            />
        </div>

        <div class="grid gap-2">
            <Label for="name">{{ t('auth.your_name') }}</Label>
            <Input
                id="name"
                name="name"
                required
                autofocus
                :placeholder="t('auth.full_name')"
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="password">{{ t('auth.password') }}</Label>
            <Input
                id="password"
                name="password"
                type="password"
                required
                autocomplete="new-password"
            />
            <InputError :message="errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">{{ t('auth.confirm_password') }}</Label>
            <Input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                required
                autocomplete="new-password"
            />
        </div>

        <InputError :message="errors.token" />

        <Button type="submit" class="w-full" :disabled="processing">
            <Spinner v-if="processing" />
            {{ t('auth.accept_invitation') }}
        </Button>
    </Form>
</template>
