<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — CvChangeList (what a newer CV would change, with tick boxes)
//  Location: resources/js/Components/Cv/CvChangeList.vue
//
//      <CvChangeList :rows="rows" :ticked="ticked" />
//
//  rows    from CvProfileUpdate::compare() on the server
//  ticked  a reactive { rowId: true|false } the page owns and sends
//  Used by "Update from this CV" (staff, Pages/App/Cv/Update.vue) and
//  by "Replace my CV" (job seekers, Pages/Public/CvChanges.vue).
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { usePreferences } from '@/composables/usePreferences';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';
import { occIn } from '@/Components/Beneficiaries/ben';

const props = defineProps({
    rows: { type: Array, required: true },
    ticked: { type: Object, required: true },
});

const { t, locale } = useTranslations();
const { prefs } = usePreferences();

const GROUPS = ['details', 'occupation', 'jobs', 'education', 'skills', 'languages'];
const groups = computed(() => GROUPS.map((g) => ({ key: g, rows: props.rows.filter((r) => r.group === g) })).filter((g) => g.rows.length));
const all = (group, on) => group.rows.forEach((r) => { if (!r.blocked) props.ticked[r.id] = on; });

function value(field, v) {
    if (v === null || v === undefined || v === '') return '—';
    switch (field) {
        case 'governorate': return t(`gov.${v}`);
        case 'gender': return t(`ben.gender_${v}`);
        case 'military_status': return t(`ben.mil_${v}`);
        case 'education_level': return t(`ben.edu_${v}`);
        case 'date_of_birth': return formatDate(v, locale.value);
        default: return String(v);
    }
}
const occ = (block) => {
    if (!block) return '—';
    const s = occIn(block, block.esco ? 'esco' : prefs.standard === 'esco' ? 'isco' : prefs.standard, locale.value);
    return `${s.code} · ${s.title}`;
};
const period = (j) => `${j.from || '?'} – ${j.current ? t('ben.present') : (j.to || '?')}`;
const lang = (l) => `${t(`ben.lang_${l.code}`)}${l.level ? ' · ' + t(`ben.level_${l.level}`) : ''}`;
</script>

<template>
  <div>
    <div v-for="g in groups" :key="g.key" class="panel">
            <div class="panel-h">
                <h3>{{ t(`cv.upd_g_${g.key}`) }}</h3>
                <span v-if="g.rows.length > 1" class="small">
                    <button type="button" class="linkbtn" @click="all(g, true)">{{ t('cv.upd_tick_all') }}</button> ·
                    <button type="button" class="linkbtn" @click="all(g, false)">{{ t('cv.upd_tick_none') }}</button>
                </span>
            </div>

            <label v-for="r in g.rows" :key="r.id" class="chg" :class="{ off: !ticked[r.id], locked: r.blocked }">
                <input v-model="ticked[r.id]" type="checkbox" :disabled="!!r.blocked">
                <div class="min0 grow">
                    <!-- details -->
                    <template v-if="g.key === 'details'">
                        <b>{{ t(`ben.f_${r.field}`) }}</b>
                        <div class="cmp">
                            <span class="old">{{ value(r.field, r.old) }}</span>
                            <AppIcon name="chevron-left" :size="12" class="arrow" />
                            <span class="new">{{ value(r.field, r.new) }}</span>
                        </div>
                    </template>
                    <!-- occupation -->
                    <template v-else-if="g.key === 'occupation'">
                        <div class="cmp"><span class="old">{{ occ(r.old) }}</span><AppIcon name="chevron-left" :size="12" class="arrow" /><span class="new">{{ occ(r.new) }}</span></div>
                    </template>
                    <!-- jobs -->
                    <template v-else-if="g.key === 'jobs'">
                        <b>{{ r.new.title }}<template v-if="r.new.employer"> · {{ r.new.employer }}</template></b>
                        <span class="small mute"> · {{ period(r.new) }}<template v-if="r.new.location"> · {{ r.new.location }}</template></span>
                        <div class="small">{{ t(r.kind === 'add' ? 'cv.upd_new_job' : 'cv.upd_add_duties') }}</div>
                        <ul v-if="r.new.responsibilities?.length" class="duties">
                            <li v-for="(d, k) in r.new.responsibilities.slice(0, 6)" :key="k">{{ d }}</li>
                            <li v-if="r.new.responsibilities.length > 6" class="mute">{{ t('cv.upd_more', { n: r.new.responsibilities.length - 6 }) }}</li>
                        </ul>
                    </template>
                    <!-- education -->
                    <template v-else-if="g.key === 'education'">
                        <b>{{ r.new.qualification || r.new.institution }}</b>
                        <span class="small mute"><template v-if="r.new.institution && r.new.qualification"> · {{ r.new.institution }}</template><template v-if="r.new.year"> · {{ r.new.year }}</template></span>
                    </template>
                    <!-- skills -->
                    <template v-else-if="g.key === 'skills'"><b>{{ r.new }}</b></template>
                    <!-- languages -->
                    <template v-else>
                        <div class="cmp">
                            <span v-if="r.old" class="old">{{ lang(r.old) }}</span>
                            <AppIcon v-if="r.old" name="chevron-left" :size="12" class="arrow" />
                            <span class="new">{{ lang(r.new) }}</span>
                        </div>
                    </template>

                    <div class="small" :class="r.blocked ? 'c-danger' : r.kind === 'replace' ? 'c-orange' : 'mute'">
                        {{ r.blocked ? t(`cv.upd_${r.blocked}`) : t(`cv.upd_k_${r.kind}`) }}
                    </div>
                </div>
            </label>
        </div>

  </div>
</template>

<style scoped>
.panel + .panel { margin-top: 14px; }
.chg { display: flex; gap: 12px; align-items: flex-start; padding: 10px 0; border-top: 1px solid var(--ms-border); cursor: pointer; }
.chg:first-of-type { border-top: 0; }
.chg input { margin-top: 3px; }
.chg.off .new { opacity: .55; }
.chg.locked { cursor: not-allowed; opacity: .7; }
.cmp { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; font-size: 13px; margin-top: 2px; }
.cmp .old { color: var(--ms-text-muted); text-decoration: line-through; }
.cmp .new { font-weight: 700; }
[dir="ltr"] .arrow { transform: rotate(180deg); }
.duties { margin: 4px 0 0; padding-inline-start: 18px; font-size: 12.5px; }
.linkbtn { background: none; border: 0; padding: 0; color: var(--ms-navy); cursor: pointer; font-size: 12px; }
.min0 { min-width: 0; }
.grow { flex: 1; }
</style>
