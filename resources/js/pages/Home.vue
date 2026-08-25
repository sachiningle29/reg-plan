<template>
  <div class="welcome-page">
    <nav class="navbar px-4 py-3">
      <span class="navbar-brand fw-bold text-white">Rig-Plan</span>
    </nav>

    <div class="hero d-flex flex-column align-items-center justify-content-center text-center text-white px-3">
      <h1 class="display-5 fw-bold mb-3">Rig Deployment Planning, Made Simple</h1>
      <p class="lead mb-5 col-md-6">
        Manage contracts, track approvals, and collaborate — all in one place.
      </p>

      <div class="d-flex gap-4 flex-wrap justify-content-center">
        <router-link to="/admin/login" class="login-card">
          <i class="bi bi-shield-lock-fill fs-1"></i>
          <h5 class="mt-3 mb-1">Admin Login</h5>
          <p class="small text-white-50 mb-0">Manage users & settings</p>
        </router-link>

        <router-link to="/login" class="login-card">
          <i class="bi bi-person-fill fs-1"></i>
          <h5 class="mt-3 mb-1">User Login</h5>
          <p class="small text-white-50 mb-0">Access your dashboard</p>
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
</script>

<style scoped>
.welcome-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #0f172a, #1e293b);
}
.hero {
  min-height: calc(100vh - 70px);
}
.login-card {
  width: 220px;
  padding: 2rem 1rem;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 1rem;
  color: #fff;
  text-decoration: none;
  transition: 0.2s ease;
}
.login-card:hover {
  background: rgba(255, 255, 255, 0.12);
  transform: translateY(-4px);
}
</style>
