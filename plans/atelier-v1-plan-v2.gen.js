// Générateur du plan 2D coté — Atelier V1 "Style Tesla" — indice V2
// Sortie : HTML autonome (SVG statique inline), unités saisies en mètres.
const fs = require('fs');

const S = 34;                       // px par mètre
const M = { l: 104, t: 76, r: 96, b: 136 };
const W = 18.32, H = 24.62, T = 0.30;   // intérieur + épaisseur mur

const X = m => +(M.l + m * S).toFixed(2);
const Y = m => +(M.t + m * S).toFixed(2);
const P = m => +(m * S).toFixed(2);

const svgW = Math.ceil(M.l + W * S + M.r);
const svgH = Math.ceil(M.t + H * S + M.b);

const out = [];
const add = s => out.push(s);
const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

// ---------- primitives ----------
const rect = (x, y, w, h, attrs = '') =>
  `<rect x="${X(x)}" y="${Y(y)}" width="${P(w)}" height="${P(h)}" ${attrs}/>`;
const line = (x1, y1, x2, y2, attrs = '') =>
  `<line x1="${X(x1)}" y1="${Y(y1)}" x2="${X(x2)}" y2="${Y(y2)}" ${attrs}/>`;
const txt = (x, y, s, attrs = '') =>
  `<text x="${X(x)}" y="${Y(y)}" ${attrs}>${esc(s)}</text>`;

// ---------- données ----------
const ZONES = [
  { id: 'cabine',  x: 0.40,  y: 0.40,  w: 7.20, h: 4.10, kind: 'built' },
  { id: 'grptech', x: 7.60,  y: 0.40,  w: 1.45, h: 4.10, kind: 'tech'  },
  { id: 'prepa',   x: 9.85,  y: 0.40,  w: 8.07, h: 3.66, kind: 'open'  },
  { id: 'ciseaux', x: 7.40,  y: 8.20,  w: 5.50, h: 5.00, kind: 'open'  },
  { id: 'deuxcol', x: 0.90,  y: 14.75, w: 6.00, h: 3.50, kind: 'open'  },
  { id: 'vestiaire', x: 15.72, y: 16.60, w: 2.00, h: 2.00, kind: 'built' },
  { id: 'pneus',   x: 13.72, y: 18.60, w: 4.00, h: 2.875, kind: 'open' },
];
const Z = id => ZONES.find(z => z.id === id);

// zone libérée par la suppression du stockage peinture fermé
const LIB = { x: 15.40, y: 4.80, w: 2.52, h: 2.60 };

// pont ciseaux Weber EXPERT SH-3500 — centre
const SC = { cx: 10.15, cy: 10.70, ow: 1.94, ol: 1.75, pw: 0.555 };
// pont 2 colonnes Weber 4 T — centre, pivoté 90°
const TP = { cx: 3.90, cy: 16.49, span: 3.38, reach: 2.95, post: 0.60 };

// ---------- défs ----------
add(`<defs>
  <pattern id="hatchLib" width="9" height="9" patternTransform="rotate(45)" patternUnits="userSpaceOnUse">
    <line x1="0" y1="0" x2="0" y2="9" stroke="var(--red)" stroke-width="1.1" opacity=".42"/>
  </pattern>
  <pattern id="hatchWall" width="6" height="6" patternTransform="rotate(45)" patternUnits="userSpaceOnUse">
    <line x1="0" y1="0" x2="0" y2="6" stroke="var(--ink)" stroke-width="4.6" opacity=".92"/>
  </pattern>
  <marker id="dimA" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
    <path d="M0 0 L10 5 L0 10 z" fill="var(--blue)"/>
  </marker>
  <marker id="flowA" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="8" markerHeight="8" orient="auto-start-reverse">
    <path d="M0 1.5 L10 5 L0 8.5 z" fill="var(--blue)"/>
  </marker>
  <marker id="redA" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="8" markerHeight="8" orient="auto-start-reverse">
    <path d="M0 1.5 L10 5 L0 8.5 z" fill="var(--red)"/>
  </marker>
</defs>`);

// ---------- dalle + murs ----------
add(rect(0, 0, W, H, 'fill="var(--floor)"'));

// trame de sol discrète (dalles 2 m)
let grid = '';
for (let i = 2; i < W; i += 2) grid += line(i, 0, i, H, 'stroke="var(--floorline)" stroke-width="1"');
for (let j = 2; j < H; j += 2) grid += line(0, j, W, j, 'stroke="var(--floorline)" stroke-width="1"');
add(`<g opacity=".55">${grid}</g>`);

// murs (couronne hachurée)
add(`<path d="M ${X(-T)} ${Y(-T)} H ${X(W + T)} V ${Y(H + T)} H ${X(-T)} Z
          M ${X(0)} ${Y(0)} V ${Y(H)} H ${X(W)} V ${Y(0)} Z"
      fill="url(#hatchWall)" fill-rule="evenodd" stroke="var(--ink)" stroke-width="1.4"/>`);

