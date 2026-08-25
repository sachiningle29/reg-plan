<template>
  <div>
    <!-- <div class="mb-4">
      <h4 class="mb-1">Well Materials Consumable</h4>
      <p class="text-muted small mb-0">Materials matched against each well's planned work schedule</p>
    </div> -->

    <!-- added logic export import on 24-8-26 for import well-materials -->
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Well Materials Consumable</h4>
            <p class="text-muted small mb-0">Materials matched against each well's planned work schedule</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importMaterialsModal">
            <i class="bi bi-cloud-upload-fill me-1"></i>Import Materials
        </button>
    </div>
    <!-- end -->

    <div v-if="loadError" class="alert alert-danger d-flex align-items-center gap-2">
      <i class="bi bi-exclamation-triangle-fill"></i>
      {{ loadError }}
    </div>

    <div class="card border-0 shadow-sm mb-3 filter-card">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Asset</label>
            <select v-model="asset" class="form-select" @change="onAssetChange">
              <option v-for="a in assetOptions" :key="a" :value="a">{{ a }}</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Wells</label>
            <MultiSelectDropdown v-model="selectedWells" :options="wellOptions" placeholder="All Wells" />
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Quarter (Start Date)</label>
            <MultiSelectDropdown v-model="selectedQuarters" :options="quarterOptions.map(q => q.label)" placeholder="All Quarters" />
          </div>
          <div class="col-md-1 d-flex align-items-end">
            <button class="btn btn-outline-secondary w-100" @click="resetFilters" title="Reset filters">
              <i class="bi bi-arrow-counterclockwise"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <!-- <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">{{ asset || 'Select an asset' }}</h6>
        <span class="text-muted small">{{ filteredRows.length }} records</span>
      </div> -->


      <!-- added logic export button on 24-8-26 for export well-materials -->
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">{{ asset || 'Select an asset' }}</h6>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small">{{ filteredRows.length }} records</span>
            <button
            class="btn btn-sm btn-outline-success"
            :disabled="!filteredRows.length"
            @click="exportToExcel"
            >
            <i class="bi bi-file-earmark-excel-fill me-1"></i>Export Materials
            </button>
        </div>
      </div>
      <!-- end -->




      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Asset</th>
              <th>Well Name</th>
              <th>HPTP</th>
              <th>Type</th>
              <th>Item Name</th>
              <th >Meters</th>

              <th>Well Type</th>
              <th>Start Date</th>
              <th>Finish Date</th>

              <th class="text-center">Status</th>

            </tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
            <tr v-else-if="!pagedRows.length"><td colspan="7" class="text-center text-muted py-4">No matching records</td></tr>
            <tr v-for="r in pagedRows" :key="r.material_id + '-' + r.start_date">
              <td>{{ r.asset_name }}</td>
              <td class="fw-semibold">{{ r.well_name }}</td>
              <td>
                <span class="badge" :class="r.hptp === 'HPTP' ? 'bg-danger-subtle text-danger-emphasis' : 'bg-light text-dark border'">
                  {{ r.hptp }}
                </span>
              </td>
              <td>{{ r.type || '—' }}</td>
              <td>{{ r.item_name }}</td>
              <td>{{ r.meters ?? '—' }}</td>
              <td>{{ r.wi_well || '—' }}</td>

              <td>{{ formatDate(r.start_date) }}</td>

              <td>{{ formatDate(r.finish_date) }}</td>

              <td class="text-center">
                 <span class="badge" :class="isCompleted(r.status) ? 'bg-success' : 'bg-light text-dark border'">
                  {{ isCompleted(r.status) ? 'Completed' : (r.status || '—') }}
                 </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="card-footer bg-white d-flex justify-content-between align-items-center" v-if="totalPages > 1">
        <span class="text-muted small">Page {{ page }} of {{ totalPages }}</span>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-secondary" :disabled="page <= 1" @click="page--"><i class="bi bi-chevron-left"></i></button>
          <button
            v-for="p in pageNumbers" :key="p"
            class="btn" :class="p === page ? 'btn-primary' : 'btn-outline-secondary'"
            @click="page = p"
          >{{ p }}</button>
          <button class="btn btn-outline-secondary" :disabled="page >= totalPages" @click="page++"><i class="bi bi-chevron-right"></i></button>
        </div>
      </div>
    </div>


    <!-- added logic to import excel records of well material -->
    <div class="modal fade" id="importMaterialsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title"><i class="bi bi-box-seam-fill me-2"></i>Import Materials</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="import-hint mb-4">
                <div class="import-hint-icon"><i class="bi bi-info-circle-fill"></i></div>
                <div>
                    <div class="fw-semibold small">Expected columns</div>
                    <div class="text-muted small">Asset Name · Well Name · HPTP · Item Name — header names are matched flexibly, so close variants work too.</div>
                </div>
                </div>

                <label class="upload-drop" :class="{ 'has-file': importFile }">
                <input type="file" accept=".xlsx,.xls" class="d-none" @change="onImportFileChange" />
                <i class="bi bi-file-earmark-spreadsheet-fill fs-1" :class="importFile ? 'text-primary' : 'text-muted'"></i>
                <div class="mt-2 fw-semibold">{{ importFile ? importFile.name : 'Choose an Excel file' }}</div>
                <div class="text-muted small">.xlsx or .xls</div>
                </label>

                <div v-if="importMessage" class="alert py-2 mt-3 mb-0" :class="importSuccess ? 'alert-success' : 'alert-danger'">
                {{ importMessage }}
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" :disabled="!importFile || importing" @click="submitImport">
                <i class="bi bi-cloud-upload-fill me-1"></i>
                {{ importing ? 'Importing...' : 'Import' }}
                </button>
            </div>
            </div>
        </div>
    </div>
    <!-- end well material logic -->

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import MultiSelectDropdown from '../../components/MultiSelectDropdown.vue';
import { quartersBetween } from '../../utils/fiscalPeriods';

