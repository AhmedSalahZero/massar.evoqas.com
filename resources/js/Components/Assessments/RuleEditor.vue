<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — RuleEditor (Step 11)
//  Location: resources/js/Components/Assessments/RuleEditor.vue
//
//  One eligibility rule, on the Job or Training form (Step 12):
//    · what it checks (age, governorate, education … — fixed once added)
//    · Must have / Counts, and the points of a "Counts" rule
//    · the rule's own settings
//  Errors come from the server as rules.<index>.<field>.
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import OccupationChoice from '@/Components/Assessments/OccupationChoice.vue';
import WordsInput from '@/Components/Assessments/WordsInput.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    rule: { type: Object, required: true },
    index: { type: Number, required: true },
    errors: { type: Object, default: () => ({}) },
    choices: { type: Object, required: true },
});
const emit = defineEmits(['remove']);
const { t } = useTranslations();

const err = (field) => props.errors[`rules.${props.index}.${field}`] || '';
const anyError = computed(() => Object.keys(props.errors).some((k) => k.startsWith(`rules.${props.index}.`)));

function toggle(field, v) {
    const list = props.rule[field] || [];
    props.rule[field] = list.includes(v) ? list.filter((x) => x !== v) : [...list, v];
}
// Education: "from this level up" ticks it and every level above it.
function fromLevel(e) {
    const i = props.choices.education_levels.indexOf(e.target.value);
    if (i >= 0) props.rule.values = props.choices.education_levels.slice(i);
    e.target.value = '';
}
const LEVELS = ['basic', 'good', 'fluent', 'native'];
</script>

