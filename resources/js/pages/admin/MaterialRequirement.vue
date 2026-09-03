<template>
  <div>
    <div class="mb-4">
      <h4 class="mb-1">Material Requirement</h4>
      <p class="text-muted small mb-0">Monthwise quantity requirement per material, for planned work only</p>
    </div>

    <div class="card border-0 shadow-sm mb-3 filter-card">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Asset</label>
            <select v-model="asset" class="form-select" @change="load">
              <option v-for="a in assetOptions" :key="a" :value="a">{{ a }}</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div v-if="loadError" class="alert alert-danger d-flex align-items-center gap-2">
      <i class="bi bi-exclamation-triangle-fill"></i>{{ loadError }}
    </div>

    <!-- <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="card border-0 shadow-sm stat-card">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-primary"><i class="bi bi-box-seam-fill"></i></div>
            <div>
              <div class="text-muted small">Materials Tracked</div>
              <div class="fs-4 fw-bold">{{ items.length }}</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-0 shadow-sm stat-card">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-danger"><i class="bi bi-lightning-fill"></i></div>
            <div>
              <div class="text-muted small">HPHT Items</div>
              <div class="fs-4 fw-bold">{{ hphtCount }}</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-0 shadow-sm stat-card">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon bg-success"><i class="bi bi-calculator-fill"></i></div>
            <div>
              <div class="text-muted small">Total Quantity Required</div>
              <div class="fs-4 fw-bold">{{ grandTotal.toLocaleString() }}</div>
            </div>
          </div>
        </div>
      </div>
    </div> -->

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">{{ asset || 'Select an asset' }} — Monthwise Requirement</h6>
        <span class="text-muted small">{{ months.length }} months</span>
      </div>

      <div v-if="loading" class="text-center text-muted py-5">Loading...</div>
      <div v-else-if="!items.length" class="text-center text-muted py-5">
        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
        No material requirements found for this asset
      </div>

      <div v-else class="pivot-scroll">
        <table class="pivot-table mb-0">
          <thead>
            <tr>
              <th class="corner-cell">Material</th>
              <th v-for="m in months" :key="m.key" class="text-center month-head">{{ m.label }}</th>
              <th class="text-center total-head">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.name" :class="{ 'hpht-row': item.isHpht }">
              <!-- <td class="material-cell">
                <span v-if="item.isHpht" class="badge bg-danger-subtle text-danger-emphasis me-1">HPHT</span>
                {{ item.name }}
              </td> -->
              <td class="material-cell">{{ item.name }}</td>

              <td v-for="m in months" :key="m.key" class="text-center">
                {{ matrix[item.name]?.[m.key] ? formatQty(matrix[item.name][m.key]) : '' }}
              </td>
              <td class="text-center fw-bold total-cell">{{ formatQty(rowTotals[item.name]) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td class="material-cell fw-bold">Total</td>
              <td v-for="m in months" :key="m.key" class="text-center fw-bold">{{ formatQty(colTotals[m.key]) }}</td>
              <td class="text-center fw-bold total-cell">{{ formatQty(grandTotal) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const assetOptions = ref([]);
const asset = ref('');
const rawRows = ref([]);
const loading = ref(false);
const loadError = ref('');

function assertArray(data, context) {
  if (!Array.isArray(data)) {
    throw new Error(`${context} did not return a valid list.`);
  }
  return data;
}

async function loadAssets() {
  const { data } = await axios.get('/api/well-materials/assets');
  assetOptions.value = assertArray(data, 'Asset list');
  if (assetOptions.value.length) asset.value = assetOptions.value[0];
}

async function load() {
  if (!asset.value) return;
  loading.value = true;
  loadError.value = '';
  try {
    const { data } = await axios.get('/api/material-requirements', { params: { asset: asset.value } });
    rawRows.value = assertArray(data, 'Material requirement data');
  } catch (e) {
    loadError.value = e.message || 'Failed to load material requirements.';
    rawRows.value = [];
  } finally {
    loading.value = false;
  }
}

// --- Build the pivot: months (columns), items (rows), matrix of quantities ---
const months = computed(() => {
  const seen = new Map();
  rawRows.value.forEach((r) => {
    if (!seen.has(r.month_key)) seen.set(r.month_key, { key: r.month_key, label: r.month_label });
  });
  return [...seen.values()].sort((a, b) => a.key.localeCompare(b.key));
});

const items = computed(() => {
  const seen = new Map();
  rawRows.value.forEach((r) => {
    const existing = seen.get(r.item_name);
    const isHpht = !!Number(r.is_hpht);
    if (!existing) {
      seen.set(r.item_name, { name: r.item_name, isHpht });
    } else if (isHpht) {
      existing.isHpht = true;
    }
  });
  return [...seen.values()].sort((a, b) => a.name.localeCompare(b.name));
});

const matrix = computed(() => {
  const m = {};
  rawRows.value.forEach((r) => {
    m[r.item_name] ??= {};
    m[r.item_name][r.month_key] = Number(r.qty);
  });
  return m;
});

const rowTotals = computed(() => {
  const totals = {};
  items.value.forEach((item) => {
    totals[item.name] = months.value.reduce((sum, m) => sum + (matrix.value[item.name]?.[m.key] || 0), 0);
  });
  return totals;
});

const colTotals = computed(() => {
  const totals = {};
  months.value.forEach((m) => {
    totals[m.key] = items.value.reduce((sum, item) => sum + (matrix.value[item.name]?.[m.key] || 0), 0);
  });
  return totals;
});

const grandTotal = computed(() => Object.values(rowTotals.value).reduce((a, b) => a + b, 0));
//const hphtCount = computed(() => items.value.filter((i) => i.isHpht).length);

function formatQty(v) {
  return Number(v).toLocaleString();
}

onMounted(async () => {
  loadError.value = '';
  try {
    await loadAssets();
    await load();
  } catch (e) {
    loadError.value = e.message;
  }
});
</script>

<style scoped>
.filter-card { border-radius: 0.9rem; }
.stat-card { border-radius: 0.9rem; }
.stat-icon {
  width: 44px; height: 44px; border-radius: 0.65rem;
  display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.1rem;
}

.pivot-scroll {
  overflow: auto;
  max-height: 640px;
}

.pivot-table {
  border-collapse: separate;
  border-spacing: 0;
  width: 100%;
  font-size: 0.85rem;
}

.pivot-table th, .pivot-table td {
  padding: 0.55rem 0.85rem;
  border-bottom: 1px solid #eef2f7;
  border-right: 1px solid #f1f5f9;
  white-space: nowrap;
}

.pivot-table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: linear-gradient(180deg, #1e293b, #0f172a);
  color: #fff;
  font-weight: 600;
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  border-bottom: none;
}

.corner-cell {
  position: sticky;
  left: 0;
  z-index: 3;
  text-align: left;
  min-width: 220px;
}

.total-head { background: #1d4ed8 !important; }

.material-cell {
  position: sticky;
  left: 0;
  z-index: 1;
  background: #fff;
  text-align: left;
  font-weight: 500;
  min-width: 220px;
}

tbody tr:nth-child(even) .material-cell,
tbody tr:nth-child(even) td { background: #f8fafc; }

.hpht-row .material-cell { background: #fef2f2; }
.hpht-row:nth-child(even) .material-cell { background: #fee2e2; }

.total-cell { background: #eff6ff; color: #1d4ed8; }

tfoot td {
  position: sticky;
  bottom: 0;
  background: #f1f5f9;
  border-top: 2px solid #cbd5e1;
}
tfoot .material-cell { background: #f1f5f9; }
</style>
