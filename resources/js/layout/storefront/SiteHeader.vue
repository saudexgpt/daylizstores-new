<template>
  <header class="site-header">
    <div class="site-header__row site-header__row--top">
      <div class="site-header__side site-header__side--left">
        <button type="button" class="site-header__hamburger" aria-label="Open menu" @click="emit('toggle-nav')">
          <el-icon><Fold /></el-icon>
        </button>
        <router-link to="/home" class="site-header__logo">
          <img src="/images/logo.png" alt="DayLiz Stores">
        </router-link>
      </div>
      <section class="site-header__search">
        <div class="site-header__search-inner">
          <SearchBox />
        </div>
      </section>
      <div class="site-header__side site-header__side--right">
        <button type="button" class="site-header__icon-link" @click="emit('open-panel', 'wish_list')">
          <span class="site-header__icon-wrap">
            <i class="fas fa-heart" aria-hidden="true" />
            <span v-if="wishListCount > 0" class="site-header__badge">{{ wishListCount }}</span>
          </span>
          <span class="site-header__icon-label">Wishlist</span>
        </button>
        <button
          type="button"
          class="site-header__icon-link"
          :class="{ 'site-header__icon-link--blink': cartCount > 0 }"
          @click="emit('open-panel', 'cart')"
        >
          <span class="site-header__icon-wrap">
            <el-icon><ShoppingCart /></el-icon>
            <span v-if="cartCount > 0" class="site-header__badge">{{ cartCount }}</span>
          </span>
          <span class="site-header__icon-label">Cart</span>
        </button>
        <el-dropdown trigger="click">
          <button type="button" class="site-header__icon-link" aria-label="Account menu">
            <span class="site-header__icon-wrap"><el-icon><User /></el-icon></span>
            <span class="site-header__icon-label">Account <el-icon class="site-header__chevron"><ArrowDown /></el-icon></span>
          </button>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item><router-link to="/track/order">Track Order</router-link></el-dropdown-item>
              <el-dropdown-item><router-link to="/my-account/edit">My Profile</router-link></el-dropdown-item>
              <el-dropdown-item v-if="userData.id === null" divided><router-link to="/login">Sign In</router-link></el-dropdown-item>
              <el-dropdown-item v-if="userData.id !== null" divided><a @click="logout">Logout</a></el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </div>
    </div>

    <div class="site-header__row site-header__row--bottom">
      <el-dropdown trigger="click">
        <button type="button" class="site-header__categories-btn">
          <el-icon><Menu /></el-icon>
          All Categories
          <el-icon class="site-header__chevron"><ArrowDown /></el-icon>
        </button>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item v-for="category in categories" :key="category.id">
              <router-link :to="{ name: 'CategorizedItems', params: { categoryId: category.id } }">{{ category.name }}</router-link>
            </el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>

      <nav class="site-header__nav" aria-label="Primary">
        <router-link to="/home">Home</router-link>
        <router-link to="/product/list">Shop</router-link>
        <router-link :to="{ path: '/product/list', query: { discounted: '1' } }">Deals</router-link>
        <router-link :to="{ path: '/product/list', query: { sort: 'newest' } }">New Arrivals</router-link>
        <router-link to="/about">About Us</router-link>
        <router-link :to="{ path: '/about', hash: '#contact' }">Contact</router-link>
      </nav>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue';
import SearchBox from '@/pages/partials/SearchBox.vue';
import { useRouter } from 'vue-router';
import { Fold, User, ShoppingCart, ArrowDown, Menu } from '@element-plus/icons-vue';
import { useUserStore, useItemsStore, useOrderStore } from '@/store';

const emit = defineEmits(['toggle-nav', 'open-panel']);

const router = useRouter();
const userStore = useUserStore();
const itemsStore = useItemsStore();
const orderStore = useOrderStore();

const userData = computed(() => userStore.userData);
const categories = computed(() => itemsStore.categories);
const cartCount = computed(() => orderStore.cart.length);
const wishListCount = computed(() => orderStore.wishList.length);

async function logout() {
  await userStore.logout();
  router.push('/home');
}
</script>

<style lang="scss" scoped>
.site-header {
  position: sticky;
  top: 0;
  z-index: 10;
  background: var(--color-white);
  border-bottom: 1px solid var(--color-border);

  &__row {
    max-width: var(--content-max-wide);
    margin: 0 auto;
    display: flex;
    align-items: center;
  }

  &__row--top {
    padding: 16px 32px;
    justify-content: space-between;

    @media (max-width: 900px) {
      flex-wrap: wrap;
      row-gap: 0;
      padding: 14px 20px;
    }
  }

  &__row--bottom {
    padding: 10px 32px;
    justify-content: flex-start;
    gap: 40px;
    border-top: 1px solid var(--color-border);

    @media (max-width: 900px) {
      display: none;
    }
  }

  &__search {
    flex: 1;

    @media (max-width: 900px) {
      order: 3;
      flex-basis: 100%;
      width: 100%;
      margin-top: 10px;
    }
  }

  &__search-inner {
    max-width: 560px;
    margin: 0 auto;
    padding: 0 32px;

    @media (max-width: 700px) {
      padding: 0;
    }
  }

  &__side {
    display: flex;
    align-items: center;
    gap: 24px;

    &--right {
      gap: 20px;
    }
  }

  &__hamburger {
    display: none;
    background: none;
    border: none;
    font-size: 20px;
    color: var(--color-navy);
    cursor: pointer;

    @media (max-width: 900px) {
      display: block;
    }
  }

  &__logo {
    img {
      height: 56px;
      width: auto;
      display: block;
    }
  }

  &__icon-link {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--color-text);
    font-family: var(--font-sans);

    &:hover {
      color: var(--color-navy);
    }

    @media (max-width: 700px) {
      .site-header__icon-label {
        display: none;
      }
    }
  }

  &__icon-wrap {
    position: relative;
    font-size: 18px;
    line-height: 1;
  }

  &__icon-label {
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
  }

  &__chevron {
    font-size: 10px;
    vertical-align: middle;
  }

  &__badge {
    position: absolute;
    top: -8px;
    right: -10px;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    border-radius: 999px;
    background: var(--color-accent);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    line-height: 16px;
    text-align: center;
  }

  &__icon-link--blink .site-header__icon-wrap {
    animation: site-header-blink 1.6s ease-out infinite;
    border-radius: 50%;
  }

  @media (prefers-reduced-motion: reduce) {
    &__icon-link--blink .site-header__icon-wrap {
      animation: none;
    }
  }

  &__categories-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--color-dark);
    color: #fff;
    border: none;
    border-radius: var(--radius-sm);
    padding: 9px 16px;
    font-family: var(--font-sans);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    white-space: nowrap;

    &:hover {
      background: var(--color-navy);
    }
  }

  &__nav {
    display: flex;
    gap: 26px;
    font-family: var(--font-sans);
    font-size: 13px;
    font-weight: 500;
    color: var(--color-text);

    a {
      color: var(--color-text);
      text-decoration: none;
      white-space: nowrap;

      &:hover,
      &.router-link-active {
        color: var(--color-navy);
      }
    }
  }
}

@keyframes site-header-blink {
  0% {
    box-shadow: 0 0 0 0 rgba(190, 23, 18, 0.45);
  }
  70% {
    box-shadow: 0 0 0 10px rgba(190, 23, 18, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(190, 23, 18, 0);
  }
}
</style>
