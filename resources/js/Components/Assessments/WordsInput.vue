<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — WordsInput (Step 11)
//  Location: resources/js/Components/Assessments/WordsInput.vue
//
//  A list of short words (skills, fields of study) typed one by one:
//  Enter or a comma adds the word, × removes it.
//      <WordsInput v-model="rule.values" :placeholder="…" />
// ══════════════════════════════════════════════════════════════════

import { ref } from 'vue';

const model = defineModel({ type: Array, default: () => [] });
defineProps({ placeholder: { type: String, default: '' } });
const text = ref('');

function add() {
    const parts = text.value.split(/[,،]/).map((w) => w.trim()).filter(Boolean);
    const next = [...model.value];
    for (const w of parts) {
        if (!next.some((x) => x.toLowerCase() === w.toLowerCase())) next.push(w);
    }
    model.value = next;
    text.value = '';
}
const remove = (i) => { model.value = model.value.filter((_, k) => k !== i); };
function onKey(e) {
    if (e.key === 'Enter' || e.key === ',' || e.key === '،') { e.preventDefault(); add(); }
    else if (e.key === 'Backspace' && !text.value && model.value.length) remove(model.value.length - 1);
}
</script>

<template>
    <div class="words">
        <span v-for="(w, i) in model" :key="w" class="tag plain"><bdi>{{ w }}</bdi><span class="x" role="button" :aria-label="'×'" @click="remove(i)">×</span></span>
        <input v-model="text" type="text" :placeholder="placeholder" @keydown="onKey" @blur="add">
    </div>
</template>

<style scoped>
.words { display: flex; flex-wrap: wrap; gap: 5px; align-items: center; border: 1px solid var(--ms-border); border-radius: var(--r-md, 8px);
    padding: 5px 8px; background: var(--ms-bg-input, var(--ms-bg-card)); min-height: 38px; }
.words input { border: 0 !important; box-shadow: none !important; background: transparent; flex: 1; min-width: 120px; padding: 3px 2px; outline: none; }
</style>
