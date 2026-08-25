<template>
  <div>
    <div class="mb-4">
      <h4 class="mb-1">Add New User</h4>
      <p class="text-muted small mb-0">Create an admin or user account for Reg-Plan</p>
    </div>

    <div class="card border-0 shadow-sm overflow-hidden">
      <div class="row g-0">
        <div class="col-md-4 preview-panel">
          <div class="avatar-circle" :class="form.role === 'admin' ? 'bg-danger' : 'bg-primary'">
            {{ initials }}
          </div>
          <h6 class="text-white mt-3 mb-1">{{ form.name || 'New User' }}</h6>
          <p class="text-white-50 small mb-4">{{ form.email || 'email@example.com' }}</p>

          <div class="role-cards">
            <button
              type="button"
              class="role-card"
              :class="{ active: form.role === 'user' }"
              @click="form.role = 'user'"
            >
              <i class="bi bi-person-fill"></i>
              <div>
                <div class="fw-semibold">User</div>
                <div class="small text-white-50">Tracks own rig work</div>
              </div>
            </button>
            <button
              type="button"
              class="role-card"
              :class="{ active: form.role === 'admin' }"
              @click="form.role = 'admin'"
            >
              <i class="bi bi-shield-lock-fill"></i>
              <div>
                <div class="fw-semibold">Admin</div>
                <div class="small text-white-50">Full access & user management</div>
              </div>
            </button>
          </div>
        </div>

        <div class="col-md-8">
          <div class="card-body p-4">
            <form @submit.prevent="submit">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small text-muted">Full Name</label>
                  <input v-model="form.name" type="text" class="form-control" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-muted">Email Address</label>
                  <input v-model="form.email" type="email" class="form-control" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-muted">Password</label>
                  <input v-model="form.password" type="password" class="form-control" required minlength="8" />
                  <div class="form-text">At least 8 characters</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-muted">Confirm Password</label>
                  <input v-model="form.password_confirmation" type="password" class="form-control" required />
                </div>
              </div>

              <div v-if="message" class="alert py-2 mt-4" :class="success ? 'alert-success' : 'alert-danger'">
                {{ message }}
              </div>

              <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary px-4" :disabled="loading">
                  <i class="bi bi-person-plus-fill me-1"></i>
                  {{ loading ? 'Creating...' : 'Create User' }}
                </button>
                <router-link :to="{ name: 'admin-users' }" class="btn btn-light border">
                  View All Users
                </router-link>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const form = ref({ name: '', email: '', password: '', password_confirmation: '', role: 'user' });
const message = ref('');
const success = ref(false);
const loading = ref(false);

const initials = computed(() => {
  const parts = form.value.name.trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return '?';
  return (parts[0][0] + (parts[1]?.[0] || '')).toUpperCase();
});

async function submit() {
  message.value = '';
  loading.value = true;
  try {
    await axios.post('/api/users', form.value);
    success.value = true;
    message.value = `${form.value.name} was created successfully as ${form.value.role}.`;
    form.value = { name: '', email: '', password: '', password_confirmation: '', role: 'user' };
  } catch (e) {
    success.value = false;
    message.value = e.response?.data?.message || 'Could not create user. Check the fields and try again.';
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.preview-panel {
  background: linear-gradient(160deg, #0f172a, #1e293b);
  padding: 2.5rem 2rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}
.avatar-circle {
  width: 84px;
  height: 84px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 1.6rem;
  font-weight: 700;
  transition: background 0.2s ease;
}
.role-cards {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  width: 100%;
  margin-top: 0.5rem;
}
.role-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  border-radius: 0.65rem;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #fff;
  text-align: left;
  transition: 0.15s ease;
}
.role-card i { font-size: 1.2rem; opacity: 0.85; }
.role-card:hover { background: rgba(255, 255, 255, 0.09); }
.role-card.active {
  background: rgba(37, 99, 235, 0.25);
  border-color: #3b82f6;
}
</style>
