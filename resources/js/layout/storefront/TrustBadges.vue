<template>
  <section class="trust-badges">
    <div class="trust-badges__inner">
      <div class="trust-badges__item">
        <el-icon><Van /></el-icon>
        <div>
          <p class="trust-badges__title">Pickup &amp; Delivery</p>
          <p class="trust-badges__desc">Collect in-store or have it delivered</p>
        </div>
      </div>
      <div class="trust-badges__item">
        <el-icon><Lock /></el-icon>
        <div>
          <p class="trust-badges__title">Secure Checkout</p>
          <p class="trust-badges__desc">Verified bank transfer payments</p>
        </div>
      </div>
      <div class="trust-badges__item">
        <el-icon><ChatDotRound /></el-icon>
        <div>
          <p class="trust-badges__title">Dedicated Support</p>
          <p class="trust-badges__desc">Reach us directly on WhatsApp</p>
        </div>
      </div>
      <div class="trust-badges__item">
        <el-icon><Medal /></el-icon>
        <div>
          <p class="trust-badges__title">Quality Guaranteed</p>
          <p class="trust-badges__desc">Every item hand-checked before pickup</p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { Van, Lock, ChatDotRound, Medal } from '@element-plus/icons-vue';
</script>

<style lang="scss" scoped>
.trust-badges {
  background: var(--color-surface-alt);

  &__inner {
    max-width: var(--content-max-wide);
    margin: 0 auto;
    padding: 36px 32px;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;

    @media (max-width: 900px) {
      grid-template-columns: repeat(2, 1fr);
    }

    @media (max-width: 560px) {
      grid-template-columns: 1fr;
    }
  }

  &__item {
    display: flex;
    align-items: flex-start;
    gap: 14px;

    .el-icon {
      font-size: 26px;
      color: var(--color-accent);
      margin-top: 2px;
      flex-shrink: 0;
    }
  }

  &__title {
    font-family: var(--font-sans);
    font-size: 14px;
    font-weight: 600;
    color: var(--color-navy);
    margin: 0 0 2px;
  }

  &__desc {
    font-family: var(--font-sans);
    font-size: 12px;
    color: var(--color-text-muted);
    margin: 0;
  }
}
</style>