// ---------- porte sectionnelle 4,00 m (mur sud, x 2,50 → 6,50) ----------
add(rect(2.50, H, 4.00, T, 'fill="var(--paper)" stroke="none"'));
for (let k = 0; k <= 4; k++) {
  add(line(2.50, H + (T / 4) * k, 6.50, H + (T / 4) * k, 'stroke="var(--rule)" stroke-width="1"'));
}
add(rect(2.50, H, 4.00, T, 'fill="none" stroke="var(--ink)" stroke-width="1.6"'));
add(txt(4.50, H - 0.62, 'PORTE SECTIONNELLE', 'class="zt" text-anchor="middle"'));
add(txt(4.50, H - 0.22, '4,00 × 4,00 m — motorisée', 'class="zd" text-anchor="middle"'));

// ---------- zones ----------
const zoneFill = k => k === 'built' ? 'var(--built)' : k === 'tech' ? 'var(--tech)' : 'var(--zone)';
ZONES.forEach(z => {
  add(rect(z.x, z.y, z.w, z.h,
    `fill="${zoneFill(z.kind)}" stroke="var(--ink)" stroke-width="${z.kind === 'open' ? 1 : 1.6}" ${z.kind === 'open' ? 'stroke-dasharray="7 4" opacity=".95"' : ''}`));
});

// cabine : panneaux latéraux + porte de service
const cab = Z('cabine');
for (let k = 1; k < 6; k++) add(line(cab.x + (cab.w / 6) * k, cab.y + cab.h - 0.34, cab.x + (cab.w / 6) * k, cab.y + cab.h, 'stroke="var(--rule)" stroke-width="1"'));
add(rect(cab.x, cab.y + cab.h - 0.34, cab.w, 0.34, 'fill="none" stroke="var(--rule)" stroke-width="1"'));
add(txt(cab.x + cab.w / 2, cab.y + 1.62, 'CABINE DE PEINTURE', 'class="zt" text-anchor="middle"'));
add(txt(cab.x + cab.w / 2, cab.y + 2.14, 'SellerPro NS7000', 'class="zs" text-anchor="middle"'));
add(txt(cab.x + cab.w / 2, cab.y + 2.62, '7,20 × 4,10 m', 'class="zd" text-anchor="middle"'));

const gt = Z('grptech');
add(txt(gt.x + gt.w / 2, gt.y + 1.90, 'GROUPE', 'class="zs" text-anchor="middle"'));
add(txt(gt.x + gt.w / 2, gt.y + 2.32, 'TECHNIQUE', 'class="zs" text-anchor="middle"'));
add(txt(gt.x + gt.w / 2, gt.y + 2.78, 'plénum', 'class="zd" text-anchor="middle"'));

const pr = Z('prepa');
// plans de travail le long du mur nord
add(rect(pr.x, pr.y, pr.w, 0.62, 'fill="var(--built)" stroke="var(--ink)" stroke-width="1.2"'));
for (let k = 1; k < 7; k++) add(line(pr.x + (pr.w / 7) * k, pr.y, pr.x + (pr.w / 7) * k, pr.y + 0.62, 'stroke="var(--rule)" stroke-width="1"'));
add(txt(pr.x + pr.w / 2, pr.y + 1.86, 'PRÉPARATION + LABO PEINTURE', 'class="zt" text-anchor="middle"'));
add(txt(pr.x + pr.w / 2, pr.y + 2.34, 'zone ouverte — 29,5 m²', 'class="zd" text-anchor="middle"'));
// table de préparation
add(rect(pr.x + 3.10, pr.y + 2.72, 1.90, 0.80, 'fill="var(--built)" stroke="var(--ink)" stroke-width="1.1"'));

// ---------- Δ2 : zone libérée (stockage peinture fermé supprimé) ----------
add(rect(LIB.x, LIB.y, LIB.w, LIB.h, 'fill="url(#hatchLib)" stroke="var(--red)" stroke-width="1.2" stroke-dasharray="5 4"'));
add(txt(LIB.x + LIB.w / 2, LIB.y + 0.98, 'ZONE', 'class="zt2 red" text-anchor="middle"'));
add(txt(LIB.x + LIB.w / 2, LIB.y + 1.36, 'LIBÉRÉE', 'class="zt2 red" text-anchor="middle"'));
add(txt(LIB.x + LIB.w / 2, LIB.y + 1.80, 'ex-stockage', 'class="zd red" text-anchor="middle"'));
add(txt(LIB.x + LIB.w / 2, LIB.y + 2.16, 'peinture fermé', 'class="zd red" text-anchor="middle"'));

// ---------- Δ3 : pont ciseaux Weber EXPERT SH-3500 ----------
const zc = Z('ciseaux');
// gabarit véhicule
add(rect(SC.cx - 0.925, SC.cy - 2.40, 1.85, 4.80, 'fill="none" stroke="var(--rule)" stroke-width="1.1" stroke-dasharray="6 5"'));

