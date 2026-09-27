// ══════════════════════════════════════════════════════════════════
//  Massar — CV Bank helpers for the screens
//  Location: resources/js/Components/Cv/cv.js
//
//  STATUS_BADGE    status → badge colour (style guide §5 badges)
//  sizeText(bytes) '212 KB' / '1.4 MB'
//  segments(line, needles)  splits one line of CV text into pieces,
//                  marking the parts that are contact details or known
//                  skills — used by CvPaper to colour them.
// ══════════════════════════════════════════════════════════════════

export const STATUS_BADGE = {
    added: 'green',
    approved: 'green',
    attached: 'teal',
    review: 'orange',
    duplicate: 'danger',
    unreadable: 'plain',
    rejected: 'plain',
};

export function sizeText(bytes, locale = 'en') {
    if (!bytes && bytes !== 0) return '';
    const kb = bytes / 1024;
    const n = kb >= 1024 ? (kb / 1024).toFixed(1) : Math.max(1, Math.round(kb));
    const unit = kb >= 1024 ? (locale === 'ar' ? 'م.ب' : 'MB') : (locale === 'ar' ? 'ك.ب' : 'KB');
    return `${n} ${unit}`;
}

/**
 * @param {string} line
 * @param {Array<[string, string]>} needles  [text, css class], longest first
 * @returns {Array<{text: string, cls: string|null}>}
 */
export function segments(line, needles) {
    const low = line.toLowerCase();
    const hits = [];
    for (const [needle, cls] of needles) {
        if (!needle) continue;
        const n = needle.toLowerCase();
        let from = 0;
        let at;
        while ((at = low.indexOf(n, from)) !== -1) {
            const end = at + n.length;
            if (!hits.some((h) => at < h.end && end > h.start)) hits.push({ start: at, end, cls });
            from = end;
        }
    }
    hits.sort((a, b) => a.start - b.start);
    const out = [];
    let pos = 0;
    for (const h of hits) {
        if (h.start > pos) out.push({ text: line.slice(pos, h.start), cls: null });
        out.push({ text: line.slice(h.start, h.end), cls: h.cls });
        pos = h.end;
    }
    if (pos < line.length) out.push({ text: line.slice(pos), cls: null });
    return out;
}
