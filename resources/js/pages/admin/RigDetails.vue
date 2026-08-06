<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">Rig Details</h4>
      <span class="text-muted small">{{ meta.total ?? 0 }} records</span>
    </div>

    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small text-muted">Plan</label>
            <select v-model="filters.plan_name" class="form-select" @change="load(1)">
              <option value="">All Plans</option>
              <option v-for="p in planNames" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small text-muted">Type</label>
            <select v-model="filters.record_type" class="form-select" @change="load(1)">
              <option value="">Planned & Actual</option>
              <option value="planned">Planned</option>
              <option value="actual">Actual</option>
            </select>
          </div>
          <div class="col-md-5">
            <label class="form-label small text-muted">Search</label>
            <input v-model="filters.search" @input="debouncedSearch" type="text"
                   class="form-control" placeholder="Rig, asset, or well name..." />
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
              <th>Asset</th>
              <th>Platform</th>
              <th>Well Name</th>
              <th>Type</th>
              <th>WI Well</th>
              <th class="text-center">Days</th>
              <th>Start Date</th>
              <th>Finish Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="9" class="text-center text-muted py-4">Loading...</td>
            </tr>
            <tr v-else-if="!rows.length">
              <td colspan="9" class="text-center text-muted py-4">No records found</td>
            </tr>
            <tr v-for="r in rows" :key="r.id">
              <td class="fw-semibold">{{ r.rig_name }}</td>
              <td><span class="badge bg-light text-dark border">{{ r.asset }}</span></td>
              <td>{{ r.platform }}</td>
              <td>{{ r.well_name }}</td>
              <td>{{ r.type }}</td>
              <td>
                <span class="badge" :class="wiWellBadge(r.wi_well)">{{ r.wi_well }}</span>
              </td>
              <td class="text-center">{{ r.days ?? '—' }}</td>
              <td>{{ formatDate(r.start_date) }}</td>
              <td>{{ formatDate(r.finish_date) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="card-footer bg-white d-flex justify-content-between align-items-center" v-if="meta.last_page > 1">
        <span class="text-muted small">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-secondary" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)">
            <i class="bi bi-chevron-left"></i>
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
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';

const rows = ref([]);
const meta = ref({});
const loading = ref(false);
const planNames = ref([]);
const filters = reactive({ plan_name: '', record_type: '', search: '' });
let searchTimeout = null;

async function load(page = 1) {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/rig-details', {
      params: { ...filters, page },
    });
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

function wiWellBadge(status) {
  const map = { OP: 'bg-success', GP: 'bg-info', COMPL: 'bg-secondary' };
  return map[status] || 'bg-light text-dark border';
}

onMounted(() => {
  load();
  loadPlanNames();
});
</script>
