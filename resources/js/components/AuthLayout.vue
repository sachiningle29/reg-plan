<template>
  <div class="auth-wrapper">
    <div class="auth-panel d-none d-md-flex flex-column justify-content-between">
      <div>
        <h2 class="brand">Reg-Plan</h2>
        <p class="tagline">Regulatory Planning, Simplified.</p>
      </div>
      <p class="panel-quote">"Plan smarter. Approve faster. Stay in control."</p>
    </div>

    <div class="auth-form-side d-flex align-items-center justify-content-center">
      <div class="auth-card">
        <div class="text-center mb-4">
          <div class="icon-circle" :class="iconBg">
            <slot name="icon" />
          </div>
          <h3 class="mt-3 mb-1">{{ title }}</h3>
          <p class="text-muted small">{{ subtitle }}</p>
        </div>

        <slot />
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: String,
  subtitle: String,
  iconBg: { type: String, default: 'bg-primary' },
});
</script>

<style scoped>
.auth-wrapper {
  display: flex;
  min-height: 100vh;
}
.auth-panel {
  flex: 1;
  background: linear-gradient(135deg, #0f172a, #1e293b);
  color: #fff;
  padding: 3rem;
}
.brand { font-weight: 700; letter-spacing: 0.5px; }
.tagline { color: #94a3b8; }
.panel-quote { color: #cbd5e1; font-style: italic; max-width: 320px; }
.auth-form-side {
  flex: 1;
  background: #f8fafc;
  padding: 2rem;
}
.auth-card {
  width: 100%;
  max-width: 380px;
  background: #fff;
  padding: 2.5rem;
  border-radius: 1rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}
.icon-circle {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto;
  color: #fff;
  font-size: 1.4rem;
}
</style>
