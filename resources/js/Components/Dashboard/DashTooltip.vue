<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Dashboard tooltip (Step 15)
//  Location: resources/js/Components/Dashboard/DashTooltip.vue
//
//  One floating box for every chart on the page: pointing at a bar, a
//  dot, a month or a funnel stage shows its numbers (data-tip, written
//  by charts.js). On a line chart it also shows the guide line and dots.
// ══════════════════════════════════════════════════════════════════

import { onBeforeUnmount, onMounted, ref } from 'vue';

const box = ref(null);
const data = ref(null);
const pos = ref({ x: 0, y: 0 });

function move(e) {
    const el = e.target.closest?.('[data-tip]');
    document.querySelectorAll('.dash .hv .hl, .dash .hv circle').forEach((n) => { n.style.display = 'none'; });
    if (!el || !el.closest('.dash')) { data.value = null; return; }
    const hv = el.closest('.hv');
    if (hv) hv.querySelectorAll('.hl, circle').forEach((n) => { n.style.display = ''; });
    try { data.value = JSON.parse(el.dataset.tip); } catch { data.value = null; return; }
    requestAnimationFrame(() => {
        const r = box.value?.getBoundingClientRect() || { width: 160, height: 60 };
        let x = e.clientX + 14; let y = e.clientY + 14;
        if (x + r.width > innerWidth - 8) x = e.clientX - r.width - 14;
        if (y + r.height > innerHeight - 8) y = e.clientY - r.height - 14;
        pos.value = { x, y };
    });
}
onMounted(() => document.addEventListener('mousemove', move));
onBeforeUnmount(() => document.removeEventListener('mousemove', move));
</script>

<template>
    <div v-show="data" ref="box" class="dash-tip" :style="`left:${pos.x}px;top:${pos.y}px`" role="tooltip">
        <template v-if="data">
            <div class="t">{{ data.title }}</div>
            <div v-for="(r, i) in data.rows" :key="i" class="row">
                <span><i v-if="r[0]" :style="`background:${r[0]}`" />{{ r[1] }}</span><b class="num">{{ r[2] }}</b>
            </div>
        </template>
    </div>
</template>
