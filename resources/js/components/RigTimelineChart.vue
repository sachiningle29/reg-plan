<!-- <template>
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
          <button
            class="btn"
            :class="colorMode === 'activity' ? 'btn-primary' : 'btn-outline-secondary'"
            @click="colorMode = 'activity'"
          >
            <i class="bi bi-palette-fill me-1"></i>By Activity
          </button>
          <button
            class="btn"
            :class="colorMode === 'asset' ? 'btn-primary' : 'btn-outline-secondary'"
            @click="colorMode = 'asset'"
          >
            <i class="bi bi-geo-alt-fill me-1"></i>By Asset
          </button>
        </div>

        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assetSummaryModal">
          <i class="bi bi-table me-1"></i>Asset Summary
        </button>
      </div>
    </div>

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
            <table class="table table-sm table-bordered align-middle mb-0">
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
                  <td v-for="code in summaryColumns" :key="code" class="text-center">
                    {{ asset.counts[code] || '' }}
                  </td>
                  <td class="text-center fw-semibold">{{ asset.total }}</td>
                </tr>
                <tr v-if="!summaryRows.length">
                  <td :colspan="summaryColumns.length + 2" class="text-center text-muted py-3">No data</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="table-light fw-semibold">
                  <td>Total</td>
                  <td v-for="code in summaryColumns" :key="code" class="text-center">{{ columnTotal(code) }}</td>
                  <td class="text-center">{{ grandTotal }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { colorForActivity, colorForAsset } from '../utils/activityColors';

const props = defineProps({
  planName: String,
  recordType: String, // 'planned' | 'actual' | 'both'
  assetFilter: { type: String, default: '' },
});
const emit = defineEmits(['loaded']);

const rawRows = ref([]);
const colorMode = ref('activity'); // 'activity' | 'asset'

const KNOWN_CODES = ['ST', 'WO', 'DEV', 'EXP', 'DEHIRE', 'HIRE'];

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

const displayRows = computed(() => {
  const rows = props.assetFilter
    ? rawRows.value.filter((r) => r.asset === props.assetFilter)
    : rawRows.value;

  return [...rows].sort((a, b) => {
    const catA = categoryFor(a), catB = categoryFor(b);
    if (catA === catB) return new Date(a.start_date) - new Date(b.start_date);
    return catA.localeCompare(catB);
  });
});

function activityCode(row) {
  const raw = (row.type || row.activity || row.status || '').toString().toUpperCase().trim();
  if (!raw) return 'RM';
  return KNOWN_CODES.includes(raw) ? raw : 'RM';
}

function categoryFor(row) {
  if (props.recordType === 'both') {
    return `${row.rig_name} • ${row.record_type === 'actual' ? 'Actual' : 'Planned'}`;
  }
  return row.rig_name;
}

function segmentLabel(row) {
  const code = activityCode(row);
  if (code === 'DEHIRE' || code === 'HIRE' || code === 'RM') {
    return row.platform ? `${row.platform} ${code}` : code;
  }
  const days = row.days ? `${row.days}` : '';
//   return `${row.platform || row.well_name || ''} ${days}${code}`.trim();
    return `${row.platform || row.well_name || ''} ${code}`.trim();
}

function colorForRow(row) {
  return colorMode.value === 'asset'
    ? colorForAsset(row.asset || 'Unknown')
    : colorForActivity(activityCode(row));
}

const legendItems = computed(() => {
  if (colorMode.value === 'asset') {
    const assets = [...new Set(displayRows.value.map((r) => r.asset || 'Unknown'))];
    return Object.fromEntries(assets.map((a) => [a, colorForAsset(a)]));
  }
  const codes = [...new Set(displayRows.value.map(activityCode))];
  return Object.fromEntries(codes.map((c) => [c, colorForActivity(c)]));
});

const chartHeight = computed(() => {
  const cats = new Set(displayRows.value.map(categoryFor)).size;
  return Math.max(320, cats * 44);
});

const series = computed(() => [{
  data: displayRows.value.map((r) => ({
    x: categoryFor(r),
    y: [new Date(r.start_date).getTime(), new Date(r.finish_date).getTime()],
    fillColor: colorForRow(r),
    label: segmentLabel(r),
    meta: r,
  })),
}]);

const chartOptions = computed(() => ({
  chart: { type: 'rangeBar', toolbar: { show: true }, animations: { enabled: false }, fontFamily: 'inherit' },
  plotOptions: {
    bar: { horizontal: true, barHeight: '62%', borderRadius: 3, rangeBarGroupRows: true },
  },
  xaxis: { type: 'datetime', labels: { style: { fontSize: '11px' } } },
  yaxis: { labels: { style: { fontSize: '12px', fontWeight: 600 } } },
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

const summaryColumns = computed(() => [...new Set(displayRows.value.map(activityCode))].sort());

const summaryRows = computed(() => {
  const byAsset = {};
  displayRows.value.forEach((r) => {
    const asset = r.asset || 'Unknown';
    const code = activityCode(r);
    byAsset[asset] ??= { name: asset, counts: {}, total: 0 };
    byAsset[asset].counts[code] = (byAsset[asset].counts[code] || 0) + 1;
    byAsset[asset].total += 1;
  });
  return Object.values(byAsset).sort((a, b) => a.name.localeCompare(b.name));
});

function columnTotal(code) {
  return summaryRows.value.reduce((sum, r) => sum + (r.counts[code] || 0), 0);
}
const grandTotal = computed(() => summaryRows.value.reduce((sum, r) => sum + r.total, 0));
</script>

<style scoped>
.chart-toolbar {
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #eef2f7;
}
.legend-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.78rem;
  color: #475569;
  background: #f8fafc;
  padding: 3px 10px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
}
.legend-dot { width: 10px; height: 10px; border-radius: 3px; display: inline-block; flex-shrink: 0; }
.chart-scroll { overflow-x: auto; }
</style> -->
<!--
<template>
  <div>
    <div class="chart-toolbar d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
      <div class="d-flex flex-wrap gap-2">
        <span v-for="(color, label) in legendItems" :key="label" class="legend-chip">
          <span class="legend-dot" :style="{ background: color }"></span>
          {{ label }}
        </span>
      </div>

      <div class="d-flex flex-wrap align-items-center gap-2">
        <select v-model="timeRange" class="form-select form-select-sm time-range-select">
          <option value="all">Full Timeline</option>
          <optgroup label="Quarter" v-if="quarterOptions.length">
            <option v-for="q in quarterOptions" :key="q.value" :value="q.value">{{ q.label }}</option>
          </optgroup>
          <optgroup label="Month" v-if="monthOptions.length">
            <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
          </optgroup>
        </select>

        <div class="btn-group btn-group-sm" role="group">
          <button
            class="btn"
            :class="colorMode === 'activity' ? 'btn-primary' : 'btn-outline-secondary'"
            @click="colorMode = 'activity'"
          >
            <i class="bi bi-palette-fill me-1"></i>By Activity
          </button>
          <button
            class="btn"
            :class="colorMode === 'asset' ? 'btn-primary' : 'btn-outline-secondary'"
            @click="colorMode = 'asset'"
          >
            <i class="bi bi-geo-alt-fill me-1"></i>By Asset
          </button>
        </div>

        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assetSummaryModal">
          <i class="bi bi-table me-1"></i>Asset Summary
        </button>
      </div>
    </div>

    <div v-if="!displayRows.length" class="text-center text-muted py-5">
      <i class="bi bi-inbox fs-1 d-block mb-2"></i>
      No records to display
    </div>
    <div v-else class="chart-scroll">
      <apexchart type="rangeBar" :height="chartHeight" :options="chartOptions" :series="series" />
    </div>

    <div class="modal fade" id="assetSummaryModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title d-flex align-items-center gap-2">
              <i class="bi bi-bar-chart-line-fill"></i>Asset Summary
              <span class="badge rounded-pill text-bg-light border fw-normal">{{ modeLabel }}</span>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <table v-if="!isBothMode" class="table table-sm table-bordered align-middle mb-0">
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
                  <td v-for="code in summaryColumns" :key="code" class="text-center">
                    {{ asset.counts[code] || '' }}
                  </td>
                  <td class="text-center fw-semibold">{{ asset.total }}</td>
                </tr>
                <tr v-if="!summaryRows.length">
                  <td :colspan="summaryColumns.length + 2" class="text-center text-muted py-3">No data</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="table-light fw-semibold">
                  <td>Total</td>
                  <td v-for="code in summaryColumns" :key="code" class="text-center">{{ columnTotal(code) }}</td>
                  <td class="text-center">{{ grandTotal }}</td>
                </tr>
              </tfoot>
            </table>

            <div v-else class="table-responsive">
              <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th rowspan="2" class="align-middle">Asset</th>
                    <th :colspan="summaryColumns.length + 1" class="text-center planned-col">Planned</th>
                    <th :colspan="summaryColumns.length + 1" class="text-center actual-col">Actual</th>
                  </tr>
                  <tr>
                    <th v-for="code in summaryColumns" :key="'p-' + code" class="text-center planned-col small">{{ code }}</th>
                    <th class="text-center planned-col small fw-semibold">Total</th>
                    <th v-for="code in summaryColumns" :key="'a-' + code" class="text-center actual-col small">{{ code }}</th>
                    <th class="text-center actual-col small fw-semibold">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="asset in bothSummaryRows" :key="asset.name">
                    <td class="fw-semibold">{{ asset.name }}</td>
                    <td v-for="code in summaryColumns" :key="'p-' + code" class="text-center planned-col">
                      {{ asset.planned.counts[code] || '' }}
                    </td>
                    <td class="text-center planned-col fw-semibold">{{ asset.planned.total }}</td>
                    <td v-for="code in summaryColumns" :key="'a-' + code" class="text-center actual-col">
                      {{ asset.actual.counts[code] || '' }}
                    </td>
                    <td class="text-center actual-col fw-semibold">{{ asset.actual.total }}</td>
                  </tr>
                  <tr v-if="!bothSummaryRows.length">
                    <td :colspan="(summaryColumns.length + 1) * 2 + 1" class="text-center text-muted py-3">No data</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="table-light fw-semibold">
                    <td>Total</td>
                    <td v-for="code in summaryColumns" :key="'pt-' + code" class="text-center planned-col">
                      {{ bothColumnTotal('planned', code) }}
                    </td>
                    <td class="text-center planned-col">{{ bothBucketTotal('planned') }}</td>
                    <td v-for="code in summaryColumns" :key="'at-' + code" class="text-center actual-col">
                      {{ bothColumnTotal('actual', code) }}
                    </td>
                    <td class="text-center actual-col">{{ bothBucketTotal('actual') }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <p class="text-muted small mt-2 mb-0">
              <i class="bi bi-info-circle me-1"></i>Rig-move (RM) segments are excluded from these counts — they represent transit time, not completed work.
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
import { colorForActivity, colorForAsset } from '../utils/activityColors';

const props = defineProps({
  planName: String,
  recordType: String, // 'planned' | 'actual' | 'both'
  assetFilter: { type: String, default: '' },
});
const emit = defineEmits(['loaded']);

const rawRows = ref([]);
const colorMode = ref('activity'); // 'activity' | 'asset'
const timeRange = ref('all');

const KNOWN_CODES = ['ST', 'WO', 'DEV', 'EXP', 'DEHIRE', 'HIRE'];

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
  timeRange.value = 'all';
  emit('loaded', rawRows.value);
}
watch(() => [props.planName, props.recordType], load, { immediate: true });

const displayRows = computed(() => {
  const rows = props.assetFilter
    ? rawRows.value.filter((r) => r.asset === props.assetFilter)
    : rawRows.value;

  return [...rows].sort((a, b) => {
    const catA = categoryFor(a), catB = categoryFor(b);
    if (catA === catB) return new Date(a.start_date) - new Date(b.start_date);
    return catA.localeCompare(catB);
  });
});

function activityCode(row) {
  const raw = (row.type || row.activity || row.status || '').toString().toUpperCase().trim();
  if (!raw) return 'RM';
  return KNOWN_CODES.includes(raw) ? raw : 'RM';
}

function categoryFor(row) {
  if (props.recordType === 'both') {
    return `${row.rig_name} • ${row.record_type === 'actual' ? 'Actual' : 'Planned'}`;
  }
  return row.rig_name;
}

function segmentLabel(row) {
  const code = activityCode(row);
  const primary = row.well_name || row.platform || '';

  if (code === 'DEHIRE' || code === 'HIRE' || code === 'RM') {
    return primary ? `${primary} ${code}` : code;
  }
  return `${primary} ${code}`.trim();
}

function colorForRow(row) {
  return colorMode.value === 'asset'
    ? colorForAsset(row.asset || 'Unknown')
    : colorForActivity(activityCode(row));
}

const legendItems = computed(() => {
  if (colorMode.value === 'asset') {
    const assets = [...new Set(displayRows.value.map((r) => r.asset || 'Unknown'))];
    return Object.fromEntries(assets.map((a) => [a, colorForAsset(a)]));
  }
  const codes = [...new Set(displayRows.value.map(activityCode))];
  return Object.fromEntries(codes.map((c) => [c, colorForActivity(c)]));
});

const chartHeight = computed(() => {
  const cats = new Set(displayRows.value.map(categoryFor)).size;
  return Math.max(320, cats * 44);
});

// --- Quarter / Month zoom options, derived from the loaded data's own date span ---
function fiscalYearStart(date) {
  const y = date.getFullYear();
  const m = date.getMonth(); // April = 3
  return m >= 3 ? new Date(y, 3, 1) : new Date(y - 1, 3, 1);
}

const dateBounds = computed(() => {
  if (!rawRows.value.length) return null;
  const starts = rawRows.value.map((r) => new Date(r.start_date).getTime());
  const ends = rawRows.value.map((r) => new Date(r.finish_date).getTime());
  return { min: Math.min(...starts), max: Math.max(...ends) };
});

const quarterOptions = computed(() => {
  if (!dateBounds.value) return [];
  const names = ['Q1 (Apr-Jun)', 'Q2 (Jul-Sep)', 'Q3 (Oct-Dec)', 'Q4 (Jan-Mar)'];
  const opts = [];
  let cursor = fiscalYearStart(new Date(dateBounds.value.min));
  let qi = 0;
  while (cursor.getTime() <= dateBounds.value.max) {
    const start = new Date(cursor);
    const end = new Date(cursor);
    end.setMonth(end.getMonth() + 3);
    opts.push({
      value: `q-${start.getFullYear()}-${start.getMonth()}`,
      label: `${names[qi % 4]} FY${start.getFullYear()}`,
      start: start.getTime(),
      end: end.getTime() - 1,
    });
    cursor = end;
    qi++;
  }
  return opts;
});

const monthOptions = computed(() => {
  if (!dateBounds.value) return [];
  const opts = [];
  const minD = new Date(dateBounds.value.min);
  let cursor = new Date(minD.getFullYear(), minD.getMonth(), 1);
  while (cursor.getTime() <= dateBounds.value.max) {
    const start = new Date(cursor);
    const end = new Date(cursor);
    end.setMonth(end.getMonth() + 1);
    opts.push({
      value: `m-${start.getFullYear()}-${start.getMonth()}`,
      label: start.toLocaleDateString('en-GB', { month: 'short', year: 'numeric' }),
      start: start.getTime(),
      end: end.getTime() - 1,
    });
    cursor = end;
  }
  return opts;
});

const selectedRange = computed(() => {
  if (timeRange.value === 'all') return null;
  return [...quarterOptions.value, ...monthOptions.value].find((o) => o.value === timeRange.value) || null;
});

const series = computed(() => [{
  data: displayRows.value.map((r) => ({
    x: categoryFor(r),
    y: [new Date(r.start_date).getTime(), new Date(r.finish_date).getTime()],
    fillColor: colorForRow(r),
    label: segmentLabel(r),
    meta: r,
  })),
}]);

const chartOptions = computed(() => ({
  chart: { type: 'rangeBar', toolbar: { show: true }, animations: { enabled: false }, fontFamily: 'inherit' },
  plotOptions: {
    bar: { horizontal: true, barHeight: '62%', borderRadius: 3, rangeBarGroupRows: true },
  },
  xaxis: {
    type: 'datetime',
    labels: { style: { fontSize: '11px' } },
    ...(selectedRange.value ? { min: selectedRange.value.start, max: selectedRange.value.end } : {}),
  },
  yaxis: { labels: { style: { fontSize: '12px', fontWeight: 600 } } },
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
        Well: ${r.well_name || '—'} | Asset: ${r.asset || '—'} | Platform: ${r.platform || '—'}<br/>
        ${new Date(r.start_date).toLocaleDateString()} → ${new Date(r.finish_date).toLocaleDateString()}
      </div>`;
    },
  },
}));

// --- Asset Summary (RM excluded — it's transit time, not work) ---
const isBothMode = computed(() => props.recordType === 'both');
const modeLabel = computed(() => ({
  planned: 'Planned', actual: 'Actual', both: 'Planned vs Actual',
}[props.recordType]));

const summaryEligibleRows = computed(() => displayRows.value.filter((r) => activityCode(r) !== 'RM'));

const summaryColumns = computed(() => [...new Set(summaryEligibleRows.value.map(activityCode))].sort());

const summaryRows = computed(() => {
  const byAsset = {};
  summaryEligibleRows.value.forEach((r) => {
    const asset = r.asset || 'Unknown';
    const code = activityCode(r);
    byAsset[asset] ??= { name: asset, counts: {}, total: 0 };
    byAsset[asset].counts[code] = (byAsset[asset].counts[code] || 0) + 1;
    byAsset[asset].total += 1;
  });
  return Object.values(byAsset).sort((a, b) => a.name.localeCompare(b.name));
});

function columnTotal(code) {
  return summaryRows.value.reduce((sum, r) => sum + (r.counts[code] || 0), 0);
}
const grandTotal = computed(() => summaryRows.value.reduce((sum, r) => sum + r.total, 0));

const bothSummaryRows = computed(() => {
  const byAsset = {};
  summaryEligibleRows.value.forEach((r) => {
    const asset = r.asset || 'Unknown';
    const code = activityCode(r);
    const bucket = r.record_type === 'actual' ? 'actual' : 'planned';
    byAsset[asset] ??= { name: asset, planned: { counts: {}, total: 0 }, actual: { counts: {}, total: 0 } };
    byAsset[asset][bucket].counts[code] = (byAsset[asset][bucket].counts[code] || 0) + 1;
    byAsset[asset][bucket].total += 1;
  });
  return Object.values(byAsset).sort((a, b) => a.name.localeCompare(b.name));
});

function bothColumnTotal(bucket, code) {
  return bothSummaryRows.value.reduce((sum, r) => sum + (r[bucket].counts[code] || 0), 0);
}
function bothBucketTotal(bucket) {
  return bothSummaryRows.value.reduce((sum, r) => sum + r[bucket].total, 0);
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
.time-range-select { width: auto; min-width: 160px; }
.planned-col { background: #eff6ff; }
.actual-col { background: #f0fdf4; }
</style> -->






<!-- recently working logic -->


<!-- <template>
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

// ---------------------------------------------------------------------------
// Lanes: one rig can have several overlapping bars (actual data logs a rig
// against more than one well at a time). Overlapping bars move to extra
// lines under the same rig instead of drawing on top of each other.
// ---------------------------------------------------------------------------
const laidOutRows = computed(() => {
  const groups = new Map();
  displayRows.value.forEach((row) => {
    const key = categoryFor(row);
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push(row);
  });

  const result = [];

  groups.forEach((rows, baseKey) => {
    const sorted = [...rows].sort((a, b) => new Date(a.start_date) - new Date(b.start_date));

    // Put each bar in the first lane that is already free at its start date.
    const laneEnds = [];
    const placed = sorted.map((row) => {
      const start = new Date(row.start_date).getTime();
      const end = new Date(row.finish_date).getTime();
      let lane = laneEnds.findIndex((laneEnd) => start >= laneEnd);
      if (lane === -1) {
        lane = laneEnds.length;
        laneEnds.push(end);
      } else {
        laneEnds[lane] = end;
      }
      return { row, lane };
    });

    // Lane 1 keeps the plain rig name; extra lanes get a short VISIBLE label
    // like "ABAN IV (2)", so what the chart measures is what it draws.
    const keyForLane = (lane) => (lane === 0 ? baseKey : `${baseKey} (${lane + 1})`);

    placed
      .sort((a, b) => a.lane - b.lane || new Date(a.row.start_date) - new Date(b.row.start_date))
      .forEach(({ row, lane }) => {
        result.push({ row, axisKey: keyForLane(lane) });
      });
  });

  return result;
});

// Bar label: the well name only. Type, platform and dates are in the tooltip.
function segmentLabel(row) {
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

const chartHeight = computed(() => {
  const rows = new Set(laidOutRows.value.map((r) => r.axisKey)).size;
  return Math.max(320, rows * 44);
});

const series = computed(() => [{
  data: laidOutRows.value.map(({ row, axisKey }) => ({
    x: axisKey,
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

const summaryColumns = computed(() =>
  [...new Set(displayRows.value.map(classifyActivity))].filter((c) => c !== 'RM').sort()
);

const isSplitView = computed(() => props.recordType === 'both');
const summaryRows = computed(() => buildSummary(displayRows.value));
const plannedSummaryRows = computed(() => buildSummary(displayRows.value.filter((r) => r.record_type === 'planned')));
const actualSummaryRows = computed(() => buildSummary(displayRows.value.filter((r) => r.record_type === 'actual')));

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
</style> -->


<!-- end -->







<!-- start previous working logic -->

 <!-- <template>
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

// const displayRows = computed(() => {
//   const rows = props.assetFilter
//     ? rawRows.value.filter((r) => r.asset === props.assetFilter)
//     : rawRows.value;

//   return [...rows].sort((a, b) => {
//     const catA = categoryFor(a), catB = categoryFor(b);
//     if (catA === catB) return new Date(a.start_date) - new Date(b.start_date);
//     return catA.localeCompare(catB);
//   });
// });

// const displayRows = computed(() => {
//   const rows = props.assetFilter
//     ? rawRows.value.filter((r) => r.asset === props.assetFilter)
//     : rawRows.value;

//   return [...rows].sort((a, b) => {
//     // 1. Group by rig name first
//     if (a.rig_name !== b.rig_name) return a.rig_name.localeCompare(b.rig_name);

//     // 2. Within the same rig, Planned always comes before Actual
//     if (props.recordType === 'both') {
//       const orderA = a.record_type === 'actual' ? 1 : 0;
//       const orderB = b.record_type === 'actual' ? 1 : 0;
//       if (orderA !== orderB) return orderA - orderB;
//     }

//     // 3. Within the same rig + type, order chronologically
//     return new Date(a.start_date) - new Date(b.start_date);
//   });
// });
//added on 5-10-26
const displayRows = computed(() => {
  const rows = props.assetFilter
    ? rawRows.value.filter((r) => r.asset === props.assetFilter)
    : rawRows.value;

  return [...rows].sort((a, b) => {
    const keyA = normalizeRigKey(a.rig_name);
    const keyB = normalizeRigKey(b.rig_name);
    if (keyA !== keyB) return keyA.localeCompare(keyB);

    if (props.recordType === 'both') {
      const orderA = a.record_type === 'actual' ? 1 : 0;
      const orderB = b.record_type === 'actual' ? 1 : 0;
      if (orderA !== orderB) return orderA - orderB;
    }

    return new Date(a.start_date) - new Date(b.start_date);
  });
});
//end

// added by sachin on 5-10-26

// function normalizeRigKey(name) {
//   return (name || '').toString().trim().toUpperCase();
// }

function normalizeRigKey(name) {
  return (name || '')
    .toString()
    .trim()
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g, ' ') // collapse hyphens, parentheses, extra spaces, etc. into one space
    .trim();
}

// Build one canonical spelling per rig, regardless of how differently the
// planned vs. actual source files capitalized it. Planned's spelling wins
// when both exist, since that's usually the "official" naming.
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
  return rigCanonicalNames.value.get(normalizeRigKey(rigName)) ?? rigName;
}
//end




// function categoryFor(row) {
//   if (props.recordType === 'both') {
//     return `${row.rig_name} • ${row.record_type === 'actual' ? 'Actual' : 'Planned'}`;
//   }
//   return row.rig_name;
// }

//added by sachin on 5-10-26
function categoryFor(row) {
  const name = canonicalRigName(row.rig_name);
  if (props.recordType === 'both') {
    return `${name} • ${row.record_type === 'actual' ? 'Actual' : 'Planned'}`;
  }
  return name;
}
//end

// function segmentLabel(row) {
//   const code = classifyActivity(row);
//   if (code === 'DEHIRE' || code === 'HIRE' || code === 'RM') {
//     return row.platform ? `${row.platform} ${code}` : code;
//   }
//   const well = row.well_name ? ` (${row.well_name})` : '';
//   return `${row.platform || ''}${well} ${code}`.trim();
// }
//added on 5-10-26
function segmentLabel(row) {
  if (row.well_name) return row.well_name;
  const code = classifyActivity(row);
  return row.platform || code;
}
//end

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

const chartHeight = computed(() => {
  const cats = new Set(displayRows.value.map(categoryFor)).size;
  return Math.max(320, cats * 44);
});

const series = computed(() => [{
  data: displayRows.value.map((r) => ({
    x: categoryFor(r),
    y: [new Date(r.start_date).getTime(), new Date(r.finish_date).getTime()],
    fillColor: colorForRow(r),
    label: segmentLabel(r),
    meta: r,
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
  yaxis: { labels: { style: { fontSize: '12px', fontWeight: 600 } } },
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

// --- Asset Summary (RM excluded from all counts, per your request) ---
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

const summaryColumns = computed(() =>
  [...new Set(displayRows.value.map(classifyActivity))].filter((c) => c !== 'RM').sort()
);

const isSplitView = computed(() => props.recordType === 'both');
const summaryRows = computed(() => buildSummary(displayRows.value));
const plannedSummaryRows = computed(() => buildSummary(displayRows.value.filter((r) => r.record_type === 'planned')));
const actualSummaryRows = computed(() => buildSummary(displayRows.value.filter((r) => r.record_type === 'actual')));

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
</style> -->


<!-- end previous logic -->


<!-- <template>
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

const displayRows = computed(() => {
  const rows = props.assetFilter
    ? rawRows.value.filter((r) => r.asset === props.assetFilter)
    : rawRows.value;

  return [...rows].sort((a, b) => {
    const nameA = canonicalRigName(a.rig_name), nameB = canonicalRigName(b.rig_name);
    if (nameA !== nameB) return nameA.localeCompare(nameB);

    if (props.recordType === 'both') {
      const orderA = a.record_type === 'actual' ? 1 : 0;
      const orderB = b.record_type === 'actual' ? 1 : 0;
      if (orderA !== orderB) return orderA - orderB; // Planned (0) before Actual (1)
    }

    return new Date(a.start_date) - new Date(b.start_date);
  });
});

// --- Rig-name normalization: "ABAN VIII", "Aban-VIII" and "aban viii" all
// resolve to the same grouping key, so Planned and Actual rows for the
// same physical rig land on the same chart row instead of two unrelated
// ones. The displayed label still uses one real spelling (Planned's, if
// it exists for that rig) — never the stripped key itself.
function rigKey(name) {
  return (name || '').toString().toLowerCase().replace(/[^a-z0-9]/g, '');
}

const canonicalNameMap = computed(() => {
  const preferred = {}; // key -> name from a planned row
  const fallback = {};  // key -> name from any row
  rawRows.value.forEach((r) => {
    const key = rigKey(r.rig_name);
    if (!key) return;
    if (!fallback[key]) fallback[key] = r.rig_name;
    if (r.record_type === 'planned' && !preferred[key]) preferred[key] = r.rig_name;
  });
  return { preferred, fallback };
});

function canonicalRigName(name) {
  const key = rigKey(name);
  const { preferred, fallback } = canonicalNameMap.value;
  return preferred[key] || fallback[key] || name || '';
}

function categoryFor(row) {
  const name = canonicalRigName(row.rig_name);
  if (props.recordType === 'both') {
    return `${name} • ${row.record_type === 'actual' ? 'Actual' : 'Planned'}`;
  }
  return name;
}

// Bar text shows the well name only — type/activity is still available via
// color (legend) and the hover tooltip, it just no longer crowds the label
// itself, which was causing well names to get clipped on narrow bars.
function segmentLabel(row) {
  if (row.well_name) return row.well_name;
  if (row.platform) return row.platform;
  return classifyActivity(row);
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

const chartHeight = computed(() => {
  const cats = new Set(displayRows.value.map(categoryFor)).size;
  return Math.max(320, cats * 44);
});

const series = computed(() => [{
  data: displayRows.value.map((r) => ({
    x: categoryFor(r),
    y: [new Date(r.start_date).getTime(), new Date(r.finish_date).getTime()],
    fillColor: colorForRow(r),
    label: segmentLabel(r),
    meta: r,
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
  yaxis: { labels: { style: { fontSize: '12px', fontWeight: 600 } } },
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
        Well: ${r.well_name || '—'} | Asset: ${r.asset || '—'} | Type: ${classifyActivity(r)}<br/>
        ${new Date(r.start_date).toLocaleDateString()} → ${new Date(r.finish_date).toLocaleDateString()}
      </div>`;
    },
  },
}));

const summaryColumns = computed(() =>
  [...new Set(displayRows.value.map(classifyActivity))].filter((c) => c !== 'RM').sort()
);

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

const isSplitView = computed(() => props.recordType === 'both');
const summaryRows = computed(() => buildSummary(displayRows.value));
const plannedSummaryRows = computed(() => buildSummary(displayRows.value.filter((r) => r.record_type === 'planned')));
const actualSummaryRows = computed(() => buildSummary(displayRows.value.filter((r) => r.record_type === 'actual')));

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
</style> -->








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
function segmentLabel(row) {
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
