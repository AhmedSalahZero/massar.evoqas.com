// ══════════════════════════════════════════════════════════════════
//  Massar — beneficiary display helpers
//  Location: resources/js/Components/Beneficiaries/ben.js
//
//  occIn(block, standard, locale)
//      One stored occupation read in the standard chosen in the top
//      bar (the server sends all three — see OccupationDisplay.php):
//        ENOC    → ENOC code + Egyptian Arabic title (English screens
//                  use the ISCO-08 English title, like everywhere else)
//        ISCO-08 → unit group code + title
//        ESCO    → detailed job code + title; for the 10 groups ESCO
//                  does not detail: the group, marked "group level only"
//      → { code, title, note } where note is a translation key or ''.
//
//  names(b, locale)  → [first, second] name for this screen language
//  experience(months, t) → "3 years 2 months"
// ══════════════════════════════════════════════════════════════════

import { cap } from '@/Components/Backbone/occ';

export function occIn(block, standard, locale) {
    if (!block) return null;
    const ar = locale === 'ar';
    const isco = { code: block.isco.code, title: ar ? (block.isco.title_ar || block.isco.title_en) : block.isco.title_en };

    if (standard === 'enoc') {
        if (!block.enoc) return { ...isco, note: 'ben.not_in_enoc' };
        return { code: block.enoc.code, title: ar ? block.enoc.title : block.isco.title_en, note: '' };
    }
    if (standard === 'esco') {
        if (!block.esco) return { ...isco, note: 'ben.group_only' };
        return { code: block.esco.code, title: ar ? (block.esco.title_ar || cap(block.esco.title_en)) : cap(block.esco.title_en), note: '' };
    }
    return { ...isco, note: '' };
}

export function names(b, locale) {
    const first = locale === 'ar' ? (b.name_ar || b.name_en) : (b.name_en || b.name_ar);
    const second = first === b.name_ar ? b.name_en : b.name_ar;
    return [first || '—', second || ''];
}

// Arabic counts need the right word form: 1 سنة · 2 سنتان · 3–10 سنوات · 11+ سنة.
function arCount(n, [one, two, few, many]) {
    if (n === 1) return one;
    if (n === 2) return two;
    return `${n} ${n <= 10 ? few : many}`;
}

export function experience(months, t, locale = 'en') {
    const m = Number(months || 0);
    if (!m) return t('ben.exp_none');
    const y = Math.floor(m / 12);
    const r = m % 12;
    if (locale === 'ar') {
        const ys = y ? arCount(y, ['سنة', 'سنتان', 'سنوات', 'سنة']) : '';
        const ms = r ? arCount(r, ['شهر', 'شهران', 'أشهر', 'شهراً']) : '';
        return [ys, ms].filter(Boolean).join(' و');
    }
    if (!y) return t('ben.exp_m', { m: r });
    if (!r) return t('ben.exp_y', { y });
    return t('ben.exp_ym', { y, m: r });
}

export function money(v, locale) {
    return v === null || v === undefined ? '' : Number(v).toLocaleString(locale === 'ar' ? 'ar-EG' : 'en-US');
}
