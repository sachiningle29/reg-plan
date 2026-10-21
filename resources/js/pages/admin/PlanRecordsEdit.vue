<template>
  <div>
    <div class="d-flex align-items-center gap-3 mb-4">
      <router-link :to="{ name: 'admin-plan-import' }" class="btn btn-sm btn-light border">
        <i class="bi bi-arrow-left"></i>
      </router-link>
      <div>
        <h4 class="mb-0">{{ planName }}</h4>
        <span class="badge bg-success-subtle text-success-emphasis mt-1">
          <i class="bi bi-check2-circle me-1"></i>Actual Records
        </span>
      </div>
      <span class="text-muted small ms-auto">{{ meta.total ?? 0 }} wells</span>
    </div>

    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <input v-model="search" @input="debouncedSearch" type="text" class="form-control"
               placeholder="Search rig, asset, or well name..." />
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:40px"></th>
              <th>Rig Name</th>
              <th>Asset</th>
              <th>Platform</th>
              <th>Well Name</th>
              <th class="text-center">Segments</th>
              <th class="text-center">Status</th>
              <th>Start Date</th>
              <th>Finish Date</th>
              <th class="text-center">Edit</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td colspan="10" class="text-center text-muted py-4">Loading...</td></tr>
            <tr v-else-if="!groups.length"><td colspan="10" class="text-center text-muted py-4">No records found</td></tr>

            <template v-for="g in groups" :key="g.well_name">
              <tr class="well-row" @click="toggle(g.well_name)">
                <td class="text-muted">
                  <i :class="expanded.has(g.well_name) ? 'bi bi-chevron-down' : 'bi bi-chevron-right'"></i>
                </td>
                <td class="fw-semibold">{{ g.rig_name || '—' }}</td>
                <td>{{ g.asset || '—' }}</td>
                <td>{{ g.platform || '—' }}</td>
                <td>{{ g.well_name }}</td>
                <td class="text-center"><span class="badge bg-light text-dark border">{{ g.segments.length }}</span></td>
                <td class="text-center">
                  <span class="badge" :class="g.has_completed ? 'bg-success' : 'bg-light text-dark border'">
                    {{ g.has_completed ? 'C' : '—' }}
                  </span>
                </td>
                <td>{{ formatDate(g.start_date) }}</td>
                <td>{{ formatDate(g.finish_date) }}</td>
                <td class="text-center">
                  <button class="btn btn-sm btn-outline-primary" title="Show segments to edit" @click.stop="toggle(g.well_name)">
                    <i class="bi bi-pencil-square"></i>
                  </button>
                </td>
              </tr>

              <tr v-if="expanded.has(g.well_name)" class="segments-row">
                <td colspan="10" class="p-0">
                  <div class="segments-panel">
                    <table class="table table-sm mb-0">
                      <thead>
                        <tr class="text-muted small">
                          <th>Type</th>
                          <th class="text-end">Meters</th>
                          <th class="text-end">Days</th>
                          <th class="text-center">Status</th>
                          <th>Start Date</th>
                          <th>Finish Date</th>
                          <th class="text-center">Edit</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="s in g.segments" :key="s.id">
                          <td><span class="badge bg-light text-dark border">{{ s.type || '—' }}</span></td>
                          <td class="text-end">{{ s.meters ?? '—' }}</td>
                          <td class="text-end">{{ s.days ?? '—' }}</td>
                          <td class="text-center">
                            <span class="badge" :class="isCompleted(s.status) ? 'bg-success' : 'bg-light text-dark border'">
                              {{ isCompleted(s.status) ? 'C' : '—' }}
                            </span>
                          </td>
                          <td>{{ formatDate(s.start_date) }}</td>
                          <td>{{ formatDate(s.finish_date) }}</td>
                          <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary" @click="openEdit(s)">
                              <i class="bi bi-pencil-square"></i>
                            </button>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <div class="card-footer bg-white d-flex justify-content-between align-items-center" v-if="meta.last_page > 1">
        <span class="text-muted small">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-secondary" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)">
            <i class="bi bi-chevron-left"></i>
          </button>
          <button v-for="p in pageNumbers" :key="p" class="btn"
                  :class="p === meta.current_page ? 'btn-primary' : 'btn-outline-secondary'" @click="load(p)">{{ p }}</button>
          <button class="btn btn-outline-secondary" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" tabindex="-1" ref="modalEl">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Segment</h5>
            <button type="button" class="btn-close" @click="modalInstance.hide()"></button>
          </div>
          <div class="modal-body" v-if="editing">
            <div class="section-label"><i class="bi bi-lock-fill me-1"></i>Fixed — from source data</div>
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label small text-muted">Rig Name</label>
                <input :value="editing.rig_name" class="form-control locked" disabled />
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Asset</label>
                <input :value="editing.asset" class="form-control locked" disabled />
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Platform</label>
                <input :value="editing.platform" class="form-control locked" disabled />
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Well Name</label>
                <input :value="editing.well_name" class="form-control locked" disabled />
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted">Start Date</label>
                <input :value="formatDate(editing.start_date)" class="form-control locked" disabled />
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted">Finish Date</label>
                <input :value="formatDate(editing.finish_date)" class="form-control locked" disabled />
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted">Days</label>
                <input :value="editing.days" class="form-control locked" disabled />
              </div>
            </div>

            <div class="section-label"><i class="bi bi-pencil-fill me-1"></i>Editable</div>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label small text-muted">Type</label>
                <select v-model="editForm.type" class="form-select">
                  <option value="">—</option>
                  <option v-for="t in typeOptions" :key="t" :value="t">{{ t }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted">Status</label>
                <select v-model="editForm.status" class="form-select">
                  <option value="">In progress</option>
                  <option value="C">C — Completed</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small text-muted">WI Well</label>
                <input v-model="editForm.wi_well" type="text" class="form-control" />
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Activity</label>
                <input v-model="editForm.activity" type="text" class="form-control" />
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Meters</label>
                <input v-model="editForm.meters" type="number" step="0.01" class="form-control" />
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Oil Gain</label>
                <input v-model="editForm.oil_gain" type="number" step="0.01" class="form-control" />
              </div>
              <div class="col-md-6">
                <label class="form-label small text-muted">Gas Gain</label>
                <input v-model="editForm.gas_gain" type="number" step="0.01" class="form-control" />
              </div>
            </div>

            <div v-if="saveError" class="alert alert-danger py-2 mt-3 mb-0">{{ saveError }}</div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-light" @click="modalInstance.hide()">Cancel</button>
            <button class="btn btn-primary" :disabled="saving" @click="saveEdit">
              <i class="bi bi-check2 me-1"></i>{{ saving ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { Modal } from 'bootstrap';

const route = useRoute();
const planName = computed(() => route.query.plan_name || '');

const BASE_TYPES = ['ST', 'WO', 'DEV', 'EXP', 'Drilling', 'OOC', 'CAP REP', 'Other'];

const groups = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const loading = ref(false);
const search = ref('');
let searchTimeout = null;

const expanded = ref(new Set());

const modalEl = ref(null);
let modalInstance = null;
const editing = ref(null);
const editForm = ref({});
const saving = ref(false);
const saveError = ref('');

// If a record already holds a type outside the standard list, keep it
// selectable instead of silently blanking it on save.
const typeOptions = computed(() => {
  const current = editing.value?.type;
  return current && !BASE_TYPES.includes(current) ? [...BASE_TYPES, current] : BASE_TYPES;
});

async function load(page = 1) {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/rig-details/well-groups', {
      params: { plan_name: planName.value, record_type: 'actual', search: search.value, page },
    });
    groups.value = data.data;
    meta.value = { current_page: data.current_page, last_page: data.last_page, total: data.total };
  } finally {
    loading.value = false;
  }
}

function debouncedSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => load(1), 400);
}

function toggle(wellName) {
  const next = new Set(expanded.value);
  next.has(wellName) ? next.delete(wellName) : next.add(wellName);
  expanded.value = next;
}

function formatDate(date) {
  if (!date) return '—';
  return new Date(date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function isCompleted(status) {
  return (status || '').toString().trim().toUpperCase() === 'C';
}

function openEdit(segment) {
  editing.value = segment;
  editForm.value = {
    type: segment.type || '',
    status: isCompleted(segment.status) ? 'C' : '',
    wi_well: segment.wi_well || '',
    activity: segment.activity || '',
    meters: segment.meters ?? '',
    oil_gain: segment.oil_gain ?? '',
    gas_gain: segment.gas_gain ?? '',
  };
  saveError.value = '';
  modalInstance.show();
}

async function saveEdit() {
  saving.value = true;
  saveError.value = '';
  try {
    // Only the editable fields are sent — the locked ones never leave the page.
    await axios.put(`/api/rig-details/${editing.value.id}`, editForm.value);
    modalInstance.hide();
    await load(meta.value.current_page); // reload; expanded wells stay open
  } catch (e) {
    saveError.value = e.response?.data?.message || 'Failed to save changes.';
  } finally {
    saving.value = false;
  }
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
  modalInstance = new Modal(modalEl.value);
  load();
});
</script>

<style scoped>
.well-row { cursor: pointer; }
.well-row:hover { background: #f8fafc; }
.segments-panel { background: #f8fafc; padding: 0.75rem 1.25rem 0.75rem 3rem; border-bottom: 1px solid #e2e8f0; }
.section-label {
  font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
  color: #64748b; margin-bottom: 0.75rem; padding-bottom: 0.35rem; border-bottom: 1px solid #eef2f7;
}
.locked { background: #f1f5f9; color: #64748b; cursor: not-allowed; }
</style>
