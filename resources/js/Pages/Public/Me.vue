<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — "My profile" (Step 10)
//  Location: resources/js/Pages/Public/Me.vue
//  Route: GET /me (seeker.profile) — Public\MyProfileController
//
//  As agreed in the demo: the details, the CV (download · replace),
//  the organisations that added them, and privacy: be seen in the
//  Talent Pool (switch), delete my profile (password needed).
// ══════════════════════════════════════════════════════════════════

import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Modal.vue';
import Field from '@/Components/Field.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { useTranslations } from '@/composables/useTranslations';
import { usePreferences } from '@/composables/usePreferences';
import { experience, money, occIn } from '@/Components/Beneficiaries/ben';
import { formatDate } from '@/Utils/date';

const props = defineProps({
    profile: { type: Object, required: true },
    occupation: { type: Object, default: null },
    account: { type: Object, required: true },
    cv: { type: Object, default: null },
    added_by: { type: Array, default: () => [] },
    limits: { type: Object, required: true },
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
const orgName = (o) => (locale.value === 'ar' && o.name_ar ? o.name_ar : o.name);
const initials = (s) => s.split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const kb = (n) => `${Math.max(1, Math.round(n / 1024))} KB`;

// Privacy: be seen in the Talent Pool
const visible = ref(props.account.visible);
const setVisible = () => router.patch(route('seeker.profile.visibility'), { visible: visible.value }, { preserveScroll: true, onError: () => { visible.value = !visible.value; } });

// Replace my CV
const fileInput = ref(null);
const cvForm = useForm({ file: null });
const accept = props.limits.extensions.map((e) => `.${e}`).join(',');
function chosen(e) {
    const f = e.target.files?.[0];
    e.target.value = '';
    if (!f) return;
    cvForm.file = f;
    cvForm.post(route('seeker.cv.upload'), { forceFormData: true, preserveScroll: true });
}

// Delete my profile
const del = ref(false);
const delForm = useForm({ password: '' });
const destroy = () => delForm.delete(route('seeker.profile.destroy'), { preserveScroll: true });
</script>

<template>
    <PublicLayout :title="t('pub.my_profile')">
        <div class="hero first">
            <div class="top">
                <span class="avatar lg">{{ p.initials }}</span>
                <div class="grow">
                    <h1>{{ name }}</h1>
                    <div v-if="other" class="alt">{{ other }}</div>
                    <div class="meta">
                        <span v-if="occ" class="tag teal">{{ occ }}</span>
                        <span v-if="p.governorate" class="tag plain">{{ t(`gov.${p.governorate}`) }}<template v-if="p.city"> · {{ p.city }}</template></span>
                        <span v-if="p.experience_months" class="tag plain">{{ experience(p.experience_months, t, locale) }}</span>
                    </div>
                </div>
                <span class="badge" :class="account.visible ? 'green' : 'orange'"><span class="dot"></span>{{ t(account.visible ? 'me.visible' : 'me.hidden') }}</span>
            </div>
        </div>

        <div class="grid-me mt-4">
            <div>
                <div class="panel">
                    <div class="panel-h">
                        <h3>{{ t('me.details') }}</h3>
                        <Link :href="route('seeker.profile.edit')" class="btn btn-line sm"><AppIcon name="edit" :size="14" />{{ t('me.edit') }}</Link>
                    </div>
                    <dl class="kv">
                        <dt>{{ t('me.contact') }}</dt><dd><bdi>{{ p.phone }}</bdi> · <bdi>{{ account.email }}</bdi></dd>
                        <dt>{{ t('ben.s_work') }}</dt>
                        <dd>
                            <div v-for="(j, i) in p.work_history" :key="i">{{ j.title }}<template v-if="j.employer"> · {{ j.employer }}</template> <span class="mute small">({{ j.from }} – {{ j.current ? t('ben.present') : j.to }}<template v-if="j.governorate"> · {{ t(`gov.${j.governorate}`) }}</template><template v-if="j.country && j.country !== 'EG'"> · {{ t(`country.${j.country}`) }}</template>)</span><span v-if="j.sector_label" class="small c-teal"> · {{ j.sector_label }}</span></div>
                            <span v-if="!p.work_history.length" class="mute">{{ t('me.no_jobs') }}</span>
                        </dd>
                        <dt>{{ t('ben.s_education') }}</dt>
                        <dd>
                            <div v-if="p.education_level">{{ t(`ben.edu_${p.education_level}`) }}</div>
                            <div v-for="(e, i) in p.education" :key="i">{{ [e.qualification, e.institution, e.year].filter(Boolean).join(' · ') }}</div>
                            <span v-if="!p.education_level && !p.education.length" class="mute">—</span>
                        </dd>
                        <dt>{{ t('ben.s_skills') }}</dt>
                        <dd>
                            <div>{{ p.skills.join(', ') || '—' }}</div>
                            <div v-if="p.languages.length" class="mute small">{{ p.languages.map((l) => `${t(`ben.lang_${l.code}`)} (${t(`ben.level_${l.level}`)})`).join(', ') }}</div>
                        </dd>
                        <dt>{{ t('me.looking_for') }}</dt>
                        <dd>
                            {{ [p.job_type ? t(`ben.job_${p.job_type}`) : '', p.expected_salary ? `${money(p.expected_salary, locale)} ${t('ben.egp_month')}` : '',
                                account.notice_period ? `${t('join.notice')}: ${t(`join.notice_${account.notice_period}`)}` : ''].filter(Boolean).join(' · ') || '—' }}
                        </dd>
                    </dl>
                </div>

                <div class="panel">
                    <div class="panel-h"><h3>{{ t('me.cv') }}</h3></div>
                    <div v-if="cvForm.errors.file" class="alert warning mb-3"><AppIcon name="alert" :size="16" /><span>{{ cvForm.errors.file }}</span></div>
                    <div class="cvrow">
                        <span class="ft-ico">{{ (cv?.extension || 'cv').toUpperCase() }}</span>
                        <span class="grow min0">
                            <template v-if="cv"><b><a :href="route('seeker.cv.download')">{{ cv.file }}</a></b><span class="mute small">{{ formatDate(cv.at, locale) }} · {{ kb(cv.size) }}</span></template>
                            <span v-else class="mute">{{ t('me.no_cv') }}</span>
                        </span>
                        <input ref="fileInput" type="file" class="hidden" :accept="accept" @change="chosen">
                        <button type="button" class="btn btn-line sm" :class="{ 'is-loading': cvForm.processing }" :disabled="cvForm.processing" @click="fileInput.click()">
                            {{ t(cv ? 'me.replace_cv' : 'me.add_cv') }}
                        </button>
                    </div>
                    <p class="mute small mt-2">{{ t('me.replace_note') }}</p>
                </div>
            </div>

            <div>
                <div class="panel">
                    <h3>{{ t('me.added_by') }}</h3>
                    <div class="sub">{{ t('me.added_by_sub') }}</div>
                    <div v-for="(o, i) in added_by" :key="i" class="org">
                        <span class="avatar sm">{{ initials(orgName(o)) }}</span>
                        <div><b>{{ orgName(o) }}</b><span class="mute small">{{ t('me.added_on', { date: formatDate(o.at, locale) }) }}</span></div>
                    </div>
                    <p v-if="!added_by.length" class="mute small">{{ t('me.added_none') }}</p>
                </div>

                <div class="panel">
                    <h3>{{ t('me.privacy') }}</h3>
                    <label class="switch mt-2"><input v-model="visible" type="checkbox" @change="setVisible"><span></span><b>{{ t('me.visible_switch') }}</b></label>
                    <p class="mute small mt-2">{{ t('me.visible_note') }}</p>
                    <hr>
                    <button type="button" class="btn btn-danger sm" @click="del = true"><AppIcon name="trash" :size="14" />{{ t('me.delete') }}</button>
                    <p class="mute xsmall mt-2">{{ t('me.delete_note') }}</p>
                </div>
            </div>
        </div>

        <Modal :show="del" :title="t('me.delete')" size="sm" @close="del = false">
            <p>{{ t('me.delete_confirm') }}</p>
            <Field :label="t('join.password')" :error="delForm.errors.password"><PasswordInput v-model="delForm.password" /></Field>
            <template #footer>
                <button type="button" class="btn btn-line" @click="del = false">{{ t('common.cancel') }}</button>
                <button type="button" class="btn btn-danger" :class="{ 'is-loading': delForm.processing }" :disabled="delForm.processing || !delForm.password" @click="destroy">{{ t('me.delete_yes') }}</button>
            </template>
        </Modal>
    </PublicLayout>
</template>

<style scoped>
.first { margin-top: 28px; }
.hero .badge { margin-inline-start: auto; }
.grid-me { display: grid; grid-template-columns: 3fr 2fr; gap: 14px; align-items: start; }
.grid-me > div > .panel + .panel { margin-top: 14px; }
.kv { display: grid; grid-template-columns: 150px 1fr; gap: 10px 16px; margin: 0; font-size: 13.5px; }
.kv dt { color: var(--ms-text-muted); font-size: 12.5px; font-weight: 600; }
.kv dd { margin: 0; line-height: 1.6; overflow-wrap: anywhere; }
.cvrow { display: flex; gap: 12px; align-items: center; border: 1px solid var(--ms-border); border-radius: var(--r-lg); padding: 9px 14px; }
.cvrow b { display: block; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cvrow .small { display: block; }
.ft-ico { width: 38px; height: 38px; border-radius: 9px; background: var(--ms-danger-dim); color: var(--ms-danger); display: grid; place-items: center; font-size: 10.5px; font-weight: 800; flex-shrink: 0; }
.org { display: flex; gap: 10px; align-items: center; padding: 10px 0; border-top: 1px solid var(--ms-border); }
.org:first-of-type { border-top: 0; }
.org b { display: block; font-size: 13.5px; }
.org .small { display: block; }
.hidden { display: none; }
hr { border: 0; border-top: 1px solid var(--ms-border); margin: 16px 0; }
.grow { flex: 1; }
.min0 { min-width: 0; }
@media (max-width: 760px) {
    .grid-me { grid-template-columns: 1fr; }
    .kv { grid-template-columns: 1fr; gap: 2px 0; }
    .kv dd { margin-bottom: 8px; }
}
</style>