// deux plateformes indépendantes (1 750 × 555 mm) — largeur hors tout 1 940 mm
const rwY = SC.cy - SC.ol / 2;
[SC.cx - SC.ow / 2, SC.cx + SC.ow / 2 - SC.pw].forEach(rx => {
  // rallonges basculantes
  add(rect(rx, rwY - 0.30, SC.pw, 0.30, 'fill="var(--built)" stroke="var(--ink)" stroke-width="1" stroke-dasharray="4 3"'));
  add(rect(rx, rwY + SC.ol, SC.pw, 0.30, 'fill="var(--built)" stroke="var(--ink)" stroke-width="1" stroke-dasharray="4 3"'));
  // plateforme
  add(rect(rx, rwY, SC.pw, SC.ol, 'fill="var(--equip)" stroke="var(--ink)" stroke-width="1.5"'));
  // ciseaux sous plateforme
  add(line(rx + 0.06, rwY + 0.14, rx + SC.pw - 0.06, rwY + SC.ol - 0.14, 'stroke="var(--equipline)" stroke-width="1.1"'));
  add(line(rx + SC.pw - 0.06, rwY + 0.14, rx + 0.06, rwY + SC.ol - 0.14, 'stroke="var(--equipline)" stroke-width="1.1"'));
});
// châssis de liaison
add(rect(SC.cx - SC.ow / 2, SC.cy - 0.16, SC.ow, 0.32, 'fill="var(--equip)" stroke="var(--ink)" stroke-width="1.2"'));
// coffret hydraulique déporté
add(rect(12.15, SC.cy - 0.225, 0.50, 0.45, 'fill="var(--equip)" stroke="var(--ink)" stroke-width="1.5"'));
add(`<path d="M ${X(SC.cx + SC.ow / 2)} ${Y(SC.cy)} H ${X(12.15)}" stroke="var(--ink)" stroke-width="1.1" stroke-dasharray="3 3" fill="none"/>`);
add(`<path d="M ${X(12.65)} ${Y(SC.cy)} H ${X(13.34)}" stroke="var(--rule)" stroke-width="1"/>`);
add(txt(13.46, SC.cy - 0.10, 'COFFRET HYDRAULIQUE', 'class="zd" text-anchor="start"'));
add(txt(13.46, SC.cy + 0.30, 'déporté — 400 V tri', 'class="zd" text-anchor="start"'));
// cotes machine
add(`<path d="M ${X(SC.cx - SC.ow / 2)} ${Y(rwY - 0.62)} H ${X(SC.cx + SC.ow / 2)}" stroke="var(--blue)" stroke-width="1.4" marker-start="url(#dimA)" marker-end="url(#dimA)"/>`);
add(txt(SC.cx, rwY - 0.78, '1,94 m', 'class="dim" text-anchor="middle"'));
add(txt(SC.cx, SC.cy + 2.16, 'gabarit VL 4,80 × 1,85 m', 'class="zd" text-anchor="middle"'));
// cartouche de zone, sous la zone
add(txt(zc.x + zc.w / 2, zc.y + zc.h + 0.52, 'PONT CISEAUX WEBER EXPERT SH-3500', 'class="zt" text-anchor="middle"'));
add(txt(zc.x + zc.w / 2, zc.y + zc.h + 0.94, '2 plateformes 1 750 × 555 mm — 3 500 kg — levée 950 mm', 'class="zd" text-anchor="middle"'));

// ---------- Δ1 : pont 2 colonnes Weber 4 T — pivoté 90° ----------
const zd = Z('deuxcol');
// gabarit véhicule, axe est-ouest
add(rect(TP.cx - 2.40, TP.cy - 0.925, 4.80, 1.85, 'fill="none" stroke="var(--rule)" stroke-width="1.1" stroke-dasharray="6 5"'));
const postN = TP.cy - TP.span / 2;
const postS = TP.cy + TP.span / 2 - TP.post;
[postN, postS].forEach(py => {
  add(rect(TP.cx - TP.post / 2, py, TP.post, TP.post, 'fill="var(--equip)" stroke="var(--ink)" stroke-width="1.6"'));
});
// bras de levage (4)
const armY1 = postN + TP.post, armY2 = postS;
[[armY1, 0.60], [armY2, -0.60]].forEach(([ay, dy]) => {
  [-1, 1].forEach(sx => {
    add(`<path d="M ${X(TP.cx)} ${Y(ay)} L ${X(TP.cx + sx * 1.475)} ${Y(ay + dy)}" stroke="var(--equipline)" stroke-width="3" stroke-linecap="round" fill="none"/>`);
  });
});
// liaison au sol entre colonnes
add(line(TP.cx, postN + TP.post, TP.cx, postS, 'stroke="var(--equipline)" stroke-width="1" stroke-dasharray="4 4"'));
// cotes machine
add(`<path d="M ${X(TP.cx - 2.85)} ${Y(postN)} V ${Y(postS + TP.post)}" stroke="var(--blue)" stroke-width="1.4" marker-start="url(#dimA)" marker-end="url(#dimA)"/>`);
add(`<text x="${X(TP.cx - 3.28)}" y="${Y(TP.cy)}" class="dim" text-anchor="middle" transform="rotate(-90 ${X(TP.cx - 3.28)} ${Y(TP.cy)})">3,38 m</text>`);
add(`<path d="M ${X(TP.cx - TP.reach / 2)} ${Y(postS + TP.post + 0.46)} H ${X(TP.cx + TP.reach / 2)}" stroke="var(--blue)" stroke-width="1.4" marker-start="url(#dimA)" marker-end="url(#dimA)"/>`);
add(txt(TP.cx, postS + TP.post + 0.90, '2,95 m', 'class="dim" text-anchor="middle"'));
// cartouche de zone, sous la zone
add(txt(zd.x + zd.w / 2, zd.y + zd.h + 1.52, 'PONT 2 COLONNES WEBER 4 T', 'class="zt" text-anchor="middle"'));
add(txt(zd.x + zd.w / 2, zd.y + zd.h + 1.94, 'pivoté 90° — 3,38 × 2,95 m — 21,0 m²', 'class="zd" text-anchor="middle"'));

