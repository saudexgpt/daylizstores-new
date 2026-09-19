<template>
  <div class="floating-actions">
    <button
      type="button"
      class="floating-actions__btn floating-actions__btn--cart"
      :class="{ 'floating-actions__btn--blink': cartCount > 0 }"
      aria-label="Open cart"
      @click="emit('open-panel', 'cart')"
    >
      <el-icon><ShoppingCart /></el-icon>
      <span v-if="cartCount > 0" class="floating-actions__badge">{{ cartCount }}</span>
    </button>
    <button
      type="button"
      class="floating-actions__btn floating-actions__btn--wishlist"
      :class="{ 'floating-actions__btn--blink': wishListCount > 0 }"
      aria-label="Open wishlist"
      @click="emit('open-panel', 'wish_list')"
    >
      <i class="fas fa-heart" aria-hidden="true" />
      <span v-if="wishListCount > 0" class="floating-actions__badge">{{ wishListCount }}</span>
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { ShoppingCart } from '@element-plus/icons-vue';
import { useOrderStore } from '@/store';

const emit = defineEmits(['open-panel']);

const orderStore = useOrderStore();
const cartCount = computed(() => orderStore.cart.length);
const wishListCount = computed(() => orderStore.wishList.length);
</script>

<style lang="scss" scoped>
.floating-actions {
  position: fixed;
  right: 10px;
  bottom: 300px;
  z-index: 90;
  display: flex;
  flex-direction: column;
  gap: 14px;

  @media (max-width: 900px) {
    bottom: 76px;
  }

  &__btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    border-radius: 10px;
    background: var(--color-navy);
    color: #fff;
    border: none;
    font-size: 20px;
    cursor: pointer;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    transition: background-color 0.2s ease, transform 0.15s ease;

    &:hover {
      background: var(--color-navy-hover);
      transform: translateY(-2px);
    }

    &--wishlist {
      font-size: 18px;
      background: var(--color-accent);

      &:hover {
        background: var(--color-accent-hover);
      }
    }

    // Pulses a ring of the button's own color outward to draw the eye once
    // there's at least one item — a box-shadow animation rather than an
    // opacity flicker so it stays legible and doesn't read as "broken".
    &--blink {
      animation: floating-actions-blink 1.6s ease-out infinite;

      &.floating-actions__btn--wishlist {
        animation-name: floating-actions-blink-accent;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      &--blink {
        animation: none;
      }
    }
  }

  &__badge {
    position: absolute;
    top: -2px;
    right: -2px;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    border-radius: 999px;
    background: #fff;
    color: var(--color-navy);
    font-size: 11px;
    font-weight: 700;
    line-height: 20px;
    text-align: center;
    font-family: var(--font-sans);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
  }
}

@keyframes floating-actions-blink {
  0% {
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2), 0 0 0 0 rgba(25, 46, 167, 0.55);
  }
  70% {
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2), 0 0 0 14px rgba(25, 46, 167, 0);
  }
  100% {
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2), 0 0 0 0 rgba(25, 46, 167, 0);
  }
}

@keyframes floating-actions-blink-accent {
  0% {
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2), 0 0 0 0 rgba(190, 23, 18, 0.55);
  }
  70% {
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2), 0 0 0 14px rgba(190, 23, 18, 0);
  }
  100% {
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2), 0 0 0 0 rgba(190, 23, 18, 0);
  }
}
</style>
