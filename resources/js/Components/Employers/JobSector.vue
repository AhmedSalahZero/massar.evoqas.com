<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — JobSector (where the job was, and its sector · Step 10.5)
//  Location: resources/js/Components/Employers/JobSector.vue
//
//      <JobSector :job="r" :options="options" />          staff
//      <JobSector :job="r" :options="options" public />   public site
//
//  Row 1 — where: Country, then
//    Egypt     Governorate (the 27) + City or area (typed)
//    abroad    City: a list of the country's main cities, or type any city
//  Row 2 — the sector: Sector, then Sub-sector (the fixed list) with, last,
//    "+ Other (not in the list)" → a box to type it. Those go to the
//                                   Super Admin, who can add them to the list.
//    public site also: "I don't know" (a wrong guess is worse than none)
//  The sector is not required, but asked: when empty, a quiet orange hint.
//  Picking a known company (EmployerInput) fills it.
//  Writes on the job: country, governorate, location, sub_sector, and for
//  "Other": sector + sub_sector_other; "I don't know": sector_unknown.
// ══════════════════════════════════════════════════════════════════

import { computed, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    job: { type: Object, required: true },
    options: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    public: { type: Boolean, default: false },
    uid: { type: String, default: () => Math.random().toString(36).slice(2, 8) },
});

const { t, locale } = useTranslations();
const name = (x) => (locale.value === 'ar' ? x.name_ar : x.name_en);
const OTHER = '__other';
const UNKNOWN = '__unknown';

if (!props.job.country) props.job.country = 'EG';
const cityInput = ref(null);
function openCities() {
    const el = cityInput.value;
    if (!el) return;
    el.focus();
    try { el.showPicker?.(); } catch { /* older browsers: the list opens as you type */ }
}
const egypt = computed(() => props.job.country === 'EG');
const cities = computed(() => (props.options.cities?.[props.job.country] ?? []).map(([en, ar]) => (locale.value === 'ar' ? ar : en)));
watch(() => props.job.country, (c, old) => {
    if (old && c !== old) { props.job.governorate = ''; if (old === 'EG' || c === 'EG') props.job.location = ''; }
});

// ── Sector ───────────────────────────────────────────────────────
const sectorOf = (code) => props.options.sectors?.find((s) => s.subs.some((x) => x.code === code))?.code ?? '';
const sector = ref(props.job.sector_unknown ? UNKNOWN : (sectorOf(props.job.sub_sector) || props.job.sector || ''));
const sub = ref(props.job.sub_sector_other ? OTHER : (props.job.sub_sector || ''));
watch(() => props.job.sub_sector, (v) => { if (v) { sector.value = sectorOf(v); sub.value = v; } });
const subs = computed(() => props.options.sectors?.find((s) => s.code === sector.value)?.subs ?? []);
function setSector(v) {
    sector.value = v;
    props.job.sector_unknown = v === UNKNOWN;
    if (v === UNKNOWN || !subs.value.some((x) => x.code === props.job.sub_sector)) {
        props.job.sub_sector = '';
        if (sub.value !== OTHER || v === UNKNOWN) { sub.value = ''; props.job.sub_sector_other = null; }
    }
    props.job.sector = sub.value === OTHER ? v : null;
}
function setSub(v) {
    sub.value = v;
    if (v === OTHER) { props.job.sub_sector = ''; props.job.sector = sector.value; props.job.sub_sector_other = props.job.sub_sector_other || ''; }
    else { props.job.sub_sector = v; props.job.sector = null; props.job.sub_sector_other = null; }
}
const asking = computed(() => !props.job.sub_sector && !props.job.sector_unknown && !(sub.value === OTHER && (props.job.sub_sector_other || '').trim()));
</script>

