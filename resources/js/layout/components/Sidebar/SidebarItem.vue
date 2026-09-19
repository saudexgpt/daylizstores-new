<template>
  <div v-if="!item.hidden && item.children" class="menu-wrapper">
    <!-- a group with a single visible child collapses into that child's own link -->
    <template v-if="isSingleLink">
      <app-link :to="resolvePath(onlyOneChild.path)">
        <el-menu-item :index="resolvePath(onlyOneChild.path)" :class="{ 'submenu-title-noDropdown': !isNest }">
          <el-icon v-if="iconName"><component :is="iconName" /></el-icon>
          <template #title>
            <span>{{ titleOf(onlyOneChild) }}</span>
          </template>
        </el-menu-item>
      </app-link>
    </template>

    <el-sub-menu v-else :index="resolvePath(item.path)" popper-class="sidebar-popper">
      <template #title>
        <el-icon v-if="groupIconName"><component :is="groupIconName" /></el-icon>
        <span>{{ titleOf(item) }}</span>
      </template>

      <template v-for="child in visibleChildren" :key="child.path">
        <sidebar-item
          v-if="child.children && child.children.length > 0"
          :is-nest="true"
          :item="child"
          :base-path="resolvePath(child.path)"
          class="nest-menu"
        />
        <app-link v-else :to="resolvePath(child.path)">
          <el-menu-item :index="resolvePath(child.path)">
            <template #title>
              <span>{{ titleOf(child) }}</span>
            </template>
          </el-menu-item>
        </app-link>
      </template>
    </el-sub-menu>
  </div>
</template>

<script>
import { resolvePath as resolveRoutePath } from '@/utils/resolvePath';
import { resolveIconName } from '@/utils/icons';
import { isExternal } from '@/utils/validate';
import AppLink from './Link';
import { generateTitle } from '@/utils/i18n';

export default {
  name: 'SidebarItem',
  components: { AppLink },
  props: {
    // route object
    item: {
      type: Object,
      required: true,
    },
    isNest: {
      type: Boolean,
      default: false,
    },
    basePath: {
      type: String,
      default: '',
    },
  },
  computed: {
    visibleChildren() {
      return (this.item.children || []).filter(child => !child.hidden);
    },
    // Derived, not assigned during render (the old version wrote to component
    // state from inside the template's v-if, which Vue 3 treats as a render-time side effect).
    onlyOneChild() {
      if (this.visibleChildren.length === 1) {
        return this.visibleChildren[0];
      }
      // no visible child: show the parent itself
      if (this.visibleChildren.length === 0) {
        return { ...this.item, path: '', noShowingChildren: true };
      }
      return null;
    },
    isSingleLink() {
      const only = this.onlyOneChild;
      return !!only && (!only.children || only.noShowingChildren) && !this.item.alwaysShow;
    },
    iconName() {
      const only = this.onlyOneChild;
      const icon = (only && only.meta && only.meta.icon) || (this.item.meta && this.item.meta.icon);
      return resolveIconName(icon);
    },
    groupIconName() {
      return resolveIconName(this.item.meta && this.item.meta.icon);
    },
  },
  methods: {
    resolvePath(routePath) {
      if (isExternal(routePath)) {
        return routePath;
      }
      return resolveRoutePath(this.basePath, routePath);
    },
    titleOf(route) {
      return route.meta && route.meta.title ? generateTitle(route.meta.title) : '';
    },
  },
};
</script>
