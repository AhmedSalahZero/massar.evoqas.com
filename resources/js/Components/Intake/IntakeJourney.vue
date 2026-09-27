<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — IntakeJourney (the question-by-question profile journey)
//  Location: resources/js/Components/Intake/IntakeJourney.vue
//
//  ONE journey, used in three places:
//    mode="staff"  the staff Guided Intake (Step 9) — Pages/App/Intake/Journey.vue
//    mode="join"   a job seeker registers on the public site (Step 10) — Pages/Public/Join.vue
//    mode="edit"   a job seeker edits their profile (Step 10) — Pages/Public/Edit.vue
//
//  Two doors into the same profile:
//    · "Has a CV": the CV is read first (POST cvUrl), the answers are
//      filled from it, and only the questions the CV did not answer (or
//      answered with doubt) are asked. "Show all questions" opens the
//      rest of a step.
//    · "No CV": short, simple questions, a few at a time.
//  Steps: about you · contact · where you live · education · work ·
//  occupation · skills & languages · preferences · (public site:
//  consent · your sign-in) · check and save.
//  Saving uses the registration form's own rules on the server; an
//  error sends the person back to the step with that question.
//
//  Public site differences: a mobile AND an email are needed (the email
//  is the sign-in, and cannot be changed in "edit"); the preferences add
//  the notice period; "join" adds consent and the password.
// ══════════════════════════════════════════════════════════════════

