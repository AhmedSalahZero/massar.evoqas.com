<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — EmployerInput (the employer selector of a job · Step 10.5)
//  Location: resources/js/Components/Employers/EmployerInput.vue
//
//      <EmployerInput :job="r" :url="route('app.employer-search')" :sectors="options.sectors" />
//      <EmployerInput :job="r" :url="route('seeker.employers')" public />
//
//  A selector (▾): click it to open the list, type to narrow it. The
//  list is the Massar list of companies (staff also see the ones their
//  workspace added). Picking one fills the sector and sub-sector.
//  The last line is always:
//    staff   "+ Add new company: '…'"      → saved in their workspace's list
//                                           when the profile is saved
//    public  "+ My company is not in the list" → kept on the profile only
//  After ⇄ moves a name into the box, it is looked up at once: a known
//  company gets its sector without opening the list.
//  Jobs abroad: the Egyptian list is not offered (the name is typed).
//  Writes on the job: employer, employer_id (a known company, or null),
//  sub_sector.
// ══════════════════════════════════════════════════════════════════

import { computed, nextTick, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    job: { type: Object, required: true },
    url: { type: String, required: true },
    sectors: { type: Array, default: () => [] },
    error: { type: String, default: '' },
    label: { type: String, default: '' },
    optional: { type: Boolean, default: false },
    public: { type: Boolean, default: false },
});

const { t, locale } = useTranslations();
const input = ref(null);
const open = ref(false);
const results = ref([]);
const recent = ref(false);
const loading = ref(false);
const active = ref(-1);
const abroad = computed(() => !!props.job.country && props.job.country !== 'EG');
const typedName = computed(() => (props.job.employer || '').trim());
const nameOf = (e) => (locale.value === 'ar' ? (e.name_ar || e.name_en) : (e.name_en || e.name_ar));
const subName = (code) => {
    for (const s of props.sectors) {
        const x = s.subs.find((y) => y.code === code);
        if (x) return locale.value === 'ar' ? x.name_ar : x.name_en;
    }
    return '';
};

let timer = null;
let seq = 0;
async function load(q) {
    const mine = ++seq;
    loading.value = true;
    try {
        const { data } = await window.axios.get(props.url, { params: { q } });
        if (mine === seq) { results.value = data.results ?? []; recent.value = !!data.recent; }
    } catch { if (mine === seq) results.value = []; } finally { if (mine === seq) loading.value = false; }
}
function typed() {
    props.job.employer_id = null;              // typed by hand: no longer the picked company
    active.value = -1;
    if (abroad.value) return;
    open.value = true;
    clearTimeout(timer);
    const q = typedName.value;
    timer = setTimeout(() => (q.length >= 2 ? load(q) : (q.length === 0 ? load('') : (results.value = []))), 250);
}
function toggle() {
    if (open.value) { close(); return; }
    if (abroad.value) { input.value?.focus(); return; }
    open.value = true;
    load(typedName.value.length >= 2 && !props.job.employer_id ? typedName.value : '');
    nextTick(() => input.value?.focus());
}
let known = props.job.employer ?? '';       // the last value typed here or picked here
function pick(e) {
    known = nameOf(e);
    props.job.employer = known;
    props.job.employer_id = e.id;
    if (e.sub_sector) { props.job.sub_sector = e.sub_sector; props.job.sub_sector_other = null; props.job.sector = null; props.job.sector_unknown = false; }
    close();
}
function addNew() { props.job.employer_id = null; close(); }
function close() { open.value = false; active.value = -1; }
function key(e) {
    if (e.key === 'ArrowDown' && !open.value) { toggle(); return; }
    if (!open.value) return;
    const n = results.value.length + (typedName.value ? 1 : 0);
    if (e.key === 'ArrowDown') { e.preventDefault(); active.value = (active.value + 1) % n; }
    else if (e.key === 'ArrowUp') { e.preventDefault(); active.value = (active.value - 1 + n) % n; }
    else if (e.key === 'Enter' && active.value >= 0) { e.preventDefault(); active.value < results.value.length ? pick(results.value[active.value]) : addNew(); }
    else if (e.key === 'Escape') close();
}
const blurLater = () => setTimeout(close, 180);

