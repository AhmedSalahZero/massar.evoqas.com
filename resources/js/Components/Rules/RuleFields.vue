<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — RuleFields (Scope v2 §3 Learned Rules)
//  Location: resources/js/Components/Rules/RuleFields.vue
//
//      <RuleFields :form="form" :sections="sections" />
//
//  The fields of one Learned Rule, shared by the Learned Rules page and
//  the "Teach" window of the review screen:
//    kind     heading | title | skill | employer (a company name: nothing more to choose)
//    phrase   the words as they appear in CVs
//    section  (heading)  what the heading means
//    occupation (title)  chosen with the occupation search
//    skill_name (skill)  the name added to the profile (optional)
//  `form` is an Inertia useForm with those keys; the parent sends it.
// ══════════════════════════════════════════════════════════════════

import Field from '@/Components/Field.vue';
import OccupationPicker from '@/Components/Beneficiaries/OccupationPicker.vue';
import { useTranslations } from '@/composables/useTranslations';
import { READ_SECTIONS } from '@/Components/Rules/rules';

const props = defineProps({
    form: { type: Object, required: true },
    sections: { type: Array, required: true },
    lockKind: { type: Boolean, default: false },
});
const { t } = useTranslations();
const readSections = props.sections.filter((s) => READ_SECTIONS.includes(s));
const otherSections = props.sections.filter((s) => !READ_SECTIONS.includes(s) && s !== 'none');
</script>

<template>
    <div class="rulefields">
        <div v-if="!lockKind" class="seg mb-3" role="group">
            <button v-for="k in ['heading', 'title', 'skill', 'employer']" :key="k" type="button" :aria-pressed="form.kind === k" @click="form.kind = k">
                {{ t(`rules.kind_${k}`) }}
            </button>
        </div>
        <p class="small mute mb-3">{{ t(`rules.kind_${form.kind}_help`) }}</p>

        <Field :label="t(`rules.phrase_${form.kind}`)" :error="form.errors.phrase" required>
            <input v-model="form.phrase" type="text" maxlength="100" :placeholder="t(`rules.phrase_${form.kind}_ph`)">
        </Field>

        <Field v-if="form.kind === 'heading'" :label="t('rules.means')" :error="form.errors.section" required>
            <select v-model="form.section">
                <option value="" disabled>{{ t('rules.choose_section') }}</option>
                <optgroup :label="t('rules.sections_read')">
                    <option v-for="s in readSections" :key="s" :value="s">{{ t(`rules.sec_${s}`) }}</option>
                </optgroup>
                <optgroup :label="t('rules.sections_other')">
                    <option v-for="s in otherSections" :key="s" :value="s">{{ t(`rules.sec_${s}`) }}</option>
                </optgroup>
                <option value="none">{{ t('rules.sec_none') }}</option>
            </select>
        </Field>

        <Field v-if="form.kind === 'title'" :label="t('rules.means_occupation')" :error="form.errors.occupation || form.errors.esco_occupation_id || form.errors.occupation_unit" required>
            <OccupationPicker v-model="form.occupation" />
        </Field>

        <Field v-if="form.kind === 'skill'" :label="t('rules.skill_name')" :hint="t('rules.skill_name_hint')" :error="form.errors.skill_name" optional>
            <input v-model="form.skill_name" type="text" maxlength="100" :placeholder="form.phrase">
        </Field>
    </div>
</template>
