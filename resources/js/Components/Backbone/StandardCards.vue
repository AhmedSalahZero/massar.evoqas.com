<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — StandardCards
//  Location: resources/js/Components/Backbone/StandardCards.vue
//
//  The same occupation in ENOC · ISCO-08 · ESCO, side by side. The
//  card of the standard the user works in (top-bar switch) is
//  highlighted; clicking a card switches the standard.
//      <StandardCards :enoc="{code,title}|null" :isco="{code,title}" :esco="{code,title}|null" :esco-empty="text" />
// ══════════════════════════════════════════════════════════════════

import { usePreferences } from '@/composables/usePreferences';
import { useTranslations } from '@/composables/useTranslations';
import { STANDARD_LABELS } from '@/Components/Backbone/occ';

defineProps({
    enoc: { type: Object, default: null },
    isco: { type: Object, required: true },
    esco: { type: Object, default: null },
    escoEmpty: { type: String, default: '' },
});

const { t } = useTranslations();
const { prefs, setStandard } = usePreferences();
</script>

<template>
    <div class="std3">
        <button type="button" :aria-pressed="prefs.standard === 'enoc'" @click="setStandard('enoc')">
            <span class="sl">{{ STANDARD_LABELS.enoc }}</span>
            <template v-if="enoc"><code>{{ enoc.code }}</code><span class="st">{{ enoc.title }}</span></template>
            <span v-else class="st mute">{{ t('bb.not_in_enoc') }}</span>
        </button>
        <button type="button" :aria-pressed="prefs.standard === 'isco'" @click="setStandard('isco')">
            <span class="sl">{{ STANDARD_LABELS.isco }}</span>
            <code>{{ isco.code }}</code><span class="st">{{ isco.title }}</span>
        </button>
        <button type="button" :aria-pressed="prefs.standard === 'esco'" @click="setStandard('esco')">
            <span class="sl">{{ STANDARD_LABELS.esco }}</span>
            <template v-if="esco"><code>{{ esco.code }}</code><span class="st">{{ esco.title }}</span></template>
            <span v-else class="st mute">{{ escoEmpty }}</span>
        </button>
    </div>
</template>