// ⇄ put a name in the box (not typed or picked here): look it up at once.
const onInput = (e) => { known = e.target.value; props.job.employer = known; typed(); };
watch(() => props.job.employer, async (v) => {
    if ((v ?? '') === known) return;
    known = v ?? '';
    props.job.employer_id = null;
    if (abroad.value || !v || v.trim().length < 2) return;
    try {
        const { data } = await window.axios.get(props.url, { params: { q: v.trim(), match: 1 } });
        if (data.match && props.job.employer === v) pick(data.match);
    } catch { /* stays as typed */ }
});
watch(() => props.job.country, () => { if (abroad.value) { props.job.employer_id = null; close(); } });
</script>

<template>
    <div class="fld emp" :class="{ 'has-error': !!error }">
        <span class="fl"><span>{{ label || t('ben.f_employer') }}</span><span v-if="optional" class="optional">{{ t('common.optional') }}</span></span>
        <div class="box" :class="{ abroad }">
            <input ref="input" :value="job.employer" type="text" maxlength="150" autocomplete="off"
                   :placeholder="abroad ? t('emp.abroad_ph') : t('emp.search_ph')" role="combobox" :aria-expanded="open"
                   @input="onInput" @keydown="key" @blur="blurLater" @click="!open && !abroad && toggle()">
            <button v-if="!abroad" type="button" class="caret" tabindex="-1" :aria-label="t('emp.open_list')" @mousedown.prevent="toggle">
                <AppIcon name="chevron-down" :size="15" />
            </button>
            <div v-if="open" class="list" role="listbox">
                <div v-if="loading && !results.length" class="hint">…</div>
                <div v-else-if="!results.length && !typedName" class="hint">{{ t('emp.type_hint') }}</div>
                <div v-else-if="recent && results.length" class="hint">{{ t('emp.recent') }}</div>
                <button v-for="(e, i) in results" :key="e.id" type="button" :class="{ on: i === active }" @mousedown.prevent="pick(e)">
                    <b>{{ nameOf(e) }}</b>
                    <span v-if="e.sub_sector" class="mute small"> · {{ subName(e.sub_sector) }}</span>
                    <span v-if="e.own" class="tag plain xs">{{ t('emp.own') }}</span>
                </button>
                <div v-if="typedName.length >= 2 && !loading && !results.length" class="hint">{{ t('emp.none_found') }}</div>
                <button v-if="typedName" type="button" class="addnew" :class="{ on: active === results.length }" @mousedown.prevent="addNew">
                    <AppIcon name="plus" :size="13" />
                    <template v-if="public">{{ t('emp.not_in_list') }}</template>
                    <template v-else>{{ t('emp.add_new', { name: typedName }) }}</template>
                </button>
            </div>
        </div>
        <small v-if="job.employer_id" class="linked"><AppIcon name="check" :size="11" /> {{ t('emp.linked') }}</small>
        <small v-else-if="typedName && !abroad && !open" class="newco">{{ t(public ? 'emp.new_public' : 'emp.new_staff') }}</small>
        <span v-if="error" class="fld-error"><AppIcon name="alert" :size="12" />{{ error }}</span>
    </div>
</template>

<style scoped>
.box { position: relative; }
.box input { width: 100%; padding-inline-end: 34px !important; }
.box.abroad input { padding-inline-end: 10px !important; }
.caret { position: absolute; inset-inline-end: 4px; top: 50%; transform: translateY(-50%); width: 28px; height: 28px; border: 0; background: none;
    color: var(--ms-text-muted); cursor: pointer; display: grid; place-items: center; border-radius: 6px; }
.caret:hover { background: var(--ms-bg-hover); color: var(--ms-text-primary); }
.list { position: absolute; z-index: 30; inset-inline: 0; top: calc(100% + 3px); background: var(--ms-bg-card); border: 1px solid var(--ms-border);
    border-radius: 8px; max-height: 300px; overflow-y: auto; box-shadow: 0 8px 24px rgba(0, 0, 0, .14); min-width: 260px; }
.list button { display: flex; gap: 4px; align-items: center; flex-wrap: wrap; width: 100%; text-align: start; padding: 8px 10px; border: 0; background: none; font-size: 12.5px; cursor: pointer; color: inherit; }
.list button:hover, .list button.on { background: var(--ms-bg-hover); }
.list .hint { padding: 8px 10px; font-size: 12px; color: var(--ms-text-muted); }
.list .addnew { border-top: 1px solid var(--ms-border); color: var(--ms-green); font-weight: 700; }
.tag.xs { font-size: 10px; padding: 0 5px; margin-inline-start: auto; }
.linked { color: var(--ms-green) !important; display: flex; gap: 4px; align-items: center; }
.newco { color: var(--ms-text-muted); }
</style>