// glyphe de rotation
(() => {
  const cx = X(9.10), cy = Y(16.49), r = P(0.78);
  const pt = a => [cx + r * Math.cos(a * Math.PI / 180), cy + r * Math.sin(a * Math.PI / 180)];
  const [ax, ay] = pt(200), [bx, by] = pt(340);
  add(`<path d="M ${ax.toFixed(2)} ${ay.toFixed(2)} A ${r} ${r} 0 1 1 ${bx.toFixed(2)} ${by.toFixed(2)}"
        fill="none" stroke="var(--red)" stroke-width="2" marker-end="url(#redA)"/>`);
  add(`<text x="${cx}" y="${cy + 5}" class="zt red" text-anchor="middle">90°</text>`);
  add(`<text x="${cx}" y="${cy + r + 20}" class="zd red" text-anchor="middle">rotation</text>`);
})();

// ---------- vestiaire / poste pneus ----------
const ve = Z('vestiaire');
add(txt(ve.x + ve.w / 2, ve.y + 0.88, 'VESTIAIRE', 'class="zt2" text-anchor="middle"'));
add(txt(ve.x + ve.w / 2, ve.y + 1.24, '4,0 m²', 'class="zd" text-anchor="middle"'));
add(rect(ve.x + 0.12, ve.y + 1.52, 1.76, 0.30, 'fill="var(--equip)" stroke="var(--ink)" stroke-width="1"'));

const pn = Z('pneus');
add(txt(pn.x + 1.05, pn.y + 0.68, 'POSTE PNEUS', 'class="zt" text-anchor="start"'));
add(txt(pn.x + pn.w / 2, pn.y + 1.22, 'démonte-pneus + équilibreuse', 'class="zd" text-anchor="middle"'));
add(rect(pn.x + 0.34, pn.y + 1.56, 1.00, 1.00, 'fill="var(--equip)" stroke="var(--ink)" stroke-width="1.2"'));
add(rect(pn.x + 1.72, pn.y + 1.56, 0.90, 1.00, 'fill="var(--equip)" stroke="var(--ink)" stroke-width="1.2"'));
add(rect(pn.x + 3.00, pn.y + 1.50, 0.72, 1.10, 'fill="var(--built)" stroke="var(--ink)" stroke-width="1.2"'));

// ---------- arrivées fluides ----------
add(`<path d="M ${X(W + 0.95)} ${Y(1.30)} H ${X(W - 0.25)}" stroke="var(--blue)" stroke-width="1.8" marker-end="url(#flowA)"/>`);
add(`<path d="M ${X(W + 0.95)} ${Y(2.10)} H ${X(W - 0.25)}" stroke="var(--blue)" stroke-width="1.8" marker-end="url(#flowA)"/>`);
add(`<text x="${X(W + 1.15)}" y="${Y(1.16)}" class="dim" text-anchor="start">EAU</text>`);
add(`<text x="${X(W + 1.15)}" y="${Y(1.96)}" class="dim" text-anchor="start">ÉLEC.</text>`);
add(`<text x="${X(W + 1.15)}" y="${Y(2.44)}" class="dim" text-anchor="start">400 V TRI</text>`);

// ---------- repères numérotés ----------
const MARKS = [
  [1, cab.x + 0.72, cab.y + 0.78],
  [4, pr.x + 0.62, pr.y + 1.42],
  [2, zc.x + 0.62, zc.y + 0.72],
  [3, zd.x + 0.55, zd.y + 3.00],
  [5, pn.x + 0.55, pn.y + 0.55],
];
MARKS.forEach(([n, x, y]) => {
  add(`<circle cx="${X(x)}" cy="${Y(y)}" r="${P(0.42)}" fill="var(--red)" stroke="var(--paper)" stroke-width="1.6"/>`);
  add(`<text x="${X(x)}" y="${Y(y) + 5}" class="mark" text-anchor="middle">${n}</text>`);
});

// ---------- repères de révision Δ ----------
const REV = [
  ['1', zd.x - 0.30, zd.y - 0.30, zd.w + 0.60, zd.h + 0.60, true],
  ['2', LIB.x - 0.28, LIB.y - 0.28, LIB.w + 0.56, LIB.h + 0.56],
  ['3', zc.x - 0.30, zc.y - 0.30, zc.w + 0.60, zc.h + 0.60],
];
REV.forEach(([n, x, y, w, h, below]) => {
  add(`<rect x="${X(x)}" y="${Y(y)}" width="${P(w)}" height="${P(h)}" rx="10" fill="none" stroke="var(--red)" stroke-width="1.6" stroke-dasharray="10 6" opacity=".85"/>`);
  const ty = below ? Y(y + h) + 1 : Y(y) - 20;
  add(`<rect x="${X(x + w) - 34}" y="${ty}" width="34" height="19" rx="3" fill="var(--red)"/>`);
  add(`<text x="${X(x + w) - 17}" y="${ty + 14}" class="mark" text-anchor="middle">Δ${n}</text>`);
});

