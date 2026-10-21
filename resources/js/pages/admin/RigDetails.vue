<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Rig Details</h4>
        <p class="text-muted small mb-0">Overall campaign range per rig, by data type</p>
      </div>
      <span class="text-muted small">{{ meta.total ?? 0 }} entries</span>
    </div>

    <div class="card border-0 shadow-sm mb-3 filter-card">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Plan</label>
            <select v-model="filters.plan_name" class="form-select" @change="load(1)">
              <option value="">All Plans</option>
              <option v-for="p in planNames" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Type</label>
            <select v-model="filters.record_type" class="form-select" @change="load(1)">
              <option value="">Planned & Actual</option>
              <option value="planned">Planned</option>
              <option value="actual">Actual</option>
            </select>
          </div>
          <div class="col-md-5">
            <label class="form-label small text-muted mb-1">Search</label>
            <input v-model="filters.search" @input="debouncedSearch" type="text"
                   class="form-control" placeholder="Rig name..." />
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Rig Name</th>
              <th>Type</th>
              <th>Overall Start</th>
              <th>Overall Finish</th>
              <th class="text-center">Segments</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="5" class="text-center text-muted py-4">Loading...</td>
            </tr>
            <tr v-else-if="!rows.length">
              <td colspan="5" class="text-center text-muted py-4">No records found</td>
            </tr>
            <tr v-for="r in rows" :key="r.rig_name + r.record_type" class="type-row" :class="r.record_type">
              <td class="fw-semibold">{{ r.rig_name }}</td>
              <td>
                <span class="badge type-badge" :class="typeBadgeClass(r.record_type)">
                  <i :class="typeIcon(r.record_type)" class="me-1"></i>
                  {{ typeLabel(r.record_type) }}
                </span>
              </td>
              <td>{{ formatDate(r.start_date) }}</td>
              <td>{{ formatDate(r.finish_date) }}</td>
              <td class="text-center">
                <span class="badge bg-light text-dark border">{{ r.segments }}</span>
              </td>
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

const rows = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const loading = ref(false);
const planNames = ref([]);
const filters = reactive({ plan_name: '', record_type: '', search: '' });
let searchTimeout = null;

async function load(page = 1) {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/rig-details/summary', { params: { ...filters, page } });
    rows.value = data.data;
    meta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total };
  } finally {
    loading.value = false;
  }
}

function debouncedSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => load(1), 400);
}

async function loadPlanNames() {
  const { data } = await axios.get('/api/plans');
  planNames.value = [...new Set(data.map((p) => p.plan_name))];
}

function formatDate(date) {
  if (!date) return '—';
  return new Date(date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function typeLabel(type) {
  return type === 'actual' ? 'Actual' : 'Planned';
}
function typeBadgeClass(type) {
  return type === 'actual' ? 'bg-success-subtle text-success-emphasis' : 'bg-primary-subtle text-primary-emphasis';
}
function typeIcon(type) {
  return type === 'actual' ? 'bi bi-check2-circle' : 'bi bi-calendar-event';
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
  load();
  loadPlanNames();
});
</script>

<style scoped>
.filter-card { border-radius: 0.9rem; }
.type-badge { font-weight: 600; font-size: 0.75rem; padding: 0.4em 0.7em; }
.type-row.planned { border-left: 3px solid #2563eb; }
.type-row.actual { border-left: 3px solid #059669; }
</style>
