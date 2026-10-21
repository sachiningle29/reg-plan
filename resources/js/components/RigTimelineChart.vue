
<!-- working logic on 21-10-26 -->

<template>
  <div>
    <div class="chart-toolbar d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
      <div class="d-flex flex-wrap gap-2">
        <span v-for="(color, label) in legendItems" :key="label" class="legend-chip">
          <span class="legend-dot" :style="{ background: color }"></span>
          {{ label }}
        </span>
      </div>

      <div class="d-flex align-items-center gap-2">
        <div class="btn-group btn-group-sm" role="group">
          <button class="btn" :class="colorMode === 'activity' ? 'btn-primary' : 'btn-outline-secondary'" @click="colorMode = 'activity'">
            <i class="bi bi-palette-fill me-1"></i>By Activity
          </button>
          <button class="btn" :class="colorMode === 'asset' ? 'btn-primary' : 'btn-outline-secondary'" @click="colorMode = 'asset'">
            <i class="bi bi-geo-alt-fill me-1"></i>By Asset
          </button>
        </div>

        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assetSummaryModal">
          <i class="bi bi-table me-1"></i>Asset Summary
        </button>
      </div>
    </div>

    <details v-if="unpairedRigs.length" class="text-muted small mb-3">
      <summary>{{ unpairedRigs.length }} rig(s) appear in only one of Planned / Actual</summary>
      <div class="mt-1">{{ unpairedRigs.join(' · ') }}</div>
    </details>

    <div v-if="!displayRows.length" class="text-center text-muted py-5">
      <i class="bi bi-inbox fs-1 d-block mb-2"></i>
      No records to display
    </div>
    <div v-else class="chart-scroll">
      <apexchart type="rangeBar" :height="chartHeight" :options="chartOptions" :series="series" />
    </div>

    <div class="modal fade" id="assetSummaryModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-bar-chart-line-fill me-2"></i>Asset Summary</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">




          <p v-if="summaryRangeLabel" class="text-muted small mb-3">
              <i class="bi bi-calendar-range me-1"></i>Time range: {{ summaryRangeLabel }} — counts every segment that overlaps it.
            </p>






            <template v-if="isSplitView">
              <div class="mb-4">
                <span class="badge bg-primary-subtle text-primary-emphasis mb-2">
                  <i class="bi bi-calendar-check me-1"></i>Planned
                </span>
                <table class="table table-sm table-bordered align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Asset</th>
                      <th v-for="code in summaryColumns" :key="code" class="text-center">{{ code }}</th>
                      <th class="text-center">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="asset in plannedSummaryRows" :key="asset.name">
                      <td class="fw-semibold">{{ asset.name }}</td>
                      <td v-for="code in summaryColumns" :key="code" class="text-center">{{ asset.counts[code] || '' }}</td>
                      <td class="text-center fw-semibold">{{ asset.total }}</td>
                    </tr>
                    <tr v-if="!plannedSummaryRows.length">
                      <td :colspan="summaryColumns.length + 2" class="text-center text-muted py-3">No planned data</td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr class="table-light fw-semibold">
                      <td>Total</td>
                      <td v-for="code in summaryColumns" :key="code" class="text-center">{{ columnTotal(plannedSummaryRows, code) }}</td>
                      <td class="text-center">{{ grandTotalOf(plannedSummaryRows) }}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>

              <div>
                <span class="badge bg-success-subtle text-success-emphasis mb-2">
                  <i class="bi bi-check2-circle me-1"></i>Actual
                </span>
                <table class="table table-sm table-bordered align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Asset</th>
                      <th v-for="code in summaryColumns" :key="code" class="text-center">{{ code }}</th>
                      <th class="text-center">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="asset in actualSummaryRows" :key="asset.name">
                      <td class="fw-semibold">{{ asset.name }}</td>
                      <td v-for="code in summaryColumns" :key="code" class="text-center">{{ asset.counts[code] || '' }}</td>
                      <td class="text-center fw-semibold">{{ asset.total }}</td>
                    </tr>
                    <tr v-if="!actualSummaryRows.length">
                      <td :colspan="summaryColumns.length + 2" class="text-center text-muted py-3">No actual data</td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr class="table-light fw-semibold">
                      <td>Total</td>
                      <td v-for="code in summaryColumns" :key="code" class="text-center">{{ columnTotal(actualSummaryRows, code) }}</td>
                      <td class="text-center">{{ grandTotalOf(actualSummaryRows) }}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </template>

            <table v-else class="table table-sm table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Asset</th>
                  <th v-for="code in summaryColumns" :key="code" class="text-center">{{ code }}</th>
                  <th class="text-center">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="asset in summaryRows" :key="asset.name">
                  <td class="fw-semibold">{{ asset.name }}</td>
                  <td v-for="code in summaryColumns" :key="code" class="text-center">{{ asset.counts[code] || '' }}</td>
                  <td class="text-center fw-semibold">{{ asset.total }}</td>
                </tr>
                <tr v-if="!summaryRows.length">
                  <td :colspan="summaryColumns.length + 2" class="text-center text-muted py-3">No data</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="table-light fw-semibold">
                  <td>Total</td>
                  <td v-for="code in summaryColumns" :key="code" class="text-center">{{ columnTotal(summaryRows, code) }}</td>
                  <td class="text-center">{{ grandTotalOf(summaryRows) }}</td>
                </tr>
              </tfoot>
            </table>

            <p class="text-muted small mt-3 mb-0">
              <i class="bi bi-info-circle me-1"></i>Rig-move / unclassified segments (RM) are shown on the chart but excluded from these counts.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { colorForActivity, colorForAsset, classifyActivity } from '../utils/activityColors';