<template>
    <div class="jobsector">
        <!-- Where -->
        <label class="fld">
            <span class="fl">{{ t('emp.country') }}</span>
            <select v-model="job.country">
                <option v-for="c in options.countries" :key="c" :value="c">{{ t(`country.${c}`) }}</option>
            </select>
        </label>
        <template v-if="egypt">
            <label class="fld">
                <span class="fl">{{ t('ben.f_governorate') }}</span>
                <select v-model="job.governorate">
                    <option value="">—</option>
                    <option v-for="g in options.governorates" :key="g" :value="g">{{ t(`gov.${g}`) }}</option>
                </select>
            </label>
            <label class="fld">
                <span class="fl"><span>{{ t('emp.area') }}</span><span class="optional">{{ t('common.optional') }}</span></span>
                <input v-model="job.location" type="text" maxlength="100" :placeholder="t('emp.area_ph')">
            </label>
        </template>
        <label v-else class="fld span2">
            <span class="fl">{{ t('emp.city') }}</span>
            <span class="citybox">
                <input ref="cityInput" v-model="job.location" type="text" maxlength="100" :list="`cities-${uid}`" :placeholder="t('emp.city_ph')">
                <button type="button" class="caret" tabindex="-1" :aria-label="t('emp.city')" @mousedown.prevent="openCities"><AppIcon name="chevron-down" :size="15" /></button>
            </span>
            <datalist :id="`cities-${uid}`"><option v-for="c in cities" :key="c" :value="c" /></datalist>
            <small>{{ t('emp.city_hint') }}</small>
        </label>

        <!-- Sector -->
        <label class="fld">
            <span class="fl">{{ t('emp.sector') }}</span>
            <select :value="sector" :class="{ ask: asking && !sector }" @change="setSector($event.target.value)">
                <option value="">—</option>
                <option v-for="s in options.sectors" :key="s.code" :value="s.code">{{ name(s) }}</option>
                <option v-if="public" :value="UNKNOWN">{{ t('emp.dont_know') }}</option>
            </select>
        </label>
        <label class="fld" :class="{ 'has-error': !!errors.sub_sector, span2: sub !== OTHER }">
            <span class="fl">{{ t('emp.sub_sector') }}</span>
            <select :value="sub" :disabled="!sector || sector === UNKNOWN" :class="{ ask: asking && sector && sector !== UNKNOWN }" @change="setSub($event.target.value)">
                <option value="">—</option>
                <option v-for="x in subs" :key="x.code" :value="x.code">{{ name(x) }}</option>
                <option v-if="sector && sector !== UNKNOWN" :value="OTHER">+ {{ t('emp.other') }}</option>
            </select>
            <span v-if="errors.sub_sector" class="fld-error">{{ errors.sub_sector }}</span>
        </label>
        <label v-if="sub === OTHER" class="fld">
            <span class="fl">{{ t('emp.other_what') }}</span>
            <input v-model="job.sub_sector_other" type="text" maxlength="100" :placeholder="t('emp.other_ph')">
        </label>
        <small v-if="asking" class="askhint">{{ t(public ? 'emp.sector_hint_public' : 'emp.sector_hint') }}</small>
        <small v-else-if="sub === OTHER" class="otherhint">{{ t('emp.other_hint') }}</small>
    </div>
</template>

<style scoped>
.jobsector { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; align-items: start; }
.jobsector .fld { margin-top: 0; }
.jobsector .span2 { grid-column: span 2; }
select.ask { border-color: var(--ms-orange) !important; border-style: dashed !important; }
.citybox { position: relative; display: block; }
.citybox input { width: 100%; padding-inline-end: 34px !important; }
.citybox input::-webkit-calendar-picker-indicator { display: none !important; }
.caret { position: absolute; inset-inline-end: 4px; top: 50%; transform: translateY(-50%); width: 28px; height: 28px; border: 0; background: none;
    color: var(--ms-text-muted); cursor: pointer; display: grid; place-items: center; border-radius: 6px; }
.caret:hover { background: var(--ms-bg-hover); color: var(--ms-text-primary); }
.askhint { grid-column: 1 / -1; color: var(--ms-orange); font-size: 11.5px; margin-top: -2px; }
.otherhint { grid-column: 1 / -1; color: var(--ms-text-muted); font-size: 11.5px; margin-top: -2px; }
@media (max-width: 760px) {
    .jobsector { grid-template-columns: 1fr 1fr; }
    .jobsector .span2 { grid-column: 1 / -1; }
}
</style>
