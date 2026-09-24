<template>
  <div class="auth-wrapper">
    <div class="auth-panel d-none d-md-flex flex-column justify-content-between">
      <div>
        <h2 class="brand">Rig-Plan</h2>
        <p class="tagline">Rig Deployment Planning, Simplified.</p>
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
/* .auth-panel {
  flex: 1;
  background: linear-gradient(135deg, #0f172a, #1e293b);
  color: #fff;
  padding: 3rem;
} */

 /* .auth-panel {
  flex: 1;
  position: relative;
  color: #fff;
  padding: 3rem;
  background:
    linear-gradient(180deg, rgba(15, 23, 42, 0.55) 0%, rgba(15, 23, 42, 0.85) 100%),
    url('/images/rig4.jpg') center center / cover no-repeat;
} */

.auth-panel {
  flex: 1.1;
  position: relative;
  color: #fff;
  padding: 3rem;

  background:
    linear-gradient(180deg, rgba(15, 23, 42, 0.1) 0%, rgba(15, 23, 42, 0.7) 100%),
    /* url('/images/rig4.jpg') center center / cover no-repeat; */

    url('/images/rig4.jpg') center center / cover no-repeat;

  image-rendering: -webkit-optimize-contrast;
  image-rendering: crisp-edges;

  transform: translateZ(0);
  backface-visibility: hidden;

  /* added zoom property by sachin */
      background-blend-mode: multiply;

      animation: subtle-zoom 12s ease-in-out infinite alternate;

}

@keyframes subtle-zoom {
  from { background-size: 100%; }
  to { background-size: 118%; }
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
