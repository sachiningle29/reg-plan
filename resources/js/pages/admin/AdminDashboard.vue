<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">Admin Dashboard</h4>
    </div>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-5">
            <label class="form-label small text-muted">Plan</label>
            <select v-model="planName" class="form-select">
              <option v-for="p in planNames" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Data Type</label>
            <select v-model="recordType" class="form-select">
              <option value="planned">Planned</option>
              <option value="actual">Actual</option>
            </select>
          </div>
        </div>
      </div>
    </div>

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
        <h6 class="mb-3">{{ planName || 'Select a plan' }} — {{ recordType }}</h6>
        <GanttChart :plan-name="planName" :record-type="recordType" @loaded="onGanttData" />

        <RigTimelineChart :plan-name="planName" :record-type="recordType" @loaded="onGanttData" />

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
// import GanttChart from '../../components/GanttChart.vue';

import RigTimelineChart from '../../components/RigTimelineChart.vue';



const planNames = ref([]);
const planName = ref('');
const recordType = ref('planned');
const stats = ref([
  { label: 'Total Rigs', value: '—', icon: 'bi bi-tools', bg: 'bg-primary' },
  { label: 'Total Wells', value: '—', icon: 'bi bi-diagram-3-fill', bg: 'bg-success' },
  { label: 'Completed', value: '—', icon: 'bi bi-check-circle-fill', bg: 'bg-secondary' },
]);

function onGanttData(rows) {
  const rigs = new Set(rows.map((r) => r.rig_name));
  const completed = rows.filter((r) => (r.status || '').toUpperCase().includes('C')).length;


  stats.value = [
    { label: 'Total Rigs', value: rigs.size, icon: 'bi bi-tools', bg: 'bg-primary' },
    { label: 'Total Wells', value: rows.length, icon: 'bi bi-diagram-3-fill', bg: 'bg-success' },
    { label: 'Completed', value: completed, icon: 'bi bi-check-circle-fill', bg: 'bg-secondary' },
  ];
}

async function loadPlans() {
  const { data } = await axios.get('/api/plans');
  const names = [...new Set(data.map((p) => p.plan_name))];
  planNames.value = names;
  if (names.length) planName.value = names[0];
}

onMounted(loadPlans);
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
</style>