const props = defineProps({
  planName: String,
  recordType: String, // 'planned' | 'actual' | 'both'
  assetFilter: { type: String, default: '' },
  viewFrom: { type: Number, default: null },
  viewTo: { type: Number, default: null },
});
const emit = defineEmits(['loaded']);

const rawRows = ref([]);
const colorMode = ref('activity');

async function load() {
  let combined = [];

  if (props.recordType === 'both') {
    const [planned, actual] = await Promise.all([
      axios.get('/api/rig-details/gantt', { params: { plan_name: props.planName, record_type: 'planned' } }),
      axios.get('/api/rig-details/gantt', { params: { plan_name: props.planName, record_type: 'actual' } }),
    ]);
    combined = [...planned.data, ...actual.data];
  } else {
    const { data } = await axios.get('/api/rig-details/gantt', {
      params: { plan_name: props.planName, record_type: props.recordType },
    });
    combined = data;
  }

  rawRows.value = combined;
  emit('loaded', rawRows.value);
}
watch(() => [props.planName, props.recordType], load, { immediate: true });

// ---------------------------------------------------------------------------
// Rig-name matching: Planned and Actual files spell the same rig differently
// ("Admarine-11 (WS)" vs "ADMARINE-11", "Aban-IV (WS)" vs "ABAN IV",
// "Admarine-IX (WS)" vs "ADMARINE 9", "DS Fortune" vs "DEEPSEA FORTUNE").
// Every name is reduced to one comparable key so both land on the same row.
// ---------------------------------------------------------------------------

// Edit these two maps when two spellings are the same rig but too different to
// match automatically. Keys/values are UPPERCASE, punctuation removed.
const RIG_TOKEN_ALIASES = { DS: 'DEEPSEA', GD: 'GREATDRILL' }; // replaces the FIRST word
const RIG_NAME_ALIASES = { CET: 'CE THORNTON' };               // replaces the WHOLE name

function romanToNumber(token) {
  if (token === '' || !/^X{0,2}(IX|IV|V?I{0,3})$/.test(token)) return null;
  const value = { I: 1, V: 5, X: 10 };
  let total = 0;
  for (let i = 0; i < token.length; i++) {
    const cur = value[token[i]];
    const next = value[token[i + 1]] || 0;
    total += cur < next ? -cur : cur;
  }
  return total;
}

function normalizeRigKey(name) {
  const cleaned = (name || '')
    .toString()
    .toUpperCase()
    .replace(/\([^)]*\)/g, ' ')   // drop "(WS)" and any other bracketed suffix
    .replace(/[^A-Z0-9]+/g, ' ')  // hyphens, dots, repeated spaces -> one space
    .trim();

  const tokens = cleaned.split(' ').filter(Boolean);

  if (tokens.length && RIG_TOKEN_ALIASES[tokens[0]]) {
    tokens[0] = RIG_TOKEN_ALIASES[tokens[0]];
  }

  // Trailing Roman numeral -> number, so "ADMARINE IX" matches "ADMARINE 9".
  const last = tokens[tokens.length - 1];
  if (tokens.length > 1 && /^[IVX]+$/.test(last)) {
    const n = romanToNumber(last);
    if (n) tokens[tokens.length - 1] = String(n);
  }

  const key = tokens.join(' ');
  return RIG_NAME_ALIASES[key] ?? key;
}

