// ══════════════════════════════════════════════════════════════════
//  Massar — occupation display helpers
//  Location: resources/js/Components/Backbone/occ.js
//
//  How an occupation title is shown in each language:
//    · Arabic screen  → Arabic title first, English under it
//    · English screen → English title first, Arabic under it
//  ESCO English titles are published in lower case ("accountant");
//  they are shown with a capital first letter. ENOC has Arabic
//  titles only; on English screens its ISCO-08 English title is used.
// ══════════════════════════════════════════════════════════════════

export const STANDARD_LABELS = { enoc: 'ENOC', isco: 'ISCO-08', esco: 'ESCO' };

export function cap(text) {
    return text ? text.charAt(0).toUpperCase() + text.slice(1) : '';
}

/** [primary, secondary] for a row that has title_en and title_ar. */
export function titles(row, locale) {
    const en = cap(row.title_en);
    const ar = row.title_ar_f ? `${row.title_ar} / ${row.title_ar_f}` : row.title_ar;
    return locale === 'ar' ? [ar || en, ar ? en : ''] : [en || ar, en ? ar : ''];
}

/** Edition name and data period in the screen's language. */
export function editionName(edition, locale) {
    return (locale === 'ar' && edition?.name_ar) ? edition.name_ar : (edition?.name ?? '');
}
export function editionPeriod(edition, t) {
    if (!edition?.reference_period) return '';
    return t(edition.reference_estimated ? 'mk.period_est' : 'mk.period', { p: edition.reference_period });
}
