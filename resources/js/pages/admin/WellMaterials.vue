<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
      <div>
        <router-link :to="{ name: 'admin-material-items' }" class="btn btn-outline-secondary">
        <i class="bi bi-list-check me-1"></i>Manage Items
        </router-link>

        <h4 class="mb-1">Well Materials Consumable</h4>
        <p class="text-muted small mb-0">Materials matched against each well's planned work schedule</p>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" @click="downloadTemplate" :disabled="!filteredTemplateRows.length">
          <i class="bi bi-file-earmark-arrow-down-fill me-1"></i>Download Template
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importMaterialsModal">
          <i class="bi bi-cloud-upload-fill me-1"></i>Import Materials
        </button>
      </div>
    </div>

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
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">{{ asset || 'Select an asset' }}</h6>
        <div class="d-flex align-items-center gap-3">
          <span class="text-muted small">{{ filteredRows.length }} records</span>
          <button class="btn btn-sm btn-outline-success" :disabled="!filteredRows.length" @click="exportToExcel">
            <i class="bi bi-file-earmark-excel-fill me-1"></i>Export Materials
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Asset</th>
              <th>Well Name</th>
              <!-- <th>HPTP</th> -->
              <th>HPHT</th>
              <th>Type</th>
              <th>Item Name</th>
              <th class="text-end">Quantity</th>
              <th>Meters</th>
              <th>Well Type</th>
              <th>Start Date</th>
              <th>Finish Date</th>
              <th class="text-center">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td colspan="11" class="text-center text-muted py-4">Loading...</td></tr>
            <tr v-else-if="!pagedRows.length"><td colspan="11" class="text-center text-muted py-4">No matching records — import materials for this selection to see them here</td></tr>
            <tr v-for="r in pagedRows" :key="r.material_id + '-' + r.start_date">
              <td>{{ r.asset_name }}</td>
              <td class="fw-semibold">{{ r.well_name }}</td>
              <td>
                <!-- <span class="badge" :class="r.hptp === 'HPTP' ? 'bg-danger-subtle text-danger-emphasis' : 'bg-light text-dark border'">
                  {{ r.hptp }}
                </span> -->

                <span class="badge" :class="r.hptp === 'HPHT' ? 'bg-danger-subtle text-danger-emphasis' : 'bg-light text-dark border'">
                {{ r.hptp }}
                </span>
              </td>
              <td>{{ r.type || '—' }}</td>
              <td>{{ r.item_name }}</td>
              <td class="text-end fw-semibold">{{ r.quantity ?? '—' }}</td>
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
          <button v-for="p in pageNumbers" :key="p" class="btn" :class="p === page ? 'btn-primary' : 'btn-outline-secondary'" @click="page = p">{{ p }}</button>
          <button class="btn btn-outline-secondary" :disabled="page >= totalPages" @click="page++"><i class="bi bi-chevron-right"></i></button>
        </div>
      </div>
    </div>

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
                <div class="fw-semibold small">Expected format</div>
                <div class="text-muted small">The template you downloaded, with material item columns added — each item column's cell values become quantities.</div>
              </div>
            </div>

            <label class="form-label small text-muted mb-1">Asset</label>
            <select v-model="importAsset" class="form-select mb-3">
              <option value="" disabled>Select asset...</option>
              <option v-for="a in assetOptions" :key="a" :value="a">{{ a }}</option>
            </select>

            <label class="upload-drop" :class="{ 'has-file': importFile }">
              <input type="file" accept=".xlsx,.xls" class="d-none" @change="onImportFileChange" />
              <i class="bi bi-file-earmark-spreadsheet-fill fs-1" :class="importFile ? 'text-primary' : 'text-muted'"></i>
              <div class="mt-2 fw-semibold">{{ importFile ? importFile.name : 'Choose an Excel file' }}</div>
              <div class="text-muted small">.xlsx or .xls</div>
            </label>

            <div class="form-check mt-3">
              <input class="form-check-input" type="checkbox" id="replaceCheck" v-model="importReplace">
              <label class="form-check-label small" for="replaceCheck">
                Replace existing materials for this asset (recommended for a fresh re-import)
              </label>
            </div>

            <div v-if="importMessage" class="alert py-2 mt-3 mb-0" :class="importSuccess ? 'alert-success' : 'alert-danger'">
              {{ importMessage }}
            </div>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" :disabled="!importFile || !importAsset || importing" @click="submitImport">
              <i class="bi bi-cloud-upload-fill me-1"></i>
              {{ importing ? 'Importing...' : 'Import' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import MultiSelectDropdown from '../../components/MultiSelectDropdown.vue';
import { quartersBetween } from '../../utils/fiscalPeriods';
import * as XLSX from 'xlsx';
import { Modal } from 'bootstrap';

// import { FIXED_ITEM_NAMES } from '../../utils/materialItems';

import ExcelJS from 'exceljs';


const materialItems = ref([]); // master list for current `asset`


const assetOptions = ref([]);
const wellOptions = ref([]);
const asset = ref('');
const selectedWells = ref([]);
const selectedQuarters = ref([]);

const templateRows = ref([]); // from rig_details (planned) — drives quarter options + template download
const rawRows = ref([]);      // from well_materials JOIN rig_details — drives the results table

const loading = ref(false);
const loadError = ref('');
const page = ref(1);
const perPage = 15;

const importFile = ref(null);
const importAsset = ref('');
const importReplace = ref(true);
const importing = ref(false);
const importMessage = ref('');
const importSuccess = ref(false);

function assertArray(data, context) {
  if (!Array.isArray(data)) {
    throw new Error(`${context} did not return a valid list — check the API route exists.`);
  }
  return data;
}

async function loadAssets() {
  const { data } = await axios.get('/api/well-materials/assets');
  assetOptions.value = assertArray(data, 'Asset list');
  if (assetOptions.value.length) {
    if (!asset.value) asset.value = assetOptions.value[0];
    if (!importAsset.value) importAsset.value = assetOptions.value[0];
  }
}

async function loadWells() {
  if (!asset.value) { wellOptions.value = []; return; }
  const { data } = await axios.get('/api/well-materials/wells', { params: { asset: asset.value } });
  wellOptions.value = assertArray(data, 'Well list');
}

async function loadTemplateRows() {
  if (!asset.value) { templateRows.value = []; return; }
  const { data } = await axios.get('/api/well-materials/template', { params: { asset: asset.value } });
  templateRows.value = assertArray(data, 'Template rows');
}

async function loadReport() {
  if (!asset.value) { rawRows.value = []; return; }
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

async function refreshAll() {
  loadError.value = '';
  try {
    // await Promise.all([loadWells(), loadTemplateRows()]);
    await Promise.all([loadWells(), loadTemplateRows(), loadMaterialItems()]);
    await loadReport();
  } catch (e) {
    loadError.value = e.message;
  }
}




// async function loadMaterialItems() {
//   if (!asset.value) { materialItems.value = []; return; }
//   const { data } = await axios.get('/api/material-items', { params: { asset: asset.value } });
//   materialItems.value = assertArray(data, 'Material items');
// }

async function loadMaterialItems() {
  const { data } = await axios.get('/api/material-items');
  materialItems.value = assertArray(data, 'Material items');
}




function onAssetChange() {
  selectedWells.value = [];
  selectedQuarters.value = [];
  refreshAll();
}

watch(selectedWells, () => { loadReport(); });
watch(selectedQuarters, () => { page.value = 1; });

// Quarter options come from rig_details dates (via templateRows) — available
// even before any materials have been imported for this asset.
const templateRowsForSelectedWells = computed(() =>
  selectedWells.value.length
    ? templateRows.value.filter((r) => selectedWells.value.includes(r.well_name))
    : templateRows.value
);

const quarterOptions = computed(() => {
  const dates = templateRowsForSelectedWells.value.map((r) => new Date(r.start_date)).filter((d) => !isNaN(d));
  if (!dates.length) return [];
  return quartersBetween(new Date(Math.min(...dates)), new Date(Math.max(...dates)));
});

function withinSelectedQuarters(dateValue) {
  if (!selectedQuarters.value.length) return true;
  const ranges = quarterOptions.value.filter((q) => selectedQuarters.value.includes(q.label));
  const d = new Date(dateValue);
  return ranges.some((q) => d >= q.from && d <= q.to);
}

const filteredTemplateRows = computed(() =>
  templateRowsForSelectedWells.value.filter((r) => withinSelectedQuarters(r.start_date))
);

const filteredRows = computed(() =>
  rawRows.value.filter((r) => withinSelectedQuarters(r.start_date))
);

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
    await refreshAll();
  } catch (e) {
    loadError.value = e.message;
  }
});



