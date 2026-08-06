export const activityColors = {
  ST: '#5bc0c0',
  WO: '#c9c94a',
  DEV: '#c661d6',
  EXP: '#4169e1',
  DEHIRE: '#9e9e9e',
  HIRE: '#9e9e9e',
};

export function colorForActivity(code) {
  if (!code) return '#94a3b8';
  const key = code.toString().toUpperCase().trim();
  return activityColors[key] || '#94a3b8';
}
