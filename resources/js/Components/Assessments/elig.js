// ══════════════════════════════════════════════════════════════════
//  Massar — eligibility display helpers (Step 11 · inside Jobs & Training since Step 12)
//  Location: resources/js/Components/Assessments/elig.js
//
//  RESULT_BADGE[result]          badge colour of a result
//  STATUS[status]                ✓ ✗ ? – and its colour, for one rule line
//  sectionOf(kind)               'jobs' | 'training' — the route section
//  KIND_ICON[kind]               the icon of a Job or a Training
//  ruleText(rule, t, locale)     "Age 18 to 29", "English, at least Good" …
//  valueText(reason, t, locale)  what the profile holds for that rule
//  emptyRule(type)               a new rule of that type for the form
// ══════════════════════════════════════════════════════════════════

import { experience, money } from '@/Components/Beneficiaries/ben';
import { STANDARD_LABELS } from '@/Components/Backbone/occ';

export const RESULT_BADGE = { eligible: 'green', check: 'orange', not_eligible: 'danger', on_hold: 'navy' };
export const RESULTS = ['eligible', 'check', 'not_eligible', 'on_hold'];

export const STATUS = {
    pass: { mark: '✓', cls: 'c-green' },
    fail: { mark: '✗', cls: 'c-danger' },
    missing: { mark: '?', cls: 'c-orange' },
    na: { mark: '–', cls: 'mute' },
};

// Since Step 12 every result belongs to a job or a training (an "opportunity").
export const sectionOf = (kind) => (kind === 'training' ? 'training' : 'jobs');
export const KIND_ICON = { job: 'briefcase', training: 'graduation' };

const list = (values, fn) => (values || []).map(fn).join(', ');

export function occLabel(l, locale) {
    const title = locale === 'ar' ? (l.title_ar || l.title_en) : (l.title_en || l.title_ar);
    return `${STANDARD_LABELS[l.standard] || l.standard} ${l.code}${title ? ` · ${title}` : ''}`;
}

export function ruleText(r, t, locale) {
    switch (r.type) {
        case 'age':
            if (r.min != null && r.max != null) return t('elig.rt_age_between', { min: r.min, max: r.max });
            return r.min != null ? t('elig.rt_age_min', { min: r.min }) : t('elig.rt_age_max', { max: r.max });
        case 'governorate': return t('elig.rt_governorate', { list: list(r.values, (v) => t(`gov.${v}`)) });
        case 'education_level': return t('elig.rt_education_level', { list: list(r.values, (v) => t(`ben.edu_${v}`)) });
        case 'field_of_study': return t('elig.rt_field_of_study', { list: (r.words || []).join(', ') });
        case 'skills': return t(r.need === 'any' ? 'elig.rt_skills_any' : 'elig.rt_skills_all', { list: (r.values || []).join(', ') });
        case 'occupation': return t('elig.rt_occupation', { list: (r.labels || (r.values || []).map((v) => ({ standard: v.split(':')[0], code: v.split(':')[1] }))).map((l) => occLabel(l, locale)).join(' | ') });
        case 'experience': return t('elig.rt_experience', { n: r.min_years });
        case 'language': return t('elig.rt_language', { language: t(`ben.lang_${r.code}`), level: t(`ben.level_${r.level}`) });
        case 'military': return t('elig.rt_military', { list: list(r.values, (v) => t(`ben.mil_${v}`)) });
        case 'gender': return t('elig.rt_gender', { gender: t(`ben.gender_${r.value}`) });
        case 'salary': return t('elig.rt_salary', { n: money(r.max, locale) });
        default: return r.type;
    }
}

export function valueText(reason, t, locale) {
    const { rule, status, value } = reason;
    if (status === 'missing') return t('elig.v_missing');
    if (status === 'na') return t('elig.v_na');
    switch (rule.type) {
        case 'age': return t('ben.age', { n: value });
        case 'governorate': return t(`gov.${value}`);
        case 'education_level': return t(`ben.edu_${value}`);
        case 'field_of_study': return status === 'pass' ? t('elig.v_found', { word: value }) : t('elig.v_studied', { field: value });
        case 'skills': {
            const parts = [];
            if (value?.found?.length) parts.push(t('elig.v_has', { list: value.found.join(', ') }));
            if (value?.lacking?.length) parts.push(t('elig.v_lacks', { list: value.lacking.join(', ') }));
            return parts.join(' · ');
        }
        case 'occupation': return value ? String(value) : '—';
        case 'experience': return experience(value, t, locale);
        case 'language': return value ? t(`ben.level_${value}`) : t('elig.v_not_listed');
        case 'military': return t(`ben.mil_${value}`);
        case 'gender': return t(`ben.gender_${value}`);
        case 'salary': return `${money(value, locale)} ${t('mk.egp')}`;
        default: return value == null ? '—' : String(value);
    }
}

export function emptyRule(type) {
    const base = { type, mode: 'must', points: 10 };
    switch (type) {
        case 'age': return { ...base, min: 18, max: 35 };
        case 'governorate': case 'education_level': case 'military': return { ...base, values: [] };
        case 'field_of_study': return { ...base, words: [] };
        case 'skills': return { ...base, values: [], need: 'all' };
        case 'occupation': return { ...base, values: [], labels: [] };
        case 'experience': return { ...base, min_years: 1 };
        case 'language': return { ...base, code: 'en', level: 'good' };
        case 'gender': return { ...base, value: 'female' };
        case 'salary': return { ...base, max: '' };
        default: return base;
    }
}
