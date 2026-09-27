<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Job / Training form (Step 12)
//  Location: resources/js/Pages/App/Opportunities/Form.vue
//  Routes: GET /app/{jobs|training}/create · GET /app/{jobs|training}/{id}/edit
//  Permission: opportunities.manage
//  Scope: docs/SCOPE_JOBS_AND_TRAINING.md §4–5
//
//  ONE form, saved once: the details AND the eligibility.
//    · the details: title (one field, any language), description,
//      occupations (as many as needed), governorates, city, seats,
//      dates, contact person; for a job the employer (optional), sector,
//      job type and salary range; for a training the provider, duration,
//      format, cost and certificate
//    · Eligibility (REQUIRED, at least one rule): the two result levels
//      and the rules, exactly as in Step 11. Two helpers copy the
//      occupations or governorates above into a rule — only when the
//      partner presses them; later changes are never copied silently.
// ══════════════════════════════════════════════════════════════════

import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Field from '@/Components/Field.vue';
import EmployerInput from '@/Components/Employers/EmployerInput.vue';
import OccupationChoice from '@/Components/Assessments/OccupationChoice.vue';
import RuleEditor from '@/Components/Assessments/RuleEditor.vue';
import { useTranslations } from '@/composables/useTranslations';
import { emptyRule } from '@/Components/Assessments/elig';
import { sameSet } from '@/Components/Opportunities/opp';

const props = defineProps({
    kind: { type: String, required: true },
    section: { type: String, required: true },
    opportunity: { type: Object, default: null },
    workspace: { type: String, default: '' },
    types: { type: Array, required: true },
    choices: { type: Object, required: true },
    sectors: { type: Array, default: () => [] },
    team: { type: Array, default: () => [] },
});
const { t, locale } = useTranslations();

const o = props.opportunity;
const editing = computed(() => !!o);
const isJob = computed(() => props.kind === 'job');

const form = useForm({
    title: o?.title ?? '',
    description: o?.description ?? '',
    occupations: o ? o.occupations.map((x) => x.value) : [],
    governorates: o?.governorates ?? [],
    city: o?.city ?? '',
    seats: o?.seats ?? '',
    deadline: o?.deadline ?? '',
    starts_on: o?.starts_on ?? '',
    ends_on: o?.ends_on ?? '',
    contact_user_id: o?.contact_user_id ?? '',
    // Job
    country: 'EG',                       // the employer selector reads it; jobs are posted in Egypt
    employer: o?.employer ?? '',
    employer_id: o?.employer_id ?? null,
    sub_sector: o?.sub_sector ?? '',
    job_type: o?.job_type ?? '',
    salary_from: o?.salary_from ?? '',
    salary_to: o?.salary_to ?? '',
    // Training
    provider: o?.provider ?? (props.kind === 'training' ? props.workspace : ''),
    duration_value: o?.duration_value ?? '',
    duration_unit: o?.duration_unit ?? 'weeks',
    format: o?.format ?? '',
    cost_type: o?.cost_type ?? '',
    cost_amount: o?.cost_amount ?? '',
    certificate: o?.certificate ?? '',
    // Eligibility
    eligible_from: o?.eligible_from ?? 70,
    check_from: o?.check_from ?? 50,
    rules: o ? JSON.parse(JSON.stringify(o.rules)).map((r) => ({ ...emptyRule(r.type), ...r, points: r.mode === 'counts' ? r.points : 10 })) : [],
});
const occLabels = ref(o ? o.occupations.map((x) => x.label) : []);

// ── Sector (a known company fills it) ─────────────────────────────
const sname = (x) => (locale.value === 'ar' ? x.name_ar : x.name_en);
const sectorOf = (code) => props.sectors.find((s) => s.subs.some((x) => x.code === code))?.code ?? '';
const sector = ref(sectorOf(form.sub_sector));
const subs = computed(() => props.sectors.find((s) => s.code === (sectorOf(form.sub_sector) || sector.value))?.subs ?? []);
const setSector = (v) => { sector.value = v; if (sectorOf(form.sub_sector) !== v) form.sub_sector = ''; };

