<template>
  <AuthLayout title="Admin Login" subtitle="Sign in to manage Reg-Plan" icon-bg="bg-danger">
    <template #icon>
      <i class="bi bi-shield-lock-fill"></i>
    </template>

    <form @submit.prevent="login">
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input v-model="form.email" type="email" class="form-control" required autofocus />
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input v-model="form.password" type="password" class="form-control" required />
      </div>

      <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>

      <button type="submit" class="btn btn-danger w-100" :disabled="loading">
        {{ loading ? 'Signing in...' : 'Sign In' }}
      </button>

      <p class="text-center mt-3 small">
        Not an admin?
        <router-link to="/login" class="text-decoration-none">User login</router-link>
      </p>
    </form>
  </AuthLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import AuthLayout from '../components/AuthLayout.vue';

const router = useRouter();
const form = ref({ email: '', password: '' });
const error = ref('');
const loading = ref(false);

async function login() {
  error.value = '';
  loading.value = true;
  try {
    await axios.get('/sanctum/csrf-cookie');
    await axios.post('/api/admin/login', form.value);
    router.push('/admin/dashboard');
  } catch (e) {
    error.value = e.response?.data?.message || 'Login failed.';
  } finally {
    loading.value = false;
  }
}
</script>
