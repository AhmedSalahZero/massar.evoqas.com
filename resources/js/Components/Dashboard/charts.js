// ══════════════════════════════════════════════════════════════════
//  Massar — Dashboard charts (Step 15, the agreed demo)
//  Location: resources/js/Components/Dashboard/charts.js
//
//  The same charts as docs/dashboard-demo.html, drawn as SVG / HTML:
//    stackedColumns()  new people by month, by how they joined
//    lines()           placements by month, new people (Super Admin)
//    columns()         age bands
//    hbars()           governorates, education, industry, skills
//    dumbbell()        expected salary vs market wage
//    spark()           the small lines in the top cards
//  Every mark carries data-tip; DashTooltip.vue shows it on hover.
//  Colours are chart roles (--s1 … --s4), validated for light and dark.
//  Every text that comes from data goes through esc().
// ══════════════════════════════════════════════════════════════════

export const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const tipAttr = (title, rows) => `data-tip="${esc(JSON.stringify({ title, rows }))}"`;
const sum = (a) => a.reduce((x, y) => x + y, 0);

function niceMax(v, steps = 4) {
    if (v <= steps) return steps;
    const p = 10 ** Math.floor(Math.log10(v / steps));
    const n = Math.ceil(v / steps / p) * p;
    return n * steps;
}

function roundTop(x, y, w, h, r) {
    r = Math.max(0, Math.min(r, h, w / 2));
    return `M${x},${y + h} V${y + r} Q${x},${y} ${x + r},${y} H${x + w - r} Q${x + w},${y} ${x + w},${y + r} V${y + h} Z`;
}

function yGrid(max, y, pl, right, nf) {
    let g = '';
    for (let k = 0; k <= 4; k++) {
        const v = (max * k) / 4;
        g += `<line class="gl" x1="${pl}" x2="${right}" y1="${y(v)}" y2="${y(v)}"/><text x="${pl - 6}" y="${y(v) + 4}" text-anchor="end">${nf(v)}</text>`;
    }
    return g;
}

/** months: ['2026-01', …]; series: {key: [n per month]}; names: labels in the same order as keys. */
export function stackedColumns({ months, series, keys, colors, names, monthName, nf, totalWord }) {
    const W = 640; const H = 220; const pl = 36; const pr = 8; const pt = 10; const pb = 26;
    const totals = months.map((_, i) => sum(keys.map((k) => series[k][i] || 0)));
    const max = niceMax(Math.max(1, ...totals));
    const bw = (W - pl - pr) / months.length; const barW = Math.min(30, bw * 0.62);
    const y = (v) => pt + (H - pt - pb) * (1 - v / max);
    let g = yGrid(max, y, pl, W - pr, nf);
    const every = months.length > 14 ? 3 : months.length > 8 ? 1 : 1;
    months.forEach((m, i) => {
        const x = pl + i * bw + (bw - barW) / 2;
        let base = 0;
        const present = keys.filter((k) => series[k][i]);
        present.forEach((k, j) => {
            const v = series[k][i];
            const y1 = y(base + v); const y0 = y(base);
            const h = Math.max(0, y0 - y1 - (base ? 2 : 0));
            const c = colors[keys.indexOf(k)];
            g += j === present.length - 1 ? `<path d="${roundTop(x, y1, barW, h, 4)}" fill="${c}"/>` : `<rect x="${x}" y="${y1}" width="${barW}" height="${h}" fill="${c}"/>`;
            base += v;
        });
        if (i % every === 0 || months.length <= 14) g += `<text x="${x + barW / 2}" y="${H - 8}" text-anchor="middle">${esc(monthName(m))}</text>`;
        g += `<rect class="hit" x="${pl + i * bw}" y="${pt}" width="${bw}" height="${H - pt - pb}" ${tipAttr(monthName(m, true), keys.map((k, j) => [colors[j], names[j], nf(series[k][i] || 0)]).concat([[null, totalWord, nf(totals[i])]]))}/>`;
    });
    return `<svg class="chart" viewBox="0 0 ${W} ${H}" role="img">${g}</svg>`;
}

export function lines({ months, series, keys, colors, names, monthName, nf }) {
    const W = 640; const H = 220; const pl = 36; const pr = 12; const pt = 12; const pb = 26;
    const max = niceMax(Math.max(1, ...keys.flatMap((k) => series[k])));
    const step = (W - pl - pr) / Math.max(1, months.length - 1);
    const x = (i) => (months.length === 1 ? (pl + W - pr) / 2 : pl + i * step);
    const y = (v) => pt + (H - pt - pb) * (1 - v / max);
    let g = yGrid(max, y, pl, W - pr, nf);
    keys.forEach((k, j) => {
        const pts = series[k].map((v, i) => `${x(i)},${y(v)}`).join(' ');
        g += `<polyline points="${pts}" fill="none" stroke="${colors[j]}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>`;
        if (months.length === 1) g += `<circle cx="${x(0)}" cy="${y(series[k][0])}" r="4.5" fill="${colors[j]}"/>`;
    });
    months.forEach((m, i) => {
        if (months.length <= 6 || i % Math.ceil(months.length / 6) === 0 || i === months.length - 1) g += `<text x="${x(i)}" y="${H - 8}" text-anchor="middle">${esc(monthName(m))}</text>`;
        const hw = months.length === 1 ? W : step;
        g += `<g class="hv"><line class="hl" x1="${x(i)}" x2="${x(i)}" y1="${pt}" y2="${H - pb}" style="display:none"/>${keys.map((k, j) => `<circle cx="${x(i)}" cy="${y(series[k][i])}" r="4.5" fill="${colors[j]}" stroke="var(--ms-bg-card)" stroke-width="2" style="display:none"/>`).join('')}
          <rect class="hit" x="${x(i) - hw / 2}" y="${pt}" width="${hw}" height="${H - pt - pb}" ${tipAttr(monthName(m, true), keys.map((k, j) => [colors[j], names[j], nf(series[k][i])]))}/></g>`;
    });
    return `<svg class="chart" viewBox="0 0 ${W} ${H}" role="img">${g}</svg>`;
}

