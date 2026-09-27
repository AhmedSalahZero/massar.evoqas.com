<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — one job seeker in the Public Talent Pool (Step 10)
//  Location: resources/js/Pages/App/Pool/Show.vue
//  Route: GET /app/pool/{uuid} (pool.show) — App\PoolController
//
//  The profile the job seeker made themselves, their CV (download,
//  logged), the salary check and the labour-market panel of the
//  occupation. "Add to my workspace" makes this partner's own copy
//  (profile + CV); if it was added before, "Open in my workspace".
//  The same mobile or email already in the workspace asks the usual
//  duplicate question first.
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import SalaryCheck from '@/Components/Beneficiaries/SalaryCheck.vue';
import MarketPanel from '@/Components/Backbone/MarketPanel.vue';
import { useTranslations } from '@/composables/useTranslations';
import { usePreferences } from '@/composables/usePreferences';
import { experience, money, occIn } from '@/Components/Beneficiaries/ben';
import { formatDate } from '@/Utils/date';

const props = defineProps({
    uuid: { type: String, required: true },
    profile: { type: Object, required: true },
    occupation: { type: Object, default: null },
    market: { type: Object, default: null },
    salary: { type: Object, required: true },
    cv: { type: Object, default: null },
    copy: { type: Object, default: null },
    can_add: { type: Boolean, default: false },
    can_download: { type: Boolean, default: false },
});

const { t, locale } = useTranslations();
const { prefs } = usePreferences();
const p = props.profile;
const name = computed(() => (locale.value === 'ar' ? p.name_ar || p.name_en : p.name_en || p.name_ar));
const other = computed(() => (locale.value === 'ar' ? p.name_en : p.name_ar));
const occ = computed(() => {
    if (!props.occupation) return null;
    const s = occIn(props.occupation, props.occupation.esco ? 'esco' : prefs.standard === 'esco' ? 'isco' : prefs.standard, locale.value);
    return `${s.code} · ${s.title}`;
});

const form = useForm({ confirm_duplicate: false });
const add = () => form.post(route('app.pool.add', props.uuid), { preserveScroll: true });
</script>

