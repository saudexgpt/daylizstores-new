<template>
  <el-color-picker
    v-model="theme"
    :predefine="['#409EFF', '#11a983', '#13c2c2', '#6959CD', '#f5222d', '#eb2f96', '#DB7093', '#e6a23c', '#8B8989', '#212121']"
    class="theme-picker"
    popper-class="theme-picker-dropdown"
    @change="handleChange"
  />
</template>

<script>
// element-ui's old theme-picker fetched element-ui's compiled CSS from a CDN
// at runtime and regex-replaced its color values — a hack tied to a package
// (and a CSS structure) that no longer exists under Element Plus. Element
// Plus themes via CSS custom properties instead, so runtime switching is
// just setting one variable.
const ORIGINAL_THEME = '#409EFF'; // default color

export default {
  data() {
    return {
      theme: ORIGINAL_THEME,
    };
  },
  methods: {
    handleChange(val) {
      if (typeof val !== 'string') {
        return;
      }
      document.documentElement.style.setProperty('--el-color-primary', val);
      this.$message({
        message: 'Theme has been changed!',
        type: 'success',
      });
    },
  },
};
</script>

<style>
.theme-message,
.theme-picker-dropdown {
  z-index: 99999 !important;
}

.theme-picker .el-color-picker__trigger {
  height: 26px !important;
  width: 26px !important;
  padding: 2px;
}

.theme-picker-dropdown .el-color-dropdown__link-btn {
  display: none;
}
</style>