import { computed, onMounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Field from '@/Components/Field.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import OccupationPicker from '@/Components/Beneficiaries/OccupationPicker.vue';
import EmployerInput from '@/Components/Employers/EmployerInput.vue';
import JobSector from '@/Components/Employers/JobSector.vue';
import { useTranslations } from '@/composables/useTranslations';
import { usePreferences } from '@/composables/usePreferences';
import { occIn } from '@/Components/Beneficiaries/ben';
import { todayIso } from '@/Utils/date';

const props = defineProps({
    mode: { type: String, default: 'staff' },             // staff | join | edit
    options: { type: Object, required: true },
    backbone: { type: Boolean, default: true },
    canUpload: { type: Boolean, default: false },
    limits: { type: Object, required: true },
    cvUrl: { type: String, default: '' },
    saveUrl: { type: String, required: true },
    pickerUrl: { type: String, default: '' },
    employerUrl: { type: String, default: '' },          // the company search (Step 10.5)
    door: { type: String, default: '' },                  // join: cv | questions
    initial: { type: Object, default: null },             // edit: the profile as it is
});
const emit = defineEmits(['phase', 'step']);

const { t, locale } = useTranslations();
const { prefs } = usePreferences();
const thisMonth = todayIso().slice(0, 7);
const isPublic = props.mode !== 'staff';

// ── The answers (the same fields as the registration form) ──────
const blank = () => ({
    name_ar: '', name_en: '', gender: '', date_of_birth: '', military_status: '', governorate: '', city: '',
    phone: '', email: '', education_level: '', education: [], work_history: [], skills: [], languages: [],
    occupation: null, expected_salary: '', job_type: '', confirm_duplicate: false, cv: null,
    ...(isPublic ? { notice_period: '' } : {}),
    ...(props.mode === 'join' ? { visible: true, privacy: false, password: '', password_confirmation: '' } : {}),
});
const form = useForm(blank());
const worked = ref(null);           // "Have you worked before?" yes / no

// ── Where we are ─────────────────────────────────────────────────
const phase = ref('start');         // start | reading | steps | review
const cv = ref(null);               // { uuid, file, read, problem }
const marks = ref({});
const cvOccupation = ref(null);     // { status, title, candidates }
const readError = ref('');
const occFromCv = ref(false);       // the CV settled the occupation (asked again only on "Change")
const setPhase = (p) => { phase.value = p; emit('phase', p); };

const STEPS = [
    { id: 'about', icon: 'user', title: 'intake.s_about', fields: ['name_ar', 'name_en', 'gender', 'date_of_birth'] },
    { id: 'contact', icon: 'mail', title: 'ben.s_contact', fields: ['phone', 'email'] },
    { id: 'place', icon: 'globe', title: 'intake.s_place', fields: ['governorate', 'city', 'military_status'] },
    { id: 'education', icon: 'graduation', title: 'ben.s_education', fields: ['education_level', 'education'] },
    { id: 'work', icon: 'briefcase', title: 'ben.s_work', fields: ['work_history'] },
    { id: 'occupation', icon: 'layers', title: 'ben.s_occupation', fields: ['occupation', 'esco_occupation_id', 'occupation_unit'] },
    { id: 'skills', icon: 'sparkles', title: 'ben.s_skills', fields: ['skills', 'languages'] },
    { id: 'preferences', icon: 'clipboard', title: 'ben.s_preferences', fields: ['expected_salary', 'job_type', 'notice_period'] },
    ...(props.mode === 'join' ? [
        { id: 'consent', icon: 'shield', title: 'join.s_consent', fields: ['visible', 'privacy'] },
        { id: 'account', icon: 'lock', title: 'join.s_account', fields: ['password', 'password_confirmation'] },
    ] : []),
];
const showAll = ref({});            // step id → true: every question of the step, also those the CV answered

// Was this answered by the CV with certainty? (No CV: nothing was.)
const found = (key) => !!cv.value?.read && marks.value[key] === 'found';
const QUESTIONS = {
    about: { name: () => !found('name'), gender: () => !found('gender'), date_of_birth: () => !found('date_of_birth') },
    // Public site: both are needed, so the step is asked unless the CV gave both.
    contact: { contact: () => (isPublic ? !(found('phone') && found('email')) : !(found('phone') || found('email'))) },
    place: { governorate: () => !found('governorate'), city: () => !found('governorate'), military: () => form.gender === 'male' && !found('military_status') },
    education: { education: () => !found('education') || !found('education_level') },
    work: { work: () => !found('work_history') },
    occupation: { occupation: () => !found('occupation') || !occFromCv.value },
    skills: { skills: () => !found('skills'), languages: () => !found('languages') },
    preferences: { preferences: () => true },
    consent: { consent: () => true },
    account: { account: () => true },
};
const ask = (step, q) => !cv.value?.read || showAll.value[step] || QUESTIONS[step][q]();
const stepAsked = (step) => !cv.value?.read || Object.keys(QUESTIONS[step]).some((q) => QUESTIONS[step][q]());
const steps = computed(() => STEPS.filter((s) => stepAsked(s.id) || showAll.value[s.id]));
const current = ref(0);
const step = computed(() => steps.value[current.value] ?? null);
watch(step, (s) => emit('step', s?.id ?? ''));

// ── Door 1: read the CV ──────────────────────────────────────────
const fileInput = ref(null);
const dragging = ref(false);
const accept = computed(() => props.limits.extensions.map((e) => `.${e}`).join(','));
function pickFile() { fileInput.value?.click(); }
function dropped(e) { dragging.value = false; const f = e.dataTransfer?.files?.[0]; if (f) readFile(f); }
function chosen(e) { const f = e.target.files?.[0]; e.target.value = ''; if (f) readFile(f); }
async function readFile(file) {
    readError.value = '';
    if (file.size > props.limits.max_kb * 1024) { readError.value = t('cv.too_big', { mb: Math.round(props.limits.max_kb / 1024) }); return; }
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    if (!props.limits.extensions.includes(ext)) { readError.value = t('join.wrong_type'); return; }
    setPhase('reading');
    const data = new FormData();
    data.append('file', file);
    try {
        const { data: r } = await window.axios.post(props.cvUrl, data);
        cv.value = r.cv;
        marks.value = r.marks ?? {};
        cvOccupation.value = r.occupation;
        form.cv = r.cv.uuid;
        if (r.initial) fillFrom(r.initial);
        current.value = 0;
        setPhase(steps.value.length ? 'steps' : 'review');
    } catch (err) {
        readError.value = err.response?.data?.errors?.file?.[0] ?? err.response?.data?.message ?? t('intake.read_failed');
        setPhase('start');
    }
}
function fillFrom(i) {
    for (const k of ['name_ar', 'name_en', 'gender', 'date_of_birth', 'military_status', 'governorate', 'city', 'phone', 'email', 'education_level']) {
        form[k] = i[k] ?? '';
    }
    form.education = (i.education ?? []).map((r) => ({ qualification: r.qualification ?? '', field: r.field ?? '', institution: r.institution ?? '', year: r.year ?? '' }));
    form.work_history = (i.work_history ?? []).map((r) => ({ title: r.title ?? '', employer: r.employer ?? '', location: r.location ?? '', from: r.from ?? '', to: r.to ?? '', current: !!r.current,
        country: r.country ?? 'EG', governorate: r.governorate ?? '', sub_sector: r.sub_sector ?? '', employer_id: r.employer_id ?? null,
        sector: r.sector ?? null, sub_sector_other: r.sub_sector_other ?? null, sector_unknown: !!r.sector_unknown, responsibilities: r.responsibilities ?? [] }));
    form.skills = [...(i.skills ?? [])];
    form.languages = (i.languages ?? []).map((r) => ({ ...r }));
    form.occupation = i.occupation ?? null;
    occFromCv.value = !!i.occupation;
    worked.value = form.work_history.length ? true : null;
}

// ── Door 2: questions only ───────────────────────────────────────
function noCv() { form.defaults(blank()); form.reset(); cv.value = null; marks.value = {}; cvOccupation.value = null; current.value = 0; setPhase('steps'); }

onMounted(() => {
    if (props.mode === 'join' && props.door === 'questions') noCv();
    if (props.mode === 'edit' && props.initial) {
        fillFrom(props.initial);
        occFromCv.value = false;
        form.expected_salary = props.initial.expected_salary ?? '';
        form.job_type = props.initial.job_type ?? '';
        form.notice_period = props.initial.notice_period ?? '';
        form.defaults(); // "unchanged" is the profile as it is
        setPhase('review');
    }
});

// ── Moving through the steps ─────────────────────────────────────
const stepError = ref('');
function check(id) {
    if (id === 'about' && !(form.name_ar.trim() || form.name_en.trim())) return t('intake.need_name');
    if (id === 'about' && !form.gender) return t('intake.need_gender');
    if (id === 'contact' && isPublic && !(form.phone.trim() && form.email.trim())) return t('join.need_contact');
    if (id === 'contact' && !(form.phone.trim() || form.email.trim())) return t('intake.need_contact');
    if (id === 'place' && !form.governorate) return t('intake.need_governorate');
    if (id === 'work' && form.work_history.some((j) => !j.title.trim() || !j.from)) return t('intake.need_job');
    if (id === 'consent' && !form.privacy) return t('join.need_privacy');
    if (id === 'account' && form.password.length < 8) return t('join.need_password');
    if (id === 'account' && form.password !== form.password_confirmation) return t('join.need_same_password');
    return '';
}
function next() {
    stepError.value = check(step.value.id);
    if (stepError.value) return;
    if (current.value < steps.value.length - 1) current.value++;
    else setPhase('review');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function back() {
    stepError.value = '';
    if (phase.value === 'review') { setPhase('steps'); current.value = steps.value.length - 1; return; }
    if (current.value > 0) current.value--;
    else if (props.mode === 'edit') setPhase('review');
    else setPhase('start');
}
function goTo(id) {
    showAll.value[id] = true;
    setPhase('steps');
    current.value = Math.max(0, steps.value.findIndex((s) => s.id === id));
}

// Lists
const addEducation = () => form.education.push({ qualification: '', field: '', institution: '', year: '' });
const addJob = () => form.work_history.push({ title: '', employer: '', location: '', from: '', to: '', current: form.work_history.length === 0, country: 'EG', governorate: '', sub_sector: '', employer_id: null, responsibilities: [] });
// ⇄ the CV reader put the company in "Job title" and the title in "Employer": swap them.
const swapJob = (r) => { [r.title, r.employer] = [r.employer ?? '', r.title ?? '']; };
function setWorked(v) {
    worked.value = v;
    if (v && !form.work_history.length) addJob();
    if (!v) form.work_history = [];
}
const skill = ref('');
function addSkill() {
    skill.value.split(/[,،\n]/).map((s) => s.trim()).filter(Boolean).forEach((s) => {
        if (!form.skills.some((x) => x.toLowerCase() === s.toLowerCase()) && form.skills.length < props.options.max.skills) form.skills.push(s);
    });
    skill.value = '';
}
function skillKey(e) { if (e.key === 'Enter' || e.key === ',' || e.key === '،') { e.preventDefault(); addSkill(); } }
const addLanguage = () => {
    const used = new Set(form.languages.map((l) => l.code));
    form.languages.push({ code: props.options.languages.find((c) => !used.has(c)) ?? '', level: 'good' });
};
const occTitle = (block) => { const s = occIn(block, block.esco ? 'esco' : prefs.standard === 'esco' ? 'isco' : prefs.standard, locale.value); return `${s.code} · ${s.title}`; };

// ── Save ─────────────────────────────────────────────────────────
const err = (k) => form.errors[k] ?? '';
const stepOf = (key) => STEPS.find((s) => s.fields.some((f) => key === f || key.startsWith(`${f}.`)))?.id ?? null;
const errorList = computed(() => Object.entries(form.errors).filter(([k]) => k !== 'duplicate').map(([k, m]) => ({ key: k, message: m, step: stepOf(k) })));
function save() {
    if (skill.value.trim()) addSkill();
    form.transform((d) => {
        const { occupation, ...rest } = d;
        return {
            ...rest,
            work_history: d.work_history.map((j) => ({ ...j, to: j.current ? '' : j.to })),
            military_status: d.gender === 'male' ? d.military_status : '',
            esco_occupation_id: occupation?.esco_id ?? null,
            occupation_unit: occupation && occupation.group_only ? occupation.unit_code : null,
        };
    })[props.mode === 'edit' ? 'patch' : 'post'](props.saveUrl, { preserveScroll: true, onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }) });
}

