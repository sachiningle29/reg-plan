<template>
  <div class="d-flex app-shell">
    <Sidebar :collapsed="sidebarCollapsed" />
    <div class="flex-grow-1">
      <Navbar @toggle-sidebar="sidebarCollapsed = !sidebarCollapsed" />
      <main class="p-4 content-area">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import Sidebar from '../components/Sidebar.vue';
import Navbar from '../components/Navbar.vue';

const sidebarCollapsed = ref(false);
</script>

<style scoped>
.content-area { background: #f8fafc; min-height: calc(100vh - 64px); }
</style>
