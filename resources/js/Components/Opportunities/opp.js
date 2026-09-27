// ══════════════════════════════════════════════════════════════════
//  Massar — Jobs & Training display helpers (Step 12)
//  Location: resources/js/Components/Opportunities/opp.js
//
//  occShown(o, standard, locale)  one chosen occupation, in the standard
//                                 of the top-bar switch when it is (in) a
//                                 4-digit group; otherwise as chosen
//                                 (e.g. ISCO-08 24, a sub-major group)
//  govList(list, t)               "Cairo, Giza"
//  durationText(o, t)             "6 weeks"
//  costText(o, t, locale)         "Free" · "1,500 EGP"
//  salaryText(o, t, locale)       "6,000 – 8,000 EGP a month"
//  sameSet(a, b)                  the same values, in any order
// ══════════════════════════════════════════════════════════════════

import { occIn, money } from '@/Components/Beneficiaries/ben';
import { occLabel } from '@/Components/Assessments/elig';
import { STANDARD_LABELS } from '@/Components/Backbone/occ';

export function occShown(o, standard, locale) {
    if (o.block) {
        const s = occIn(o.block, standard, locale);
        return `${STANDARD_LABELS[standard] || ''} ${s.code} · ${s.title}`.trim();
    }
    return occLabel(o.label || { standard: o.value.split(':')[0], code: o.value.split(':')[1] }, locale);
}

export const govList = (list, t) => (list || []).map((g) => t(`gov.${g}`)).join(', ');

export function durationText(o, t) {
    if (!o.duration_value) return '';
    return t(`opp.dur_${o.duration_unit}`, { n: o.duration_value });
}

export function costText(o, t, locale) {
    if (o.cost_type === 'free') return t('opp.cost_free');
    if (o.cost_type === 'paid') return `${money(o.cost_amount, locale)} ${t('mk.egp')}`;
    return '';
}

export function salaryText(o, t, locale) {
    const from = o.salary_from ? money(o.salary_from, locale) : '';
    const to = o.salary_to ? money(o.salary_to, locale) : '';
    if (!from && !to) return '';
    if (from && to) return t('opp.salary_range', { from, to });
    return from ? t('opp.salary_from_only', { from }) : t('opp.salary_to_only', { to });
}

export function sameSet(a, b) {
    const x = new Set(a || []);
    const y = new Set(b || []);
    return x.size === y.size && [...x].every((v) => y.has(v));
}