// ── The check-and-save page ──────────────────────────────────────
const gov = computed(() => (form.governorate ? t(`gov.${form.governorate}`) : ''));
const summary = computed(() => [
    { id: 'about', lines: [[form.name_ar, form.name_en].filter(Boolean).join(' · '), form.gender ? t(`ben.gender_${form.gender}`) : '', form.date_of_birth].filter(Boolean) },
    { id: 'contact', lines: [form.phone, form.email].filter(Boolean) },
    { id: 'place', lines: [[form.city, gov.value].filter(Boolean).join(', '), form.gender === 'male' && form.military_status ? t(`ben.mil_${form.military_status}`) : ''].filter(Boolean) },
    { id: 'education', lines: [form.education_level ? t(`ben.edu_${form.education_level}`) : '', ...form.education.map((e) => [e.qualification, e.institution, e.year].filter(Boolean).join(' · '))].filter(Boolean) },
    { id: 'work', lines: form.work_history.map((j) => `${j.title}${j.employer ? ' · ' + j.employer : ''}${j.location ? ' · ' + j.location : ''}${j.governorate ? ' · ' + t(`gov.${j.governorate}`) : ''}${j.country && j.country !== 'EG' ? ' · ' + t(`country.${j.country}`) : ''} (${j.from || '?'} – ${j.current ? t('ben.present') : (j.to || '?')})${subName(j.sub_sector) ? ' · ' + subName(j.sub_sector) : (j.sub_sector_other ? ' · ' + j.sub_sector_other : '')}`) },
    { id: 'occupation', lines: form.occupation ? [occTitle(form.occupation)] : [] },
    { id: 'skills', lines: [form.skills.join(', '), form.languages.map((l) => `${t(`ben.lang_${l.code}`)} (${t(`ben.level_${l.level}`)})`).join(', ')].filter(Boolean) },
    { id: 'preferences', lines: [form.expected_salary ? `${form.expected_salary} ${t('ben.egp_month')}` : '', form.job_type ? t(`ben.job_${form.job_type}`) : '',
        isPublic && form.notice_period ? `${t('join.notice')}: ${t(`join.notice_${form.notice_period}`)}` : ''].filter(Boolean) },
    ...(props.mode === 'join' ? [{ id: 'consent', lines: [t(form.visible ? 'join.visible_yes' : 'join.visible_no'), form.privacy ? t('join.privacy_ok') : ''].filter(Boolean) }] : []),
]);
const subName = (code) => {
    for (const x of props.options.sectors ?? []) {
        const y = x.subs.find((z) => z.code === code);
        if (y) return locale.value === 'ar' ? y.name_ar : y.name_en;
    }
    return '';
};
const stepTitle = (id) => t(STEPS.find((s) => s.id === id).title);
const stepIcon = (id) => STEPS.find((s) => s.id === id).icon;
const saveLabel = computed(() => (props.mode === 'join' ? t('join.create') : props.mode === 'edit' ? t('join.save_edit') : t('intake.save')));
</script>