/** items: [[label, value]] */
export function columns({ items, color, nf, title, word }) {
    const W = 260; const H = 210; const pl = 30; const pr = 4; const pt = 10; const pb = 24;
    const max = niceMax(Math.max(1, ...items.map((i) => i[1])));
    const bw = (W - pl - pr) / items.length; const barW = Math.min(34, bw * 0.6);
    const y = (v) => pt + (H - pt - pb) * (1 - v / max);
    let g = yGrid(max, y, pl, W - pr, nf);
    items.forEach(([lbl, v], i) => {
        const x = pl + i * bw + (bw - barW) / 2;
        if (v) g += `<path d="${roundTop(x, y(v), barW, H - pb - y(v), 4)}" fill="${color}"/>`;
        g += `<text x="${x + barW / 2}" y="${H - 8}" text-anchor="middle">${esc(lbl)}</text>`;
        g += `<rect class="hit" x="${pl + i * bw}" y="${pt}" width="${bw}" height="${H - pt - pb}" ${tipAttr(`${title} ${lbl}`, [[color, word, nf(v)]])}/>`;
    });
    return `<svg class="chart" viewBox="0 0 ${W} ${H}" role="img">${g}</svg>`;
}

/** rows: [[label, value]] */
export function hbars({ rows, color, nf, word }) {
    const max = Math.max(1, ...rows.map((r) => r[1]));
    return `<div class="hbars">${rows.map(([n, v]) => `<div class="r" ${tipAttr(n, [[color, word, nf(v)]])}><span title="${esc(n)}">${esc(n)}</span><span class="trk"><i style="width:${(v / max) * 100}%;--c:${color}"></i></span><span class="v">${nf(v)}</span></div>`).join('')}</div>`;
}

/** rows: [[label, expected, market]] */
export function dumbbell({ rows, nf, names, gapWord }) {
    const W = 560; const rowH = 30; const pl = 200; const pr = 26; const pt = 8; const H = pt + rows.length * rowH + 24;
    const top = niceMax(Math.max(1000, ...rows.flatMap((r) => [r[1], r[2]])));
    const x = (v) => pl + ((W - pl - pr) * Math.min(v, top)) / top;
    let g = '';
    [0, 0.25, 0.5, 0.75, 1].map((k) => top * k).forEach((v) => { g += `<line class="gl" x1="${x(v)}" x2="${x(v)}" y1="${pt}" y2="${H - 22}"/><text x="${x(v)}" y="${H - 6}" text-anchor="middle">${nf(v)}</text>`; });
    rows.forEach(([lbl, e, m], i) => {
        const cy = pt + i * rowH + rowH / 2;
        g += `<text x="${pl - 10}" y="${cy + 4}" text-anchor="end" style="fill:var(--ms-text-primary)">${esc(lbl.length > 30 ? `${lbl.slice(0, 29)}…` : lbl)}</text>`;
        g += `<line x1="${x(e)}" x2="${x(m)}" y1="${cy}" y2="${cy}" stroke="var(--axis)" stroke-width="2" opacity=".45"/>`;
        g += `<circle cx="${x(m)}" cy="${cy}" r="6" fill="var(--s2)" stroke="var(--ms-bg-card)" stroke-width="2"/><circle cx="${x(e)}" cy="${cy}" r="6" fill="var(--s1)" stroke="var(--ms-bg-card)" stroke-width="2"/>`;
        g += `<rect class="hit" x="0" y="${cy - rowH / 2}" width="${W}" height="${rowH}" ${tipAttr(lbl, [['var(--s1)', names[0], nf(e)], ['var(--s2)', names[1], nf(m)], [null, gapWord, (e >= m ? '+' : '−') + nf(Math.abs(e - m))]])}/>`;
    });
    return `<svg class="chart" viewBox="0 0 ${W} ${H}" role="img">${g}</svg>`;
}

export function spark(vals, color) {
    const w = 160; const h = 30; const max = Math.max(...vals); const min = Math.min(...vals);
    const pts = vals.map((v, i) => `${(i / Math.max(1, vals.length - 1)) * w},${h - 3 - ((v - min) / (max - min || 1)) * (h - 6)}`).join(' ');
    return `<svg class="chart" viewBox="0 0 ${w} ${h}" width="100%" height="30" preserveAspectRatio="none" aria-hidden="true"><polyline points="${pts}" fill="none" stroke="${color}" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round"/></svg>`;
}
