<template>
  <header class="topbar d-flex align-items-center justify-content-between px-3 px-md-4">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-sm btn-light border" @click="$emit('toggle-sidebar')" title="Toggle sidebar">
        <i class="bi bi-list fs-5"></i>
      </button>
      <span class="text-muted small d-none d-sm-inline">Welcome back, {{ auth.user?.name }}</span>
    </div>

    <div class="d-flex align-items-center gap-3">
      <span class="badge" :class="auth.role === 'admin' ? 'bg-danger' : 'bg-primary'">{{ auth.role }}</span>
      <button class="btn btn-sm btn-outline-secondary" @click="handleLogout">
        <i class="bi bi-box-arrow-right"></i> Logout
      </button>
    </div>
  </header>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

defineEmits(['toggle-sidebar']);
const auth = useAuthStore();
const router = useRouter();

async function handleLogout() {
  await auth.logout();
  router.push('/');
}
</script>

<style scoped>
.topbar { height: 64px; background: #fff; border-bottom: 1px solid #e2e8f0; }
</style>