// function downloadTemplate() {
//   if (!filteredTemplateRows.value.length) return;

//   const itemNames = materialItems.value.map((i) => i.item_name);

//   const rows = filteredTemplateRows.value.map((r) => {
//     const row = {
//       'Well Name': r.well_name, 'Input Type': r.type || '', 'Well Type': r.wi_well || '',
//       'HPHT / Non-HPHT': '', 'Meters': r.meters ?? '',
//     };
//     itemNames.forEach((name) => { row[name] = ''; });
//     return row;
//   });

//   const worksheet = XLSX.utils.json_to_sheet(rows);
//   worksheet['!cols'] = [{ wch: 16 }, { wch: 12 }, { wch: 12 }, { wch: 16 }, { wch: 10 }, ...itemNames.map(() => ({ wch: 16 }))];

//   const workbook = XLSX.utils.book_new();
//   XLSX.utils.book_append_sheet(workbook, worksheet, asset.value);
//   XLSX.writeFile(workbook, `well-materials-template-${asset.value}.xlsx`);
// }


async function downloadWorkbook(headerDefs, dataRows, sheetTitle, filename) {
  const workbook = new ExcelJS.Workbook();
  const sheet = workbook.addWorksheet(sheetTitle);

  sheet.columns = headerDefs.map((h) => ({ header: h.label, key: h.key, width: h.width || 16 }));

  const headerRow = sheet.getRow(1);
  headerRow.eachCell((cell) => {
    cell.font = { bold: true, color: { argb: 'FFFFFFFF' } };
    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1F4E78' } };
    cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
    cell.border = { bottom: { style: 'thin', color: { argb: 'FFB0BEC5' } } };
  });
  headerRow.height = 32;

  dataRows.forEach((row) => sheet.addRow(row));

  sheet.views = [{ state: 'frozen', xSplit: 5, ySplit: 1 }]; // freeze Well/Type/Type/HPHT/Meters + header row
  sheet.autoFilter = { from: { row: 1, column: 1 }, to: { row: 1, column: headerDefs.length } };

  const buffer = await workbook.xlsx.writeBuffer();
  const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

async function downloadTemplate() {
  if (!filteredTemplateRows.value.length) return;

  const fixedHeaders = [
    { key: 'well_name', label: 'Well Name', width: 18 },
    { key: 'input_type', label: 'Input Type', width: 12 },
    { key: 'well_type', label: 'Well Type', width: 12 },
    { key: 'hpht', label: 'HPHT / Non-HPHT', width: 16 },
    { key: 'meters', label: 'Drilling Meterage', width: 14 },
  ];
  const itemHeaders = materialItems.value.map((i) => ({ key: `item_${i.id}`, label: i.item_name, width: 18 }));

  const rows = filteredTemplateRows.value.map((r) => {
    const row = {
      well_name: r.well_name, input_type: r.type || '', well_type: r.wi_well || '',
      hpht: '', meters: r.meters ?? '',
    };
    itemHeaders.forEach((h) => { row[h.key] = ''; });
    return row;
  });

  await downloadWorkbook([...fixedHeaders, ...itemHeaders], rows, asset.value, `well-materials-template-${asset.value}.xlsx`);
}


function onImportFileChange(e) {
  importFile.value = e.target.files[0] || null;
  importMessage.value = '';
}

// async function submitImport() {
//   if (!importFile.value || !importAsset.value) return;
//   importing.value = true;
//   importMessage.value = '';

// //   importSuccess.value = true;
// //   importMessage.value = data.new_items?.length
// //   ? `Imported ${data.rows_imported} item rows. ${data.new_items.length} new item name(s) were added to the master list — review them under "Manage Items".`
// //   : `Imported ${data.rows_imported} item rows successfully.`;

//   const payload = new FormData();
//   payload.append('file', importFile.value);
//   payload.append('asset', importAsset.value);
//   payload.append('replace', importReplace.value ? '1' : '0');

//   try {
//     const { data } = await axios.post('/api/well-materials/import', payload);
//     importSuccess.value = true;
//     importMessage.value = `Imported ${data.rows_imported} item rows successfully.`;

//     if (asset.value === importAsset.value) {
//       await refreshAll();
//     }

//     setTimeout(() => {
//       Modal.getInstance(document.getElementById('importMaterialsModal'))?.hide();
//       importFile.value = null;
//       importMessage.value = '';
//     }, 1200);
//   } catch (e) {
//     importSuccess.value = false;
//     importMessage.value = e.response?.data?.message || 'Import failed. Please check the file and try again.';
//   } finally {
//     importing.value = false;
//   }
// }




// function exportToExcel() {
//   const byWell = new Map();

//   filteredRows.value.forEach((r) => {
//     if (!byWell.has(r.well_name)) {
//       byWell.set(r.well_name, {
//         well_name: r.well_name, type: r.type, wi_well: r.wi_well, meters: r.meters,
//         isHpht: false, items: {}, starts: [], finishes: [], statuses: [],
//       });
//     }
//     const entry = byWell.get(r.well_name);
//     if (r.hptp === 'HPHT') entry.isHpht = true;

//     // Overwrite, not sum — a well material row can join against several
//     // rig_details segments for the same well, which would otherwise
//     // multiply the same quantity value once per matching segment.
//     entry.items[r.item_name] = Number(r.quantity || 0);

//     if (r.start_date) entry.starts.push(new Date(r.start_date));
//     if (r.finish_date) entry.finishes.push(new Date(r.finish_date));
//     if (r.status) entry.statuses.push(r.status.toString().trim().toUpperCase());
//   });

//   const wells = [...byWell.values()];

//   // Order item columns by the master list for this asset; anything present
//   // in the data but not yet in the master list (legacy rows) gets appended.
//   const masterOrder = materialItems.value.map((i) => i.item_name);
//   const dataItemNames = [...new Set(filteredRows.value.map((r) => r.item_name))];
//   const itemNames = [
//     ...masterOrder.filter((n) => dataItemNames.includes(n)),
//     ...dataItemNames.filter((n) => !masterOrder.includes(n)),
//   ];

//   const exportRows = wells.map((w) => {
//     const overallStart = w.starts.length ? new Date(Math.min(...w.starts)) : null;
//     const overallFinish = w.finishes.length ? new Date(Math.max(...w.finishes)) : null;
//     const allCompleted = w.statuses.length > 0 && w.statuses.every((s) => s === 'C');

//     const row = {
//       'Well Name': w.well_name, 'Input Type': w.type || '', 'Well Type': w.wi_well || '',
//       'HPHT / Non-HPHT': w.isHpht ? 'HPHT' : 'Non-HPHT', 'Meters': w.meters ?? '',
//     };
//     itemNames.forEach((name) => { row[name] = w.items[name] ?? ''; });
//     row['Start Date'] = formatDate(overallStart);
//     row['Finish Date'] = formatDate(overallFinish);
//     row['Status'] = allCompleted ? 'Completed' : (w.statuses.length ? 'In Progress' : '');
//     return row;
//   });

//   const worksheet = XLSX.utils.json_to_sheet(exportRows);
//   worksheet['!cols'] = [
//     { wch: 16 }, { wch: 12 }, { wch: 12 }, { wch: 16 }, { wch: 10 },
//     ...itemNames.map(() => ({ wch: 16 })), { wch: 14 }, { wch: 14 }, { wch: 12 },
//   ];

//   const workbook = XLSX.utils.book_new();
//   XLSX.utils.book_append_sheet(workbook, worksheet, 'Well Materials');

//   const stamp = new Date().toISOString().slice(0, 10);
//   XLSX.writeFile(workbook, `well-materials-${asset.value || 'all'}-${stamp}.xlsx`);
// }


async function submitImport() {
  if (!importFile.value || !importAsset.value) return;
  importing.value = true;
  importMessage.value = '';

  const payload = new FormData();
  payload.append('file', importFile.value);
  payload.append('asset', importAsset.value);
  payload.append('replace', importReplace.value ? '1' : '0');

  try {
    const { data } = await axios.post('/api/well-materials/import', payload);
    importSuccess.value = true;
    importMessage.value = data.new_items?.length
      ? `Imported ${data.rows_imported} item rows. ${data.new_items.length} new item name(s) were added to the shared list.`
      : `Imported ${data.rows_imported} item rows successfully.`;

    // Switch the visible filter to whatever was just imported, then reload
    // everything — so the result is on screen immediately, no manual
    // refresh or re-selecting the asset required.
    asset.value = importAsset.value;
    selectedWells.value = [];
    selectedQuarters.value = [];
    await refreshAll();

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

async function exportToExcel() {
  const byWell = new Map();

  filteredRows.value.forEach((r) => {
    if (!byWell.has(r.well_name)) {
      byWell.set(r.well_name, {
        well_name: r.well_name, type: r.type, wi_well: r.wi_well, meters: r.meters,
        isHpht: false, items: {}, starts: [], finishes: [], statuses: [],
      });
    }
    const entry = byWell.get(r.well_name);
    if (r.hptp === 'HPHT') entry.isHpht = true;
    entry.items[r.item_name] = Number(r.quantity || 0); // overwrite, never sum — see note below
    if (r.start_date) entry.starts.push(new Date(r.start_date));
    if (r.finish_date) entry.finishes.push(new Date(r.finish_date));
    if (r.status) entry.statuses.push(r.status.toString().trim().toUpperCase());
  });

  const wells = [...byWell.values()];

  const masterOrder = materialItems.value.map((i) => i.item_name);
  const dataItemNames = [...new Set(filteredRows.value.map((r) => r.item_name))];
  const itemNames = [
    ...masterOrder.filter((n) => dataItemNames.includes(n)),
    ...dataItemNames.filter((n) => !masterOrder.includes(n)),
  ];

  const fixedHeaders = [
    { key: 'well_name', label: 'Well Name', width: 18 },
    { key: 'input_type', label: 'Input Type', width: 12 },
    { key: 'well_type', label: 'Well Type', width: 12 },
    { key: 'hpht', label: 'HPHT / Non-HPHT', width: 16 },
    { key: 'meters', label: 'Drilling Meterage', width: 14 },
    { key: 'start_date', label: 'Start Date', width: 14 },
    { key: 'finish_date', label: 'Finish Date', width: 14 },
    { key: 'status', label: 'Status', width: 12 },
  ];
  const itemHeaders = itemNames.map((name, idx) => ({ key: `item_${idx}`, label: name, width: 18 }));

  const rows = wells.map((w) => {
    const overallStart = w.starts.length ? new Date(Math.min(...w.starts)) : null;
    const overallFinish = w.finishes.length ? new Date(Math.max(...w.finishes)) : null;
    const allCompleted = w.statuses.length > 0 && w.statuses.every((s) => s === 'C');

    const row = {
      well_name: w.well_name, input_type: w.type || '', well_type: w.wi_well || '',
      hpht: w.isHpht ? 'HPHT' : 'Non-HPHT', meters: w.meters ?? '',
      start_date: formatDate(overallStart), finish_date: formatDate(overallFinish),
      status: allCompleted ? 'Completed' : (w.statuses.length ? 'In Progress' : ''),
    };
    itemHeaders.forEach((h, idx) => { row[h.key] = w.items[itemNames[idx]] ?? ''; });
    return row;
  });

  const stamp = new Date().toISOString().slice(0, 10);
  await downloadWorkbook([...fixedHeaders, ...itemHeaders], rows, 'Well Materials', `well-materials-${asset.value || 'all'}-${stamp}.xlsx`);
}

</script>

<style scoped>
.filter-card { border-radius: 0.9rem; }
.import-hint {
  display: flex; gap: 0.75rem; background: #eff6ff;
  border: 1px solid #dbeafe; border-radius: 0.6rem; padding: 0.75rem 1rem;
}
.import-hint-icon { color: #2563eb; font-size: 1.1rem; margin-top: 1px; }
.upload-drop {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  text-align: center; border: 2px dashed #cbd5e1; border-radius: 0.75rem;
  padding: 2rem 1rem; cursor: pointer; transition: 0.15s ease;
}
.upload-drop:hover { border-color: #94a3b8; background: #f8fafc; }
.upload-drop.has-file { border-color: #2563eb; background: #eff6ff; }
</style>
