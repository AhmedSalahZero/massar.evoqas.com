<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — BeneficiaryForm (the profile form, shared)
//  Location: resources/js/Components/Beneficiaries/BeneficiaryForm.vue
//
//  The ONE beneficiary form, used by:
//    · Register / edit a beneficiary   (Pages/App/Beneficiaries/Form.vue)
//    · The CV review screen            (Pages/App/Cv/Review.vue), filled
//      by the CV reading engine, with each field marked
//      Found / Check / Missing (`marks`) and a short reason (`notes`).
//  So a profile made from a CV is checked exactly like one typed in.
//
//      <BeneficiaryForm :initial="…" :options="…" :backbone="true"
//                       :submit="{ method: 'post', url: … }" submit-label="…"
//                       :cancel-href="…" :marks="{ phone: 'found', … }" />
//
//  `occupation` (a block from the server, or null) can be changed from
//  outside with setOccupation() — the review screen's suggestions.
//
//  Work history tools (quick fixes for how a CV was read):
//    ⇄      swap the job title and the employer
//    Split  the cursor's responsibility line starts a second job (it
//           becomes that job's title; the lines under it its duties)
//    Merge  this job is part of the job above (its title becomes a duty)
//  A job the reader was unsure about carries `check` (['title', 'dates'])
//  and is marked; the marks are not saved.
//  The #actions slot replaces the Save / Cancel buttons.
// ══════════════════════════════════════════════════════════════════

import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Field from '@/Components/Field.vue';
import EmployerInput from '@/Components/Employers/EmployerInput.vue';
import JobSector from '@/Components/Employers/JobSector.vue';
import OccupationPicker from '@/Components/Beneficiaries/OccupationPicker.vue';
import { useTranslations } from '@/composables/useTranslations';
import { todayIso } from '@/Utils/date';

const props = defineProps({
    initial: { type: Object, default: null },
    options: { type: Object, required: true },
    backbone: { type: Boolean, default: true },
    submit: { type: Object, required: true },          // { method: 'post' | 'patch', url }
    submitLabel: { type: String, default: '' },
    cancelHref: { type: String, default: '' },
    marks: { type: Object, default: null },            // field → found | check | missing
    notes: { type: Object, default: () => ({}) },      // field → reason code (cv.note_*)
    extra: { type: Object, default: () => ({}) },      // sent with the form (the review screen's learn_title)
});

const { t } = useTranslations();
const b = props.initial ?? {};
const form = useForm({
    name_ar: b.name_ar ?? '',
    name_en: b.name_en ?? '',
    gender: b.gender ?? '',
    date_of_birth: b.date_of_birth ?? '',
    military_status: b.military_status ?? '',
    governorate: b.governorate ?? '',
    city: b.city ?? '',
    phone: b.phone ?? '',
    email: b.email ?? '',
    education_level: b.education_level ?? '',
    education: (b.education ?? []).map((r) => ({ ...r, year: r.year ?? '' })),
    // Job responsibilities are edited as text, one duty per line (turned back into a list on save).
    work_history: (b.work_history ?? []).map((r) => ({ ...r, location: r.location ?? '', to: r.to ?? '', current: !!r.current, check: r.check ?? [],
        country: r.country ?? 'EG', governorate: r.governorate ?? '', sub_sector: r.sub_sector ?? '', employer_id: r.employer_id ?? null,
        sector: r.sector ?? null, sub_sector_other: r.sub_sector_other ?? null, sector_unknown: !!r.sector_unknown, duties: (r.responsibilities ?? []).join('\n') })),
    skills: [...(b.skills ?? [])],
    languages: (b.languages ?? []).map((r) => ({ ...r })),
    occupation: b.occupation ?? null,
    expected_salary: b.expected_salary ?? '',
    job_type: b.job_type ?? '',
    confirm_duplicate: false,
});

// A new mobile or email means the duplicate question must be asked again.
watch(() => [form.phone, form.email], () => { form.confirm_duplicate = false; });

const thisMonth = todayIso().slice(0, 7);
const addEducation = () => form.education.push({ qualification: '', field: '', institution: '', year: '' });
const addJob = () => form.work_history.push({ title: '', employer: '', location: '', from: '', to: '', current: false, check: [], country: 'EG', governorate: '', sub_sector: '', employer_id: null, duties: '' });

