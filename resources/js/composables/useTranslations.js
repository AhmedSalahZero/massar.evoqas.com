// ══════════════════════════════════════════════════════════════════
//  Massar — useTranslations
//  Location: resources/js/composables/useTranslations.js
//
//      const { t, locale, isRtl } = useTranslations();
//      t('team.seats_used', { used: 3, limit: 5 })
//
//  Follows the current language from usePreferences, so switching
//  EN/عربي re-renders every label at once. Falls back to English,
//  then to the key itself, so a missing label is visible but never
//  breaks the page. Placeholders use Laravel's :name style.
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import { translations } from '@/lang/translations';
import { usePreferences } from '@/composables/usePreferences';

export function useTranslations() {
    const { prefs } = usePreferences();

    const locale = computed(() => prefs.locale);
    const isRtl = computed(() => prefs.locale === 'ar');

    function t(key, replacements = {}) {
        let value = translations[prefs.locale]?.[key] ?? translations.en[key] ?? key;

        for (const [name, replacement] of Object.entries(replacements)) {
            // Whole names only: ':to' must not eat the start of ':total'.
            value = value.replace(new RegExp(`:${name}(?![A-Za-z0-9_])`, 'g'), () => String(replacement ?? ''));
        }

        return value;
    }

    return { t, locale, isRtl };
}
