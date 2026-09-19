import { defineStore } from 'pinia';
import defaultSettings from '@/settings';

const { showSettings, tagsView, fixedHeader, sidebarLogo, theme, secondaryTheme } = defaultSettings;

export const useSettingsStore = defineStore('settings', {
  state: () => ({
    theme,
    secondaryTheme,
    showSettings,
    tagsView,
    fixedHeader,
    sidebarLogo,
  }),
  actions: {
    changeSetting({ key, value }) {
      // eslint-disable-next-line no-prototype-builtins
      if (this.$state.hasOwnProperty(key)) {
        this[key] = value;
      }
    },
  },
});
