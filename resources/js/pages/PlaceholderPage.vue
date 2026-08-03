<template>
  <div class="text-center text-muted py-5">
    <i class="bi bi-cone-striped fs-1 d-block mb-3"></i>
    <h4>{{ title }}</h4>
    <p>This screen is coming in the next step.</p>
  </div>
</template>

<script setup>
defineProps({ title: String });
</script>
