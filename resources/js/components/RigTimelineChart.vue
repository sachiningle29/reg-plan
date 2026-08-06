<template>
  <div>
    <div class="d-flex flex-wrap gap-3 mb-3">
      <span v-for="(color, code) in legendItems" :key="code" class="legend-chip">
        <span class="legend-dot" :style="{ background: color }"></span>
        {{ code }}
      </span>
    </div>

    <div class="row g-3">
      <div class="col-12 col-xl-9">
        <div v-if="!rows.length" class="text-center text-muted py-5">
          <i class="bi bi-inbox fs-1 d-block mb-2"></i>
          No records to display for this plan
        </div>
        <div v-else class="chart-scroll">
          <apexchart type="rangeBar" :height="chartHeight" :options="chartOptions" :series="series" />
        </div>
      </div>

      <div class="col-12 col-xl-3">
        <div class="summary-table-wrap">
          <table class="table table-sm table-bordered mb-0">
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
                <td v-for="code in summaryColumns" :key="code" class="text-center">
                  {{ columnTotal(code) }}
                </td>
                <td class="text-center">{{ grandTotal }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { colorForActivity } from '../utils/activityColors';

const props = defineProps({
  planName: String,
  recordType: String,
});
const emit = defineEmits(['loaded']);

const rows = ref([]);


async function load() {
  const { data } = await axios.get('/api/rig-details/gantt', {
    params: { plan_name: props.planName, record_type: props.recordType },
  });
  rows.value = [...data].sort((a, b) => {
    if (a.rig_name === b.rig_name) return new Date(a.start_date) - new Date(b.start_date);
    return a.rig_name.localeCompare(b.rig_name);
  });
  emit('loaded', rows.value);
}
watch(() => [props.planName, props.recordType], load, { immediate: true });

function activityCode(row) {
  return (row.type || row.activity || row.status || 'ST').toUpperCase().trim();
}

function segmentLabel(row) {
  const days = row.days ? `${row.days}` : '';
  return `${row.platform || row.well_name || ''} ${days}${activityCode(row)}`.trim();
}

const legendItems = computed(() => {
  const codes = [...new Set(rows.value.map(activityCode))];
  return Object.fromEntries(codes.map((c) => [c, colorForActivity(c)]));
});

const chartHeight = computed(() => {
  const rigCount = new Set(rows.value.map((r) => r.rig_name)).size;
  return Math.max(320, rigCount * 42);
});

const series = computed(() => [{
  data: rows.value
    .filter((r) => r.start_date && r.finish_date)
    .map((r) => ({
      x: r.rig_name,
      y: [new Date(r.start_date).getTime(), new Date(r.finish_date).getTime()],
      fillColor: colorForActivity(activityCode(r)),
      label: segmentLabel(r),
      meta: r,
    })),
}]);

const chartOptions = computed(() => ({
  chart: { type: 'rangeBar', toolbar: { show: true }, animations: { enabled: false } },
  plotOptions: {
    bar: { horizontal: true, barHeight: '65%', rangeBarGroupRows: true },
  },
  xaxis: { type: 'datetime' },
  legend: { show: false },
  grid: { borderColor: '#e2e8f0' },
  dataLabels: {
    enabled: true,
    style: { fontSize: '10px', fontWeight: 600, colors: ['#1e293b'] },
    formatter: (val, opts) => {
      const point = opts.w.config.series[opts.seriesIndex].data[opts.dataPointIndex];
      return point.label || '';
    },
  },
  tooltip: {
    custom: ({ seriesIndex, dataPointIndex, w }) => {
      const point = w.config.series[seriesIndex].data[dataPointIndex];
      const r = point.meta;
      return `<div class="p-2">
        <strong>${r.rig_name}</strong><br/>
        Well: ${r.well_name || '—'} | Asset: ${r.asset || '—'}<br/>
        ${new Date(r.start_date).toLocaleDateString()} → ${new Date(r.finish_date).toLocaleDateString()}
      </div>`;
    },
  },
}));

// --- Right-hand summary table: Asset x Activity counts ---
const summaryColumns = computed(() => [...new Set(rows.value.map(activityCode))].sort());

const summaryRows = computed(() => {
  const byAsset = {};
  rows.value.forEach((r) => {
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
.legend-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
  color: #475569;
}
.legend-dot {
  width: 12px;
  height: 12px;
  border-radius: 3px;
  display: inline-block;
}
.chart-scroll {
  overflow-x: auto;
}
.summary-table-wrap {
  overflow-x: auto;
  max-height: 480px;
  overflow-y: auto;
}
.summary-table-wrap table {
  font-size: 0.78rem;
}
</style>
