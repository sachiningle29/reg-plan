<!-- <template>
  <div>
    <div class="dashboard-header mb-4">
      <h4 class="mb-1">Admin Dashboard</h4>
      <p class="text-muted small mb-0">Track rig activity and progress across your import plans</p>
    </div>

    <div class="card border-0 shadow-sm mb-4 filter-card">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Plan</label>
            <select v-model="planName" class="form-select">
              <option v-for="p in planNames" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Data Type</label>
            <select v-model="recordType" class="form-select">
              <option value="planned">Planned</option>
              <option value="actual">Actual</option>
              <option value="both">Planned & Actual</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Asset</label>
            <select v-model="assetFilter" class="form-select">
              <option value="">All Assets</option>
              <option v-for="a in assetOptions" :key="a" :value="a">{{ a }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <button class="btn btn-outline-secondary w-100" @click="resetFilters">
              <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
            </button>
          </div>
        </div>
      </div>
    </div>



    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h6 class="mb-0">{{ planName || 'Select a plan' }}</h6>
        <div class="d-flex gap-2">
          <span class="badge rounded-pill text-bg-light border">{{ recordTypeLabel }}</span>
          <span v-if="assetFilter" class="badge rounded-pill text-bg-light border">{{ assetFilter }}</span>
        </div>
      </div>
      <div class="card-body">
        <RigTimelineChart
          :plan-name="planName"
          :record-type="recordType"
          :asset-filter="assetFilter"
          @loaded="onGanttData"
        />
      </div>
    </div>


    <div class="row g-3 mb-4">
      <div class="col-md-4" v-for="card in stats" :key="card.label">
        <div class="card border-0 shadow-sm stat-card">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon" :class="card.bg"><i :class="card.icon"></i></div>
            <div>
              <div class="text-muted small">{{ card.label }}</div>
              <div class="fs-4 fw-bold">{{ card.value }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>



  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import RigTimelineChart from '../../components/RigTimelineChart.vue';

const planNames = ref([]);
const planName = ref('');
const recordType = ref('planned');
const assetFilter = ref('');
const latestRows = ref([]);

const stats = ref([
  { label: 'Total Rigs', value: '—', icon: 'bi bi-tools', bg: 'bg-primary' },
  { label: 'Total Wells', value: '—', icon: 'bi bi-diagram-3-fill', bg: 'bg-success' },
  { label: 'Completed', value: '—', icon: 'bi bi-check-circle-fill', bg: 'bg-secondary' },
]);

const recordTypeLabel = computed(() => ({
  planned: 'Planned', actual: 'Actual', both: 'Planned & Actual',
}[recordType.value]));

const assetOptions = computed(() => [...new Set(latestRows.value.map((r) => r.asset).filter(Boolean))].sort());

function onGanttData(rows) {
  latestRows.value = rows;

  const workRows = rows.filter((r) => !detectHireStatus(r));
  const rigs = new Set(workRows.map((r) => r.rig_name));
  const completed = workRows.filter((r) => (r.status || '').toUpperCase().trim() === 'C').length;

  stats.value = [
    { label: 'Total Rigs', value: rigs.size, icon: 'bi bi-tools', bg: 'bg-primary' },
    { label: 'Total Wells', value: workRows.length, icon: 'bi bi-diagram-3-fill', bg: 'bg-success' },
    { label: 'Completed', value: completed, icon: 'bi bi-check-circle-fill', bg: 'bg-secondary' },
  ];
}

function resetFilters() {
  assetFilter.value = '';
}

watch(planName, () => { assetFilter.value = ''; });

async function loadPlans() {
  const { data } = await axios.get('/api/plans');
  const names = [...new Set(data.map((p) => p.plan_name))];
  planNames.value = names;
  if (names.length) planName.value = names[0];
}

onMounted(loadPlans);
</script>

<style scoped>
.dashboard-header h4 { font-weight: 700; color: #0f172a; }
.filter-card { border-radius: 0.9rem; }
.stat-card { border-radius: 0.9rem; transition: transform .15s ease; }
.stat-card:hover { transform: translateY(-2px); }
.stat-icon {
  width: 48px; height: 48px; border-radius: 0.75rem;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 1.25rem;
}
</style> -->

<template>
  <div>
    <div class="dashboard-header mb-4">
      <h4 class="mb-1">Admin Dashboard</h4>
      <p class="text-muted small mb-0">Track rig activity and progress across your import plans</p>
    </div>

    <div class="card border-0 shadow-sm mb-4 filter-card">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Plan</label>
            <select v-model="planName" class="form-select">
              <option v-for="p in planNames" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small text-muted mb-1">Data Type</label>
            <select v-model="recordType" class="form-select">
              <option value="planned">Planned</option>
              <option value="actual">Actual</option>
              <option value="both">Planned & Actual</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Asset</label>
            <select v-model="assetFilter" class="form-select">
              <option value="">All Assets</option>
              <option v-for="a in assetOptions" :key="a" :value="a">{{ a }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small text-muted mb-1">Time Range</label>
            <select v-model="timeRange" class="form-select">
              <option value="">All Time</option>
              <optgroup label="Quarterly">
                <option v-for="q in quarterOptions" :key="q.label" :value="'q:' + q.label">{{ q.label }}</option>
              </optgroup>
              <optgroup label="Monthly">
                <option v-for="m in monthOptions" :key="m.label" :value="'m:' + m.label">{{ m.label }}</option>
              </optgroup>
            </select>
          </div>
          <div class="col-md-2">
            <button class="btn btn-outline-secondary w-100" @click="resetFilters">
              <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-4" v-for="card in stats" :key="card.label">
        <div class="card border-0 shadow-sm stat-card">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon" :class="card.bg"><i :class="card.icon"></i></div>
            <div>
              <div class="text-muted small">{{ card.label }}</div>
              <div class="fs-4 fw-bold">{{ card.value }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h6 class="mb-0">{{ planName || 'Select a plan' }}</h6>
        <div class="d-flex gap-2">
          <span class="badge rounded-pill text-bg-light border">{{ recordTypeLabel }}</span>
          <span v-if="assetFilter" class="badge rounded-pill text-bg-light border">{{ assetFilter }}</span>
          <span v-if="timeRangeLabel" class="badge rounded-pill text-bg-light border">{{ timeRangeLabel }}</span>
        </div>
      </div>
      <div class="card-body">
        <RigTimelineChart
          :plan-name="planName"
          :record-type="recordType"
          :asset-filter="assetFilter"
          :view-from="viewFrom"
          :view-to="viewTo"
          @loaded="onGanttData"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import RigTimelineChart from '../../components/RigTimelineChart.vue';
import { classifyActivity, detectHireStatus } from '../../utils/activityColors';
import { quartersBetween, monthsBetween } from '../../utils/fiscalPeriods';

const planNames = ref([]);
const planName = ref('');
const recordType = ref('planned');
const assetFilter = ref('');
const timeRange = ref('');
const latestRows = ref([]);

// const stats = ref([
//   { label: 'Total Rigs', value: '—', icon: 'bi bi-tools', bg: 'bg-primary' },
//   { label: 'Total Wells', value: '—', icon: 'bi bi-diagram-3-fill', bg: 'bg-success' },
//   { label: 'Completed', value: '—', icon: 'bi bi-check-circle-fill', bg: 'bg-secondary' },
// ]);

const recordTypeLabel = computed(() => ({
  planned: 'Planned', actual: 'Actual', both: 'Planned & Actual',
}[recordType.value]));

const assetOptions = computed(() => [...new Set(latestRows.value.map((r) => r.asset).filter(Boolean))].sort());

// --- Quarter / Month filter options, derived from the currently loaded plan's date range ---
const dataDateRange = computed(() => {
  const dates = latestRows.value
    .flatMap((r) => [new Date(r.start_date), new Date(r.finish_date)])
    .filter((d) => !isNaN(d));
  if (!dates.length) return null;
  return { min: new Date(Math.min(...dates)), max: new Date(Math.max(...dates)) };
});

const quarterOptions = computed(() => dataDateRange.value ? quartersBetween(dataDateRange.value.min, dataDateRange.value.max) : []);
const monthOptions = computed(() => dataDateRange.value ? monthsBetween(dataDateRange.value.min, dataDateRange.value.max) : []);

function selectedPeriod() {
  if (!timeRange.value) return null;
  const kind = timeRange.value.slice(0, 1);
  const label = timeRange.value.slice(2);
  const list = kind === 'q' ? quarterOptions.value : monthOptions.value;
  return list.find((p) => p.label === label) || null;
}

const viewFrom = computed(() => selectedPeriod()?.from.getTime() ?? null);
const viewTo = computed(() => selectedPeriod()?.to.getTime() ?? null);
const timeRangeLabel = computed(() => selectedPeriod()?.label ?? '');

function onGanttData(rows) {
  latestRows.value = rows;

  // RM (blank/unclassified) and HIRE/DEHIRE rows aren't real work — exclude from stats
  const workRows = rows.filter((r) => !detectHireStatus(r) && classifyActivity(r) !== 'RM');
  const rigs = new Set(workRows.map((r) => r.rig_name));
  const completed = workRows.filter((r) => (r.status || '').toString().trim().toUpperCase() === 'C').length;

  stats.value = [
    { label: 'Total Rigs', value: rigs.size, icon: 'bi bi-tools', bg: 'bg-primary' },
    { label: 'Total Wells', value: workRows.length, icon: 'bi bi-diagram-3-fill', bg: 'bg-success' },
    { label: 'Completed', value: completed, icon: 'bi bi-check-circle-fill', bg: 'bg-secondary' },
  ];
}

function resetFilters() {
  assetFilter.value = '';
  timeRange.value = '';
}

watch(planName, () => { assetFilter.value = ''; timeRange.value = ''; });

async function loadPlans() {
  const { data } = await axios.get('/api/plans');
  const names = [...new Set(data.map((p) => p.plan_name))];
  planNames.value = names;
  if (names.length) planName.value = names[0];
}

onMounted(loadPlans);
</script>

<style scoped>
.dashboard-header h4 { font-weight: 700; color: #0f172a; }
.filter-card { border-radius: 0.9rem; }
.stat-card { border-radius: 0.9rem; transition: transform .15s ease; }
.stat-card:hover { transform: translateY(-2px); }
.stat-icon {
  width: 48px; height: 48px; border-radius: 0.75rem;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 1.25rem;
}
</style>
