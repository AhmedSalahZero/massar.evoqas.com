// ══════════════════════════════════════════════════════════════════
//  Massar — Learned Rules helpers
//  Location: resources/js/Components/Rules/rules.js
// ══════════════════════════════════════════════════════════════════

import { occIn } from '@/Components/Beneficiaries/ben';

/** Sections whose text fills the form (shown first when teaching a heading). */
export const READ_SECTIONS = ['experience', 'internships', 'responsibilities', 'education', 'skills', 'languages', 'skills_languages', 'personal', 'contact', 'military'];

/** A new, empty rule for useForm(). */
export const emptyRule = (over = {}) => ({ kind: 'heading', phrase: '', section: '', occupation: null, skill_name: '', ...over });

/** The form values as the server wants them. */
export const ruleData = (d) => ({
    kind: d.kind,
    phrase: d.phrase,
    section: d.kind === 'heading' ? d.section : null,
    esco_occupation_id: d.kind === 'title' ? (d.occupation?.esco_id ?? null) : null,
    occupation_unit: d.kind === 'title' && d.occupation?.group_only ? d.occupation.unit_code : null,
    skill_name: d.kind === 'skill' ? d.skill_name : null,
});

/** What a rule means, in words: "→ Work experience", "→ 2411 · Accountant", "→ skill: Peachtree". */
export function meaning(rule, t, standard, locale) {
    if (rule.kind === 'heading') return t(`rules.sec_${rule.section}`);
    if (rule.kind === 'title') {
        if (!rule.occupation) return t('rules.occupation_gone');
        const s = occIn(rule.occupation, rule.occupation.esco ? 'esco' : standard === 'esco' ? 'isco' : standard, locale);
        return `${s.code} · ${s.title}`;
    }
    if (rule.kind === 'employer') return t('rules.means_employer');
    return rule.skill_name || rule.phrase;
}

export const PROPOSAL_BADGE = { pending: 'orange', promoted: 'green', declined: 'danger' };
