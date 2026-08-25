<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <div>
        <h4 class="mb-1">Manage Users</h4>
        <p class="text-muted small mb-0">{{ meta.total ?? 0 }} accounts</p>
      </div>
      <router-link :to="{ name: 'admin-users-create' }" class="btn btn-primary">
        <i class="bi bi-person-plus-fill me-1"></i>Add User
      </router-link>
    </div>

    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <select v-model="filters.role" class="form-select" @change="load(1)">
              <option value="">All Roles</option>
              <option value="admin">Admin</option>
              <option value="user">User</option>
            </select>
          </div>
          <div class="col-md-8">
            <input v-model="filters.search" @input="debouncedSearch" type="text"
                   class="form-control" placeholder="Search name or email..." />
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>User</th>
              <th>Email</th>
              <th>Role</th>
              <th>Joined</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
            <tr v-else-if="!rows.length"><td colspan="5" class="text-center text-muted py-4">No users found</td></tr>
            <tr v-for="u in rows" :key="u.id">
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="avatar-sm" :class="u.role === 'admin' ? 'bg-danger' : 'bg-primary'">
                    {{ initials(u.name) }}
                  </span>
                  <span class="fw-semibold">{{ u.name }}</span>
                </div>
              </td>
              <td class="text-muted">{{ u.email }}</td>
              <td>
                <span class="badge" :class="u.role === 'admin' ? 'bg-danger' : 'bg-primary'">{{ u.role }}</span>
              </td>
              <td class="text-muted small">{{ formatDate(u.created_at) }}</td>
              <td class="text-center">
                <button class="btn btn-sm btn-outline-primary me-1" @click="openEdit(u)">
                  <i class="bi bi-pencil-square"></i>
                </button>
                <button
                  class="btn btn-sm btn-outline-danger"
                  :disabled="u.id === myId"
                  :title="u.id === myId ? 'You cannot delete your own account' : 'Delete user'"
                  @click="openDelete(u)"
                >
                  <i class="bi bi-trash3"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="card-footer bg-white d-flex justify-content-between align-items-center" v-if="meta.last_page > 1">
        <span class="text-muted small">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-secondary" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)"><i class="bi bi-chevron-left"></i></button>
          <button class="btn btn-outline-secondary" :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)"><i class="bi bi-chevron-right"></i></button>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" tabindex="-1" ref="editModalEl">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit User</h5>
            <button type="button" class="btn-close" @click="editModal.hide()"></button>
          </div>
          <div class="modal-body" v-if="editForm">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label small text-muted">Full Name</label>
                <input v-model="editForm.name" type="text" class="form-control" required />
              </div>
              <div class="col-12">
                <label class="form-label small text-muted">Email</label>
                <input v-model="editForm.email" type="email" class="form-control" required />
              </div>
              <div class="col-12">
                <label class="form-label small text-muted">Role</label>
                <select v-model="editForm.role" class="form-select">
                  <option value="user">User</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <div class="col-12">
                <button type="button" class="btn btn-sm btn-link p-0" @click="showPasswordFields = !showPasswordFields">
                  <i class="bi bi-key-fill me-1"></i>{{ showPasswordFields ? 'Cancel password change' : 'Change password' }}
                </button>
              </div>
              <template v-if="showPasswordFields">
                <div class="col-md-6">
                  <label class="form-label small text-muted">New Password</label>
                  <input v-model="editForm.password" type="password" class="form-control" minlength="8" />
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-muted">Confirm Password</label>
                  <input v-model="editForm.password_confirmation" type="password" class="form-control" />
                </div>
              </template>
            </div>
            <div v-if="editError" class="alert alert-danger py-2 mt-3 mb-0">{{ editError }}</div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-light" @click="editModal.hide()">Cancel</button>
            <button class="btn btn-primary" :disabled="savingEdit" @click="saveEdit">
              {{ savingEdit ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirm Modal -->
    <div class="modal fade" tabindex="-1" ref="deleteModalEl">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-body text-center py-4">
            <div class="delete-icon mb-3"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <h5 class="mb-2">Delete this user?</h5>
            <p class="text-muted mb-0">
              <strong>{{ userToDelete?.name }}</strong> ({{ userToDelete?.email }}) will lose access permanently.
              This cannot be undone.
            </p>
            <div v-if="deleteError" class="alert alert-danger py-2 mt-3 mb-0">{{ deleteError }}</div>
          </div>
          <div class="modal-footer justify-content-center border-0 pb-4">
            <button class="btn btn-light" @click="deleteModal.hide()">Cancel</button>
            <button class="btn btn-danger" :disabled="deleting" @click="confirmDelete">
              <i class="bi bi-trash3 me-1"></i>{{ deleting ? 'Deleting...' : 'Delete User' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import { Modal } from 'bootstrap';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const myId = auth.user?.id;

const rows = ref([]);
const meta = ref({});
const loading = ref(false);
const filters = reactive({ role: '', search: '' });
let searchTimeout = null;

const editModalEl = ref(null);
let editModal;
const editForm = ref(null);
const editError = ref('');
const savingEdit = ref(false);
const showPasswordFields = ref(false);

const deleteModalEl = ref(null);
let deleteModal;
const userToDelete = ref(null);
const deleteError = ref('');
const deleting = ref(false);

async function load(page = 1) {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/users', { params: { ...filters, page } });
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

function initials(name) {
  const parts = (name || '').trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return '?';
  return (parts[0][0] + (parts[1]?.[0] || '')).toUpperCase();
}

function formatDate(date) {
  if (!date) return '—';
  return new Date(date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function openEdit(user) {
  editForm.value = {
    id: user.id, name: user.name, email: user.email, role: user.role,
    password: '', password_confirmation: '',
  };
  showPasswordFields.value = false;
  editError.value = '';
  editModal.show();
}

async function saveEdit() {
  savingEdit.value = true;
  editError.value = '';
  try {
    const payload = { ...editForm.value };
    if (!showPasswordFields.value) { delete payload.password; delete payload.password_confirmation; }

    const { data } = await axios.put(`/api/users/${editForm.value.id}`, payload);
    const idx = rows.value.findIndex((r) => r.id === editForm.value.id);
    if (idx !== -1) rows.value[idx] = data.user;
    editModal.hide();
  } catch (e) {
    editError.value = e.response?.data?.message || 'Could not save changes.';
  } finally {
    savingEdit.value = false;
  }
}

function openDelete(user) {
  userToDelete.value = user;
  deleteError.value = '';
  deleteModal.show();
}

async function confirmDelete() {
  deleting.value = true;
  deleteError.value = '';
  try {
    await axios.delete(`/api/users/${userToDelete.value.id}`);
    rows.value = rows.value.filter((r) => r.id !== userToDelete.value.id);
    deleteModal.hide();
  } catch (e) {
    deleteError.value = e.response?.data?.message || 'Could not delete user.';
  } finally {
    deleting.value = false;
  }
}

onMounted(() => {
  editModal = new Modal(editModalEl.value);
  deleteModal = new Modal(deleteModalEl.value);
  load();
});
</script>

<style scoped>
.avatar-sm {
  width: 34px; height: 34px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 0.75rem; font-weight: 700; flex-shrink: 0;
}
.delete-icon {
  width: 60px; height: 60px; border-radius: 50%;
  background: #fee2e2; color: #dc2626;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.6rem; margin: 0 auto;
}
</style>
