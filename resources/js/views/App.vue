<template>
  <el-config-provider :locale="elementLocale">
    <div id="app">
      <router-view />
    </div>
  </el-config-provider>
</template>

<script>
import { ElConfigProvider } from 'element-plus';
import { getElementLocale } from '@/lang';
import { useAppStore } from '@/store';

export default {
  name: 'App',
  components: { ElConfigProvider },
  data() {
    return {
    };
  },
  computed: {
    appStore() {
      return useAppStore();
    },
    elementLocale() {
      return getElementLocale(this.appStore.language);
    },
  },
};
</script>
