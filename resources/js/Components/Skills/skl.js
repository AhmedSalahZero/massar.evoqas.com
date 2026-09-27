// ══════════════════════════════════════════════════════════════════
//  Massar — skill display helpers
//  Location: resources/js/Components/Skills/skl.js
//
//  Same rule as occupation titles (Backbone/occ.js): the screen's
//  language first, the other one under it. ESCO English names are
//  published in lower case; they get a capital first letter.
//  Lists are sorted by the name shown, in the screen's language.
// ══════════════════════════════════════════════════════════════════

import { cap } from '@/Components/Backbone/occ';

/** [primary, secondary] name of a skill or skill group. */
export function skillNames(row, locale) {
    const en = cap(row.title_en);
    const ar = row.title_ar || '';
    // ESCO gives some product names ("Apache Maven") as the Arabic name: no need to show them twice.
    const same = ar && ar.toLowerCase() === (row.title_en || '').toLowerCase();
    if (locale === 'ar') return [ar || en, ar && !same ? en : ''];
    return [en || ar, same ? '' : ar];
}

export function sortByName(list, locale) {
    return [...list].sort((a, b) => skillNames(a, locale)[0].localeCompare(skillNames(b, locale)[0], locale === 'ar' ? 'ar' : 'en'));
}

/** Split a list into skills and knowledge (items with no type in ESCO count as skills). */
export function byType(list) {
    return {
        skill: list.filter((s) => s.type !== 'knowledge'),
        knowledge: list.filter((s) => s.type === 'knowledge'),
    };
}
