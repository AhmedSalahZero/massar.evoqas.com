<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — CvPaper (the CV text, highlighted)
//  Location: resources/js/Components/Cv/CvPaper.vue
//
//      <CvPaper :text="doc.text" :marks="doc.on_text" :dir="'rtl'" />
//
//  Shows the text read from the CV on a white "paper" (style guide §16)
//  and colours what the reading engine recognised:
//      blue    contact details (mobile, email, LinkedIn)
//      yellow  a known section heading
//      green   a skill from the ESCO skills dictionary
//      red     a heading the app does not know (for the reviewer)
//  Plain text only — nothing from the CV is ever run as HTML.
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { segments } from '@/Components/Cv/cv';

const props = defineProps({
    text: { type: String, default: '' },
    marks: { type: Object, default: null },   // { headings: {line: section}, unknown: [line], contacts: [], skills: [] }
    dir: { type: String, default: 'ltr' },
});

const { t } = useTranslations();

const needles = computed(() => {
    const m = props.marks ?? {};
    const list = [
        ...(m.contacts ?? []).map((s) => [s, 'hl hl-c']),
        ...(m.skills ?? []).map((s) => [s, 'hl hl-s']),
    ];
    return list.sort((a, b) => b[0].length - a[0].length);
});

const lines = computed(() => (props.text || '').split('\n').map((line, i) => {
    const heading = props.marks?.headings?.[i] !== undefined;
    const unknown = (props.marks?.unknown ?? []).includes(i);
    return { i, line, kind: heading ? 'h' : (unknown ? 'u' : null), parts: heading || unknown ? [] : segments(line, needles.value) };
}));
</script>

<template>
    <div>
        <div class="paper" :dir="dir">
            <template v-for="l in lines" :key="l.i">
                <div v-if="l.kind" class="ph"><span class="hl" :class="l.kind === 'h' ? 'hl-h' : 'hl-u'">{{ l.line }}</span></div>
                <div v-else-if="l.line.trim() === ''" class="gap" />
                <div v-else class="ln"><template v-for="(p, k) in l.parts" :key="k"><span v-if="p.cls" :class="p.cls">{{ p.text }}</span><template v-else>{{ p.text }}</template></template></div>
            </template>
        </div>
        <div class="legend-hl">
            <span><i style="background:#E3ECFD" />{{ t('cv.lg_contact') }}</span>
            <span><i style="background:#FEF1D6" />{{ t('cv.lg_heading') }}</span>
            <span><i style="background:#DCF5E4" />{{ t('cv.lg_skill') }}</span>
            <span><i style="background:#FDE3E4" />{{ t('cv.lg_unknown') }}</span>
        </div>
    </div>
</template>

<style scoped>
.paper .gap { height: 8px; }
.paper .ln { white-space: pre-wrap; overflow-wrap: anywhere; }
</style>