// ---------- cotation ----------
const dimTop = -1.32, dimL1 = H + 0.86, dimL2 = H + 1.92, dimLeft = -1.42;
// hors-tout haut
add(`<path d="M ${X(0)} ${Y(dimTop)} H ${X(W)}" stroke="var(--blue)" stroke-width="1.5" marker-start="url(#dimA)" marker-end="url(#dimA)"/>`);
add(line(0, dimTop - 0.24, 0, -T, 'stroke="var(--blue)" stroke-width=".8" stroke-dasharray="3 3"'));
add(line(W, dimTop - 0.24, W, -T, 'stroke="var(--blue)" stroke-width=".8" stroke-dasharray="3 3"'));
add(txt(W / 2, dimTop - 0.26, '18,32 m', 'class="dimb" text-anchor="middle"'));
// hors-tout gauche
add(`<path d="M ${X(dimLeft)} ${Y(0)} V ${Y(H)}" stroke="var(--blue)" stroke-width="1.5" marker-start="url(#dimA)" marker-end="url(#dimA)"/>`);
add(`<text x="${X(dimLeft) - 9}" y="${Y(H / 2)}" class="dimb" text-anchor="middle" transform="rotate(-90 ${X(dimLeft) - 9} ${Y(H / 2)})">24,62 m</text>`);
// chaîne de cotes basse
const CHAIN = [2.50, 4.00, 2.26, 2.50, 2.50, 2.50, 2.06];
let cx0 = 0;
CHAIN.forEach(seg => {
  const a = cx0, b = cx0 + seg;
  add(`<path d="M ${X(a)} ${Y(dimL1)} H ${X(b)}" stroke="var(--blue)" stroke-width="1.3" marker-start="url(#dimA)" marker-end="url(#dimA)"/>`);
  add(line(a, H + T, a, dimL1, 'stroke="var(--blue)" stroke-width=".8" stroke-dasharray="3 3"'));
  add(txt((a + b) / 2, dimL1 - 0.20, seg.toFixed(2).replace('.', ',') + ' m', 'class="dim" text-anchor="middle"'));
  cx0 = b;
});
add(line(W, H + T, W, dimL1, 'stroke="var(--blue)" stroke-width=".8" stroke-dasharray="3 3"'));
add(`<path d="M ${X(0)} ${Y(dimL2)} H ${X(W)}" stroke="var(--blue)" stroke-width="1.5" marker-start="url(#dimA)" marker-end="url(#dimA)"/>`);
add(txt(W / 2, dimL2 - 0.22, '18,32 m', 'class="dimb" text-anchor="middle"'));

// ---------- échelle graphique ----------
(() => {
  const bx = X(11.30), by = Y(H + 2.62), u = P(1);
  let g = '';
  for (let i = 0; i < 5; i++) {
    g += `<rect x="${bx + i * u}" y="${by}" width="${u}" height="9" fill="${i % 2 ? 'var(--paper)' : 'var(--ink)'}" stroke="var(--ink)" stroke-width="1"/>`;
  }
  for (let i = 0; i <= 5; i++) g += `<text x="${bx + i * u}" y="${by - 6}" class="dim" text-anchor="middle">${i}</text>`;
  g += `<text x="${bx + 5 * u + 10}" y="${by + 9}" class="dim" text-anchor="start">m — échelle 1:100 (A3)</text>`;
  add(g);
})();

const SVG = `<svg class="plan" viewBox="0 0 ${svgW} ${svgH}" role="img"
  aria-label="Plan 2D coté de l'atelier, indice V2 : pont 2 colonnes pivoté de 90°, stockage peinture fermé supprimé, pont ciseaux remplacé par un Weber EXPERT SH-3500 à deux plateformes.">
${out.join('\n')}
</svg>`;