// One display spelling per rig key. Planned's spelling wins when both exist.
const rigCanonicalNames = computed(() => {
  const map = new Map();
  rawRows.value.forEach((r) => {
    const key = normalizeRigKey(r.rig_name);
    const existing = map.get(key);
    if (!existing || (r.record_type === 'planned' && existing.type !== 'planned')) {
      map.set(key, { name: r.rig_name, type: r.record_type });
    }
  });
  const result = new Map();
  map.forEach((v, k) => result.set(k, v.name));
  return result;
});

function canonicalRigName(rigName) {
  return rigCanonicalNames.value.get(normalizeRigKey(rigName)) ?? rigName ?? 'Unassigned';
}

// Rows sorted: rig (natural order, so 9 comes before 10) -> Planned above
// Actual -> chronological.
const displayRows = computed(() => {
  const rows = props.assetFilter
    ? rawRows.value.filter((r) => r.asset === props.assetFilter)
    : rawRows.value;

  return [...rows].sort((a, b) => {
    const keyA = normalizeRigKey(a.rig_name);
    const keyB = normalizeRigKey(b.rig_name);
    if (keyA !== keyB) return keyA.localeCompare(keyB, undefined, { numeric: true });

    if (props.recordType === 'both') {
      const orderA = a.record_type === 'actual' ? 1 : 0;
      const orderB = b.record_type === 'actual' ? 1 : 0;
      if (orderA !== orderB) return orderA - orderB;
    }

    return new Date(a.start_date) - new Date(b.start_date);
  });
});

function categoryFor(row) {
  const name = canonicalRigName(row.rig_name);
  if (props.recordType === 'both') {
    return `${name} • ${row.record_type === 'actual' ? 'Actual' : 'Planned'}`;
  }
  return name;
}

// Rigs that exist on only one side in "Planned & Actual" mode — shown as a
// hint so a spelling mismatch is easy to spot and add to the alias maps above.
const unpairedRigs = computed(() => {
  if (props.recordType !== 'both') return [];
  const sides = new Map();
  displayRows.value.forEach((r) => {
    const key = normalizeRigKey(r.rig_name);
    if (!sides.has(key)) sides.set(key, { name: canonicalRigName(r.rig_name), planned: false, actual: false });
    sides.get(key)[r.record_type === 'actual' ? 'actual' : 'planned'] = true;
  });
  return [...sides.values()]
    .filter((s) => s.planned !== s.actual)
    .map((s) => `${s.name} (${s.planned ? 'planned only' : 'actual only'})`)
    .sort((a, b) => a.localeCompare(b));
});

// Bar label: the well name only. Type, platform and dates are in the tooltip.
//function segmentLabel(row) {
  //if (row.well_name) return row.well_name;
  //const code = classifyActivity(row);
  //return row.platform || code;
//}



// Bar label: the well name only. Type, platform and dates are in the tooltip.
function segmentLabel(row) {
  // Rig moves read "RM" like Planned does, not the well the move was logged against.
  if (row.record_type === 'actual' && classifyActivity(row) === 'RM') return 'RM';
  if (row.well_name) return row.well_name;
  const code = classifyActivity(row);
  return row.platform || code;
}






function colorForRow(row) {
  return colorMode.value === 'asset'
    ? colorForAsset(row.asset || 'Unknown')
    : colorForActivity(classifyActivity(row));
}

const legendItems = computed(() => {
  if (colorMode.value === 'asset') {
    const assets = [...new Set(displayRows.value.map((r) => r.asset || 'Unknown'))];
    return Object.fromEntries(assets.map((a) => [a, colorForAsset(a)]));
  }
  const codes = [...new Set(displayRows.value.map(classifyActivity))];
  return Object.fromEntries(codes.map((c) => [c, colorForActivity(c)]));
});

// One line per rig (and per Planned/Actual in "both" mode) — the same layout
// Planned has always used, so Actual is easy to compare against it.
const chartHeight = computed(() => {
  const lines = new Set(displayRows.value.map(categoryFor)).size;
  return Math.max(320, lines * 44);
});

const series = computed(() => [{
  data: displayRows.value.map((row) => ({
    x: categoryFor(row),
    y: [new Date(row.start_date).getTime(), new Date(row.finish_date).getTime()],
    fillColor: colorForRow(row),
    label: segmentLabel(row),
    meta: row,
  })),
}]);

