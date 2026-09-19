<template>
  <div class="top-bar">
    <p>Quality products at affordable prices &nbsp;&bull;&nbsp; Pickup &amp; delivery available</p>
  </div>
</template>

<script setup>
</script>

<style lang="scss" scoped>
.top-bar {
  background: var(--color-yellow);
  color: #000;
  text-align: center;
  padding: 9px 16px;

  p {
    margin: 0;
    font-family: var(--font-sans);
    font-size: 13px;
    letter-spacing: 0.01em;
  }

  @media (max-width: 700px) {
    p {
      font-size: 11px;
    }
  }
}
</style>
