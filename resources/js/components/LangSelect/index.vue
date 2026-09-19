<template>
  <el-dropdown trigger="click" class="international" @command="handleSetLanguage">
    <div>
      <svg-icon class-name="international-icon" icon-class="language" />
    </div>
    <template #dropdown>
      <el-dropdown-menu>
        <el-dropdown-item :disabled="language==='vi'" command="vi">
          Tiếng Việt
        </el-dropdown-item>
        <el-dropdown-item :disabled="language==='en'" command="en">
          English
        </el-dropdown-item>
        <el-dropdown-item :disabled="language==='zh'" command="zh">
          中文
        </el-dropdown-item>
        <el-dropdown-item :disabled="language==='ru'" command="ru">
          Русский
        </el-dropdown-item>
      </el-dropdown-menu>
    </template>
  </el-dropdown>
</template>

<script>
import { useAppStore } from '@/store';
export default {
  computed: {
    language() {
      return useAppStore().language;
    },
  },
  methods: {
    handleSetLanguage(lang) {
      this.$i18n.locale = lang;
      useAppStore().setLanguage(lang);
      this.$message({
        message: 'Switch Language Success',
        type: 'success',
      });
    },
  },
};
</script>

<style scoped>
.international-icon {
  font-size: 20px;
  cursor: pointer;
  vertical-align: -5px!important;
}
</style>