// started script of logic import excel
import * as XLSX from 'xlsx';

import { Modal } from 'bootstrap';

const importFile = ref(null);
const importing = ref(false);
const importMessage = ref('');
const importSuccess = ref(false);
// endd

const assetOptions = ref([]);
const wellOptions = ref([]);
const asset = ref('');
const selectedWells = ref([]);
const selectedQuarters = ref([]);

const rawRows = ref([]);
const loading = ref(false);
const loadError = ref('');
const page = ref(1);
const perPage = 15;

// Guards against ever silently treating an HTML/error response as data again.
function assertArray(data, context) {
  if (!Array.isArray(data)) {
    throw new Error(`${context} did not return a valid list — check that the API route exists (php artisan route:list --path=well-materials).`);
  }
  return data;
}

async function loadAssets() {
  const { data } = await axios.get('/api/well-materials/assets');
  assetOptions.value = assertArray(data, 'Asset list');
  if (assetOptions.value.length) asset.value = assetOptions.value[0];
}

async function loadWells() {
  const { data } = await axios.get('/api/well-materials/wells', { params: { asset: asset.value } });
  wellOptions.value = assertArray(data, 'Well list');
}

function onAssetChange() {
  selectedWells.value = [];
  loadWells().catch((e) => { loadError.value = e.message; });
  loadReport();
}

async function loadReport() {
  if (!asset.value) return;
  loading.value = true;
  try {
    const { data } = await axios.get('/api/well-materials/report', {
      params: { asset: asset.value, well_names: selectedWells.value },
    });
    rawRows.value = assertArray(data, 'Materials report');
    page.value = 1;
  } catch (e) {
    loadError.value = e.message || 'Failed to load report data.';
    rawRows.value = [];
  } finally {
    loading.value = false;
  }
}

watch([asset, selectedWells], loadReport);

const quarterOptions = computed(() => {
  const dates = rawRows.value.map((r) => new Date(r.start_date)).filter((d) => !isNaN(d));
  if (!dates.length) return [];
  return quartersBetween(new Date(Math.min(...dates)), new Date(Math.max(...dates)));
});