// ── Governorates ──────────────────────────────────────────────────
const toggleGov = (g) => {
    form.governorates = form.governorates.includes(g) ? form.governorates.filter((x) => x !== g) : [...form.governorates, g];
};

// ── Eligibility ───────────────────────────────────────────────────
const newType = ref('');
function addRule() {
    if (!newType.value) return;
    form.rules.push(emptyRule(newType.value));
    newType.value = '';
}
const removeRule = (i) => form.rules.splice(i, 1);
const counting = computed(() => form.rules.filter((r) => r.mode === 'counts'));
const total = computed(() => counting.value.reduce((s, r) => s + (Number(r.points) || 0), 0));
const hasMust = computed(() => form.rules.some((r) => r.mode === 'must'));
const e = computed(() => Math.max(0, Math.min(100, Number(form.eligible_from) || 0)));
const c = computed(() => Math.max(0, Math.min(e.value, Number(form.check_from) || 0)));

// The two helpers: copy the details into a rule, only when pressed.
const occRule = computed(() => form.rules.find((r) => r.type === 'occupation'));
const govRule = computed(() => form.rules.find((r) => r.type === 'governorate'));
function useOccupations() {
    if (!form.occupations.length) return;
    const labels = form.occupations.map((v) => occLabels.value.find((l) => l.value === v) || { value: v, standard: v.split(':')[0], code: v.split(':')[1] });
    if (occRule.value) { occRule.value.values = [...form.occupations]; occRule.value.labels = labels; } else form.rules.push({ ...emptyRule('occupation'), values: [...form.occupations], labels });
}
function useGovernorates() {
    if (!form.governorates.length) return;
    if (govRule.value) govRule.value.values = [...form.governorates]; else form.rules.push({ ...emptyRule('governorate'), values: [...form.governorates] });
}
const occDiffers = computed(() => occRule.value && form.occupations.length && !sameSet(occRule.value.values, form.occupations));
const govDiffers = computed(() => govRule.value && form.governorates.length && !sameSet(govRule.value.values, form.governorates));

// ── Save ──────────────────────────────────────────────────────────
const save = () => {
    const send = form.transform((d) => {
        const { country, ...rest } = d;
        return { ...rest, rules: d.rules.map(({ labels, ...r }) => r) };
    });
    editing.value ? send.patch(route(`app.${props.section}.update`, o.id), { preserveScroll: true })
        : send.post(route(`app.${props.section}.store`), { preserveScroll: true });
};
const ruleErrors = computed(() => Object.keys(form.errors).some((k) => k.startsWith('rules.')));
const occError = computed(() => form.errors.occupations || Object.entries(form.errors).find(([k]) => k.startsWith('occupations.'))?.[1] || '');
</script>

