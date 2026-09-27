<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — SkillTags (a list of skills as small links)
//  Location: resources/js/Components/Skills/SkillTags.vue
//
//      <SkillTags :skills="list" route-name="app.occupations.skill" />
//      <SkillTags :skills="list" route-name="…" :sorted="false" show-jobs />
//
//  Each tag opens the skill page; the other language is in the
//  tooltip. Knowledge items are teal, transversal skills green, other
//  skills plain. With show-jobs, each tag carries "· n" (how many
//  ESCO jobs list it). Long lists show the first 60 and a
//  "Show all" button.
// ══════════════════════════════════════════════════════════════════

import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';
import { skillNames, sortByName } from '@/Components/Skills/skl';

const props = defineProps({
    skills: { type: Array, required: true },
    routeName: { type: String, required: true },
    sorted: { type: Boolean, default: true },
    showJobs: { type: Boolean, default: false },
    limit: { type: Number, default: 60 },
});

const { t, locale } = useTranslations();
const all = ref(false);
const list = computed(() => (props.sorted ? sortByName(props.skills, locale.value) : props.skills));
const shown = computed(() => (all.value ? list.value : list.value.slice(0, props.limit)));
const colour = (s) => (s.type === 'knowledge' ? 'teal' : (s.reuse === 'transversal' ? 'green' : 'plain'));
</script>

<template>
    <div>
        <div class="tags">
            <Link v-for="s in shown" :key="s.id" :href="route(routeName, s.id)" class="tag" :class="colour(s)" :title="skillNames(s, locale)[1]">
                {{ skillNames(s, locale)[0] }}<span v-if="showJobs && s.jobs" class="mute ltr">&nbsp;· {{ s.jobs }}</span>
            </Link>
        </div>
        <button v-if="list.length > limit" type="button" class="btn btn-ghost sm mt-2" @click="all = !all">
            {{ all ? t('sk.show_less') : t('sk.show_all', { n: list.length }) }}
        </button>
    </div>
</template>
