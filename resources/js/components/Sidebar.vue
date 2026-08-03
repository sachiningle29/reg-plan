<template>
  <aside class="sidebar">
    <div class="sidebar-brand">Reg-Plan</div>
    <nav class="nav flex-column">
      <router-link
        v-for="item in menu"
        :key="item.to"
        :to="item.to"
        class="sidebar-link"
        active-class="active"
      >
        <i :class="item.icon"></i>
        <span>{{ item.label }}</span>
      </router-link>
    </nav>
  </aside>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const adminMenu = [
  { label: 'Dashboard', to: '/admin/dashboard', icon: 'bi bi-speedometer2' },
  { label: 'Add User', to: '/admin/users/create', icon: 'bi bi-person-plus-fill' },
  { label: 'Manage Users', to: '/admin/users', icon: 'bi bi-people-fill' },
  { label: 'Rig Details', to: '/admin/rigs', icon: 'bi bi-diagram-3-fill' },
];

const userMenu = [
  { label: 'Dashboard', to: '/user/dashboard', icon: 'bi bi-speedometer2' },
  { label: 'My Rigs', to: '/user/rigs', icon: 'bi bi-tools' },
];

const menu = computed(() => (route.path.startsWith('/admin') ? adminMenu : userMenu));
</script>

<style scoped>
.sidebar {
  width: 240px;
  min-height: 100vh;
  background: #0f172a;
  color: #cbd5e1;
  flex-shrink: 0;
}
.sidebar-brand {
  font-size: 1.25rem;
  font-weight: 700;
  color: #fff;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.sidebar-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1.5rem;
  color: #cbd5e1;
  text-decoration: none;
  transition: 0.15s ease;
}
.sidebar-link:hover {
  background: rgba(255, 255, 255, 0.06);
  color: #fff;
}
.sidebar-link.active {
  background: #1d4ed8;
  color: #fff;
}
</style>
