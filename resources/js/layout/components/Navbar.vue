<template>
  <header class="navbar">
    <button
      type="button"
      class="navbar__icon-btn"
      :aria-label="sidebar.opened ? 'Collapse sidebar' : 'Expand sidebar'"
      @click="toggleSideBar"
    >
      <el-icon>
        <IconFold v-if="sidebar.opened" />
        <IconExpand v-else />
      </el-icon>
    </button>

    <breadcrumb class="navbar__breadcrumb" />

    <div class="navbar__right">
      <router-link v-if="device !== 'mobile'" to="/home" class="navbar__store">
        <el-icon><IconTopRight /></el-icon>
        View store
      </router-link>

      <el-tooltip v-if="device !== 'mobile'" content="Toggle fullscreen" placement="bottom">
        <button type="button" class="navbar__icon-btn" aria-label="Toggle fullscreen" @click="toggleFullscreen">
          <el-icon><IconFullScreen /></el-icon>
        </button>
      </el-tooltip>

      <notification />

      <el-dropdown trigger="click" placement="bottom-end" @command="onCommand">
        <button type="button" class="navbar__user" aria-label="Account menu">
          <span class="navbar__avatar">{{ initials }}</span>
          <span v-if="device !== 'mobile'" class="navbar__who">
            <span class="navbar__name">{{ name || 'Account' }}</span>
            <span class="navbar__role">{{ roleLabel }}</span>
          </span>
          <el-icon v-if="device !== 'mobile'" class="navbar__caret"><IconArrowDown /></el-icon>
        </button>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item command="dashboard">
              <el-icon><IconDataBoard /></el-icon>
              Dashboard
            </el-dropdown-item>
            <el-dropdown-item v-if="userId !== null" command="profile">
              <el-icon><IconUser /></el-icon>
              My profile
            </el-dropdown-item>
            <el-dropdown-item command="store">
              <el-icon><IconTopRight /></el-icon>
              View storefront
            </el-dropdown-item>
            <el-dropdown-item divided command="logout">
              <el-icon><IconSwitchButton /></el-icon>
              Sign out
            </el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </div>
  </header>
</template>

<script>
import { mapState } from 'pinia';
import screenfull from 'screenfull';
import { useAppStore, useUserStore } from '@/store';
import Breadcrumb from '@/components/Breadcrumb';
import Notification from './Notification';

export default {
  components: {
    Breadcrumb,
    Notification,
  },
  computed: {
    ...mapState(useAppStore, ['sidebar', 'device']),
    ...mapState(useUserStore, ['name', 'userId', 'roles']),
    initials() {
      const words = String(this.name || '').trim().split(/\s+/).filter(Boolean);
      if (!words.length) {
        return '?';
      }
      return (words[0][0] + (words.length > 1 ? words[words.length - 1][0] : '')).toUpperCase();
    },
    // roles also carries the coarse "staff" marker — show the meaningful one
    roleLabel() {
      const roles = (this.roles || []).filter(role => role !== 'staff');
      const label = roles[0] || (this.roles || [])[0] || '';
      return label ? label.charAt(0).toUpperCase() + label.slice(1) : '';
    },
  },
  methods: {
    toggleSideBar() {
      useAppStore().toggleSideBar();
    },
    toggleFullscreen() {
      if (!screenfull.isEnabled) {
        this.$message({ message: 'Fullscreen is not available in this browser', type: 'warning' });
        return;
      }
      screenfull.toggle();
    },
    onCommand(command) {
      if (command === 'dashboard') {
        this.$router.push('/dashboard');
      } else if (command === 'profile') {
        this.$router.push('/profile/edit');
      } else if (command === 'store') {
        this.$router.push('/home');
      } else if (command === 'logout') {
        this.logout();
      }
    },
    async logout() {
      await useUserStore().logout();
      window.location = '/';
    },
  },
};
</script>

<style lang="scss" scoped>
.navbar {
  display: flex;
  align-items: center;
  gap: 14px;
  height: var(--admin-header-height);
  padding: 0 28px;

  &__icon-btn {
    display: grid;
    place-items: center;
    flex: none;
    width: 40px;
    height: 40px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: var(--admin-muted);
    font-size: 20px;
    cursor: pointer;
    transition: background-color 0.15s ease, color 0.15s ease;

    &:hover {
      background: var(--admin-primary-soft);
      color: var(--admin-primary);
    }
  }

  &__breadcrumb {
    flex: 1 1 auto;
    min-width: 0;
  }

  &__right {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: none;
  }

  &__store {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-right: 8px;
    padding: 8px 14px;
    border-radius: 999px;
    border: 1px solid var(--admin-border-strong);
    color: var(--admin-text);
    font-size: 13px;
    font-weight: 600;
    transition: border-color 0.15s ease, color 0.15s ease, background-color 0.15s ease;

    &:hover {
      border-color: var(--admin-primary);
      color: var(--admin-primary);
      background: var(--admin-primary-soft);
    }
  }

  &__user {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: 8px;
    padding: 5px 10px 5px 5px;
    border: 0;
    border-radius: 999px;
    background: transparent;
    cursor: pointer;
    transition: background-color 0.15s ease;

    &:hover {
      background: var(--admin-primary-soft);
    }
  }

  &__avatar {
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2b45d6, var(--admin-primary));
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.02em;
  }

  &__who {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.25;
  }

  &__name {
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 13.5px;
    font-weight: 650;
    color: var(--admin-text);
  }

  &__role {
    font-size: 12px;
    color: var(--admin-muted);
  }

  &__caret {
    color: var(--admin-muted);
    font-size: 13px;
  }
}

@media (max-width: 991px) {
  .navbar {
    padding: 0 12px;
    gap: 8px;
  }
}
</style>
