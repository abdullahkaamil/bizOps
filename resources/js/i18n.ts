import { createI18n } from 'vue-i18n';
import en from '@/lang/en.json';
import tr from '@/lang/tr.json';

/**
 * Shared vue-i18n instance. Turkish default, English fallback. The active locale
 * is synced from the server's shared `locale` prop in app.ts (`withApp`). Import
 * `i18n.global.t` to translate outside a component setup (e.g. in
 * `defineOptions({ layout: { title } })`).
 */
export const i18n = createI18n({
    legacy: false,
    locale: 'tr',
    fallbackLocale: 'en',
    messages: { en, tr },
});

/** Translate outside setup() — uses the current global locale. */
export const t = (key: string, ...args: unknown[]): string =>
    // @ts-expect-error vue-i18n's global t is callable with (key) / (key, named).
    i18n.global.t(key, ...args);