// ── Work history tools ───────────────────────────────────────────
const swapJob = (r) => { [r.title, r.employer] = [r.employer ?? '', r.title ?? '']; r.check = (r.check ?? []).filter((c) => c !== 'title'); };
const caret = ref({});           // job index → the cursor position in its responsibilities
const keepCaret = (i, e) => { caret.value[i] = e.target.selectionStart ?? null; };
function splitJob(i) {
    const r = form.work_history[i];
    const lines = (r.duties || '').split('\n');
    const at = caret.value[i];
    let from = lines.length;                     // no cursor: a new empty job under the same employer
    if (at !== null && at !== undefined && (r.duties || '').length) {
        from = (r.duties || '').slice(0, at).split('\n').length - 1;
    }
    const moved = lines.slice(from).filter((l) => l.trim());
    const first = moved.length && moved[0].trim().split(/\s+/).length <= 8 ? moved.shift().trim().replace(/^[-•▪*·\d.)\s]+/, '') : '';
    r.duties = lines.slice(0, from).join('\n').replace(/\n+$/, '');
    form.work_history.splice(i + 1, 0, { title: first, employer: r.employer ?? '', location: r.location ?? '', from: '', to: '', current: false, check: ['dates'],
        country: r.country ?? 'EG', governorate: r.governorate ?? '', sub_sector: r.sub_sector ?? '', employer_id: r.employer_id ?? null, duties: moved.join('\n') });
    caret.value = {};
}
function mergeJob(i) {
    if (i < 1) return;
    const up = form.work_history[i - 1];
    const r = form.work_history[i];
    up.duties = [up.duties, r.title, r.employer && r.employer !== up.employer ? r.employer : '', r.duties].filter((x) => (x || '').trim()).join('\n');
    up.from ||= r.from;
    if (!up.to && !up.current) { up.to = r.to; up.current = r.current; }
    up.employer ||= r.employer;
    up.location ||= r.location;
    form.work_history.splice(i, 1);
    caret.value = {};
}
const dutyCount = (r) => (r.duties || '').split('\n').filter((l) => l.trim()).length;
const dutiesError = (i) => err(`work_history.${i}.responsibilities`)
    || Object.entries(form.errors).find(([k]) => k.startsWith(`work_history.${i}.responsibilities.`))?.[1] || '';
const addLanguage = () => {
    const used = new Set(form.languages.map((l) => l.code));
    form.languages.push({ code: props.options.languages.find((c) => !used.has(c)) ?? '', level: 'good' });
};

const skill = ref('');
function addSkill() {
    skill.value.split(/[,،\n]/).map((s) => s.trim()).filter(Boolean).forEach((s) => {
        if (!form.skills.some((x) => x.toLowerCase() === s.toLowerCase()) && form.skills.length < props.options.max.skills) form.skills.push(s);
    });
    skill.value = '';
}
function skillKey(e) {
    if (e.key === 'Enter' || e.key === ',' || e.key === '،') { e.preventDefault(); addSkill(); }
    else if (e.key === 'Backspace' && !skill.value && form.skills.length) form.skills.pop();
}

const err = (key) => form.errors[key] ?? '';
const occupationError = computed(() => form.errors.occupation || form.errors.esco_occupation_id || form.errors.occupation_unit || '');

