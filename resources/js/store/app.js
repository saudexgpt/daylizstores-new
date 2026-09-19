import Cookies from 'js-cookie';
import { defineStore } from 'pinia';
import { getLanguage } from '@/lang/index';
import Resource from '@/api/resource';

export const useAppStore = defineStore('app', {
  state: () => ({
    sidebar: {
      opened: Cookies.get('sidebarStatus') ? !!+Cookies.get('sidebarStatus') : true,
      withoutAnimation: false,
    },
    device: 'desktop',
    language: getLanguage(),
    size: Cookies.get('size') || 'medium',
    params: null,
  }),
  actions: {
    toggleSideBar() {
      this.sidebar.opened = !this.sidebar.opened;
      this.sidebar.withoutAnimation = false;
      Cookies.set('sidebarStatus', this.sidebar.opened ? 1 : 0);
    },
    closeSideBar({ withoutAnimation }) {
      Cookies.set('sidebarStatus', 0);
      this.sidebar.opened = false;
      this.sidebar.withoutAnimation = withoutAnimation;
    },
    toggleDevice(device) {
      this.device = device;
    },
    setLanguage(language) {
      this.language = language;
      Cookies.set('language', language);
    },
    setSize(size) {
      this.size = size;
      Cookies.set('size', size);
    },
    setNecessaryParams() {
      const necessaryParams = new Resource('fetch-necessary-params');
      return necessaryParams.list().then(response => {
        this.params = response.params;
      });
    },
  },
});
