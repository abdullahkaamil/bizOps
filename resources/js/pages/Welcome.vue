<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Building2, KeyRound, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, login } from '@/routes';

const page = usePage();
const appName = computed(() => page.props.name);
const user = computed(() => page.props.auth.user);
const tenant = computed(() => page.props.tenant);

const features = [
    {
        icon: Building2,
        title: 'Multi-tenant',
        text: 'Every organization gets its own isolated workspace.',
    },
    {
        icon: ShieldCheck,
        title: 'Role-based access',
        text: 'Fine-grained permissions for every team member.',
    },
    {
        icon: KeyRound,
        title: 'Secure by default',
        text: 'Passkeys, two-factor auth and per-tenant data.',
    },
];
</script>

<template>
    <Head :title="tenant ? tenant.name : 'Welcome'" />

    <div
        class="relative flex min-h-svh flex-col overflow-hidden bg-background text-foreground"
    >
        <!-- soft decorative glow -->
        <div
            class="pointer-events-none absolute -top-40 left-1/2 size-[36rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl"
            aria-hidden="true"
        />

        <header
            class="relative z-10 mx-auto flex w-full max-w-5xl items-center justify-between p-6"
        >
            <div class="flex items-center gap-2 font-semibold">
                <AppLogoIcon class="size-6 fill-current" />
                <span>{{ appName }}</span>
            </div>

            <Link
                v-if="user"
                :href="dashboard()"
                class="rounded-full bg-primary px-5 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
            >
                Dashboard
            </Link>
            <Link
                v-else
                :href="login()"
                class="rounded-full border border-border px-5 py-2 text-sm font-medium transition hover:bg-muted"
            >
                Log in
            </Link>
        </header>

        <main
            class="relative z-10 mx-auto flex w-full max-w-3xl flex-1 flex-col items-center justify-center gap-8 px-6 py-16 text-center"
        >
            <div
                class="flex size-16 items-center justify-center rounded-2xl border border-border bg-card shadow-sm"
            >
                <AppLogoIcon class="size-8 fill-current" />
            </div>

            <div class="space-y-4">
                <span
                    class="inline-flex items-center rounded-full border border-border px-3 py-1 text-xs font-medium text-muted-foreground"
                >
                    <template v-if="tenant"
                        >{{ tenant.name }} workspace</template
                    >
                    <template v-else>Business Operations Platform</template>
                </span>

                <h1
                    class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                >
                    Run your business, all in one place.
                </h1>

                <p
                    class="mx-auto max-w-xl text-base text-balance text-muted-foreground"
                >
                    {{ appName }} gives every team its own secure workspace —
                    with users, roles and permissions built in. Sign in to get
                    started.
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-center gap-3">
                <Link
                    v-if="user"
                    :href="dashboard()"
                    class="rounded-full bg-primary px-6 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Go to dashboard
                </Link>
                <Link
                    v-else
                    :href="login()"
                    class="rounded-full bg-primary px-6 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Log in
                </Link>
            </div>

            <div class="mt-6 grid w-full gap-4 sm:grid-cols-3">
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="rounded-xl border border-border bg-card p-5 text-left"
                >
                    <component
                        :is="feature.icon"
                        class="mb-3 size-5 text-primary"
                    />
                    <h2 class="text-sm font-semibold">{{ feature.title }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ feature.text }}
                    </p>
                </div>
            </div>
        </main>

        <footer
            class="relative z-10 mx-auto w-full max-w-5xl p-6 text-center text-xs text-muted-foreground"
        >
            &copy; {{ new Date().getFullYear() }} {{ appName }}
        </footer>
    </div>
</template>
