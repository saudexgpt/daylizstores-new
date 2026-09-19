<template>
  <div class="product-card-skeleton">
    <div class="product-card-skeleton__media" />
    <div class="product-card-skeleton__line product-card-skeleton__line--short" />
    <div class="product-card-skeleton__line" />
    <div class="product-card-skeleton__line product-card-skeleton__line--short" />
  </div>
</template>

<script setup>
</script>

<style lang="scss" scoped>
.product-card-skeleton {
  &__media {
    aspect-ratio: 3 / 4;
    border-radius: var(--radius-sm);
  }

  &__line {
    height: 12px;
    margin-top: 10px;
    border-radius: 2px;

    &--short {
      width: 60%;
    }
  }

  &__media,
  &__line {
    background: linear-gradient(90deg, var(--color-border) 25%, #efefe9 37%, var(--color-border) 63%);
    background-size: 400% 100%;
    animation: skeleton-shimmer 1.6s ease infinite;
  }
}

@keyframes skeleton-shimmer {
  0% {
    background-position: 100% 50%;
  }
  100% {
    background-position: 0 50%;
  }
}

@media (prefers-reduced-motion: reduce) {
  .product-card-skeleton__media,
  .product-card-skeleton__line {
    animation: none;
  }
}
</style>
