<template>
  <div class="item-skeleton" role="status" aria-busy="true" aria-label="Loading product details">
    <div class="item-skeleton__crumbs shimmer" />

    <div class="item-skeleton__main">
      <div class="item-skeleton__gallery">
        <div class="item-skeleton__thumbs">
          <div v-for="n in 4" :key="n" class="item-skeleton__thumb shimmer" />
        </div>
        <div class="item-skeleton__image shimmer" />
      </div>

      <div class="item-skeleton__info">
        <div class="item-skeleton__line item-skeleton__line--category shimmer" />
        <div class="item-skeleton__line item-skeleton__line--title shimmer" />
        <div class="item-skeleton__line item-skeleton__line--meta shimmer" />
        <div class="item-skeleton__line shimmer" />
        <div class="item-skeleton__line shimmer" />
        <div class="item-skeleton__line item-skeleton__line--short shimmer" />
        <div class="item-skeleton__line item-skeleton__line--price shimmer" />

        <div class="item-skeleton__row">
          <div v-for="n in 3" :key="n" class="item-skeleton__swatch shimmer" />
        </div>
        <div class="item-skeleton__row">
          <div v-for="n in 3" :key="n" class="item-skeleton__size shimmer" />
        </div>
        <div class="item-skeleton__row">
          <div class="item-skeleton__qty shimmer" />
        </div>

        <div class="item-skeleton__actions">
          <div class="item-skeleton__button shimmer" />
        </div>
      </div>
    </div>

    <div class="item-skeleton__tabs">
      <div v-for="n in 4" :key="n" class="item-skeleton__tab shimmer" />
    </div>
  </div>
</template>

<script setup>
</script>

<style lang="scss" scoped>
// Dimensions deliberately mirror ItemDetails.vue's real layout (grid,
// thumbnail rail, square image, control heights) so the page doesn't jump
// when the content replaces this.
.item-skeleton {
  &__crumbs {
    width: 260px;
    max-width: 70%;
    height: 16px;
    border-radius: 2px;
  }

  &__main {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin: 24px 0 48px;

    @media (max-width: 900px) {
      grid-template-columns: 1fr;
      gap: 24px;
    }
  }

  &__gallery {
    display: flex;
    gap: 12px;

    @media (max-width: 700px) {
      flex-direction: column-reverse;
    }
  }

  &__thumbs {
    display: flex;
    flex-direction: column;
    gap: 8px;

    @media (max-width: 700px) {
      flex-direction: row;
    }
  }

  &__thumb {
    width: 64px;
    height: 64px;
    flex-shrink: 0;
    border-radius: var(--radius-sm);
  }

  &__image {
    flex: 1;
    aspect-ratio: 1 / 1;
    border-radius: var(--radius-sm);
  }

  &__line {
    height: 14px;
    margin-bottom: 14px;
    border-radius: 2px;

    &--category {
      width: 90px;
      height: 12px;
    }

    &--title {
      width: 75%;
      height: 34px;
      margin-bottom: 18px;
    }

    &--meta {
      width: 45%;
    }

    &--short {
      width: 60%;
    }

    &--price {
      width: 140px;
      height: 30px;
      margin: 6px 0 22px;
    }
  }

  &__row {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
  }

  &__swatch {
    width: 28px;
    height: 28px;
    border-radius: 50%;
  }

  &__size {
    width: 44px;
    height: 40px;
    border-radius: var(--radius-sm);
  }

  &__qty {
    width: 150px;
    height: 32px;
    border-radius: var(--radius-sm);
  }

  &__actions {
    margin-top: 24px;
  }

  &__button {
    width: 100%;
    height: 40px;
    border-radius: var(--radius-sm);
  }

  &__tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 48px;
  }

  &__tab {
    width: 110px;
    height: 40px;
    border-radius: var(--radius-sm);

    @media (max-width: 700px) {
      width: 72px;
    }
  }
}

.shimmer {
  background: linear-gradient(90deg, var(--color-border) 25%, #efefe9 37%, var(--color-border) 63%);
  background-size: 400% 100%;
  animation: item-skeleton-shimmer 1.6s ease infinite;
}

@keyframes item-skeleton-shimmer {
  0% {
    background-position: 100% 50%;
  }
  100% {
    background-position: 0 50%;
  }
}

@media (prefers-reduced-motion: reduce) {
  .shimmer {
    animation: none;
  }
}
</style>
