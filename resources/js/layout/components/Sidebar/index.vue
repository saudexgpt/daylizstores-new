<template>
  <div class="sidebar">
    <router-link to="/dashboard" class="sidebar__brand" :class="{ 'is-collapsed': isCollapse }" aria-label="DayLiz Stores dashboard">
      <img v-if="!isCollapse" src="/svg/logo.png" alt="DayLiz Stores" class="sidebar__logo">
      <span v-else class="sidebar__monogram">D</span>
    </router-link>

    <el-scrollbar class="sidebar__scroll">
      <el-menu
        :default-active="activeMenu"
        :collapse="isCollapse"
        :collapse-transition="false"
        popper-class="sidebar-popper"
        class="sidebar__menu"
      >
        <sidebar-item
          v-for="route in routes"
          :key="route.path"
          :item="route"
          :base-path="route.path"
        />
      </el-menu>
    </el-scrollbar>

    <router-link to="/home" class="sidebar__store" :class="{ 'is-collapsed': isCollapse }">
      <el-icon><IconTopRight /></el-icon>
      <span v-show="!isCollapse">View storefront</span>
    </router-link>
  </div>
</template>

<script>
import { mapState } from 'pinia';
import { useAppStore, usePermissionStore } from '@/store';
import SidebarItem from './SidebarItem';

export default {
  components: { SidebarItem },
  computed: {
    ...mapState(useAppStore, ['sidebar']),
    routes() {
      return usePermissionStore().routes;
    },
    isCollapse() {
      return !this.sidebar.opened;
    },
    // detail pages can name the menu entry they belong under (meta.activeMenu)
    activeMenu() {
      const { meta, path } = this.$route;
      return (meta && meta.activeMenu) || path;
    },
  },
};
</script>

<style lang="scss" scoped>
.sidebar {
  display: flex;
  flex-direction: column;
  height: 100%;
  color: var(--admin-sidebar-text);

  &__brand {
    flex: none;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 96px;
    padding: 0 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);

    &.is-collapsed {
      height: 76px;
      padding: 0;
    }
  }

  &__logo {
    display: block;
    width: 150px;
    max-width: 100%;
    height: auto;
  }

  &__monogram {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg, #2b45d6, var(--admin-primary));
    color: #fff;
    font-size: 20px;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
  }

  &__scroll {
    flex: 1 1 auto;
    min-height: 0;
  }

  &__menu.el-menu {
    --el-menu-bg-color: transparent;
    --el-menu-text-color: var(--admin-sidebar-text);
    --el-menu-hover-bg-color: rgba(255, 255, 255, 0.07);
    --el-menu-active-color: #ffffff;
    --el-menu-item-height: 46px;
    --el-menu-sub-item-height: 42px;
    --el-menu-base-level-padding: 16px;
    --el-menu-level-padding: 18px;

    border: 0;
    padding: 14px 14px 20px;
    background: transparent;
    width: 100%;
  }

  // group titles and top-level links
  &__menu :deep(.el-menu-item),
  &__menu :deep(.el-sub-menu__title) {
    margin-bottom: 4px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    gap: 2px;
    color: var(--admin-sidebar-text);

    &:hover {
      color: #fff;
    }

    .el-icon {
      margin-right: 12px;
      font-size: 19px;
      color: inherit;
    }
  }

  &__menu :deep(.el-sub-menu__icon-arrow) {
    font-size: 12px;
    color: inherit;
  }

  &__menu :deep(.el-menu-item.is-active) {
    position: relative;
    background: rgba(255, 255, 255, 0.14);
    color: #fff;
    font-weight: 600;

    // accent marker on the active entry
    &::before {
      content: '';
      position: absolute;
      left: -14px;
      top: 11px;
      bottom: 11px;
      width: 4px;
      border-radius: 0 4px 4px 0;
      background: var(--admin-accent);
    }
  }

  &__menu :deep(.el-sub-menu.is-active > .el-sub-menu__title) {
    color: #fff;
  }

  // second level: a quiet indented list with a bullet
  &__menu :deep(.el-sub-menu .el-menu) {
    background: transparent;
    padding: 2px 0 6px;
  }

  &__menu :deep(.el-sub-menu .el-menu-item) {
    padding-left: 56px !important;
    height: var(--el-menu-sub-item-height);
    line-height: var(--el-menu-sub-item-height);
    font-size: 13.5px;

    &::after {
      content: '';
      position: absolute;
      left: 34px;
      top: 50%;
      width: 6px;
      height: 6px;
      margin-top: -3px;
      border-radius: 50%;
      background: currentColor;
      opacity: 0.45;
    }
  }

  &__menu :deep(.el-sub-menu .el-menu-item.is-active::after) {
    opacity: 1;
    background: var(--admin-accent);
  }

  // Collapsed rail. Element Plus hides the label/arrow with direct-child selectors
  // (`.el-menu--collapse > .el-sub-menu ...`), which don't match here because each
  // entry sits inside a `.menu-wrapper` — so the rules are spelled out.
  &__menu.el-menu--collapse {
    padding: 14px 12px 20px;

    :deep(.el-menu-item),
    :deep(.el-sub-menu__title) {
      justify-content: center;
      padding: 0 !important;

      .el-icon {
        margin-right: 0;
      }

      > span:not(.el-tooltip__trigger),
      .el-sub-menu__icon-arrow {
        display: none;
      }
    }

    :deep(.el-menu-item.is-active::before) {
      left: -12px;
    }
  }

  &__store {
    flex: none;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 14px 16px;
    padding: 12px 14px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.06);
    color: var(--admin-sidebar-text);
    font-size: 13.5px;
    font-weight: 500;
    transition: background-color 0.15s ease, color 0.15s ease;

    &:hover {
      background: rgba(255, 255, 255, 0.12);
      color: #fff;
    }

    &.is-collapsed {
      justify-content: center;
      padding: 12px 0;
    }

    .el-icon {
      font-size: 18px;
    }
  }
}
</style>
