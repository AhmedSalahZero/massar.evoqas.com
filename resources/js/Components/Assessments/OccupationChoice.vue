<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — OccupationChoice (Step 11)
//  Location: resources/js/Components/Assessments/OccupationChoice.vue
//
//  Several occupations, of any standard and level (the same search as
//  the CV Bank occupation filter): ISCO-08 2 · 24 · 241 · 2411, ENOC
//  2411, or an ESCO job such as 2411.1 (which also covers the jobs
//  under it). v-model = ['isco:24', 'esco:2411.1']; `labels` keeps what
//  is shown for each.
// ══════════════════════════════════════════════════════════════════

import { ref, watch } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { occLabel } from '@/Components/Assessments/elig';
import { STANDARD_LABELS } from '@/Components/Backbone/occ';

const model = defineModel({ type: Array, default: () => [] });
const labels = defineModel('labels', { type: Array, default: () => [] });
const { t, locale } = useTranslations();

const q = ref('');
const results = ref([]);
const open = ref(false);
let timer = null;
let seq = 0;
watch(q, (v) => {
    clearTimeout(timer);
    if (!v.trim()) { results.value = []; return; }
    timer = setTimeout(async () => {
        const mine = ++seq;
        const { data } = await window.axios.get(route('app.cv-bank.occupations'), { params: { q: v.trim() } });
        if (mine === seq) results.value = data;
    }, 250);
});
const levelName = (o) => (o.standard === 'esco' ? t('bank.lvl_esco') : t(`bank.lvl_${o.level}`));
function pick(o) {
    if (!model.value.includes(o.value)) {
        model.value = [...model.value, o.value];
        const title = o.title;
        labels.value = [...labels.value, { value: o.value, standard: o.standard, code: o.code, title_en: locale.value === 'ar' ? null : title, title_ar: locale.value === 'ar' ? title : null }];
    }
    q.value = ''; open.value = false;
}
function remove(v) {
    model.value = model.value.filter((x) => x !== v);
    labels.value = labels.value.filter((l) => l.value !== v);
}
const labelOf = (v) => labels.value.find((l) => l.value === v) || { standard: v.split(':')[0], code: v.split(':')[1] };
const closeLater = () => setTimeout(() => { open.value = false; }, 200);
</script>

<template>
    <div class="occ">
        <div v-if="model.length" class="tags mb-2">
            <span v-for="v in model" :key="v" class="tag teal">{{ occLabel(labelOf(v), locale) }}<span class="x" role="button" @click="remove(v)">×</span></span>
        </div>
        <input v-model="q" type="search" class="inp" :placeholder="t('bank.occ_ph')" @focus="open = true" @blur="closeLater">
        <div v-if="open && results.length" class="occlist">
            <button v-for="o in results" :key="o.value" type="button" @mousedown.prevent="pick(o)">
                <span class="tag plain">{{ STANDARD_LABELS[o.standard] }}</span>
                <b><bdi dir="ltr">{{ o.code }}</bdi></b> {{ o.title }}
                <span class="mute small"> · {{ levelName(o) }}</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
.occ { position: relative; }
.occlist { position: absolute; z-index: 20; inset-inline: 0; top: calc(100% + 4px); background: var(--ms-bg-card); border: 1px solid var(--ms-border);
    border-radius: 8px; max-height: 300px; overflow-y: auto; box-shadow: 0 8px 24px rgba(0, 0, 0, .12); }
.occlist button { display: block; width: 100%; text-align: start; padding: 8px 10px; border: 0; background: none; font-size: 12.5px; cursor: pointer; color: inherit; }
.occlist button:hover { background: var(--ms-bg-hover); }
.tag { white-space: normal; }
</style>
