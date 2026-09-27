<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — OccupationBrowser (search + browse in three standards)
//  Location: resources/js/Components/Backbone/OccupationBrowser.vue
//
//      <OccupationBrowser :standard :filters :majors :browse
//          :routes="{ index: 'app.occupations.index', unit: 'app.occupations.unit', esco: 'app.occupations.esco' }" />
//
//  Used by the Super Admin backbone page and the partner Occupations
//  page, so both search and browse exactly the same way:
//    ENOC    → the 426 Egyptian occupations (flat list)
//    ISCO-08 → the four-level tree, or a flat list when searching
//    ESCO    → the ~3,000 detailed occupations
//  Follows the Standard switch in the top bar (and changes it).
//  Searching matches Arabic and English titles and alternative titles.
// ══════════════════════════════════════════════════════════════════

import { onMounted, reactive, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { usePreferences } from '@/composables/usePreferences';
import { useTranslations } from '@/composables/useTranslations';
import { STANDARD_LABELS, titles } from '@/Components/Backbone/occ';

const props = defineProps({
    standard: { type: String, required: true },
    filters: { type: Object, required: true },
    majors: { type: Array, default: () => [] },
    browse: { type: Object, required: true },
    routes: { type: Object, required: true },   // { index, unit, esco } route names
});

const { t, locale } = useTranslations();
const { prefs, setStandard } = usePreferences();

const f = reactive({ q: props.filters.q, major: props.filters.major });

function load(standard = props.standard) {
    router.get(route(props.routes.index), {
        standard,
        q: f.q || undefined,
        major: f.major || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true, only: ['standard', 'filters', 'browse'] });
}

// The top-bar Standard switch (and the chips below) change prefs.standard.
watch(() => prefs.standard, (s) => { if (s !== props.standard) load(s); });

// A link that opens a given standard (pagination, bookmarks) also makes
// it the working standard, so the top bar and the list always agree.
onMounted(() => { if (prefs.standard !== props.standard) setStandard(props.standard); });

function clearSearch() { f.q = ''; load(); }

const majorName = (m) => {
    if (props.standard === 'enoc' && m.enoc_title_ar && locale.value === 'ar') return m.enoc_title_ar;
    return locale.value === 'ar' ? (m.title_ar || m.title_en) : m.title_en;
};
// Show "Matched: …" only when the matching title is not already on screen.
const showMatch = (row) => {
    if (!row.match) return false;
    const shown = titles(row, locale.value).join(' / ').toLowerCase();
    return !shown.includes(row.match.label.toLowerCase());
};
</script>

<template>
    <div>
        <div class="filter-bar">
            <span class="seg-l">{{ t('bb.showing_in') }}</span>
            <div class="seg std" role="group" :aria-label="t('shell.standard')">
                <button v-for="(label, key) in STANDARD_LABELS" :key="key" type="button" :aria-pressed="standard === key" @click="setStandard(key)">{{ label }}</button>
            </div>
        </div>

        <form class="filter-bar" @submit.prevent="load()">
            <div class="search grow">
                <AppIcon name="search" :size="16" />
                <input v-model="f.q" type="search" :placeholder="t('bb.search_ph')" :aria-label="t('common.search')">
                <button v-if="f.q" type="button" class="btn btn-ghost sm" @click="clearSearch">{{ t('common.clear') }}</button>
                <button type="submit" class="btn btn-teal sm">{{ t('common.search') }}</button>
            </div>
            <select v-model="f.major" class="inp" style="max-width:320px;width:auto" :aria-label="t('bb.level_1')" @change="load()">
                <option value="">{{ t('bb.all_majors') }}</option>
                <option v-for="m in majors" :key="m.code" :value="m.code">{{ m.code }} · {{ majorName(m) }}</option>
            </select>
        </form>

        <!-- ENOC list · ISCO-08 search results · ESCO list -->
        <template v-for="list in [browse.enoc || browse.units || browse.esco]" :key="standard">
            <template v-if="list">
                <EmptyState v-if="!list.data.length" icon="search" :text="t('bb.no_results')" />
                <div v-else class="table-wrap">
                    <div style="padding:6px">
                        <Link v-for="row in list.data" :key="row.id ?? row.code" class="orow"
                              :href="row.id ? route(routes.esco, row.id) : route(routes.unit, row.code)">
                            <code>{{ row.code }}</code>
                            <span class="ot">
                                <b>{{ titles(row, locale)[0] }}</b>
                                <span v-if="titles(row, locale)[1]">{{ titles(row, locale)[1] }}</span>
                                <span v-if="showMatch(row)" class="snip" style="display:inline-block">
                                    {{ t('bb.matched') }}: <mark>{{ row.match.label }}</mark>
                                </span>
                            </span>
                            <span class="tags">
                                <span v-if="row.regulated" class="tag orange">{{ t('bb.regulated') }}</span>
                                <span v-if="row.isco_code" class="tag plain code">ISCO {{ row.isco_code }}</span>
                                <span v-if="row.in_enoc === false" class="tag plain">{{ t('bb.not_in_enoc') }}</span>
                                <template v-if="row.esco_count !== undefined">
                                    <span v-if="row.esco_count" class="tag purple">{{ t('bb.esco_n', { n: row.esco_count }) }}</span>
                                    <span v-else class="tag orange">{{ t('bb.no_esco') }}</span>
                                </template>
                            </span>
                        </Link>
                    </div>
                    <Pagination :paginator="list" />
                </div>
            </template>
        </template>

        <!-- ISCO-08 four-level tree -->
        <div v-if="browse.tree" class="tree">
            <details v-for="major in browse.tree" :key="major.code" :open="!!filters.major">
                <summary><code>{{ major.code }}</code>{{ locale === 'ar' ? major.title_ar : major.title_en }}<span class="n">{{ t('bb.tree_units', { n: major.units }) }}</span></summary>
                <div class="units">
                    <details v-for="sub in major.children" :key="sub.code" class="nested">
                        <summary><code>{{ sub.code }}</code>{{ locale === 'ar' ? sub.title_ar : sub.title_en }}<span class="n">{{ t('bb.tree_units', { n: sub.units }) }}</span></summary>
                        <div class="units">
                            <details v-for="minor in sub.children" :key="minor.code" class="nested">
                                <summary><code>{{ minor.code }}</code>{{ locale === 'ar' ? minor.title_ar : minor.title_en }}<span class="n">{{ t('bb.tree_units', { n: minor.units }) }}</span></summary>
                                <div class="units">
                                    <Link v-for="unit in minor.children" :key="unit.code" class="orow" :href="route(routes.unit, unit.code)">
                                        <code>{{ unit.code }}</code>
                                        <span class="ot"><b>{{ titles(unit, locale)[0] }}</b><span>{{ titles(unit, locale)[1] }}</span></span>
                                        <span class="tags">
                                            <span v-if="!unit.in_enoc" class="tag plain">{{ t('bb.not_in_enoc') }}</span>
                                            <span v-if="unit.esco_count" class="tag purple">{{ t('bb.esco_n', { n: unit.esco_count }) }}</span>
                                            <span v-else class="tag orange">{{ t('bb.no_esco') }}</span>
                                        </span>
                                    </Link>
                                </div>
                            </details>
                        </div>
                    </details>
                </div>
            </details>
        </div>
    </div>
</template>

<style scoped>
/* Nested tree levels sit inside their parent card, lighter and indented. */
.tree details.nested { margin-top: 4px; background: transparent; border-color: var(--ms-border-soft); }
.tree details.nested > summary { font-weight: 600; font-size: 13px; padding: 8px 12px; }
.tree details.nested .units { padding-inline-start: 14px; }
.orow { grid-template-columns: 96px minmax(0, 1fr) auto; }
</style>
