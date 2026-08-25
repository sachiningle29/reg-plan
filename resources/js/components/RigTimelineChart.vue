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

const displayRows = computed(() => {
  const rows = props.assetFilter
    ? rawRows.value.filter((r) => r.asset === props.assetFilter)
    : rawRows.value;

  return [...rows].sort((a, b) => {
    // 1. Group by rig name first
    if (a.rig_name !== b.rig_name) return a.rig_name.localeCompare(b.rig_name);

    // 2. Within the same rig, Planned always comes before Actual
    if (props.recordType === 'both') {
      const orderA = a.record_type === 'actual' ? 1 : 0;
      const orderB = b.record_type === 'actual' ? 1 : 0;
      if (orderA !== orderB) return orderA - orderB;
    }

    // 3. Within the same rig + type, order chronologically
    return new Date(a.start_date) - new Date(b.start_date);
  });
});

function categoryFor(row) {
  if (props.recordType === 'both') {
    return `${row.rig_name} • ${row.record_type === 'actual' ? 'Actual' : 'Planned'}`;
  }
  return row.rig_name;
}

function segmentLabel(row) {
  const code = classifyActivity(row);
  if (code === 'DEHIRE' || code === 'HIRE' || code === 'RM') {
    return row.platform ? `${row.platform} ${code}` : code;
  }
  const well = row.well_name ? ` (${row.well_name})` : '';
  return `${row.platform || ''}${well} ${code}`.trim();
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
</style>
