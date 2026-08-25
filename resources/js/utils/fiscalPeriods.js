// Oil & gas fiscal year: April 1 – March 31
function fiscalQuarterInfo(date) {
  const y = date.getFullYear();
  const m = date.getMonth(); // 0 = Jan

  const shifted = (m + 9) % 12; // April becomes month 0
  const quarter = Math.floor(shifted / 3) + 1; // 1..4
  const fyStartYear = m >= 3 ? y : y - 1;
  const qStartMonth = [3, 6, 9, 0][quarter - 1];
  const qStartYear = quarter === 4 ? fyStartYear + 1 : fyStartYear;

  const from = new Date(qStartYear, qStartMonth, 1);
  const to = new Date(from.getFullYear(), from.getMonth() + 3, 0, 23, 59, 59);

  return {
    label: `FY${fyStartYear}-${(fyStartYear + 1).toString().slice(-2)} Q${quarter}`,
    from,
    to,
  };
}

export function quartersBetween(minDate, maxDate) {
  const seen = new Map();
  const cur = new Date(minDate.getFullYear(), minDate.getMonth(), 1);
  const end = new Date(maxDate.getFullYear(), maxDate.getMonth(), 1);

  while (cur <= end) {
    const info = fiscalQuarterInfo(cur);
    if (!seen.has(info.label)) seen.set(info.label, info);
    cur.setMonth(cur.getMonth() + 1);
  }
  return [...seen.values()].sort((a, b) => a.from - b.from);
}

export function monthsBetween(minDate, maxDate) {
  const months = [];
  const cur = new Date(minDate.getFullYear(), minDate.getMonth(), 1);
  const end = new Date(maxDate.getFullYear(), maxDate.getMonth(), 1);

  while (cur <= end) {
    months.push({
      label: cur.toLocaleDateString('en-GB', { month: 'short', year: 'numeric' }),
      from: new Date(cur.getFullYear(), cur.getMonth(), 1),
      to: new Date(cur.getFullYear(), cur.getMonth() + 1, 0, 23, 59, 59),
    });
    cur.setMonth(cur.getMonth() + 1);
  }
  return months;
}
