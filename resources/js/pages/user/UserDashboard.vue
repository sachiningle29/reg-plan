<template>
  <div>
    <h4 class="mb-4">My Dashboard</h4>

    <div class="row g-3 mb-4">
      <div class="col-md-4" v-for="card in stats" :key="card.label">
        <div class="card border-0 shadow-sm">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon" :class="card.bg"><i :class="card.icon"></i></div>
            <div>
              <div class="text-muted small">{{ card.label }}</div>
              <div class="fs-4 fw-bold">{{ card.value }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h6 class="mb-3">My Work — Planned vs Actual</h6>
        <div class="gantt-placeholder d-flex align-items-center justify-content-center text-muted">
          <div class="text-center">
            <i class="bi bi-calendar-range fs-1 d-block mb-2"></i>
            Your rig timeline appears here once you add entries in "My Rigs"
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const stats = [
  { label: 'My Rigs', value: '—', icon: 'bi bi-tools', bg: 'bg-primary' },
  { label: 'In Progress', value: '—', icon: 'bi bi-hourglass-split', bg: 'bg-warning' },
  { label: 'Completed', value: '—', icon: 'bi bi-check-circle-fill', bg: 'bg-success' },
];
</script>

<style scoped>
.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 1.25rem;
}
.gantt-placeholder {
  height: 220px;
  background: #f1f5f9;
  border-radius: 0.5rem;
}
</style>
