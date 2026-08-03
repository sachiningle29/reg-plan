<template>
  <div>
    <h4 class="mb-4">Admin Dashboard</h4>

    <div class="row g-3 mb-4">
      <div class="col-md-3" v-for="card in stats" :key="card.label">
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
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="mb-0">Work Progress — All Users</h6>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary active">Day</button>
            <button class="btn btn-outline-secondary">Week</button>
            <button class="btn btn-outline-secondary">Month</button>
            <button class="btn btn-outline-secondary">Year</button>
          </div>
        </div>

        <div class="gantt-placeholder d-flex align-items-center justify-content-center text-muted">
          <div class="text-center">
            <i class="bi bi-bar-chart-steps fs-1 d-block mb-2"></i>
            Gantt chart renders here once Rig Details data exists
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const stats = [
  { label: 'Total Rigs', value: '—', icon: 'bi bi-tools', bg: 'bg-primary' },
  { label: 'Active Wells', value: '—', icon: 'bi bi-diagram-3-fill', bg: 'bg-success' },
  { label: 'Completed', value: '—', icon: 'bi bi-check-circle-fill', bg: 'bg-secondary' },
  { label: 'Users', value: '—', icon: 'bi bi-people-fill', bg: 'bg-danger' },
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
  height: 260px;
  background: #f1f5f9;
  border-radius: 0.5rem;
}
</style>