<template>
    <div class="ij" :class="`ij-${mode}`">
        <!-- ── Start ────────────────────────────────────────── -->
        <template v-if="phase === 'start'">
            <div v-if="readError" class="alert warning mb-3"><AppIcon name="alert" :size="16" /><span>{{ readError }}</span></div>
            <input ref="fileInput" type="file" class="hidden" :accept="accept" @change="chosen">

            <!-- Staff: the two doors -->
            <div v-if="mode === 'staff'" class="doors">
                <button v-if="canUpload" type="button" class="sdoor panel" @click="pickFile">
                    <AppIcon name="file" :size="26" />
                    <b>{{ t('intake.with_cv') }}</b>
                    <span>{{ t('intake.with_cv_sub') }}</span>
                </button>
                <button type="button" class="sdoor panel" @click="noCv">
                    <AppIcon name="clipboard" :size="26" />
                    <b>{{ t('intake.no_cv') }}</b>
                    <span>{{ t('intake.no_cv_sub') }}</span>
                </button>
            </div>

            <!-- Public site: the drop zone -->
            <template v-else>
                <h1 class="ph">{{ t('join.cv_title') }}</h1>
                <p class="mute mb-4">{{ t('join.cv_sub', { mb: Math.round(limits.max_kb / 1024) }) }}</p>
                <div class="drop" :class="{ over: dragging }" @dragover.prevent="dragging = true" @dragleave="dragging = false" @drop.prevent="dropped">
                    <div class="ic"><AppIcon name="upload" :size="26" /></div>
                    <h3>{{ t('join.drop') }}</h3>
                    <p class="mute small">{{ t('join.or') }}</p>
                    <div class="row"><button type="button" class="btn btn-primary" @click="pickFile">{{ t('join.choose') }}</button></div>
                </div>
                <p class="mute small mt-3">{{ t('join.no_cv_q') }} <button type="button" class="linkbtn" @click="noCv">{{ t('join.no_cv_link') }}</button></p>
            </template>
        </template>

        <div v-else-if="phase === 'reading'">
            <template v-if="mode === 'staff'">
                <div class="panel reading"><span class="spinner" aria-hidden="true"></span><span>{{ t('intake.reading') }}</span></div>
            </template>
            <template v-else>
                <h1 class="ph">{{ t('join.reading') }}</h1>
                <ul class="rsteps">
                    <li class="ok"><i>✓</i>{{ t('join.r1') }}</li>
                    <li class="on"><i></i>{{ t('join.r2') }}</li>
                    <li><i></i>{{ t('join.r3') }}</li>
                </ul>
            </template>
        </div>

        <!-- ── One step at a time ───────────────────────────── -->
        <div v-else-if="phase === 'steps' && step" class="journey">
            <div class="progress" :aria-label="t('intake.progress', { n: current + 1, of: steps.length })">
                <span v-for="(s, i) in steps" :key="s.id" :class="{ done: i < current, now: i === current }"></span>
            </div>
            <div class="small mute mb-2">{{ t('intake.progress', { n: current + 1, of: steps.length }) }}</div>

            <div v-if="cv?.read" class="cvinfo"><AppIcon name="file" :size="14" />{{ t('intake.from_cv', { file: cv.file }) }}</div>
            <div v-else-if="cv && !cv.read" class="alert warning mb-3"><AppIcon name="alert" :size="16" /><span>{{ t(cv.problem !== 'no_pdf_reader' ? 'intake.cv_unread' : isPublic ? 'join.cv_unread_ours' : 'intake.cv_unread_reader') }}</span></div>

            <div class="panel">
                <h3><AppIcon :name="step.icon" :size="16" />{{ t(step.title) }}</h3>

                <!-- About you -->
                <template v-if="step.id === 'about'">
                    <div v-if="ask('about', 'name')" class="q">
                        <p class="ql">{{ t(isPublic ? 'join.q_name' : 'intake.q_name') }}</p>
                        <div class="form-grid">
                            <Field :label="t('ben.f_name_ar')" :error="err('name_ar')"><input v-model="form.name_ar" type="text" dir="rtl" lang="ar" maxlength="150" autocomplete="off"></Field>
                            <Field :label="t('ben.f_name_en')" :error="err('name_en')"><input v-model="form.name_en" type="text" dir="ltr" lang="en" maxlength="150" autocomplete="off"></Field>
                        </div>
                    </div>
                    <div v-if="ask('about', 'gender')" class="q">
                        <p class="ql">{{ t(isPublic ? 'join.q_gender' : 'intake.q_gender') }}</p>
                        <div class="seg big" role="group">
                            <button v-for="g in options.genders" :key="g" type="button" :aria-pressed="form.gender === g" @click="form.gender = g">{{ t(`ben.gender_${g}`) }}</button>
                        </div>
                    </div>
                    <div v-if="ask('about', 'date_of_birth')" class="q">
                        <p class="ql">{{ t(isPublic ? 'join.q_birth' : 'intake.q_birth') }} <span class="mute small">{{ t('common.optional') }}</span></p>
                        <Field :error="err('date_of_birth')"><input v-model="form.date_of_birth" type="date" min="1940-01-02" :max="todayIso()"></Field>
                    </div>
                </template>

                <!-- Contact -->
                <template v-else-if="step.id === 'contact'">
                    <p class="ql">{{ t(isPublic ? 'join.q_contact' : 'intake.q_contact') }}</p>
                    <div class="form-grid">
                        <Field :label="t('ben.f_phone')" :error="err('phone')" :hint="t('ben.phone_hint')" :required="isPublic"><input v-model="form.phone" type="tel" dir="ltr" maxlength="20" autocomplete="tel"></Field>
                        <Field v-if="mode === 'edit'" :label="t('ben.f_email')" :hint="t('join.email_locked')"><input :value="form.email" type="email" dir="ltr" readonly></Field>
                        <Field v-else :label="t('ben.f_email')" :error="err('email')" :optional="!isPublic" :required="isPublic" :hint="isPublic ? t('join.email_hint') : ''">
                            <input v-model="form.email" type="email" dir="ltr" maxlength="150" :autocomplete="isPublic ? 'email' : 'off'">
                        </Field>
                    </div>
                </template>

                <!-- Where you live -->
                <template v-else-if="step.id === 'place'">
                    <div v-if="ask('place', 'governorate')" class="q">
                        <p class="ql">{{ t(isPublic ? 'join.q_place' : 'intake.q_place') }}</p>
                        <div class="form-grid">
                            <Field :label="t('ben.f_governorate')" :error="err('governorate')">
                                <select v-model="form.governorate">
                                    <option value="" disabled>—</option>
                                    <option v-for="g in options.governorates" :key="g" :value="g">{{ t(`gov.${g}`) }}</option>
                                </select>
                            </Field>
                            <Field :label="t('ben.f_city')" :error="err('city')" optional><input v-model="form.city" type="text" maxlength="100"></Field>
                        </div>
                    </div>
                    <div v-if="form.gender === 'male' && ask('place', 'military')" class="q">
                        <p class="ql">{{ t(isPublic ? 'join.q_military' : 'intake.q_military') }}</p>
                        <Field :error="err('military_status')">
                            <select v-model="form.military_status">
                                <option value="">—</option>
                                <option v-for="m in options.military_statuses" :key="m" :value="m">{{ t(`ben.mil_${m}`) }}</option>
                            </select>
                        </Field>
                    </div>
                </template>

                <!-- Education -->
                <template v-else-if="step.id === 'education'">
                    <p class="ql">{{ t(isPublic ? 'join.q_education' : 'intake.q_education') }}</p>
                    <Field :label="t('ben.f_education_level')" :error="err('education_level')">
                        <select v-model="form.education_level">
                            <option value="">—</option>
                            <option v-for="e in options.education_levels" :key="e" :value="e">{{ t(`ben.edu_${e}`) }}</option>
                        </select>
                    </Field>
                    <p class="ql mt-3">{{ t(isPublic ? 'join.q_studied' : 'intake.q_studied') }} <span class="mute small">{{ t('common.optional') }}</span></p>
                    <div v-for="(r, i) in form.education" :key="`e${i}`" class="row4">
                        <Field :label="t('ben.f_qualification')" :error="err(`education.${i}.qualification`)"><input v-model="r.qualification" type="text" maxlength="150" :placeholder="t('ben.qualification_ph')"></Field>
                        <Field :label="t('ben.f_field')"><input v-model="r.field" type="text" maxlength="150"></Field>
                        <Field :label="t('ben.f_institution')"><input v-model="r.institution" type="text" maxlength="150"></Field>
                        <Field :label="t('ben.f_year')" :error="err(`education.${i}.year`)"><input v-model="r.year" type="number" min="1950" :max="new Date().getFullYear() + 6"></Field>
                        <button type="button" class="xdel" :aria-label="t('common.delete')" @click="form.education.splice(i, 1)">×</button>
                    </div>
                    <button v-if="form.education.length < options.max.education" type="button" class="addmini" @click="addEducation">+ {{ t('ben.add_qualification') }}</button>
                </template>

                <!-- Work -->
                <template v-else-if="step.id === 'work'">
                    <p class="ql">{{ t(isPublic ? 'join.q_worked' : 'intake.q_worked') }}</p>
                    <div class="seg big mb-3" role="group">
                        <button type="button" :aria-pressed="worked === true || form.work_history.length > 0" @click="setWorked(true)">{{ t('common.yes') }}</button>
                        <button type="button" :aria-pressed="worked === false && !form.work_history.length" @click="setWorked(false)">{{ t(isPublic ? 'join.first_job' : 'intake.first_job') }}</button>
                    </div>
                    <template v-if="form.work_history.length">
                        <p class="ql">{{ t(isPublic ? 'join.q_jobs' : 'intake.q_jobs') }}</p>
                        <div v-for="(r, i) in form.work_history" :key="`w${i}`" class="job">
                            <div class="form-grid">
                                <Field :label="t('ben.f_job_title')" :error="err(`work_history.${i}.title`)"><input v-model="r.title" type="text" maxlength="150"></Field>
                                <EmployerInput :job="r" :url="employerUrl || route('app.employer-search')" :sectors="options.sectors ?? []" optional :public="isPublic"
                                               :error="err(`work_history.${i}.employer`) || err(`work_history.${i}.employer_id`)" />
                                <Field :label="t('ben.f_from')" :error="err(`work_history.${i}.from`)"><input v-model="r.from" type="month" :max="thisMonth"></Field>
                                <div class="fld" :class="{ 'has-error': !!err(`work_history.${i}.to`) }">
                                    <label class="fl" :for="`ito${i}`">{{ t('ben.f_to') }}</label>
                                    <input :id="`ito${i}`" v-model="r.to" type="month" :min="r.from || undefined" :max="thisMonth" :disabled="r.current">
                                    <label class="checkline cur"><input v-model="r.current" type="checkbox" @change="r.current && (r.to = '')"> {{ t(isPublic ? 'join.current_job' : 'ben.current_job') }}</label>
                                    <span v-if="err(`work_history.${i}.to`)" class="fld-error"><AppIcon name="alert" :size="12" />{{ err(`work_history.${i}.to`) }}</span>
                                </div>
                            </div>
                            <button v-if="cv?.read" type="button" class="linkbtn swapbtn" :title="t('ben.swap')" @click="swapJob(r)">⇄ {{ t('ben.swap') }}</button>
                            <JobSector class="mt-2" :job="r" :options="options" :public="isPublic" :errors="{ sub_sector: err(`work_history.${i}.sub_sector`) }" />
                            <button type="button" class="linkbtn danger" @click="form.work_history.splice(i, 1)">{{ t('intake.remove_job') }}</button>
                        </div>
                        <button v-if="form.work_history.length < options.max.work_history" type="button" class="addmini" @click="addJob">+ {{ t('intake.add_job') }}</button>
                    </template>
                </template>

                <!-- Occupation -->
                <template v-else-if="step.id === 'occupation'">
                    <p class="ql">{{ t(isPublic ? 'join.q_occupation' : 'intake.q_occupation') }}</p>
                    <div v-if="cvOccupation?.candidates?.length && !form.occupation" class="opts mb-3">
                        <div class="small mute mb-1">{{ t('intake.from_cv_title', { title: cvOccupation.title }) }}</div>
                        <button v-for="(c, i) in cvOccupation.candidates" :key="i" type="button" class="opt" @click="form.occupation = c.block">
                            <span class="grow">{{ occTitle(c.block) }}</span>
                            <span class="btn btn-line xs">{{ t('cv.use') }}</span>
                        </button>
                    </div>
                    <div v-if="!backbone" class="alert info"><AppIcon name="info" :size="16" /><span>{{ t('ben.no_backbone') }}</span></div>
                    <OccupationPicker v-else v-model="form.occupation" :gender="form.gender" :url="pickerUrl" :error="err('occupation') || err('esco_occupation_id') || err('occupation_unit')" />
                    <div class="small mute mt-2">{{ t(isPublic ? 'join.occupation_later' : 'intake.occupation_later') }}</div>
                </template>

                <!-- Skills and languages -->
                <template v-else-if="step.id === 'skills'">
                    <div v-if="ask('skills', 'skills')" class="q">
                        <p class="ql">{{ t(isPublic ? 'join.q_skills' : 'intake.q_skills') }}</p>
                        <div class="chipbox">
                            <span v-for="(s, i) in form.skills" :key="s" class="tag plain">{{ s }}<button type="button" :aria-label="`${t('common.delete')} ${s}`" @click="form.skills.splice(i, 1)">×</button></span>
                            <input v-model="skill" type="text" maxlength="60" :placeholder="form.skills.length ? '' : t('ben.skills_ph')" @keydown="skillKey" @blur="skill.trim() && addSkill()">
                        </div>
                    </div>
                    <div v-if="ask('skills', 'languages')" class="q">
                        <p class="ql">{{ t(isPublic ? 'join.q_languages' : 'intake.q_languages') }}</p>
                        <div v-for="(r, i) in form.languages" :key="`l${i}`" class="row2">
                            <select v-model="r.code"><option v-for="c in options.languages" :key="c" :value="c">{{ t(`ben.lang_${c}`) }}</option></select>
                            <select v-model="r.level"><option v-for="l in options.language_levels" :key="l" :value="l">{{ t(`ben.level_${l}`) }}</option></select>
                            <button type="button" class="xdel" :aria-label="t('common.delete')" @click="form.languages.splice(i, 1)">×</button>
                        </div>
                        <button v-if="form.languages.length < options.max.languages" type="button" class="addmini" @click="addLanguage">+ {{ t('ben.add_language') }}</button>
                    </div>
                </template>

                <!-- Preferences -->
                <template v-else-if="step.id === 'preferences'">
                    <p class="ql">{{ t(isPublic ? 'join.q_salary' : 'intake.q_salary') }} <span class="mute small">{{ t('common.optional') }}</span></p>
                    <Field :error="err('expected_salary')">
                        <div class="inp-group">
                            <input v-model="form.expected_salary" type="text" inputmode="numeric" maxlength="12" placeholder="7500">
                            <span class="addon">{{ t('ben.egp_month') }}</span>
                        </div>
                    </Field>
                    <p class="ql mt-3">{{ t(isPublic ? 'join.q_job_type' : 'intake.q_job_type') }} <span class="mute small">{{ t('common.optional') }}</span></p>
                    <div class="seg" role="group">
                        <button v-for="j in options.job_types" :key="j" type="button" :aria-pressed="form.job_type === j" @click="form.job_type = form.job_type === j ? '' : j">{{ t(`ben.job_${j}`) }}</button>
                    </div>
                    <template v-if="isPublic">
                        <p class="ql mt-3">{{ t('join.q_notice') }} <span class="mute small">{{ t('common.optional') }}</span></p>
                        <div class="seg" role="group">
                            <button v-for="n in options.notice_periods" :key="n" type="button" :aria-pressed="form.notice_period === n" @click="form.notice_period = form.notice_period === n ? '' : n">{{ t(`join.notice_${n}`) }}</button>
                        </div>
                    </template>
                </template>

                <!-- Consent (public site) -->
                <template v-else-if="step.id === 'consent'">
                    <label class="checkline big">
                        <input v-model="form.visible" type="checkbox">
                        <span><b>{{ t('join.visible_label') }}</b><br><span class="mute small">{{ t('join.visible_text') }}</span></span>
                    </label>
                    <label class="checkline big mt-3" :class="{ 'has-error': !!err('privacy') }">
                        <input v-model="form.privacy" type="checkbox">
                        <span>{{ t('join.privacy_label') }}</span>
                    </label>
                    <details class="privacy mt-3">
                        <summary>{{ t('join.privacy_read') }}</summary>
                        <ul>
                            <li>{{ t('join.p1') }}</li>
                            <li>{{ t('join.p2') }}</li>
                            <li>{{ t('join.p3') }}</li>
                            <li>{{ t('join.p4') }}</li>
                            <li>{{ t('join.p5') }}</li>
                        </ul>
                    </details>
                    <span v-if="err('privacy')" class="fld-error"><AppIcon name="alert" :size="12" />{{ err('privacy') }}</span>
                </template>

                <!-- Sign-in (public site) -->
                <template v-else-if="step.id === 'account'">
                    <p class="ql">{{ t('join.q_account') }}</p>
                    <div class="small mute mb-2">{{ t('join.account_email', { email: form.email }) }}</div>
                    <Field :label="t('join.password')" :error="err('password')" :hint="t('join.password_hint')">
                        <PasswordInput v-model="form.password" autocomplete="new-password" />
                    </Field>
                    <Field :label="t('join.password_again')" :error="err('password_confirmation')">
                        <PasswordInput v-model="form.password_confirmation" autocomplete="new-password" />
                    </Field>
                    <div class="note mt-3"><AppIcon name="mail" :size="16" /><div class="grow">{{ t('join.code_note') }}</div></div>
                </template>

                <button v-if="cv?.read && !showAll[step.id] && !['preferences', 'consent', 'account'].includes(step.id)" type="button" class="linkbtn mt-3" @click="showAll[step.id] = true">{{ t('intake.show_all') }}</button>
                <div v-if="stepError" class="alert warning mt-3"><AppIcon name="alert" :size="16" /><span>{{ stepError }}</span></div>
            </div>

            <div class="navbar">
                <button type="button" class="btn btn-line" @click="back"><AppIcon name="arrow-left" :size="14" class="flip" />{{ t('intake.back') }}</button>
                <button type="button" class="btn btn-primary" @click="next">{{ current < steps.length - 1 ? t('intake.next') : t('intake.to_check') }}</button>
            </div>
        </div>

        <!-- ── Check and save ───────────────────────────────── -->
        <div v-else-if="phase === 'review'" class="journey">
            <div v-if="form.errors.cv" class="alert warning mb-3"><AppIcon name="alert" :size="16" /><span>{{ form.errors.cv }}</span></div>
            <div v-if="form.errors.duplicate" class="alert warning mb-3">
                <AppIcon name="alert" :size="16" />
                <div class="grow">
                    <div>{{ form.errors.duplicate }}</div>
                    <label class="checkline mt-2"><input v-model="form.confirm_duplicate" type="checkbox"> {{ t('ben.dup_confirm') }}</label>
                </div>
            </div>
            <div v-if="errorList.length" class="alert warning mb-3">
                <AppIcon name="alert" :size="16" />
                <div class="grow">
                    <div v-for="e in errorList" :key="e.key" class="errline">
                        <span>{{ e.message }}</span>
                        <button v-if="e.step" type="button" class="btn btn-line xs" @click="goTo(e.step)">{{ t('intake.fix') }}</button>
                    </div>
                </div>
            </div>

            <div class="panel">
                <h3><AppIcon name="check" :size="16" />{{ t(mode === 'edit' ? 'join.edit_title' : 'intake.check_title') }}</h3>
                <div class="sub">{{ t(mode === 'edit' ? 'join.edit_sub' : cv?.read ? (isPublic ? 'join.check_sub_cv' : 'intake.check_sub_cv') : (isPublic ? 'join.check_sub' : 'intake.check_sub')) }}</div>
                <div v-for="s in summary" :key="s.id" class="sumrow">
                    <div class="sl"><AppIcon :name="stepIcon(s.id)" :size="14" />{{ stepTitle(s.id) }}</div>
                    <div class="sv">
                        <div v-for="(l, i) in s.lines" :key="i">{{ l }}</div>
                        <div v-if="!s.lines.length" class="mute">—</div>
                    </div>
                    <button type="button" class="btn btn-line xs" @click="goTo(s.id)">{{ t(mode === 'edit' ? 'common.edit' : 'intake.change') }}</button>
                </div>
            </div>

            <div class="navbar">
                <button v-if="mode !== 'edit'" type="button" class="btn btn-line" @click="back"><AppIcon name="arrow-left" :size="14" class="flip" />{{ t('intake.back') }}</button>
                <slot v-else name="cancel" />
                <button type="button" class="btn btn-primary" :class="{ 'is-loading': form.processing }" :disabled="form.processing" @click="save">
                    <AppIcon name="check" :size="15" />{{ saveLabel }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.doors { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; max-width: 760px; }
.sdoor { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; text-align: start; cursor: pointer; padding: 22px; border: 1px solid var(--ms-border); }
.sdoor:hover { border-color: var(--ms-border-focus); }
.sdoor b { font-size: 16px; }
.sdoor span { font-size: 13px; color: var(--ms-text-muted); }
.panel + .panel { margin-top: 0; }
.hidden { display: none; }
.ph { font-size: 24px; margin: 6px 0; }
.reading { display: flex; gap: 12px; align-items: center; max-width: 760px; }
.journey { max-width: 760px; }
.ij-join .journey, .ij-edit .journey { max-width: none; }
.progress { display: flex; gap: 4px; margin-bottom: 6px; }
.progress span { flex: 1; height: 5px; border-radius: 3px; background: var(--ms-border); }
.progress span.done { background: var(--ms-green); }
.progress span.now { background: var(--ms-navy); }
.cvinfo { display: flex; gap: 6px; align-items: center; font-size: 12.5px; color: var(--ms-text-muted); margin-bottom: 8px; }
.q + .q { margin-top: 18px; }
.ql { font-size: 15px; font-weight: 600; margin: 0 0 8px; }
.ij-join .ql, .ij-edit .ql { font-size: 17px; }
.seg { display: flex; flex-wrap: wrap; }
.seg button { flex: 1; min-width: 110px; padding: 9px 10px; font-size: 13px; }
.seg.big button { padding: 12px; font-size: 14px; }
.row4 { display: grid; grid-template-columns: 1.4fr 1fr 1.2fr 90px 34px; gap: 8px; align-items: end; margin-bottom: 8px; }
.row2 { display: grid; grid-template-columns: minmax(0, 220px) minmax(0, 220px) 34px; gap: 8px; margin-bottom: 8px; }
.job { border: 1px solid var(--ms-border); border-radius: var(--r-md); padding: 12px; margin-bottom: 10px; }
.cur { font-size: 11.5px; margin-top: 4px; }
.linkbtn { background: none; border: 0; padding: 0; color: var(--ms-navy); cursor: pointer; font-size: 12.5px; }
.linkbtn.danger { color: var(--ms-danger); margin-top: 6px; }
.swapbtn { margin-top: 6px; display: inline-block; }
.navbar { display: flex; justify-content: space-between; gap: 10px; margin-top: 16px; }
.opt { width: 100%; text-align: start; display: flex; gap: 8px; align-items: center; }
.opt .grow { flex: 1; font-size: 13px; }
.opts .opt + .opt { margin-top: 6px; }
.btn.xs { padding: 3px 8px; font-size: 11.5px; }
.chipbox { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; border: 1px solid var(--ms-border); background: var(--ms-bg-input); border-radius: var(--r-md); padding: 6px 8px; min-height: 40px; }
.chipbox .tag button { border: 0; background: none; color: inherit; margin-inline-start: 6px; font-size: 14px; line-height: 1; padding: 0; cursor: pointer; }
.chipbox input { flex: 1; min-width: 140px; border: 0 !important; background: transparent !important; box-shadow: none !important; padding: 4px !important; }
.sumrow { display: grid; grid-template-columns: 170px minmax(0, 1fr) auto; gap: 10px; align-items: start; padding: 10px 0; border-top: 1px solid var(--ms-border); font-size: 13px; }
.sumrow .sl { display: flex; gap: 6px; align-items: center; font-weight: 600; }
.errline { display: flex; justify-content: space-between; gap: 10px; align-items: center; }
.errline + .errline { margin-top: 6px; }
.checkline.big { font-size: 14px; }
.checkline.has-error { color: var(--ms-danger); }
.privacy summary { cursor: pointer; font-size: 13px; color: var(--ms-navy); }
.privacy ul { margin: 8px 0 0; padding-inline-start: 20px; font-size: 13px; color: var(--ms-text-muted); display: grid; gap: 4px; }
[dir="rtl"] .flip { transform: scaleX(-1); }
@media (max-width: 760px) {
    .row4 { grid-template-columns: 1fr 1fr; }
    .sumrow { grid-template-columns: 1fr auto; }
    .sumrow .sv { grid-column: 1 / -1; grid-row: 2; }
}
</style>
