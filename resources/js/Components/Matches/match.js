// ══════════════════════════════════════════════════════════════════
//  Massar — Matches display helpers (Step 13)
//  Location: resources/js/Components/Matches/match.js
//
//  STAGES                       referred · accepted · in_progress · done
//  stageText(kind, stage, t)    the words of a stage for a Job or a Training
//                               ("Interviewing", "In training", "Hired" …)
//  stageShort(stage, t, kind)   the short words for counts ("Accepted" …)
//  STAGE_BADGE[stage]           badge colour of a stage
//  nextStage(stage)             the next stage, or null after the last
//  fitText(fit, t, locale)      "Same occupation", "Same minor group 241" …
//  skillsText(skills, t)        "4 of 12 essential skills"
//  skillTitle(s, locale)        one ESCO skill's name in this language
// ══════════════════════════════════════════════════════════════════

export const STAGES = ['referred', 'accepted', 'in_progress', 'done'];

export const STAGE_BADGE = { referred: 'navy', accepted: 'teal', in_progress: 'orange', done: 'green', stopped: 'plain' };

export const stageText = (kind, stage, t) => t(`mt.st_${kind === 'training' ? 'training' : 'job'}_${stage}`);

export const stageShort = (stage, t, kind = '') => {
    if (stage === 'done') return kind === 'job' ? t('mt.s_hired') : kind === 'training' ? t('mt.s_completed') : t('mt.s_done');
    return t(`mt.s_${stage}`);
};

export function nextStage(stage) {
    const i = STAGES.indexOf(stage);
    return i >= 0 && i < STAGES.length - 1 ? STAGES[i + 1] : null;
}

export const FIT_BADGE = { same: 'green', unit: 'teal', minor: 'teal', sub_major: 'plain', major: 'plain', none: 'plain', unknown: 'orange' };

export function fitText(fit, t) {
    if (!fit) return '';
    if (fit.level === 'same') return t('mt.fit_same');
    if (fit.level === 'none') return t('mt.fit_none');
    if (fit.level === 'unknown') return t('mt.fit_unknown');
    return t(`mt.fit_${fit.level}`, { code: fit.code || '' });
}

export function skillsText(s, t) {
    if (!s) return '';
    if (s.state === 'no_job_skills') return t('mt.skills_no_job');
    if (s.state === 'no_person_skills') return t('mt.skills_no_person', { n: s.total });
    return t('mt.skills_n', { found: s.found.length, n: s.total });
}

export const skillTitle = (s, locale) => (locale === 'ar' ? (s.ar || s.en) : s.en);

export const stopReasonText = (kind, reason, t) => t(`mt.stop_${reason === 'not_accepted' ? `not_accepted_${kind === 'training' ? 'training' : 'job'}` : reason}`);
