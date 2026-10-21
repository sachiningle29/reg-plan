// export const activityColors = {
//   ST: '#5bc0c0',
//   WO: '#c9c94a',
//   DEV: '#c661d6',
//   EXP: '#4169e1',
//   DEHIRE: '#475569',
//   HIRE: '#b45309',
//   RM: '#94a3b8',
// };

// export function colorForActivity(code) {
//   if (!code) return '#cbd5e1';
//   const key = code.toString().toUpperCase().trim();
//   return activityColors[key] || '#cbd5e1';
// }

// const ASSET_PALETTE = [
//   '#2563eb', '#059669', '#d97706', '#dc2626', '#7c3aed',
//   '#0891b2', '#db2777', '#65a30d', '#ea580c', '#4f46e5',
//   '#0ea5e9', '#a855f7',
// ];

// export function colorForAsset(name) {
//   if (!name) return '#94a3b8';
//   let hash = 0;
//   for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
//   return ASSET_PALETTE[Math.abs(hash) % ASSET_PALETTE.length];
// }

// const KNOWN_CODES = ['ST', 'WO', 'DEV', 'EXP', 'DEHIRE', 'HIRE'];

// // The single definition of "what kind of segment is this" — used by the
// // chart (for coloring/labeling) AND the dashboard (for stat counting),
// // so the two can never disagree on what counts as real work vs a gap.
// export function classifyActivity(row) {
//   const raw = (row.type || row.activity || row.status || '').toString().toUpperCase().trim();
//   if (!raw) return 'RM';
//   return KNOWN_CODES.includes(raw) ? raw : 'RM';
// }

// export function detectHireStatus(row) {
//   const fields = [row.platform, row.well_name, row.type, row.activity, row.status];
//   for (const f of fields) {
//     const v = f && f.toString().toUpperCase().trim();
//     if (v === 'DEHIRE' || v === 'HIRE') return v;
//   }
//   return null;
// }



export const activityColors = {
  ST: '#5bc0c0',
  WO: '#c9c94a',
  DEV: '#c661d6',
  EXP: '#4169e1',
  DRILLING: '#fb923c',   // new: orange
  'CAP REP': '#fb7185',  // new: coral
  OOC: '#6fcf97',        // new: green
  DEHIRE: '#475569',
  HIRE: '#b45309',
  RM: '#94a3b8',
};

export function colorForActivity(code) {
  if (!code) return '#cbd5e1';
  const key = code.toString().toUpperCase().trim();
  return activityColors[key] || '#cbd5e1';
}

const ASSET_PALETTE = [
  '#2563eb', '#059669', '#d97706', '#dc2626', '#7c3aed',
  '#0891b2', '#db2777', '#65a30d', '#ea580c', '#4f46e5',
  '#0ea5e9', '#a855f7',
];

export function colorForAsset(name) {
  if (!name) return '#94a3b8';
  let hash = 0;
  for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
  return ASSET_PALETTE[Math.abs(hash) % ASSET_PALETTE.length];
}

// Uppercase, because classifyActivity() uppercases the value before comparing.
const KNOWN_CODES = ['ST', 'WO', 'DEV', 'EXP', 'DRILLING', 'CAP REP', 'OOC', 'DEHIRE', 'HIRE'];

// The single definition of "what kind of segment is this" — used by the
// chart (for coloring/labeling) AND the dashboard (for stat counting),
// so the two can never disagree on what counts as real work vs a gap.
export function classifyActivity(row) {
  const raw = (row.type || row.activity || row.status || '').toString().toUpperCase().trim();
  if (!raw) return 'RM';
  return KNOWN_CODES.includes(raw) ? raw : 'RM';
}

export function detectHireStatus(row) {
  const fields = [row.platform, row.well_name, row.type, row.activity, row.status];
  for (const f of fields) {
    const v = f && f.toString().toUpperCase().trim();
    if (v === 'DEHIRE' || v === 'HIRE') return v;
  }
  return null;
}