<template>
    <AppLayout :title="name">
        <Link :href="route('app.pool.index')" class="back"><AppIcon name="arrow-left" :size="14" />{{ t('pool.title') }}</Link>

        <div class="hero">
            <div class="top">
                <span class="avatar lg">{{ p.initials }}</span>
                <div class="grow">
                    <h1>{{ name }}</h1>
                    <div v-if="other" class="alt">{{ other }}</div>
                    <div class="meta">
                        <span class="tag teal"><AppIcon name="globe" :size="12" /> {{ t('pool.self_registered') }}</span>
                        <span v-if="occ" class="tag plain">{{ occ }}</span>
                        <span v-if="p.governorate" class="tag plain">{{ t(`gov.${p.governorate}`) }}<template v-if="p.city"> · {{ p.city }}</template></span>
                        <span v-if="p.experience_months" class="tag plain">{{ experience(p.experience_months, t, locale) }}</span>
                    </div>
                </div>
                <div class="acts">
                    <Link v-if="copy" :href="route('app.beneficiaries.show', copy.number)" class="btn btn-line"><AppIcon name="check" :size="15" />{{ t('pool.open_copy', { number: copy.number }) }}</Link>
                    <button v-else-if="can_add" type="button" class="btn btn-primary" :class="{ 'is-loading': form.processing }" :disabled="form.processing" @click="add">
                        <AppIcon name="user-plus" :size="15" />{{ t('pool.add') }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="form.errors.duplicate" class="alert warning mt-3">
            <AppIcon name="alert" :size="16" />
            <div class="grow">
                <div>{{ form.errors.duplicate }}</div>
                <label class="checkline mt-2"><input v-model="form.confirm_duplicate" type="checkbox"> {{ t('ben.dup_confirm') }}</label>
                <button type="button" class="btn btn-primary sm mt-2" :disabled="!form.confirm_duplicate || form.processing" @click="add">{{ t('pool.add') }}</button>
            </div>
        </div>
        <div class="note mt-3"><AppIcon name="info" :size="16" /><div class="grow">{{ t('pool.add_note') }}</div></div>

        <div class="grid-2 mt-4" style="align-items:start">
            <div class="panel">
                <h3><AppIcon name="mail" :size="16" />{{ t('ben.s_contact') }}</h3>
                <dl class="kv mt-3">
                    <dt>{{ t('ben.f_phone') }}</dt><dd><bdi>{{ p.phone || '—' }}</bdi></dd>
                    <dt>{{ t('ben.f_email') }}</dt><dd><bdi>{{ p.email || '—' }}</bdi></dd>
                    <dt>{{ t('ben.f_gender') }}</dt><dd>{{ p.gender ? t(`ben.gender_${p.gender}`) : '—' }}<template v-if="p.age"> · {{ t('pool.age', { n: p.age }) }}</template></dd>
                    <dt>{{ t('ben.s_preferences') }}</dt>
                    <dd>{{ [p.job_type ? t(`ben.job_${p.job_type}`) : '', p.expected_salary ? `${money(p.expected_salary, locale)} ${t('ben.egp_month')}` : '',
                        p.notice_period ? `${t('join.notice')}: ${t(`join.notice_${p.notice_period}`)}` : ''].filter(Boolean).join(' · ') || '—' }}</dd>
                    <dt>{{ t('pool.since') }}</dt><dd>{{ formatDate(p.since, locale) }}</dd>
                </dl>
            </div>
            <div class="panel">
                <h3><AppIcon name="file" :size="16" />{{ t('pool.cv') }}</h3>
                <div v-if="cv" class="cvrow mt-2">
                    <span class="grow min0"><b>{{ cv.file }}</b></span>
                    <a v-if="can_download" :href="route('app.pool.cv', uuid)" class="btn btn-line sm">{{ t('cv.download') }}</a>
                </div>
                <p v-else class="mute small mt-2">{{ t('pool.no_cv') }}</p>
                <h3 class="mt-4"><AppIcon name="sparkles" :size="16" />{{ t('ben.s_skills') }}</h3>
                <div class="tags mt-2"><span v-for="s in p.skills" :key="s" class="tag plain">{{ s }}</span><span v-if="!p.skills.length" class="mute small">—</span></div>
                <div v-if="p.languages.length" class="small mute mt-2">{{ p.languages.map((l) => `${t(`ben.lang_${l.code}`)} (${t(`ben.level_${l.level}`)})`).join(', ') }}</div>
            </div>
        </div>

        <SalaryCheck class="mt-4" :salary="salary" :market="market" />
        <MarketPanel v-if="occupation" class="mt-4" :market="market"
                     :inherited="occupation.esco && occupation.enoc ? { code: occupation.enoc.code, title: occupation.enoc.title } : null" />

        <div class="grid-2 mt-4" style="align-items:start">
            <div class="panel">
                <h3><AppIcon name="briefcase" :size="16" />{{ t('ben.s_work') }}</h3>
                <div v-for="(j, i) in p.work_history" :key="i" class="job">
                    <b>{{ j.title }}<template v-if="j.employer"> · {{ j.employer }}</template></b>
                    <div class="small mute">{{ j.from }} – {{ j.current ? t('ben.present') : j.to }}<template v-if="j.location"> · {{ j.location }}</template><template v-if="j.governorate"> · {{ t(`gov.${j.governorate}`) }}</template><template v-if="j.country && j.country !== 'EG'"> · {{ t(`country.${j.country}`) }}</template></div>
                    <div v-if="j.sector_label" class="small c-teal">{{ j.sector_label }}</div>
                    <ul v-if="j.responsibilities?.length" class="duties"><li v-for="(d, k) in j.responsibilities.slice(0, 5)" :key="k">{{ d }}</li></ul>
                </div>
                <p v-if="!p.work_history.length" class="mute small mt-2">{{ t('ben.no_jobs_yet') }}</p>
            </div>
            <div class="panel">
                <h3><AppIcon name="graduation" :size="16" />{{ t('ben.s_education') }}</h3>
                <div v-if="p.education_level" class="mt-2"><b>{{ t(`ben.edu_${p.education_level}`) }}</b></div>
                <div v-for="(e, i) in p.education" :key="i" class="small mt-1">{{ [e.qualification, e.field, e.institution, e.year].filter(Boolean).join(' · ') }}</div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.kv { display: grid; grid-template-columns: 130px 1fr; gap: 8px 14px; margin: 0; font-size: 13px; }
.kv dt { color: var(--ms-text-muted); font-weight: 600; font-size: 12.5px; }
.kv dd { margin: 0; overflow-wrap: anywhere; }
.cvrow { display: flex; gap: 10px; align-items: center; border: 1px solid var(--ms-border); border-radius: var(--r-lg); padding: 8px 12px; }
.cvrow b { display: block; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.job { padding: 10px 0; border-top: 1px solid var(--ms-border); font-size: 13px; }
.job:first-of-type { border-top: 0; }
.duties { margin: 4px 0 0; padding-inline-start: 18px; font-size: 12.5px; }
.grow { flex: 1; }
.min0 { min-width: 0; }
@media (max-width: 760px) { .kv { grid-template-columns: 1fr; } }
</style>
