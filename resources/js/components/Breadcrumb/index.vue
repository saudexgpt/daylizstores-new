<template>
  <el-breadcrumb class="app-breadcrumb" separator-icon="IconArrowRight">
    <el-breadcrumb-item v-for="(item, index) in levelList" :key="item.path">
      <span v-if="item.redirect === 'noredirect' || index === levelList.length - 1" class="no-redirect">
        {{ generateTitle(item.meta.title) }}
      </span>
      <a v-else @click.prevent="handleLink(item)">{{ generateTitle(item.meta.title) }}</a>
    </el-breadcrumb-item>
  </el-breadcrumb>
</template>

<script>
import { generateTitle } from '@/utils/i18n';
import pathToRegexp from 'path-to-regexp';

export default {
  name: 'Breadcrumb',
  data() {
    return {
      levelList: [],
    };
  },
  watch: {
    $route() {
      this.getBreadcrumb();
    },
  },
  created() {
    this.getBreadcrumb();
  },
  methods: {
    generateTitle,
    getBreadcrumb() {
      let matched = this.$route.matched.filter(item => item.name);

      const first = matched[0];
      if (first && first.name.trim().toLocaleLowerCase() !== 'Dashboard'.toLocaleLowerCase()) {
        matched = [{ path: '/dashboard', meta: { title: 'dashboard' }}].concat(matched);
      }

      this.levelList = matched.filter(
        item => item.meta && item.meta.title && item.meta.breadcrumb !== false,
      );
    },
    pathCompile(path) {
      const { params } = this.$route;
      var toPath = pathToRegexp.compile(path);
      return toPath(params);
    },
    handleLink(item) {
      const { redirect, path } = item;
      if (redirect) {
        this.$router.push(redirect);
        return;
      }
      this.$router.push(this.pathCompile(path));
    },
  },
};
</script>

<style lang="scss" scoped>
.app-breadcrumb.el-breadcrumb {
  display: flex;
  align-items: center;
  font-size: 14px;
  line-height: 1;

  :deep(.el-breadcrumb__item) {
    display: inline-flex;
    align-items: center;
  }

  :deep(.el-breadcrumb__inner),
  :deep(.el-breadcrumb__inner a) {
    font-weight: 500;
    color: var(--admin-muted);
    transition: color 0.15s ease;

    &:hover {
      color: var(--admin-primary);
    }
  }

  :deep(.el-breadcrumb__separator) {
    display: inline-flex;
    margin: 0 6px;
    color: var(--admin-border-strong);
  }

  // the current page
  .no-redirect {
    color: var(--admin-text);
    font-weight: 650;
    cursor: default;
  }
}
</style>
