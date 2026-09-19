<template>
  <div class="storefront-shell">
    <TopBar />
    <SiteHeader @toggle-nav="openMobileNav" @open-panel="openPanel" />
    <!--
      The reference redesign shows the homepage as a clean, full-width
      layout with no persistent sidebar. Other storefront pages (product
      list, search, etc.) keep the categories/latest-products sidebar they
      already relied on — only the homepage drops it.
    -->
    <div class="storefront-shell__body">
      <div class="storefront-shell__sidebar">
        <LeftMenu />
      </div>
      <main class="storefront-shell__main">
        <AppMain />
      </main>
    </div>

    <TrustBadges />
    <SiteFooter />

    <MobileNav ref="mobileNavRef" />
    <RightPanel ref="rightPanelRef" />
    <FloatingActions @open-panel="openPanel" />
  </div>
</template>

<script setup>
import { computed, provide, ref } from 'vue';
import { useRoute } from 'vue-router';
import { AppMain } from './components';
import RightPanel from '@/components/RightPanel/index.vue';
import LeftMenu from '@/pages/partials/LeftMenu.vue';
import SiteHeader from './storefront/SiteHeader.vue';
import TopBar from './storefront/TopBar.vue';
import MobileNav from './storefront/MobileNav.vue';
import TrustBadges from './storefront/TrustBadges.vue';
import SiteFooter from './storefront/SiteFooter.vue';
import FloatingActions from './storefront/FloatingActions.vue';

const route = useRoute();
const isHome = computed(() => route.name === 'Home');

const mobileNavRef = ref(null);
const rightPanelRef = ref(null);

function openMobileNav() {
  if (mobileNavRef.value) {
    mobileNavRef.value.open = true;
  }
}

// Lets any storefront page (e.g. checkout's "Edit Cart") open the cart/wishlist panel.
provide('openPanel', openPanel);

function openPanel(view) {
  rightPanelRef.value?.open(view);
}
</script>

<style lang="scss" scoped>
.storefront-shell {
  min-height: 100vh;
  background: var(--color-bg);
  color: var(--color-text);
  font-family: var(--font-sans);

  &__body {
    max-width: var(--content-max-wide);
    margin: 0 auto;
    padding: 0 32px var(--space-section);
    display: grid;
    grid-template-columns: 220px 1fr;
    align-items: start;
    gap: 40px;

    @media (max-width: 900px) {
      grid-template-columns: 1fr;
      padding: 0 20px 96px;
    }

    &--full {
      grid-template-columns: 1fr;
      max-width: none;
      padding: 0;

      @media (max-width: 900px) {
        padding: 0;
      }
    }
  }

  &__sidebar {
    margin-top: 20px;
    @media (max-width: 900px) {
      display: none;
    }
  }

  // Grid items default to min-width:auto, so without this a wide-content
  // descendant (e.g. the homepage hero image) can force this track — and the
  // whole page — wider than the viewport instead of shrinking to fit it.
  &__main {
    min-width: 0;
    margin-top: 20px;
  }
}
</style>
