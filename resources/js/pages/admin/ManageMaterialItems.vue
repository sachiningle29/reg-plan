<template>
  <div>
    <div class="mb-4">
      <h4 class="mb-1">Manage Material Items</h4>
      <p class="text-muted small mb-0">Item names used as template/export columns — shared across every asset</p>
    </div>

    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <form class="row g-2 align-items-end" @submit.prevent="addItem">
          <div class="col-md-8">
            <label class="form-label small text-muted mb-1">New Item Name</label>
            <input v-model="newItemName" type="text" class="form-control" placeholder='e.g. 30" X-52,310 ppf,Leop' required />
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100" :disabled="adding">
              <i class="bi bi-plus-lg me-1"></i>Add
            </button>
          </div>
        </form>
        <div v-if="addError" class="alert alert-danger py-2 mt-3 mb-0">{{ addError }}</div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">All Items</h6>
        <span class="text-muted small">{{ items.length }} items</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:70px">Order</th>
              <th>Item Name</th>
              <th class="text-center" style="width:140px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td colspan="3" class="text-center text-muted py-4">Loading...</td></tr>
            <tr v-else-if="!items.length"><td colspan="3" class="text-center text-muted py-4">No items yet</td></tr>
            <tr v-for="item in items" :key="item.id">
              <td>
                <input v-if="editingId === item.id" v-model.number="editForm.sort_order" type="number" class="form-control form-control-sm" style="width:70px" />
                <span v-else class="text-muted">{{ item.sort_order }}</span>
              </td>
              <td>
                <input v-if="editingId === item.id" v-model="editForm.item_name" type="text" class="form-control form-control-sm" />
                <span v-else>{{ item.item_name }}</span>
              </td>
              <td class="text-center">
                <template v-if="editingId === item.id">
                  <button class="btn btn-sm btn-success me-1" @click="saveEdit(item)"><i class="bi bi-check-lg"></i></button>
                  <button class="btn btn-sm btn-light border" @click="cancelEdit"><i class="bi bi-x-lg"></i></button>
                </template>
                <template v-else>
                  <button class="btn btn-sm btn-outline-primary me-1" @click="startEdit(item)"><i class="bi bi-pencil-square"></i></button>
                  <button class="btn btn-sm btn-outline-danger" @click="removeItem(item)"><i class="bi bi-trash3"></i></button>
                </template>
              </td>
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

const items = ref([]);
const loading = ref(false);

const newItemName = ref('');
const adding = ref(false);
const addError = ref('');

const editingId = ref(null);
const editForm = ref({ item_name: '', sort_order: 0 });

async function load() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/material-items');
    items.value = data;
  } finally {
    loading.value = false;
  }
}

async function addItem() {
  adding.value = true;
  addError.value = '';
  try {
    await axios.post('/api/material-items', { item_name: newItemName.value.trim() });
    newItemName.value = '';
    await load();
  } catch (e) {
    addError.value = e.response?.data?.message || 'Could not add item.';
  } finally {
    adding.value = false;
  }
}

function startEdit(item) {
  editingId.value = item.id;
  editForm.value = { item_name: item.item_name, sort_order: item.sort_order };
}
function cancelEdit() { editingId.value = null; }

async function saveEdit(item) {
  await axios.put(`/api/material-items/${item.id}`, editForm.value);
  editingId.value = null;
  await load();
}

async function removeItem(item) {
  if (!confirm(`Remove "${item.item_name}" from the shared list? Any recorded quantities across all assets stay untouched.`)) return;
  await axios.delete(`/api/material-items/${item.id}`);
  await load();
}

onMounted(load);
</script>
