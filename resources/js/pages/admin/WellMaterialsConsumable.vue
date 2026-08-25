<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Well Materials Consumable</h4>
        <p class="text-muted small mb-0">Planned material requirements matched to scheduled well work</p>
      </div>
      <span class="text-muted small">{{ meta.total ?? 0 }} records</span>
    </div>

    <div class="card border-0 shadow-sm mb-3 filter-card">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Asset</label>
            <select v-model="filters.asset" class="form-select" @change="onAssetChange">
              <option value="">All Assets</option>
              <option v-for="a in assetOptions" :key="a" :value="a">{{ a }}</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Well</label>
            <select v-model="filters.well" class="form-select" @change="onWellChange">
              <option value="">All Wells</option>
              <option v-for="w in wellOptions" :key="w" :value="w">{{ w }}</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted mb-1">
              Quarter <span class="text-muted">(hold Ctrl/Cmd to select multiple)</span>
            </label>
            <select v-model="selectedQuarterLabels" multiple class="form-select quarter-select" @change="load(1)">
              <option v-for="q in quarterOptions" :key="q.label" :value="q.label">{{ q.label }}</option>
            </select>
          </div>
        </div>
        <div class="d-flex justify-content-end mt-2" v-if="selectedQuarterLabels.length">
          <button class="btn btn-sm btn-link text-decoration-none p-0" @click="clearQuarters">
            <i class="bi bi-x-circle me-1"></i>Clear quarter selection
          </button>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Asset</th>
              <th>Well Name</th>
              <th>HPTP</th>
              <th>Type</th>
              <th>Item Name</th>
              <th class="text-end">Meters</th>
              <th>Start Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="text-center text-muted py-4">Loading...</td>
            </tr>
            <tr v-else-if="!rows.length">
              <td colspan="7" class="text-center text-muted py-4">No matching records found</td>
            </tr>
            <tr v-for="(r, i) in rows" :key="i">
              <td><span class="badge bg-light text-dark border">{{ r.asset }}</span></td>
              <td class="fw-semibold">{{ r.well_name }}</td>
              <td>
                <span class="badge" :class="r.hptp === 'HPTP' ? 'bg-danger-subtle text-danger-emphasis' : 'bg-secondary-subtle text-secondary-emphasis'">
                  {{ r.hptp }}
                </span>
              </td>
              <td>{{ r.types || '—' }}</td>
              <td>{{ r.item_name }}</td>
              <td class="text-end">{{ r.meters ?? '—' }}</td>
              <td>{{ formatDate(r.start_date) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="card-footer bg-white d-flex justify-content-between align-items-center" v-if="meta.last_page > 1">
        <span class="text-muted small">Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} total</span>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-secondary" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)">
            <i class="bi bi-chevron-left"></i>
          </button>
          <button
            v-for="p in pageNumbers"
            :key="p"
            class="btn"
            :class="p === meta.current_page ? 'btn-primary' : 'btn-outline-secondary'"
            @click="load(p)"
          >
            {{ p }}
          </button>
          <button class="btn btn-outline-secondary" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { quartersBetween } from '../../utils/fiscalPeriods';

const rows = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const loading = ref(false);

const assetOptions = ref([]);
const wellOptions = ref([]);
const quarterOptions = ref([]);
const selectedQuarterLabels = ref([]);
const filters = reactive({ asset: '', well: '' });

async function loadAssets() {
  const { data } = await axios.get('/api/well-materials/assets');
  assetOptions.value = data;
}

async function loadWells() {
  const { data } = await axios.get('/api/well-materials/wells', { params: { asset: filters.asset } });
  wellOptions.value = data;
}

async function loadQuarterOptions() {
  const { data } = await axios.get('/api/well-materials/date-range', {
    params: { asset: filters.asset, well: filters.well },
  });
  quarterOptions.value = (data.min_date && data.max_date)
    ? quartersBetween(new Date(data.min_date), new Date(data.max_date))
    : [];
  selectedQuarterLabels.value = [];
}

function onAssetChange() {
  filters.well = ''; // a well from a different asset is no longer valid
  loadWells();
  loadQuarterOptions();
  load(1);
}

function onWellChange() {
  loadQuarterOptions();
  load(1);
}

function clearQuarters() {
  selectedQuarterLabels.value = [];
  load(1);
}

function selectedPeriods() {
  return quarterOptions.value
    .filter((q) => selectedQuarterLabels.value.includes(q.label))
    .map((q) => ({ from: toDateStr(q.from), to: toDateStr(q.to) }));
}

function toDateStr(date) {
  return date.toISOString().slice(0, 10);
}

async function load(page = 1) {
  loading.value = true;
  try {
    const periods = selectedPeriods();
    const { data } = await axios.get('/api/well-materials/consumables', {
      params: {
        asset: filters.asset,
        well: filters.well,
        periods: periods.length ? JSON.stringify(periods) : undefined,
        page,
      },
    });
    rows.value = data.data;
    meta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total };
  } finally {
    loading.value = false;
  }
}

function formatDate(date) {
  if (!date) return '—';
  return new Date(date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

const pageNumbers = computed(() => {
  const total = meta.value.last_page;
  const current = meta.value.current_page;
  const start = Math.max(1, current - 2);
  const end = Math.min(total, start + 4);
  const arr = [];
  for (let i = start; i <= end; i++) arr.push(i);
  return arr;
});

onMounted(() => {
  loadAssets();
  loadWells();
  loadQuarterOptions();
  load();
});
</script>

<style scoped>
.filter-card { border-radius: 0.9rem; }
.quarter-select { min-height: 84px; }
</style>
