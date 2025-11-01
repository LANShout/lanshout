import { createI18n } from 'vue-i18n';
import en from './locales/en.json';
import de from './locales/de.json';

export type MessageSchema = typeof en;

// Get default locale from environment variable or fallback to 'en'
const defaultLocale = (import.meta.env.VITE_DEFAULT_LOCALE as string) || 'en';

const i18n = createI18n<[MessageSchema], 'en' | 'de' | 'fr' | 'es'>({
    legacy: false,
    locale: defaultLocale,
    fallbackLocale: 'en',
    messages: {
        en,
        de,
    },
});

export default i18n;
