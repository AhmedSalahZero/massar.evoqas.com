<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — OccupationPicker
//  Location: resources/js/Components/Beneficiaries/OccupationPicker.vue
//
//      <OccupationPicker v-model="form.occupation" :gender="form.gender" :error="…" />
//
//  The same Arabic/English search as the Occupations page
//  (GET /app/occupation-picker). Results are the detailed ESCO jobs,
//  plus the few groups ESCO does not detail. Each result and the
//  chosen occupation are shown in the standard of the top-bar switch;
//  the chosen one also shows all three side by side.
//  The value is the server's occupation block (see ben.js) or null.
// ══════════════════════════════════════════════════════════════════

import { computed, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { usePreferences } from '@/composables/usePreferences';
import { useTranslations } from '@/composables/useTranslations';
import { STANDARD_LABELS } from '@/Components/Backbone/occ';
import { occIn } from '@/Components/Beneficiaries/ben';

const model = defineModel({ type: Object, default: null });
const props = defineProps({
    gender: { type: String, default: '' },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    url: { type: String, default: '' },        // the public site passes its own search address (Step 10)
});

const { t, locale } = useTranslations();
const { prefs, setStandard } = usePreferences();

const q = ref('');
const results = ref([]);
const loading = ref(false);
const searched = ref(false);
const failed = ref(false);
const open = ref(!model.value);
// Chosen from outside (the review screen's "Use" button, or a new reading): show it.
watch(model, (v) => { if (v) { open.value = false; q.value = ''; results.value = []; } });

let timer = null;
let seq = 0;
watch(q, (text) => {
    clearTimeout(timer);
    if (text.trim().length < 2) { results.value = []; searched.value = false; return; }
    timer = setTimeout(() => search(text.trim()), 300);
});

async function search(text) {
    const mine = ++seq;
    loading.value = true; failed.value = false;
    try {
        const { data } = await window.axios.get(props.url || route('app.occupation-picker'), { params: { q: text, gender: props.gender || undefined } });
        if (mine === seq) { results.value = data.results; searched.value = true; }
    } catch {
        if (mine === seq) failed.value = true;
    } finally {
        if (mine === seq) loading.value = false;
    }
}

function choose(r) { model.value = r.block; open.value = false; q.value = ''; results.value = []; }
function clear() { model.value = null; open.value = true; }

const row = (block) => occIn(block, prefs.standard, locale.value);
// Main line: the detailed job being chosen. Second line: its group, in the
// chosen standard (ENOC title in ENOC mode, ISCO-08 otherwise).
const detail = (block) => (block.esco ? occIn(block, 'esco', locale.value).title : occIn(block, 'isco', locale.value).title);
const group = (block) => {
    const std = prefs.standard === 'esco' ? 'isco' : prefs.standard;
    const g = occIn(block, std, locale.value);
    return `${STANDARD_LABELS[std]} ${g.code} · ${g.title}`;
};
const three = computed(() => model.value ? ['enoc', 'isco', 'esco'].map((s) => ({ s, ...occIn(model.value, s, locale.value) })) : []);
</script>

<template>
    <div class="picker" :class="{ 'has-error': !!error }">
        <!-- The chosen occupation, in all three standards -->
        <div v-if="model && !open">
            <div class="std3">
                <button v-for="c in three" :key="c.s" type="button" :aria-pressed="prefs.standard === c.s" @click="setStandard(c.s)">
                    <span class="sl">{{ STANDARD_LABELS[c.s] }}</span>
                    <code>{{ c.code }}</code>
                    <span class="st">{{ c.title }}</span>
                    <span v-if="c.note" class="st mute xsmall">{{ t(c.note) }}</span>
                </button>
            </div>
            <div class="flex gap-1 mt-2 wrap">
                <button type="button" class="btn btn-line sm" :disabled="disabled" @click="open = true"><AppIcon name="search" :size="14" />{{ t('ben.occ_change') }}</button>
                <button type="button" class="btn btn-ghost sm" :disabled="disabled" @click="clear"><AppIcon name="x" :size="14" />{{ t('ben.occ_remove') }}</button>
            </div>
        </div>

        <!-- Search -->
        <div v-else>
            <div class="search sm">
                <AppIcon name="search" :size="16" />
                <input v-model="q" type="search" :placeholder="t('ben.occ_search_ph')" :aria-label="t('ben.occ_search_ph')" :disabled="disabled" @keydown.enter.prevent>
                <span v-if="loading" class="spinner" aria-hidden="true"></span>
                <button v-if="model" type="button" class="btn btn-ghost sm" @click="open = false">{{ t('common.cancel') }}</button>
            </div>
            <div class="small mute mt-2">{{ t('ben.occ_help', { std: STANDARD_LABELS[prefs.standard] }) }}</div>

            <div v-if="failed" class="alert warning mt-2"><AppIcon name="alert" :size="16" /><span>{{ t('ben.occ_failed') }}</span></div>
            <div v-else-if="searched && !results.length" class="empty-row">{{ t('ben.occ_none') }}</div>
            <div v-else-if="results.length" class="results" role="listbox">
                <button v-for="r in results" :key="`${r.block.unit_code}-${r.block.esco_id ?? 'u'}`" type="button" class="orow" role="option" @click="choose(r)">
                    <code>{{ row(r.block).code }}</code>
                    <span class="ot">
                        <b>{{ detail(r.block) }}</b>
                        <span>{{ group(r.block) }}</span>
                        <span v-if="r.match && r.match !== detail(r.block)" class="xsmall">{{ t('bb.matched') }}: {{ r.match }}</span>
                    </span>
                    <span v-if="r.block.group_only" class="tag orange">{{ t('ben.group_only') }}</span>
                </button>
            </div>
        </div>
        <span v-if="error" class="fld-error mt-2"><AppIcon name="alert" :size="12" />{{ error }}</span>
    </div>
</template>

<style scoped>
.results { margin-top: 8px; max-height: 340px; overflow-y: auto; border: 1px solid var(--ms-border); border-radius: var(--r-lg); padding: 4px; background: var(--ms-bg-card); }
.orow { grid-template-columns: 96px minmax(0, 1fr) auto; }
.picker.has-error .search { border-color: var(--ms-danger); }
.spinner { width: 16px; height: 16px; }
</style>
