<template>
  <div :class="classObj" class="app-wrapper admin-shell">
    <audio id="myAudio">
      <source src="/alert.mp3" type="audio/mpeg">
    </audio>
    <div style="display: none">
      <button id="play_audio" @click="playAudio()">Play Audio</button>
    </div>

    <div v-if="device==='mobile'&&sidebar.opened" class="drawer-bg" @click="handleClickOutside" />
    <sidebar class="admin-sidebar" />
    <div class="admin-main">
      <div class="admin-header">
        <navbar />
        <tags-view v-if="needTagsView" />
      </div>
      <main class="admin-content">
        <app-main />
      </main>
      <right-panel v-if="showSettings">
        <settings />
      </right-panel>
    </div>
  </div>
</template>

<script>
import RightPanel from '@/components/RightPanel';
import { Navbar, Sidebar, AppMain, TagsView, Settings } from './components';
import ResizeMixin from './mixin/resize-handler.js';
import { mapState } from 'pinia';
import { useAppStore, useSettingsStore, useUserStore } from '@/store';
import Pusher from 'pusher-js';
import Echo from 'laravel-echo';
import Resource from '@/api/resource';
const userNotifications = new Resource('user-notifications');
export default {
  name: 'Layout',
  components: {
    AppMain,
    Navbar,
    RightPanel,
    Settings,
    Sidebar,
    TagsView,
  },
  mixins: [ResizeMixin],
  computed: {
    userStore() {
      return useUserStore();
    },
    ...mapState(useAppStore, {
      sidebar: 'sidebar',
      device: 'device',
    }),
    ...mapState(useSettingsStore, {
      showSettings: 'showSettings',
      needTagsView: 'tagsView',
    }),
    classObj() {
      return {
        hideSidebar: !this.sidebar.opened,
        openSidebar: this.sidebar.opened,
        withoutAnimation: this.sidebar.withoutAnimation,
        mobile: this.device === 'mobile',
      };
    },
  },
  created() {
    this.fetchUserNotifications();
    // this.listenForChanges();
  },
  mounted() {
    // Scopes the admin design system (styles/admin.scss) to admin screens only —
    // it also has to reach popovers/dialogs, which Element Plus teleports to <body>.
    document.body.classList.add('admin-mode');
  },
  beforeUnmount() {
    document.body.classList.remove('admin-mode');
  },
  methods: {
    fetchUserNotifications() {
      userNotifications.list().then((response) => {
        // always an array — an undefined here used to crash every admin page render
        this.userStore.setNotifications(response.notifications);
      });
    },
    // Realtime notifications over Pusher. Currently switched off (see created()).
    listenForChanges() {
      window.Pusher = Pusher;
      window.Echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        encrypted: true,
        auth: {
          headers: {
            Authorization: 'Bearer ' + this.userStore.token,
          },
        },
      });
      const currentUserId = this.userStore.userId;
      return window.Echo.private('App.Laravue.Models.User.' + currentUserId)
        .notification((notification) => {
          document.getElementById('play_audio').click();
          this.pushNotification(notification);
          this.$notify({
            title: notification.title,
            message: notification.description,
            type: 'success',
            duration: 10000,
          });
        });
    },
    pushNotification(notification) {
      const data = {
        title: notification.title,
        description: notification.description,
      };
      notification.data = data;
      this.userStore.notifications.unshift(notification);
    },
    handleClickOutside() {
      useAppStore().closeSideBar({ withoutAnimation: false });
    },
    playAudio() {
      const audio = document.getElementById('myAudio');
      audio.play();
    },
  },
};
</script>
