<template>
  <div class="d-flex">
    <Sidebar />
    <div class="flex-grow-1">
      <Navbar />
      <main class="p-4" style="background:#f8fafc; min-height: calc(100vh - 64px);">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import Sidebar from '../components/Sidebar.vue';
import Navbar from '../components/Navbar.vue';
</script>