function save() {
    if (skill.value.trim()) addSkill();
    const send = form.transform((d) => {
        const { occupation, ...rest } = d;
        return {
            ...rest,
            work_history: d.work_history.map(({ duties, check, ...job }) => ({
                ...job,
                responsibilities: (duties || '').split('\n').map((l) => l.trim()).filter(Boolean),
            })),
            military_status: d.gender === 'male' ? d.military_status : '',
            esco_occupation_id: occupation?.esco_id ?? null,
            occupation_unit: occupation && occupation.group_only ? occupation.unit_code : null,
            ...props.extra,
        };
    });
    const opts = { preserveScroll: true, onError: () => setTimeout(scrollToError, 50) };
    send[props.submit.method](props.submit.url, opts);
}
function scrollToError() {
    document.querySelector('.has-error, .dup')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

const errorCount = computed(() => Object.keys(form.errors).filter((k) => k !== 'duplicate' && k !== 'cv').length);

// ── Marks from the CV reading engine ─────────────────────────────
const mark = (key) => (props.marks ? (props.marks[key] ?? null) : null);
const markClass = (key) => ({ 'is-check': mark(key) === 'check', 'is-missing': mark(key) === 'missing' });
const noteFor = (key) => (props.notes?.[key] ? t(`cv.note_${props.notes[key]}`) : '');
// Fields still marked (shown on the review screen's action bar).
const marked = computed(() => (props.marks ? Object.entries(props.marks).filter(([, m]) => m === 'check').length : 0));

function setOccupation(block) { form.occupation = block; }
defineExpose({ form, save, setOccupation, marked });
</script>

<template>
    <form novalidate @submit.prevent="save">
            <div v-if="errorCount" class="form-errors">{{ t('ben.fix_errors', { n: errorCount }) }}</div>

            <div v-if="form.errors.cv" class="alert warning mb-4"><AppIcon name="alert" :size="16" /><span>{{ form.errors.cv }}</span></div>

            <!-- Same mobile / email in this workspace -->
            <div v-if="form.errors.duplicate" class="alert warning dup mb-4">
                <AppIcon name="alert" :size="16" />
                <div class="grow">
                    <div>{{ form.errors.duplicate }}</div>
                    <label class="checkline mt-2"><input v-model="form.confirm_duplicate" type="checkbox"> {{ t('ben.dup_confirm') }}</label>
                </div>
            </div>

            <!-- ── Personal details ───────────────────────────── -->
            <div class="panel">
                <h3><AppIcon name="user" :size="16" />{{ t('ben.s_personal') }}</h3>
                <div class="sub">{{ t('ben.s_personal_sub') }}</div>
                <div v-for="k in ['name', 'gender']" v-show="noteFor(k)" :key="k" class="cvnote"><AppIcon name="info" :size="13" />{{ noteFor(k) }}</div>
                <div class="form-grid">
                    <Field :label="t('ben.f_name_ar')" :error="err('name_ar')" :hint="t('ben.name_hint')" :class="form.name_ar || !form.name_en ? markClass('name') : null">
                        <template v-if="mark('name') && (form.name_ar || !form.name_en)" #label-end><span class="cf" :class="mark('name')">{{ t(`cv.f_${mark('name')}`) }}</span></template>
                        <input v-model="form.name_ar" type="text" dir="rtl" lang="ar" maxlength="150" autocomplete="off">
                    </Field>
                    <Field :label="t('ben.f_name_en')" :error="err('name_en')" :class="form.name_en || !form.name_ar ? markClass('name') : null">
                        <template v-if="mark('name') && (form.name_en || !form.name_ar)" #label-end><span class="cf" :class="mark('name')">{{ t(`cv.f_${mark('name')}`) }}</span></template>
                        <input v-model="form.name_en" type="text" dir="ltr" lang="en" maxlength="150" autocomplete="off">
                    </Field>
                    <div class="fld" :class="[{ 'has-error': !!err('gender') }, markClass('gender')]">
                        <span class="fl"><span>{{ t('ben.f_gender') }}<span class="req">*</span></span><span v-if="mark('gender')" class="cf" :class="mark('gender')">{{ t(`cv.f_${mark('gender')}`) }}</span></span>
                        <div class="seg" role="group" :aria-label="t('ben.f_gender')">
                            <button v-for="g in options.genders" :key="g" type="button" :aria-pressed="form.gender === g" @click="form.gender = g">{{ t(`ben.gender_${g}`) }}</button>
                        </div>
                        <span v-if="err('gender')" class="fld-error"><AppIcon name="alert" :size="12" />{{ err('gender') }}</span>
                    </div>
                    <Field :label="t('ben.f_date_of_birth')" :error="err('date_of_birth')" optional :class="markClass('date_of_birth')">
                        <template v-if="mark('date_of_birth')" #label-end><span class="cf" :class="mark('date_of_birth')">{{ t(`cv.f_${mark('date_of_birth')}`) }}</span></template>
                        <input v-model="form.date_of_birth" type="date" min="1940-01-02" :max="todayIso()">
                    </Field>
                    <Field v-if="form.gender === 'male'" :label="t('ben.f_military_status')" :error="err('military_status')" optional :class="markClass('military_status')">
                        <template v-if="mark('military_status')" #label-end><span class="cf" :class="mark('military_status')">{{ t(`cv.f_${mark('military_status')}`) }}</span></template>
                        <select v-model="form.military_status">
                            <option value="">—</option>
                            <option v-for="m in options.military_statuses" :key="m" :value="m">{{ t(`ben.mil_${m}`) }}</option>
                        </select>
                    </Field>
                    <Field :label="t('ben.f_governorate')" :error="err('governorate')" required :class="markClass('governorate')">
                        <template v-if="mark('governorate')" #label-end><span class="cf" :class="mark('governorate')">{{ t(`cv.f_${mark('governorate')}`) }}</span></template>
                        <select v-model="form.governorate">
                            <option value="" disabled>{{ t('ben.choose') }}</option>
                            <option v-for="g in options.governorates" :key="g" :value="g">{{ t(`gov.${g}`) }}</option>
                        </select>
                    </Field>
                    <Field :label="t('ben.f_city')" :error="err('city')" optional :class="markClass('city')">
                        <template v-if="mark('city')" #label-end><span class="cf" :class="mark('city')">{{ t(`cv.f_${mark('city')}`) }}</span></template>
                        <input v-model="form.city" type="text" maxlength="100">
                    </Field>
                </div>
            </div>

            <!-- ── Contact ────────────────────────────────────── -->
            <div class="panel mt-4">
                <h3><AppIcon name="mail" :size="16" />{{ t('ben.s_contact') }}</h3>
                <div class="sub">{{ t('ben.s_contact_sub') }}</div>
                <div v-if="noteFor('phone')" class="cvnote"><AppIcon name="info" :size="13" />{{ noteFor('phone') }}</div>
                <div class="form-grid">
                    <Field :label="t('ben.f_phone')" :error="err('phone')" :hint="t('ben.phone_hint')" :class="markClass('phone')">
                        <template v-if="mark('phone')" #label-end><span class="cf" :class="mark('phone')">{{ t(`cv.f_${mark('phone')}`) }}</span></template>
                        <input v-model="form.phone" type="tel" inputmode="tel" maxlength="20" placeholder="01XXXXXXXXX" autocomplete="off">
                    </Field>
                    <Field :label="t('ben.f_email')" :error="err('email')" :class="markClass('email')">
                        <template v-if="mark('email')" #label-end><span class="cf" :class="mark('email')">{{ t(`cv.f_${mark('email')}`) }}</span></template>
                        <input v-model="form.email" type="email" maxlength="150" autocomplete="off">
                    </Field>
                </div>
            </div>

            <!-- ── Occupation ─────────────────────────────────── -->
            <div class="panel mt-4">
                <h3><AppIcon name="layers" :size="16" />{{ t('ben.s_occupation') }}<span v-if="mark('occupation')" class="cf mis" :class="mark('occupation')">{{ t(`cv.f_${mark('occupation')}`) }}</span></h3>
                <div class="sub">{{ t('ben.s_occupation_sub') }}</div>
                <slot name="occupation-help" />
                <div v-if="!backbone" class="alert info"><AppIcon name="info" :size="16" /><span>{{ t('ben.no_backbone') }}</span></div>
                <OccupationPicker v-else v-model="form.occupation" :gender="form.gender" :error="occupationError" />
            </div>

            <!-- ── Education ──────────────────────────────────── -->
            <div class="panel mt-4">
                <h3><AppIcon name="graduation" :size="16" />{{ t('ben.s_education') }}<span v-if="mark('education')" class="cf mis" :class="mark('education')">{{ t(`cv.f_${mark('education')}`) }}</span></h3>
                <div class="form-grid">
                    <Field :label="t('ben.f_education_level')" :error="err('education_level')" optional :class="markClass('education_level')">
                        <template v-if="mark('education_level')" #label-end><span class="cf" :class="mark('education_level')">{{ t(`cv.f_${mark('education_level')}`) }}</span></template>
                        <select v-model="form.education_level">
                            <option value="">—</option>
                            <option v-for="e in options.education_levels" :key="e" :value="e">{{ t(`ben.edu_${e}`) }}</option>
                        </select>
                    </Field>
                </div>
                <div class="kv-label mt-4"><b>{{ t('ben.qualifications') }}</b></div>
                <div v-for="(r, i) in form.education" :key="`e${i}`" class="xrow">
                    <Field :label="t('ben.f_qualification')" :error="err(`education.${i}.qualification`)"><input v-model="r.qualification" type="text" maxlength="150" :placeholder="t('ben.qualification_ph')"></Field>
                    <Field :label="t('ben.f_field')" :error="err(`education.${i}.field`)"><input v-model="r.field" type="text" maxlength="150"></Field>
                    <Field :label="t('ben.f_institution')" :error="err(`education.${i}.institution`)"><input v-model="r.institution" type="text" maxlength="150"></Field>
                    <Field :label="t('ben.f_year')" :error="err(`education.${i}.year`)"><input v-model="r.year" type="number" min="1950" :max="new Date().getFullYear() + 6"></Field>
                    <button type="button" class="xdel" :aria-label="t('common.delete')" @click="form.education.splice(i, 1)">×</button>
                </div>
                <button v-if="form.education.length < options.max.education" type="button" class="addmini" @click="addEducation">+ {{ t('ben.add_qualification') }}</button>
            </div>

            <!-- ── Work history ───────────────────────────────── -->
            <div class="panel mt-4">
                <h3><AppIcon name="briefcase" :size="16" />{{ t('ben.s_work') }}<span v-if="mark('work_history')" class="cf mis" :class="mark('work_history')">{{ t(`cv.f_${mark('work_history')}`) }}</span></h3>
                <div class="sub">{{ t('ben.s_work_sub') }}</div>
                <div v-if="noteFor('work_history')" class="cvnote"><AppIcon name="info" :size="13" />{{ noteFor('work_history') }}</div>
                <div v-if="!form.work_history.length" class="empty-row">{{ t('ben.no_jobs') }}</div>
                <div v-for="(r, i) in form.work_history" :key="`w${i}`" class="xrow job" :class="{ 'is-check': marks && r.check?.length }">
                    <div v-if="marks && r.check?.length" class="jobcheck">
                        <AppIcon name="alert" :size="13" /><b>{{ t('cv.job_check') }}:</b>
                        <span v-for="c in r.check" :key="c">{{ t(`cv.job_check_${c}`) }}</span>
                    </div>
                    <Field :label="t('ben.f_job_title')" :error="err(`work_history.${i}.title`)"><input v-model="r.title" type="text" maxlength="150"></Field>
                    <button type="button" class="swap" :title="t('ben.swap')" :aria-label="t('ben.swap')" @click="swapJob(r)">⇄</button>
                    <EmployerInput :job="r" :url="route('app.employer-search')" :sectors="options.sectors ?? []" :error="err(`work_history.${i}.employer`) || err(`work_history.${i}.employer_id`)" />
                    <Field :label="t('ben.f_from')" :error="err(`work_history.${i}.from`)"><input v-model="r.from" type="month" :max="thisMonth"></Field>
                    <div class="fld" :class="{ 'has-error': !!err(`work_history.${i}.to`) }">
                        <label class="fl" :for="`to${i}`">{{ t('ben.f_to') }}</label>
                        <input :id="`to${i}`" v-model="r.to" type="month" :min="r.from || undefined" :max="thisMonth" :disabled="r.current">
                        <label class="checkline cur"><input v-model="r.current" type="checkbox" @change="r.current && (r.to = '')"> {{ t('ben.current_job') }}</label>
                        <span v-if="err(`work_history.${i}.to`)" class="fld-error"><AppIcon name="alert" :size="12" />{{ err(`work_history.${i}.to`) }}</span>
                    </div>
                    <button type="button" class="xdel" :aria-label="t('common.delete')" @click="form.work_history.splice(i, 1)">×</button>
                    <JobSector class="sect" :job="r" :options="options" :errors="{ sub_sector: err(`work_history.${i}.sub_sector`) }" />
                    <div class="jobtools">
                        <button type="button" class="btn btn-line xs" :title="t('ben.split_hint')" @click="splitJob(i)">{{ t('ben.split_job') }}</button>
                        <button v-if="i > 0" type="button" class="btn btn-line xs" :title="t('ben.merge_hint')" @click="mergeJob(i)">{{ t('ben.merge_job') }}</button>
                    </div>
                    <div class="fld duties" :class="{ 'has-error': !!dutiesError(i), 'is-missing': marks && !dutyCount(r) }">
                        <label class="fl" :for="`duties${i}`">{{ t('ben.f_responsibilities') }}<span v-if="dutyCount(r)" class="dcount"> · {{ dutyCount(r) }}</span></label>
                        <textarea :id="`duties${i}`" v-model="r.duties" rows="3" :placeholder="t('ben.responsibilities_ph')"
                                  @click="keepCaret(i, $event)" @keyup="keepCaret(i, $event)" @select="keepCaret(i, $event)"></textarea>
                        <span v-if="dutiesError(i)" class="fld-error"><AppIcon name="alert" :size="12" />{{ dutiesError(i) }}</span>
                    </div>
                </div>
                <button v-if="form.work_history.length < options.max.work_history" type="button" class="addmini" @click="addJob">+ {{ t('ben.add_job') }}</button>
            </div>

            <!-- ── Skills & languages ─────────────────────────── -->
            <div class="panel mt-4">
                <h3><AppIcon name="sparkles" :size="16" />{{ t('ben.s_skills') }}<span v-if="mark('skills')" class="cf mis" :class="mark('skills')">{{ t(`cv.f_${mark('skills')}`) }}</span></h3>
                <div class="fld">
                    <label class="fl" for="skill-input">{{ t('ben.f_skills') }}</label>
                    <div class="chipbox">
                        <span v-for="(s, i) in form.skills" :key="s" class="tag plain">{{ s }}<button type="button" :aria-label="`${t('common.delete')} ${s}`" @click="form.skills.splice(i, 1)">×</button></span>
                        <input id="skill-input" v-model="skill" type="text" maxlength="60" :placeholder="form.skills.length ? '' : t('ben.skills_ph')" @keydown="skillKey" @blur="skill.trim() && addSkill()">
                    </div>
                    <small>{{ t('ben.skills_hint') }}</small>
                </div>

                <div class="kv-label mt-4"><b>{{ t('ben.f_languages') }}</b> <span v-if="mark('languages')" class="cf" :class="mark('languages')">{{ t(`cv.f_${mark('languages')}`) }}</span></div>
                <div v-for="(r, i) in form.languages" :key="`l${i}`" class="xrow lang">
                    <Field :label="t('ben.f_language')" :error="err(`languages.${i}.code`)">
                        <select v-model="r.code"><option v-for="c in options.languages" :key="c" :value="c">{{ t(`ben.lang_${c}`) }}</option></select>
                    </Field>
                    <Field :label="t('ben.f_level')" :error="err(`languages.${i}.level`)">
                        <select v-model="r.level"><option v-for="l in options.language_levels" :key="l" :value="l">{{ t(`ben.level_${l}`) }}</option></select>
                    </Field>
                    <button type="button" class="xdel" :aria-label="t('common.delete')" @click="form.languages.splice(i, 1)">×</button>
                </div>
                <button v-if="form.languages.length < options.max.languages" type="button" class="addmini" @click="addLanguage">+ {{ t('ben.add_language') }}</button>
            </div>

            <!-- ── Preferences ────────────────────────────────── -->
            <div class="panel mt-4">
                <h3><AppIcon name="clipboard" :size="16" />{{ t('ben.s_preferences') }}</h3>
                <div class="form-grid">
                    <Field :label="t('ben.f_expected_salary')" :error="err('expected_salary')" :hint="t('ben.salary_hint')" optional>
                        <div class="inp-group">
                            <input v-model="form.expected_salary" type="text" inputmode="numeric" maxlength="12" placeholder="7500">
                            <span class="addon">{{ t('ben.egp_month') }}</span>
                        </div>
                    </Field>
                    <Field :label="t('ben.f_job_type')" :error="err('job_type')" optional>
                        <select v-model="form.job_type">
                            <option value="">—</option>
                            <option v-for="j in options.job_types" :key="j" :value="j">{{ t(`ben.job_${j}`) }}</option>
                        </select>
                    </Field>
                </div>
            </div>

            <slot name="actions" :save="save" :processing="form.processing">
                <div class="form-actions">
                    <Link v-if="cancelHref" :href="cancelHref" class="btn btn-line">{{ t('common.cancel') }}</Link>
                    <button type="submit" class="btn btn-primary" :class="{ 'is-loading': form.processing }" :disabled="form.processing">
                        <AppIcon name="check" :size="15" />{{ submitLabel || t('common.save_changes') }}
                    </button>
                </div>
            </slot>
        </form>
</template>

<style scoped>
.xrow.job { grid-template-columns: minmax(0, 1.4fr) 30px minmax(0, 1.4fr) repeat(2, minmax(0, 1fr)) 34px; align-items: start; }
.xrow.job.is-check { border-inline-start: 3px solid var(--ms-orange); padding-inline-start: 10px; }
.xrow.job .jobcheck { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 4px 8px; align-items: center; font-size: 12px; color: var(--ms-orange); }
.xrow.job .jobcheck span + span::before { content: '· '; }
.xrow.job .swap { margin-top: 22px; height: 36px; border: 1px solid var(--ms-border); background: var(--ms-bg-input); border-radius: var(--r-md); cursor: pointer; font-size: 15px; color: var(--ms-navy); padding: 0; }
.xrow.job .swap:hover { border-color: var(--ms-border-focus); }
.xrow.job .loc { grid-column: 1 / 3; }
.xrow.job .sect { grid-column: 1 / -1; }
.xrow.job .jobtools { grid-column: 1 / -1; display: flex; gap: 6px; flex-wrap: wrap; align-items: flex-end; justify-content: flex-end; }
.btn.xs { padding: 3px 8px; font-size: 11.5px; }
.xrow.job .duties { grid-column: 1 / -1; margin-top: 2px; }
.xrow.job .duties textarea { width: 100%; min-height: 64px; resize: vertical; padding: 7px 9px; font-size: 13px; line-height: 1.5; }
.xrow.job .duties.is-missing textarea { border-style: dashed; }
.xrow.job .dcount { color: var(--ms-text-muted); font-weight: 400; }
.xrow.lang { grid-template-columns: repeat(2, minmax(0, 220px)) 34px; }
.xrow .xdel { margin-top: 22px; }
.cur { font-size: 11.5px; margin-top: 4px; }
.chipbox { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; border: 1px solid var(--ms-border); background: var(--ms-bg-input); border-radius: var(--r-md); padding: 6px 8px; min-height: 40px; }
.chipbox:focus-within { border-color: var(--ms-border-focus); box-shadow: var(--ms-ring); }
.chipbox .tag button { border: 0; background: none; color: inherit; margin-inline-start: 6px; font-size: 14px; line-height: 1; padding: 0; cursor: pointer; }
.chipbox input { flex: 1; min-width: 140px; border: 0 !important; background: transparent !important; box-shadow: none !important; padding: 4px !important; }
.fld .seg { display: flex; }
.fld .seg button { flex: 1; padding: 9px 10px; font-size: 13px; }
.fld.has-error .seg { border-color: var(--ms-danger); }
.cf.mis { margin-inline-start: auto; }
h3 .cf { font-size: 10.5px; }
.cvnote { display: flex; gap: 6px; align-items: center; font-size: 12px; color: var(--ms-orange); margin: -4px 0 10px; }
@media (max-width: 760px) {
    .xrow, .xrow.job, .xrow.lang { grid-template-columns: 1fr 1fr; }
    .xrow.job .swap { grid-column: 1 / -1; margin-top: 0; height: 28px; }
    .xrow.job .loc, .xrow.job .jobtools, .xrow.job .sect { grid-column: 1 / -1; }
    .xrow.job .jobtools { justify-content: flex-start; padding-top: 0; }
    .xrow .xdel { margin-top: 0; }
}
</style>