// ---------------------------------------------------------------- page
const html = `<title>Atelier V1 — Plan 2D coté, indice V2</title>
<style>
  :root {
    --paper:#E9ECE7; --ink:#15181B; --ink-2:#4A524E; --rule:#98A199;
    --floor:#D5DAD3; --floorline:#BFC6BE; --zone:#E2E6E0; --built:#C3CAC2; --tech:#CDD3CB;
    --equip:#8D958E; --equipline:#3A423D;
    --red:#B3352C; --blue:#2B5F8C; --green:#3E7A52;
    --card:#F3F5F1; --card-line:#D2D8CF;
    --font-plate:"Arial Narrow","Helvetica Neue Condensed",Impact,"Haettenschweiler",Helvetica,Arial,sans-serif;
    --font-body:ui-sans-serif,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
    --font-data:ui-monospace,"SF Mono",SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace;
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --paper:#131719; --ink:#E4E8E2; --ink-2:#9AA49E; --rule:#5A6560;
      --floor:#232A2C; --floorline:#2E3639; --zone:#1C2224; --built:#39423F; --tech:#333C39;
      --equip:#5D6865; --equipline:#C9D2CC;
      --red:#E06553; --blue:#6BA3D4; --green:#6BAE81;
      --card:#191E20; --card-line:#2E3639;
    }
  }
  :root[data-theme="dark"] {
    --paper:#131719; --ink:#E4E8E2; --ink-2:#9AA49E; --rule:#5A6560;
    --floor:#232A2C; --floorline:#2E3639; --zone:#1C2224; --built:#39423F; --tech:#333C39;
    --equip:#5D6865; --equipline:#C9D2CC;
    --red:#E06553; --blue:#6BA3D4; --green:#6BAE81;
    --card:#191E20; --card-line:#2E3639;
  }
  :root[data-theme="light"] {
    --paper:#E9ECE7; --ink:#15181B; --ink-2:#4A524E; --rule:#98A199;
    --floor:#D5DAD3; --floorline:#BFC6BE; --zone:#E2E6E0; --built:#C3CAC2; --tech:#CDD3CB;
    --equip:#8D958E; --equipline:#3A423D;
    --red:#B3352C; --blue:#2B5F8C; --green:#3E7A52;
    --card:#F3F5F1; --card-line:#D2D8CF;
  }

  body { background:var(--paper); color:var(--ink); font-family:var(--font-body);
         font-size:15px; line-height:1.55; margin:0; padding:clamp(14px,3vw,34px); }
  .sheet { max-width:1280px; margin:0 auto; border:1.5px solid var(--ink);
           background:var(--paper); padding:clamp(12px,2vw,20px);
           display:flex; flex-direction:column; gap:clamp(16px,2.4vw,26px); }

  /* --- cartouche titre --- */
  .plate { display:flex; flex-wrap:wrap; align-items:flex-end; gap:14px 26px;
           border-bottom:1.5px solid var(--ink); padding-bottom:12px; }
  .plate h1 { font-family:var(--font-plate); font-weight:700; font-size:clamp(24px,4.4vw,40px);
              letter-spacing:.055em; text-transform:uppercase; margin:0; line-height:1;
              text-wrap:balance; }
  .plate .sub { font-family:var(--font-plate); text-transform:uppercase; letter-spacing:.16em;
                font-size:12px; color:var(--ink-2); margin:6px 0 0; }
  .plate .meta { margin-left:auto; display:flex; gap:22px; font-family:var(--font-data);
                 font-size:11.5px; color:var(--ink-2); }
  .plate .meta b { display:block; color:var(--ink); font-size:13px; font-variant-numeric:tabular-nums; }
  .rev-badge { font-family:var(--font-plate); text-transform:uppercase; letter-spacing:.1em;
               font-size:12px; color:var(--paper); background:var(--red);
               padding:4px 11px; align-self:center; }

  /* --- corps --- */
  .body { display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:clamp(16px,2.4vw,28px); align-items:start; }
  @media (max-width:940px) { .body { grid-template-columns:minmax(0,1fr); } }

  .frame { border:1px solid var(--card-line); background:var(--card); padding:8px;
           overflow-x:auto; }
  svg.plan { display:block; width:100%; min-width:660px; height:auto; }
  svg.plan text { font-family:var(--font-body); fill:var(--ink); }
  svg.plan .zt  { font-size:11.5px; font-weight:700; letter-spacing:.035em; text-transform:uppercase; }
  svg.plan .zt2 { font-size:10px; font-weight:700; letter-spacing:.02em; text-transform:uppercase; }
  svg.plan .zs  { font-size:10.5px; }
  svg.plan .zd  { font-size:9.5px; fill:var(--ink-2); }
  svg.plan .red { fill:var(--red); }
  svg.plan .dim  { font-family:var(--font-data); font-size:9.5px; fill:var(--blue); font-variant-numeric:tabular-nums; }
  svg.plan .dimb { font-family:var(--font-data); font-size:12px; font-weight:700; fill:var(--blue); font-variant-numeric:tabular-nums; }
  svg.plan .mark { font-family:var(--font-data); font-size:12px; font-weight:700; fill:#fff; }

  /* --- panneaux --- */
  aside { display:flex; flex-direction:column; gap:16px; }
  .card { border:1px solid var(--card-line); background:var(--card); padding:14px 15px; }
  .card h2 { font-family:var(--font-plate); text-transform:uppercase; letter-spacing:.14em;
             font-size:11.5px; margin:0 0 11px; color:var(--ink-2);
             border-bottom:1px solid var(--card-line); padding-bottom:7px; }
  .lg { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:9px; }
  .lg li { display:grid; grid-template-columns:22px minmax(0,1fr); gap:9px; align-items:start; font-size:13px; }
  .pin { width:20px; height:20px; border-radius:50%; background:var(--red); color:#fff;
         font-family:var(--font-data); font-size:11px; font-weight:700;
         display:grid; place-items:center; }
  .pin.none { background:transparent; border:1px solid var(--rule); color:var(--ink-2); }
  .lg small { display:block; color:var(--ink-2); font-size:11.5px; }

  table { width:100%; border-collapse:collapse; font-size:13px; }
  table th, table td { text-align:left; padding:5px 0; border-bottom:1px solid var(--card-line); }
  table th { font-weight:600; color:var(--ink-2); font-size:11px; text-transform:uppercase; letter-spacing:.08em; }
  table td.n { text-align:right; font-family:var(--font-data); font-variant-numeric:tabular-nums; white-space:nowrap; }
  tr.tot td { border-bottom:none; border-top:1.5px solid var(--ink); font-weight:700; padding-top:8px; }
  tr.gone td { color:var(--ink-2); text-decoration:line-through; text-decoration-color:var(--red); }

  /* --- révisions --- */
  .rev { border:1px solid var(--card-line); background:var(--card); }
  .rev header { display:flex; align-items:baseline; gap:12px; padding:12px 15px 10px;
                border-bottom:1px solid var(--card-line); }
  .rev header h2 { margin:0; font-family:var(--font-plate); text-transform:uppercase;
                   letter-spacing:.14em; font-size:11.5px; color:var(--ink-2); }
  .rev header span { font-family:var(--font-data); font-size:11.5px; color:var(--ink-2); }
  .revlist { list-style:none; margin:0; padding:0; }
  .revlist li { display:grid; grid-template-columns:38px minmax(0,1fr); gap:14px;
                padding:13px 15px; border-bottom:1px solid var(--card-line); }
  .revlist li:last-child { border-bottom:none; }
  .tri { width:34px; height:20px; display:grid; place-items:center; border-radius:3px;
         font-family:var(--font-data); font-size:12px; font-weight:700; color:#fff;
         background:var(--red); }
  .revlist h3 { margin:0 0 3px; font-size:14px; font-weight:650; }
  .revlist p { margin:0; font-size:13px; color:var(--ink-2); }

  .cols { display:grid; grid-template-columns:repeat(auto-fit,minmax(268px,1fr)); gap:16px; align-items:start; }
  dl.spec { margin:0; display:grid; grid-template-columns:auto 1fr; gap:5px 14px; font-size:13px; }
  dl.spec dt { color:var(--ink-2); }
  dl.spec dd { margin:0; font-family:var(--font-data); font-variant-numeric:tabular-nums; text-align:right; }
  ul.notes { margin:0; padding-left:17px; display:flex; flex-direction:column; gap:9px; font-size:13px; }
  ul.notes b { font-weight:650; }
  .flag { color:var(--red); font-weight:650; }
  footer { font-family:var(--font-data); font-size:11px; color:var(--ink-2);
           border-top:1px solid var(--card-line); padding-top:11px; display:flex;
           flex-wrap:wrap; gap:6px 20px; }
  footer a { color:var(--blue); }
  a:focus-visible, [tabindex]:focus-visible { outline:2px solid var(--blue); outline-offset:2px; }
</style>

<div class="sheet">

  <div class="plate">
    <div>
      <h1>Atelier V1 — Style Tesla</h1>
      <p class="sub">Plan 2D coté &amp; implantation · 18,32 × 24,62 m</p>
    </div>
    <span class="rev-badge">Indice V2</span>
    <div class="meta">
      <div>Échelle<b>1:100</b></div>
      <div>Surfaces utiles<b>129,0 m²</b></div>
      <div>Hauteur libre<b>4,50 m</b></div>
      <div>Révisions<b>3</b></div>
    </div>
  </div>

  <div class="rev">
    <header><h2>Registre des modifications</h2><span>V1 → V2</span></header>
    <ul class="revlist">
      <li>
        <span class="tri">&#916;1</span>
        <div>
          <h3>Pont 2 colonnes pivoté de 90°</h3>
          <p>Les colonnes passent de l'axe est-ouest à l'axe nord-sud : le véhicule se présente désormais transversalement. Emprise machine 3,38 m (colonne à colonne) × 2,95 m (bras déployés), inchangée en surface — zone maintenue à 21,0 m².</p>
        </div>
      </li>
      <li>
        <span class="tri">&#916;2</span>
        <div>
          <h3>Stockage peinture fermé supprimé</h3>
          <p>Le local fermé de la façade orientale est déposé. 6,5 m² rendus à la circulation périphérique et à l'accès direct depuis la zone préparation. Aucun impact sur le tableau des surfaces : ce local n'était pas compté en V1.</p>
        </div>
      </li>
      <li>
        <span class="tri">&#916;3</span>
        <div>
          <h3>Pont ciseaux corrigé — Weber <span style="font-family:var(--font-data)">EXPERT SH-3500</span></h3>
          <p>Le symbole V1 (plateforme pleine unique) est remplacé par le matériel réel : deux plateformes indépendantes de 1 750 × 555 mm, largeur hors tout 1 940 mm, rallonges basculantes, et coffret hydraulique déporté relié par flexibles.</p>
        </div>
      </li>
    </ul>
  </div>

  <div class="body">
    <div class="frame">
${SVG}
    </div>

    <aside>
      <section class="card">
        <h2>Légende des zones</h2>
        <ul class="lg">
          <li><span class="pin">1</span><div>Cabine de peinture SellerPro NS7000<small>7,20 × 4,10 m + groupe technique</small></div></li>
          <li><span class="pin">2</span><div>Pont ciseaux Weber EXPERT SH-3500<small class="flag">modifié en V2</small></div></li>
          <li><span class="pin">3</span><div>Pont 2 colonnes Weber 4 T<small class="flag">pivoté 90° en V2</small></div></li>
          <li><span class="pin">4</span><div>Préparation + labo peinture<small>zone ouverte</small></div></li>
          <li><span class="pin">5</span><div>Poste pneus<small>démonte-pneus + équilibreuse + rack</small></div></li>
          <li><span class="pin none">·</span><div>Vestiaire mini</div></li>
        </ul>
      </section>

      <section class="card">
        <h2>Surfaces utiles</h2>
        <table>
          <tbody>
            <tr><td>Cabine de peinture</td><td class="n">35,5 m²</td></tr>
            <tr><td>Préparation + labo</td><td class="n">29,5 m²</td></tr>
            <tr><td>Pont ciseaux</td><td class="n">27,5 m²</td></tr>
            <tr><td>Pont 2 colonnes</td><td class="n">21,0 m²</td></tr>
            <tr><td>Poste pneus</td><td class="n">11,5 m²</td></tr>
            <tr><td>Vestiaire mini</td><td class="n">4,0 m²</td></tr>
            <tr class="gone"><td>Stockage peinture fermé</td><td class="n">6,5 m²</td></tr>
            <tr class="tot"><td>Total utile</td><td class="n">129,0 m²</td></tr>
          </tbody>
        </table>
        <p style="margin:9px 0 0;font-size:12px;color:var(--ink-2)">
          Le cartouche V1 affichait <b>128,0 m²</b> : la somme des postes listés fait <b>129,0 m²</b>.
          Total corrigé ici. Emprise bâtie brute : 451,1 m².
        </p>
      </section>

      <section class="card">
        <h2>Circulations mesurées</h2>
        <dl class="spec">
          <dt>Bande nord (cabine ↔ ciseaux)</dt><dd>3,70 m</dd>
          <dt>Couloir ouest (le long ciseaux)</dt><dd>7,00 m</dd>
          <dt>Couloir est (le long ciseaux)</dt><dd>5,42 m</dd>
          <dt>Dégagement sud du 2 colonnes</dt><dd>6,72 m</dd>
          <dt>Dégagement mini autour des ponts</dt><dd>1,50 m</dd>
          <dt>Hauteur libre sous plafond</dt><dd>4,50 m</dd>
        </dl>
      </section>
    </aside>
  </div>

  <div class="cols">
    <section class="card">
      <h2>Pont ciseaux — Weber EXPERT SH-3500</h2>
      <dl class="spec">
        <dt>Capacité</dt><dd>3 500 kg</dd>
        <dt>Plateformes</dt><dd>2 × 1 750 × 555 mm</dd>
        <dt>Largeur hors tout</dt><dd>1 940 mm</dd>
        <dt>Hauteur mini au sol</dt><dd>105 mm</dd>
        <dt>Levée maxi</dt><dd>950 mm</dd>
        <dt>Vérins</dt><dd>4 hydrauliques</dd>
        <dt>Moteur</dt><dd>2,2 kW</dd>
        <dt>Alimentation</dt><dd>400 V tri / 50 Hz / 16 A</dd>
        <dt>Masse</dt><dd>780 kg</dd>
        <dt>Levage</dt><dd>4 tampons sous bas de caisse</dd>
      </dl>
    </section>

    <section class="card">
      <h2>Pont 2 colonnes — Weber 4 T</h2>
      <dl class="spec">
        <dt>Capacité</dt><dd>4 000 kg</dd>
        <dt>Levée maxi</dt><dd>1 900 mm</dd>
        <dt>Largeur hors tout</dt><dd>3 380 mm</dd>
        <dt>Profondeur (bras)</dt><dd>2 950 mm</dd>
        <dt>Hauteur</dt><dd>3 150 mm</dd>
        <dt>Moteur</dt><dd>2 200 W</dd>
        <dt>Temps de montée</dt><dd>≈ 50 s</dd>
        <dt>Sous plafond 4,50 m</dt><dd style="color:var(--green)">1,35 m de garde</dd>
      </dl>
    </section>

    <section class="card">
      <h2>Points à valider</h2>
      <ul class="notes">
        <li><b class="flag">Le SH-3500 est un pont bas profil : 950 mm de levée.</b> Il convient à la carrosserie, la préparation, le nettoyage et les roues, mais <b>ne permet pas de travailler debout sous le véhicule</b>. Si de la mécanique sous caisse est prévue sur ce poste, il faut basculer sur un ciseaux grande levée (série DSH) ou reporter ces opérations sur le 2 colonnes.</li>
        <li><b>Coffret hydraulique déporté</b> : prévoir l'alimentation 400 V tri 16 A et le cheminement des flexibles à ≈ 1,5 m du pont, en caniveau ou goulotte au sol pour ne pas couper la circulation.</li>
        <li><b>Rotation du 2 colonnes</b> : l'entrée se fait par la porte sectionnelle dans l'axe nord-sud, le véhicule doit donc effectuer un quart de tour. Le dégagement libre de 6,72 m au sud de la zone le permet pour un VL ; à revérifier pour un utilitaire long.</li>
        <li><b class="flag">Suppression du stockage peinture fermé</b> : les produits inflammables restent soumis à un stockage réglementaire. L'armoire de sécurité ventilée déjà prévue doit être dimensionnée pour absorber les quantités auparavant stockées dans le local.</li>
        <li><b>Cotes du SH-3500</b> issues de la fiche produit HBM. À recroiser avec la notice d'implantation (entraxe de fixation, épaisseur et ferraillage de dalle) avant percement.</li>
      </ul>
    </section>
  </div>

  <footer>
    <span>Atelier V1 — Style Tesla · indice V2</span>
    <span>Plan 2D coté, dessiné à l'échelle 1:100</span>
    <span>Sources équipements :
      <a href="https://www.hbm-machines.com/fr/p/weber-expert-sh-3500-pont-electrique-de-35-tonnes-pour-le-nettoyage-de-voitures-et-pont-ciseaux-400-volt">Weber EXPERT SH-3500</a> ·
      <a href="https://www.hbm-machines.com/fr/p/weber-elevateur-hydraulique-a-2-colonnes-professionnel-4-tonnes">Weber 2 colonnes 4 T</a>
    </span>
  </footer>
</div>
`;

fs.writeFileSync(process.argv[2], html);
console.log('OK', process.argv[2], html.length, 'octets · svg', svgW + 'x' + svgH);
