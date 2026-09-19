<template>
  <div id="tags-view-container" class="tags-view-container">
    <scroll-pane ref="scrollPane" class="tags-view-wrapper">
      <router-link
        v-for="tag in visitedViews"
        ref="tag"
        :key="tag.path"
        :class="isActive(tag)?'active':''"
        :to="{ path: tag.path, query: tag.query, fullPath: tag.fullPath }"
        class="tags-view-item"
        @click.middle="closeSelectedTag(tag)"
        @contextmenu.prevent="openMenu(tag,$event)"
      >
        {{ generateTitle(tag.title) }}
        <el-icon
          v-if="!tag.meta.affix"
          class="tag-close"
          aria-label="Close tab"
          @click.prevent.stop="closeSelectedTag(tag)"
        >
          <IconClose />
        </el-icon>
      </router-link>
    </scroll-pane>
    <ul v-show="visible" :style="{left:left+'px',top:top+'px'}" class="contextmenu">
      <li @click="refreshSelectedTag(selectedTag)">
        {{ $t('tagsView.refresh') }}
      </li>
      <li v-if="!(selectedTag.meta&&selectedTag.meta.affix)" @click="closeSelectedTag(selectedTag)">
        {{
          $t('tagsView.close') }}
      </li>
      <li @click="closeOthersTags">
        {{ $t('tagsView.closeOthers') }}
      </li>
      <li @click="closeAllTags(selectedTag)">
        {{ $t('tagsView.closeAll') }}
      </li>
    </ul>
  </div>
</template>

<script>
import ScrollPane from './ScrollPane';
import { generateTitle } from '@/utils/i18n';
import { resolvePath as resolveRoutePath } from '@/utils/resolvePath';
import { useTagsViewStore, usePermissionStore } from '@/store';

export default {
  components: { ScrollPane },
  data() {
    return {
      visible: false,
      top: 0,
      left: 0,
      selectedTag: {},
      affixTags: [],
    };
  },
  computed: {
    tagsViewStore() {
      return useTagsViewStore();
    },
    visitedViews() {
      return this.tagsViewStore.visitedViews;
    },
    routes() {
      return usePermissionStore().routes;
    },
  },
  watch: {
    $route() {
      this.addTags();
      this.moveToCurrentTag();
    },
    visible(value) {
      if (value) {
        document.body.addEventListener('click', this.closeMenu);
      } else {
        document.body.removeEventListener('click', this.closeMenu);
      }
    },
  },
  mounted() {
    this.initTags();
    this.addTags();
  },
  methods: {
    generateTitle, // generateTitle by vue-i18n
    isActive(route) {
      return route.path === this.$route.path;
    },
    filterAffixTags(routes, basePath = '/') {
      let tags = [];
      routes.forEach(route => {
        if (route.meta && route.meta.affix) {
          const tagPath = resolveRoutePath(basePath, route.path);
          tags.push({
            fullPath: tagPath,
            path: tagPath,
            name: route.name,
            meta: { ...route.meta },
          });
        }
        if (route.children) {
          const tempTags = this.filterAffixTags(route.children, route.path);
          if (tempTags.length >= 1) {
            tags = [...tags, ...tempTags];
          }
        }
      });
      return tags;
    },
    initTags() {
      const affixTags = this.affixTags = this.filterAffixTags(this.routes);
      for (const tag of affixTags) {
        // Must have tag name
        if (tag.name) {
          this.tagsViewStore.addVisitedView(tag);
        }
      }
    },
    addTags() {
      const { name } = this.$route;
      if (name) {
        this.tagsViewStore.addView(this.$route);
      }
      return false;
    },
    moveToCurrentTag() {
      const tags = this.$refs.tag;
      this.$nextTick(() => {
        for (const tag of tags) {
          if (tag.to.path === this.$route.path) {
            this.$refs.scrollPane.moveToTarget(tag);
            // when query is different then update
            if (tag.to.fullPath !== this.$route.fullPath) {
              this.tagsViewStore.updateVisitedView(this.$route);
            }
            break;
          }
        }
      });
    },
    refreshSelectedTag(view) {
      this.tagsViewStore.delCachedView(view).then(() => {
        const { fullPath } = view;
        this.$nextTick(() => {
          this.$router.replace({
            path: '/redirect' + fullPath,
          });
        });
      });
    },
    closeSelectedTag(view) {
      this.tagsViewStore.delView(view).then(({ visitedViews }) => {
        if (this.isActive(view)) {
          this.toLastView(visitedViews);
        }
      });
    },
    closeOthersTags() {
      this.$router.push(this.selectedTag);
      this.tagsViewStore.delOthersViews(this.selectedTag).then(() => {
        this.moveToCurrentTag();
      });
    },
    closeAllTags(view) {
      this.tagsViewStore.delAllViews().then(({ visitedViews }) => {
        if (this.affixTags.some(tag => tag.path === view.path)) {
          return;
        }
        this.toLastView(visitedViews);
      });
    },
    toLastView(visitedViews) {
      const latestView = visitedViews.slice(-1)[0];
      if (latestView) {
        this.$router.push(latestView);
      } else {
        // You can set another route
        this.$router.push('/');
      }
    },
    openMenu(tag, e) {
      // the menu is position: fixed, so these are viewport coordinates
      const menuWidth = 180;
      this.left = Math.max(8, Math.min(e.clientX, window.innerWidth - menuWidth));
      this.top = e.clientY;
      this.visible = true;
      this.selectedTag = tag;
    },
    closeMenu() {
      this.visible = false;
    },
  },
};
</script>

<style lang="scss" scoped>
.tags-view-container {
  height: 46px;
  width: 100%;
  border-top: 1px solid var(--admin-border);
  background: var(--admin-surface);

  .tags-view-wrapper {
    .tags-view-item {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      position: relative;
      height: 30px;
      margin: 8px 0 0 8px;
      padding: 0 12px;
      border-radius: 999px;
      border: 1px solid var(--admin-border);
      background: var(--admin-surface);
      color: var(--admin-muted);
      font-size: 13px;
      font-weight: 500;
      line-height: 1;
      cursor: pointer;
      transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;

      &:first-of-type {
        margin-left: 28px;
      }

      &:last-of-type {
        margin-right: 28px;
      }

      &:hover {
        border-color: var(--el-color-primary-light-7);
        color: var(--admin-primary);
      }

      &.active {
        background: var(--admin-primary-soft);
        border-color: var(--el-color-primary-light-7);
        color: var(--admin-primary);
        font-weight: 650;
      }

      .tag-close {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        font-size: 11px;
        transition: background-color 0.15s ease, color 0.15s ease;

        &:hover {
          background: var(--admin-primary);
          color: #fff;
        }
      }
    }
  }

  .contextmenu {
    margin: 0;
    background: var(--admin-surface);
    z-index: 3000;
    position: fixed;
    list-style-type: none;
    padding: 6px;
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    font-size: 13px;
    font-weight: 500;
    color: var(--admin-text);
    box-shadow: var(--admin-shadow-lg);

    li {
      margin: 0;
      padding: 8px 14px;
      border-radius: 8px;
      cursor: pointer;

      &:hover {
        background: var(--admin-primary-soft);
        color: var(--admin-primary);
      }
    }
  }
}

@media (max-width: 991px) {
  .tags-view-container .tags-view-wrapper .tags-view-item {
    &:first-of-type { margin-left: 12px; }
    &:last-of-type { margin-right: 12px; }
  }
}
</style>
