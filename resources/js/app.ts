import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import { i18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    // Register the vue-i18n plugin and sync the active locale from the server.
    withApp: (app, { page }) => {
        const locale = (page.props as { locale?: string }).locale;

        if (locale) {
            i18n.global.locale.value = locale as 'tr' | 'en';
        }

        app.use(i18n);
    },
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name === 'tenant/LicenseExpired':
                return null;
            case name === 'quotations/PublicReview':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