<template>
    <AppLayout :title="editing ? o.title : t(`opp.new_${kind}`)">
        <Link :href="o ? route(`app.${section}.show`, o.id) : route(`app.${section}.index`)" class="back"><AppIcon name="arrow-left" :size="14" />{{ editing ? o.title : t(`nav.${kind === 'job' ? 'jobs' : 'training'}`) }}</Link>
        <div class="page-head">
            <div>
                <div class="page-eyebrow">{{ t(`opp.eyebrow_${kind}`) }}</div>
                <h1 class="page-title">{{ editing ? t(`opp.edit_${kind}`) : t(`opp.new_${kind}`) }}</h1>
                <div class="page-sub">{{ t('opp.form_sub') }}</div>
            </div>
        </div>

        <div v-if="editing && o.results" class="alert mb-4">
            <AppIcon name="info" :size="16" /><span>{{ t('opp.edit_note', { n: o.results }) }}</span>
        </div>
        <div v-if="Object.keys(form.errors).length" class="form-errors mb-4">{{ t('elig.fix_below') }}</div>

        <form @submit.prevent="save">
            <!-- ── What it is ─────────────────────────────────────── -->
            <div class="panel">
                <h3><AppIcon :name="isJob ? 'briefcase' : 'graduation'" :size="16" />{{ t(`opp.s_what_${kind}`) }}</h3>
                <div class="form-grid">
                    <Field :label="t('opp.title')" :error="form.errors.title" required wide :hint="t('opp.title_hint')">
                        <input v-model="form.title" type="text" maxlength="150" dir="auto" :placeholder="t(`opp.title_ph_${kind}`)">
                    </Field>
                    <Field :label="t('opp.description')" :error="form.errors.description" optional wide>
                        <textarea v-model="form.description" maxlength="2000" rows="3" dir="auto" :placeholder="t(`opp.description_ph_${kind}`)" />
                    </Field>
                </div>
            </div>

            <!-- ── Occupations and place ──────────────────────────── -->
            <div class="panel mt-4">
                <h3><AppIcon name="layers" :size="16" />{{ t('opp.s_where') }}</h3>
                <div class="form-grid">
                    <div class="fld wide" :class="{ 'has-error': !!occError }">
                        <span class="fl"><span>{{ t('opp.occupations') }}<span class="req">*</span></span></span>
                        <OccupationChoice v-model="form.occupations" v-model:labels="occLabels" />
                        <small v-if="!occError">{{ t('opp.occupations_hint') }}</small>
                        <span v-if="occError" class="fld-error"><AppIcon name="alert" :size="12" />{{ occError }}</span>
                    </div>
                    <div class="fld wide" :class="{ 'has-error': !!form.errors.governorates }">
                        <span class="fl"><span>{{ t('opp.governorates') }}<span class="req">*</span></span>
                            <span class="optional">{{ t('opp.n_chosen', { n: form.governorates.length }) }}</span></span>
                        <div class="govs">
                            <label v-for="g in choices.governorates" :key="g" class="gov" :class="{ on: form.governorates.includes(g) }">
                                <input type="checkbox" :checked="form.governorates.includes(g)" @change="toggleGov(g)">{{ t(`gov.${g}`) }}
                            </label>
                        </div>
                        <span v-if="form.errors.governorates" class="fld-error"><AppIcon name="alert" :size="12" />{{ form.errors.governorates }}</span>
                    </div>
                    <Field :label="t('opp.city')" :error="form.errors.city" optional>
                        <input v-model="form.city" type="text" maxlength="100" dir="auto" :placeholder="t('opp.city_ph')">
                    </Field>
                </div>
            </div>

            <!-- ── Seats, dates, contact ──────────────────────────── -->
            <div class="panel mt-4">
                <h3><AppIcon name="calendar" :size="16" />{{ t('opp.s_seats') }}</h3>
                <div class="form-grid">
                    <Field :label="t('opp.seats')" :error="form.errors.seats" required :hint="t(`opp.seats_hint_${kind}`)">
                        <input v-model="form.seats" type="number" min="1" max="100000" inputmode="numeric">
                    </Field>
                    <Field :label="t('opp.deadline')" :error="form.errors.deadline" optional :hint="t('opp.deadline_hint')">
                        <input v-model="form.deadline" type="date">
                    </Field>
                    <Field :label="t('opp.starts_on')" :error="form.errors.starts_on" optional><input v-model="form.starts_on" type="date"></Field>
                    <Field :label="t('opp.ends_on')" :error="form.errors.ends_on" optional><input v-model="form.ends_on" type="date"></Field>
                    <Field :label="t('opp.contact')" :error="form.errors.contact_user_id" optional :hint="t('opp.contact_hint')">
                        <select v-model="form.contact_user_id">
                            <option value="">—</option>
                            <option v-for="u in team" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </Field>
                </div>
            </div>

            <!-- ── Job only ───────────────────────────────────────── -->
            <div v-if="isJob" class="panel mt-4">
                <h3><AppIcon name="building" :size="16" />{{ t('opp.s_job') }}</h3>
                <div class="form-grid">
                    <EmployerInput :job="form" :url="route('app.employer-search')" :sectors="sectors" optional
                                   :error="form.errors.employer || form.errors.employer_id" :label="t('opp.employer')" />
                    <Field :label="t('opp.job_type')" :error="form.errors.job_type" required>
                        <select v-model="form.job_type">
                            <option value="">—</option>
                            <option v-for="j in choices.job_types" :key="j" :value="j">{{ t(`ben.job_${j}`) }}</option>
                        </select>
                    </Field>
                    <Field :label="t('opp.sector')" optional>
                        <select :value="sectorOf(form.sub_sector) || sector" @change="setSector($event.target.value)">
                            <option value="">—</option>
                            <option v-for="s in sectors" :key="s.code" :value="s.code">{{ sname(s) }}</option>
                        </select>
                    </Field>
                    <Field :label="t('opp.sub_sector')" :error="form.errors.sub_sector" optional>
                        <select v-model="form.sub_sector" :disabled="!subs.length">
                            <option value="">—</option>
                            <option v-for="x in subs" :key="x.code" :value="x.code">{{ sname(x) }}</option>
                        </select>
                    </Field>
                    <Field :label="t('opp.salary_from')" :error="form.errors.salary_from" optional>
                        <input v-model="form.salary_from" type="number" min="1" inputmode="numeric" :placeholder="t('mk.egp')">
                    </Field>
                    <Field :label="t('opp.salary_to')" :error="form.errors.salary_to" optional :hint="t('opp.salary_hint')">
                        <input v-model="form.salary_to" type="number" min="1" inputmode="numeric" :placeholder="t('mk.egp')">
                    </Field>
                </div>
                <p class="small mute mt-2">{{ t('opp.employer_hint') }}</p>
            </div>

            <!-- ── Training only ──────────────────────────────────── -->
            <div v-else class="panel mt-4">
                <h3><AppIcon name="graduation" :size="16" />{{ t('opp.s_training') }}</h3>
                <div class="form-grid">
                    <Field :label="t('opp.provider')" :error="form.errors.provider" required :hint="t('opp.provider_hint')">
                        <input v-model="form.provider" type="text" maxlength="200" dir="auto">
                    </Field>
                    <Field :label="t('opp.format')" :error="form.errors.format" optional>
                        <select v-model="form.format">
                            <option value="">—</option>
                            <option v-for="x in choices.formats" :key="x" :value="x">{{ t(`opp.format_${x}`) }}</option>
                        </select>
                    </Field>
                    <div class="fld" :class="{ 'has-error': form.errors.duration_value || form.errors.duration_unit }">
                        <span class="fl"><span>{{ t('opp.duration') }}</span><span class="optional">{{ t('common.optional') }}</span></span>
                        <div class="pair">
                            <input v-model="form.duration_value" type="number" min="1" max="1000" inputmode="numeric" :aria-label="t('opp.duration')">
                            <select v-model="form.duration_unit" :aria-label="t('opp.duration_unit')">
                                <option v-for="u in choices.duration_units" :key="u" :value="u">{{ t(`opp.unit_${u}`) }}</option>
                            </select>
                        </div>
                        <span v-if="form.errors.duration_value || form.errors.duration_unit" class="fld-error"><AppIcon name="alert" :size="12" />{{ form.errors.duration_value || form.errors.duration_unit }}</span>
                    </div>
                    <div class="fld" :class="{ 'has-error': form.errors.cost_type || form.errors.cost_amount }">
                        <span class="fl"><span>{{ t('opp.cost') }}</span><span class="optional">{{ t('common.optional') }}</span></span>
                        <div class="pair">
                            <select v-model="form.cost_type" :aria-label="t('opp.cost')">
                                <option value="">—</option>
                                <option v-for="x in choices.costs" :key="x" :value="x">{{ t(`opp.cost_${x}`) }}</option>
                            </select>
                            <input v-if="form.cost_type === 'paid'" v-model="form.cost_amount" type="number" min="1" inputmode="numeric" :placeholder="t('mk.egp')" :aria-label="t('opp.cost_amount')">
                        </div>
                        <span v-if="form.errors.cost_type || form.errors.cost_amount" class="fld-error"><AppIcon name="alert" :size="12" />{{ form.errors.cost_type || form.errors.cost_amount }}</span>
                    </div>
                    <Field :label="t('opp.certificate')" :error="form.errors.certificate" optional wide>
                        <input v-model="form.certificate" type="text" maxlength="200" dir="auto" :placeholder="t('opp.certificate_ph')">
                    </Field>
                </div>
            </div>

            <!-- ══ Eligibility (required) ═════════════════════════════ -->
            <div class="elig-head mt-6">
                <h2><AppIcon name="check-circle" :size="18" />{{ t('opp.s_eligibility') }} <span class="req">*</span></h2>
                <p class="small mute">{{ t(`opp.eligibility_sub_${kind}`) }}</p>
            </div>

            <div class="panel">
                <h3><AppIcon name="chart" :size="16" />{{ t('elig.s_levels') }}</h3>
                <div class="sub">{{ t('opp.levels_sub') }}</div>
                <div class="levels mt-3">
                    <Field :label="t('elig.eligible_from')" :error="form.errors.eligible_from"><input v-model.number="form.eligible_from" type="number" min="0" max="100"></Field>
                    <Field :label="t('elig.check_from')" :error="form.errors.check_from" :hint="t('elig.check_from_hint')"><input v-model.number="form.check_from" type="number" min="0" max="100"></Field>
                </div>
                <div class="band mt-3" aria-hidden="true">
                    <i class="b-no" :style="`width:${c}%`" /><i class="b-check" :style="`width:${e - c}%`" /><i class="b-yes" :style="`width:${100 - e}%`" />
                </div>
                <div class="bandtxt">
                    <span><i class="dot b-yes" />{{ t('elig.band_eligible', { from: e }) }}</span>
                    <span v-if="e > c"><i class="dot b-check" />{{ t('elig.band_check', { from: c, to: e - 1 }) }}</span>
                    <span v-if="c > 0"><i class="dot b-no" />{{ t('elig.band_no', { to: c - 1 }) }}</span>
                </div>
                <p class="small mute mt-2">{{ t('elig.levels_always') }}</p>
            </div>

            <div class="panel mt-4" :class="{ 'need-rule': form.errors.rules }">
                <div class="panel-h">
                    <div>
                        <h3><AppIcon name="check-circle" :size="16" />{{ t('elig.s_rules') }}</h3>
                        <div class="sub">{{ t('elig.rules_sub') }}</div>
                    </div>
                    <div v-if="counting.length" class="total" :class="total === 100 ? 'ok' : 'off'">{{ t('elig.points_total', { n: total }) }}</div>
                </div>
                <div v-if="form.errors.rules" class="fld-error mt-2">{{ form.errors.rules }}</div>
                <div v-if="form.errors.points_total" class="fld-error mt-2">{{ form.errors.points_total }}</div>
                <div v-if="ruleErrors" class="fld-error mt-2">{{ t('elig.rules_errors') }}</div>

                <div class="helpers mt-3">
                    <button type="button" class="btn btn-line sm" :disabled="!form.occupations.length" @click="useOccupations">
                        <AppIcon name="layers" :size="13" />{{ t(occRule ? 'opp.use_occ_update' : 'opp.use_occ') }}
                    </button>
                    <button type="button" class="btn btn-line sm" :disabled="!form.governorates.length" @click="useGovernorates">
                        <AppIcon name="globe" :size="13" />{{ t(govRule ? 'opp.use_gov_update' : 'opp.use_gov') }}
                    </button>
                </div>
                <div v-if="occDiffers" class="alert warning mt-2"><AppIcon name="alert" :size="14" /><span>{{ t('opp.occ_differs') }}</span></div>
                <div v-if="govDiffers" class="alert warning mt-2"><AppIcon name="alert" :size="14" /><span>{{ t('opp.gov_differs') }}</span></div>

                <div class="rules mt-3">
                    <RuleEditor v-for="(r, i) in form.rules" :key="i" :rule="r" :index="i" :errors="form.errors" :choices="choices" @remove="removeRule(i)" />
                    <div v-if="!form.rules.length" class="empty-row">{{ t('opp.no_rules_yet') }}</div>
                </div>

                <div class="addrule mt-3">
                    <select v-model="newType" class="inp" :aria-label="t('elig.add_rule')">
                        <option value="">{{ t('elig.add_rule_ph') }}</option>
                        <option v-for="ty in types" :key="ty" :value="ty">{{ t(`elig.type_${ty}`) }}</option>
                    </select>
                    <button type="button" class="btn btn-line" :disabled="!newType" @click="addRule"><AppIcon name="plus" :size="14" />{{ t('elig.add_rule') }}</button>
                </div>
                <p v-if="form.rules.length && !counting.length && hasMust" class="small mute mt-2">{{ t('elig.only_must') }}</p>
            </div>

            <div class="form-actions mt-4">
                <Link :href="o ? route(`app.${section}.show`, o.id) : route(`app.${section}.index`)" class="btn btn-line">{{ t('common.cancel') }}</Link>
                <button type="submit" class="btn btn-primary" :class="{ 'is-loading': form.processing }" :disabled="form.processing">
                    {{ editing ? t('common.save_changes') : t(`opp.create_${kind}`) }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>

<style scoped>
.govs { display: flex; flex-wrap: wrap; gap: 6px; max-height: 190px; overflow-y: auto; padding: 2px; }
.gov { display: inline-flex; gap: 6px; align-items: center; border: 1px solid var(--ms-border); border-radius: 999px; padding: 4px 10px; font-size: 12.5px; cursor: pointer; user-select: none; }
.gov input { margin: 0; }
.gov.on { border-color: var(--ms-teal); background: var(--ms-teal-dim, var(--ms-bg-hover)); font-weight: 700; }
.pair { display: flex; gap: 8px; }
.pair > * { flex: 1; min-width: 0; }
.elig-head h2 { display: flex; gap: 8px; align-items: center; margin: 0 0 4px; font-size: 18px; }
.elig-head { margin-bottom: 10px; }
.mt-6 { margin-top: 28px; }
.need-rule { border-color: var(--ms-danger); }
.helpers { display: flex; gap: 8px; flex-wrap: wrap; }
.levels { display: grid; grid-template-columns: repeat(2, minmax(0, 220px)); gap: 0 14px; }
.levels .fld { margin-top: 0; }
.band { display: flex; height: 12px; border-radius: 6px; overflow: hidden; background: var(--ms-bg-hover); max-width: 520px; }
.band i { display: block; height: 100%; }
.b-yes { background: var(--ms-green); } .b-check { background: var(--ms-orange); } .b-no { background: var(--ms-danger); }
.bandtxt { display: flex; gap: 16px; flex-wrap: wrap; font-size: 12.5px; margin-top: 8px; }
.bandtxt .dot { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-inline-end: 6px; vertical-align: -1px; }
.rules { display: grid; gap: 10px; }
.addrule { display: flex; gap: 8px; flex-wrap: wrap; }
.addrule select { max-width: 320px; }
.total { font-weight: 800; font-size: 13px; padding: 6px 12px; border-radius: 8px; white-space: nowrap; }
.total.ok { background: var(--ms-green-dim); color: var(--ms-green); }
.total.off { background: var(--ms-orange-dim); color: var(--ms-orange); }
@media (max-width: 640px) { .levels { grid-template-columns: 1fr 1fr; } }
</style>