const chartOptions = computed(() => ({
  chart: { type: 'rangeBar', toolbar: { show: true }, animations: { enabled: false }, fontFamily: 'inherit' },
  plotOptions: {
    bar: { horizontal: true, barHeight: '62%', borderRadius: 3, rangeBarGroupRows: true },
  },
  xaxis: {
    type: 'datetime',
    min: props.viewFrom || undefined,
    max: props.viewTo || undefined,
    labels: { style: { fontSize: '11px' } },
  },
  yaxis: {
    labels: {
      maxWidth: 260, // stops long labels such as "Admarine-11 (WS) • Planned" being cut with "..."
      style: { fontSize: '12px', fontWeight: 600 },
    },
  },
  legend: { show: false },
  grid: { borderColor: '#eef2f7', xaxis: { lines: { show: true } } },
  states: { hover: { filter: { type: 'darken', value: 0.9 } } },
  dataLabels: {
    enabled: true,
    style: { fontSize: '10px', fontWeight: 600, colors: ['#1e293b'] },
    formatter: (val, opts) => opts.w.config.series[opts.seriesIndex].data[opts.dataPointIndex].label || '',
  },
  tooltip: {
    custom: ({ seriesIndex, dataPointIndex, w }) => {
      const r = w.config.series[seriesIndex].data[dataPointIndex].meta;
      return `<div class="p-2">
        <strong>${r.rig_name}</strong> <span class="text-muted">(${r.record_type || ''})</span><br/>
        Well: ${r.well_name || '—'} | Asset: ${r.asset || '—'}<br/>
        ${new Date(r.start_date).toLocaleDateString()} → ${new Date(r.finish_date).toLocaleDateString()}
      </div>`;
    },
  },
}));

// --- Asset Summary (RM excluded from all counts) ---
function buildSummary(rows) {
  const byAsset = {};
  rows.forEach((r) => {
    const code = classifyActivity(r);
    if (code === 'RM') return;
    const asset = r.asset || 'Unknown';
    byAsset[asset] ??= { name: asset, counts: {}, total: 0 };
    byAsset[asset].counts[code] = (byAsset[asset].counts[code] || 0) + 1;
    byAsset[asset].total += 1;
  });
  return Object.values(byAsset).sort((a, b) => a.name.localeCompare(b.name));
}

// const summaryColumns = computed(() =>
//   [...new Set(displayRows.value.map(classifyActivity))].filter((c) => c !== 'RM').sort()
// );

// const isSplitView = computed(() => props.recordType === 'both');
// const summaryRows = computed(() => buildSummary(displayRows.value));
// const plannedSummaryRows = computed(() => buildSummary(displayRows.value.filter((r) => r.record_type === 'planned')));
// const actualSummaryRows = computed(() => buildSummary(displayRows.value.filter((r) => r.record_type === 'actual')));

// Rows used by the Asset Summary: the chart's rows (record type + asset already
// applied) narrowed to the selected Time Range. A segment counts if ANY part of it
// falls inside the range, which is what the zoomed chart shows. No range = all rows.
const summarySource = computed(() => {
  if (props.viewFrom == null || props.viewTo == null) return displayRows.value;
  return displayRows.value.filter((r) => {
    const start = new Date(r.start_date).getTime();
    const end = new Date(r.finish_date).getTime();
    return start <= props.viewTo && end >= props.viewFrom;
  });
});

const summaryRangeLabel = computed(() => {
  if (props.viewFrom == null || props.viewTo == null) return '';
  const fmt = (ms) => new Date(ms).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
  return `${fmt(props.viewFrom)} – ${fmt(props.viewTo)}`;
});

const summaryColumns = computed(() =>
  [...new Set(summarySource.value.map(classifyActivity))].filter((c) => c !== 'RM').sort()
);

const isSplitView = computed(() => props.recordType === 'both');
const summaryRows = computed(() => buildSummary(summarySource.value));
const plannedSummaryRows = computed(() => buildSummary(summarySource.value.filter((r) => r.record_type === 'planned')));
const actualSummaryRows = computed(() => buildSummary(summarySource.value.filter((r) => r.record_type === 'actual')));

function columnTotal(rows, code) {
  return rows.reduce((sum, r) => sum + (r.counts[code] || 0), 0);
}
function grandTotalOf(rows) {
  return rows.reduce((sum, r) => sum + r.total, 0);
}
</script>

<style scoped>
.chart-toolbar { padding-bottom: 0.75rem; border-bottom: 1px solid #eef2f7; }
.legend-chip {
  display: inline-flex; align-items: center; gap: 6px; font-size: 0.78rem;
  color: #475569; background: #f8fafc; padding: 3px 10px; border-radius: 999px; border: 1px solid #e2e8f0;
}
.legend-dot { width: 10px; height: 10px; border-radius: 3px; display: inline-block; flex-shrink: 0; }
.chart-scroll { overflow-x: auto; }
</style>
