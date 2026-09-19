<template>
  <div class="mobile-nav">
    <nav class="mobile-nav__tabbar" aria-label="Mobile primary">
      <router-link to="/product/list" class="mobile-nav__tab">
        <el-icon><Sell /></el-icon>
        <span>Shop</span>
      </router-link>
      <router-link to="/track/order" class="mobile-nav__tab">
        <el-icon><Guide /></el-icon>
        <span>Track Order</span>
      </router-link>
      <button type="button" class="mobile-nav__tab" aria-label="Open menu" @click="open = true">
        <el-icon><MoreFilled /></el-icon>
        <span>More</span>
      </button>
    </nav>

    <Transition name="drawer">
      <div v-if="open" class="mobile-nav__scrim" @click.self="open = false">
        <aside class="mobile-nav__drawer">
          <button type="button" class="mobile-nav__close" aria-label="Close menu" @click="open = false">×</button>
          <p class="mobile-nav__heading">Categories</p>
          <router-link
            v-for="category in categories"
            :key="category.id"
            class="mobile-nav__link"
            :to="{ name: 'CategorizedItems', params: { categoryId: category.id } }"
            @click="open = false"
          >
            {{ category.name }}
          </router-link>
        </aside>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Sell, Guide, MoreFilled } from '@element-plus/icons-vue';
import { useItemsStore } from '@/store';

const itemsStore = useItemsStore();
const categories = computed(() => itemsStore.categories);
const open = ref(false);

defineExpose({ open });
</script>

<style lang="scss" scoped>
.mobile-nav {
  &__tabbar {
    display: none;

    @media (max-width: 900px) {
      display: flex;
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      z-index: 200;
      background: var(--color-bg);
      border-top: 1px solid var(--color-border);
    }
  }

  &__tab {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    padding: 10px 0 8px;
    background: none;
    border: none;
    color: var(--color-text);
    font-family: var(--font-sans);
    font-size: 11px;
    text-decoration: none;
    cursor: pointer;

    i {
      font-size: 18px;
    }
  }

  &__scrim {
    position: fixed;
    inset: 0;
    z-index: 300;
    background: rgba(0, 0, 0, 0.3);
  }

  &__drawer {
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    width: 78%;
    max-width: 320px;
    background: var(--color-bg);
    padding: 24px;
    overflow-y: auto;
  }

  &__close {
    background: none;
    border: none;
    font-size: 28px;
    line-height: 1;
    color: var(--color-text);
    cursor: pointer;
    margin-bottom: 24px;
  }

  &__heading {
    font-family: var(--font-sans);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--color-text-muted);
    margin: 0 0 12px;
  }

  &__link {
    display: block;
    padding: 10px 0;
    border-bottom: 1px solid var(--color-border);
    font-family: var(--font-serif);
    font-size: 18px;
    color: var(--color-text);
    text-decoration: none;
  }
}

.drawer-enter-active,
.drawer-leave-active {
  transition: opacity 0.25s ease;

  .mobile-nav__drawer {
    transition: transform 0.25s ease;
  }
}

.drawer-enter-from,
.drawer-leave-to {
  opacity: 0;

  .mobile-nav__drawer {
    transform: translateX(-100%);
  }
}
</style>
