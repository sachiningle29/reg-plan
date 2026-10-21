<template>
  <div>
    <h4 class="mb-4">Import Plan Excel</h4>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <form @submit.prevent="submitImport">
          <div class="row g-3">
            <div class="col-md-5">
              <label class="form-label">Plan Name</label>
              <input v-model="form.plan_name" type="text" class="form-control"
                     placeholder="e.g. RE 2026-27 DR3" required />
            </div>

            <div class="col-md-3">
              <label class="form-label">Data Type</label>
              <select v-model="form.record_type" class="form-select" required>
                <option value="" disabled>Select...</option>
                <option value="planned">Planned</option>
                <option value="actual">Actual</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Excel File</label>
              <input type="file" class="form-control" accept=".xlsx,.xls" @change="onFileChange" required />
            </div>
          </div>

          <div v-if="message" class="alert mt-3 py-2" :class="success ? 'alert-success' : 'alert-danger'">
            {{ message }}
          </div>

          <button type="submit" class="btn btn-primary mt-3" :disabled="loading">
            <i class="bi bi-cloud-upload-fill me-1"></i>
            {{ loading ? 'Importing...' : 'Import' }}
          </button>
        </form>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h6 class="mb-3">Existing Plans</h6>

        <table class="table table-sm align-middle">
        <thead class="table-light">
            <tr>
            <th>Plan Name</th>
            <th>Type</th>
            <th>Records</th>
            <th class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="p in plans" :key="p.plan_name + p.record_type">
            <td>{{ p.plan_name }}</td>
            <td>
                <span class="badge" :class="p.record_type === 'planned' ? 'bg-info' : 'bg-success'">
                {{ p.record_type }}
                </span>
            </td>
            <td>{{ p.total }}</td>
            <td class="text-center">
                <router-link
                v-if="p.record_type === 'actual'"
                :to="{ name: 'admin-plan-edit', query: { plan_name: p.plan_name, record_type: p.record_type } }"
                class="btn btn-sm btn-outline-primary"
                title="Edit records"
                >
                <i class="bi bi-pencil-square"></i>
                </router-link>
                <span v-else class="text-muted small">—</span>
            </td>
            </tr>
            <tr v-if="!plans.length">
            <td colspan="4" class="text-center text-muted py-3">No plans imported yet</td>
            </tr>
        </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const form = ref({ plan_name: '', record_type: '', file: null });
const message = ref('');
const success = ref(false);
const loading = ref(false);
const plans = ref([]);

function onFileChange(e) {
  form.value.file = e.target.files[0];
}

async function submitImport() {
  message.value = '';
  loading.value = true;

  const payload = new FormData();
  payload.append('plan_name', form.value.plan_name);
  payload.append('record_type', form.value.record_type);
  payload.append('file', form.value.file);

  try {
    const { data } = await axios.post('/api/plans/import', payload);
    success.value = true;
    message.value = `Imported ${data.rows_imported} rows successfully.`;
    await loadPlans();
  } catch (e) {
    success.value = false;
    message.value = e.response?.data?.message || 'Import failed.';
  } finally {
    loading.value = false;
  }
}

async function loadPlans() {
  const { data } = await axios.get('/api/plans');
  plans.value = data;
}

onMounted(loadPlans);
</script>