<template>
    <div class="rule" :class="{ bad: anyError, counts: rule.mode === 'counts' }">
        <div class="rh">
            <span class="num">{{ index + 1 }}</span>
            <b class="grow">{{ t(`elig.type_${rule.type}`) }}</b>
            <div class="seg" role="group" :aria-label="t('elig.mode')">
                <button type="button" :aria-pressed="rule.mode === 'must'" @click="rule.mode = 'must'">{{ t('elig.must') }}</button>
                <button type="button" :aria-pressed="rule.mode === 'counts'" @click="rule.mode = 'counts'">{{ t('elig.counts') }}</button>
            </div>
            <label v-if="rule.mode === 'counts'" class="pts">
                <input v-model.number="rule.points" type="number" min="1" max="100" :aria-label="t('elig.points')">
                <span>{{ t('elig.pts') }}</span>
            </label>
            <button type="button" class="xdel" :aria-label="t('common.delete')" @click="emit('remove')"><AppIcon name="trash" :size="14" /></button>
        </div>
        <div class="small mute mt-1">{{ t(rule.mode === 'must' ? 'elig.must_hint' : 'elig.counts_hint') }}</div>
        <div v-if="err('points')" class="fld-error mt-1">{{ err('points') }}</div>

        <!-- The rule's own settings -->
        <div class="rb">
            <template v-if="rule.type === 'age'">
                <div class="row2">
                    <label class="fld"><span class="fl">{{ t('elig.age_min') }}</span><input v-model.number="rule.min" type="number" min="14" max="80"></label>
                    <label class="fld"><span class="fl">{{ t('elig.age_max') }}</span><input v-model.number="rule.max" type="number" min="14" max="80"></label>
                </div>
                <div v-if="err('min') || err('max')" class="fld-error">{{ err('min') || err('max') }}</div>
            </template>

            <template v-else-if="rule.type === 'governorate'">
                <div class="chips">
                    <button v-for="g in choices.governorates" :key="g" type="button" class="chip" :aria-pressed="rule.values.includes(g)" @click="toggle('values', g)">{{ t(`gov.${g}`) }}</button>
                </div>
                <div v-if="err('values')" class="fld-error">{{ err('values') }}</div>
            </template>

            <template v-else-if="rule.type === 'education_level'">
                <select class="inp from" @change="fromLevel">
                    <option value="">{{ t('elig.from_level') }}</option>
                    <option v-for="l in choices.education_levels" :key="l" :value="l">{{ t(`ben.edu_${l}`) }}</option>
                </select>
                <div class="chips mt-2">
                    <button v-for="l in choices.education_levels" :key="l" type="button" class="chip" :aria-pressed="rule.values.includes(l)" @click="toggle('values', l)">{{ t(`ben.edu_${l}`) }}</button>
                </div>
                <div v-if="err('values')" class="fld-error">{{ err('values') }}</div>
            </template>

            <template v-else-if="rule.type === 'field_of_study'">
                <WordsInput v-model="rule.words" :placeholder="t('elig.words_ph')" />
                <small class="mute">{{ t('elig.field_hint') }}</small>
                <div v-if="err('words')" class="fld-error">{{ err('words') }}</div>
            </template>

            <template v-else-if="rule.type === 'skills'">
                <WordsInput v-model="rule.values" :placeholder="t('elig.skills_ph')" />
                <div class="seg mt-2" role="group">
                    <button type="button" :aria-pressed="rule.need === 'all'" @click="rule.need = 'all'">{{ t('elig.need_all') }}</button>
                    <button type="button" :aria-pressed="rule.need === 'any'" @click="rule.need = 'any'">{{ t('elig.need_any') }}</button>
                </div>
                <div v-if="err('values')" class="fld-error">{{ err('values') }}</div>
            </template>

            <template v-else-if="rule.type === 'occupation'">
                <OccupationChoice v-model="rule.values" v-model:labels="rule.labels" />
                <small class="mute">{{ t('elig.occ_hint') }}</small>
                <div v-if="err('values')" class="fld-error">{{ err('values') }}</div>
            </template>

            <template v-else-if="rule.type === 'experience'">
                <label class="fld short"><span class="fl">{{ t('elig.min_years') }}</span><input v-model.number="rule.min_years" type="number" min="1" max="40"></label>
                <div v-if="err('min_years')" class="fld-error">{{ err('min_years') }}</div>
            </template>

            <template v-else-if="rule.type === 'language'">
                <div class="row2">
                    <label class="fld"><span class="fl">{{ t('elig.language') }}</span>
                        <select v-model="rule.code"><option v-for="l in choices.languages" :key="l" :value="l">{{ t(`ben.lang_${l}`) }}</option></select>
                    </label>
                    <label class="fld"><span class="fl">{{ t('elig.at_least') }}</span>
                        <select v-model="rule.level"><option v-for="l in LEVELS" :key="l" :value="l">{{ t(`ben.level_${l}`) }}</option></select>
                    </label>
                </div>
            </template>

            <template v-else-if="rule.type === 'military'">
                <div class="chips">
                    <button v-for="m in choices.military_statuses" :key="m" type="button" class="chip" :aria-pressed="rule.values.includes(m)" @click="toggle('values', m)">{{ t(`ben.mil_${m}`) }}</button>
                </div>
                <small class="mute">{{ t('elig.military_hint') }}</small>
                <div v-if="err('values')" class="fld-error">{{ err('values') }}</div>
            </template>

            <template v-else-if="rule.type === 'gender'">
                <div class="seg" role="group">
                    <button v-for="g in choices.genders" :key="g" type="button" :aria-pressed="rule.value === g" @click="rule.value = g">{{ t(`ben.gender_${g}`) }}</button>
                </div>
                <div class="alert warning mt-2"><AppIcon name="alert" :size="14" /><span>{{ t('elig.sensitive_hint') }}</span></div>
            </template>

            <template v-else-if="rule.type === 'salary'">
                <label class="fld short"><span class="fl">{{ t('elig.salary_max') }}</span><input v-model="rule.max" type="number" min="1" inputmode="numeric"></label>
                <div v-if="err('max')" class="fld-error">{{ err('max') }}</div>
            </template>
        </div>
        <div v-if="rule.type === 'military'" class="alert warning mt-2"><AppIcon name="alert" :size="14" /><span>{{ t('elig.sensitive_hint') }}</span></div>
    </div>
</template>

<style scoped>
.rule { border: 1px solid var(--ms-border); border-radius: var(--r-lg, 10px); padding: 12px 14px; background: var(--ms-bg-card); border-inline-start: 3px solid var(--ms-navy); }
.rule.counts { border-inline-start-color: var(--ms-teal); }
.rule.bad { border-color: var(--ms-danger); }
.rh { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.num { width: 24px; height: 24px; border-radius: 50%; display: grid; place-items: center; font-size: 11px; font-weight: 800; background: var(--ms-bg-hover); color: var(--ms-text-muted); }
.grow { flex: 1; min-width: 120px; }
.pts { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: var(--ms-text-muted); }
.pts input { width: 70px; border: 1px solid var(--ms-border); border-radius: 8px; padding: 6px 8px; background: var(--ms-bg-input); color: var(--ms-text-primary); direction: ltr; }
.rb { margin-top: 10px; }
.row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 12px; }
.row2 .fld { margin-top: 0; }
.fld.short { max-width: 220px; margin-top: 0; }
.from { max-width: 280px; }
.xdel { display: grid; place-items: center; }
@media (max-width: 640px) { .row2 { grid-template-columns: 1fr; } }
</style>