const filteredRows = computed(() => {
  if (!selectedQuarters.value.length) return rawRows.value;
  const ranges = quarterOptions.value.filter((q) => selectedQuarters.value.includes(q.label));
  return rawRows.value.filter((r) => {
    const d = new Date(r.start_date);
    return ranges.some((q) => d >= q.from && d <= q.to);
  });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / perPage)));
const pagedRows = computed(() => {
  const start = (page.value - 1) * perPage;
  return filteredRows.value.slice(start, start + perPage);
});
const pageNumbers = computed(() => {
  const start = Math.max(1, page.value - 2);
  const end = Math.min(totalPages.value, start + 4);
  const arr = [];
  for (let i = start; i <= end; i++) arr.push(i);
  return arr;
});

watch(selectedQuarters, () => { page.value = 1; });

function resetFilters() {
  selectedWells.value = [];
  selectedQuarters.value = [];
}

function formatDate(date) {
  if (!date) return '—';
  return new Date(date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function isCompleted(status) {
  return (status || '').toString().trim().toUpperCase() === 'C';
}

onMounted(async () => {
  loadError.value = '';
  try {
    await loadAssets();
    await loadWells();
    await loadReport();
  } catch (e) {
    loadError.value = e.message;
  }
});


//started fun logic of import
function onImportFileChange(e) {
  importFile.value = e.target.files[0] || null;
  importMessage.value = '';
}

async function submitImport() {
  if (!importFile.value) return;
  importing.value = true;
  importMessage.value = '';

  const payload = new FormData();
  payload.append('file', importFile.value);

  try {
    const { data } = await axios.post('/api/well-materials/import', payload);
    importSuccess.value = true;
    importMessage.value = `Imported ${data.rows_imported} rows successfully.`;

    // Refresh everything on screen with the newly imported data
    await loadAssets();
    await loadWells();
    await loadReport();

    setTimeout(() => {
      Modal.getInstance(document.getElementById('importMaterialsModal'))?.hide();
      importFile.value = null;
      importMessage.value = '';
    }, 1200);
  } catch (e) {
    importSuccess.value = false;
    importMessage.value = e.response?.data?.message || 'Import failed. Please check the file and try again.';
  } finally {
    importing.value = false;
  }
}
//end import fun logic.

//added logic of export well material
function exportToExcel() {
  const exportRows = filteredRows.value.map((r) => ({
    'Asset': r.asset_name,
    'Well Name': r.well_name,
    'HPTP': r.hptp,
    'Type': r.type || '',
    'Item Name': r.item_name,
    'Meters': r.meters ?? '',
    'WI Well': r.wi_well || '',
    'Start Date': formatDate(r.start_date),
    'Finish Date': formatDate(r.finish_date),
    'Status': isCompleted(r.status) ? 'Completed' : (r.status || ''),
  }));

  const worksheet = XLSX.utils.json_to_sheet(exportRows);
  worksheet['!cols'] = [
    { wch: 10 }, { wch: 16 }, { wch: 10 }, { wch: 10 }, { wch: 20 },
    { wch: 10 }, { wch: 10 }, { wch: 14 }, { wch: 14 }, { wch: 12 },
  ];

  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, worksheet, 'Well Materials');

  const stamp = new Date().toISOString().slice(0, 10);
  const filename = `well-materials-${asset.value || 'all'}-${stamp}.xlsx`;
  XLSX.writeFile(workbook, filename);
}
// ended


</script>

<style scoped>
.filter-card { border-radius: 0.9rem; }




/* added style to import logic */
.import-hint {
  display: flex;
  gap: 0.75rem;
  background: #eff6ff;
  border: 1px solid #dbeafe;
  border-radius: 0.6rem;
  padding: 0.75rem 1rem;
}
.import-hint-icon { color: #2563eb; font-size: 1.1rem; margin-top: 1px; }
.upload-drop {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  border: 2px dashed #cbd5e1;
  border-radius: 0.75rem;
  padding: 2rem 1rem;
  cursor: pointer;
  transition: 0.15s ease;
}
.upload-drop:hover { border-color: #94a3b8; background: #f8fafc; }
.upload-drop.has-file { border-color: #2563eb; background: #eff6ff; }
/* end */




</style>
