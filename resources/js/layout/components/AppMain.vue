<template>
  <section class="app-main">
    <router-view v-slot="{ Component, route }">
      <transition name="fade-transform" mode="out-in">
        <keep-alive :include="cachedViews">
          <component :is="Component" :key="route.fullPath" />
        </keep-alive>
      </transition>
    </router-view>
  </section>
</template>

<script>
import { useTagsViewStore } from '@/store';
export default {
  name: 'AppMain',
  computed: {
    cachedViews() {
      return useTagsViewStore().cachedViews;
    },
  },
};
</script>

<style lang="scss" scoped>
.app-main {
  position: relative;
  width: 100%;
}
</style>
