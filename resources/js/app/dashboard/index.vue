<template>
  <div class="dashboard-container">
    <component :is="currentRole" />
  </div>
</template>

<script>
import { mapState } from 'pinia';
import { useUserStore } from '@/store';
import checkPermission from '@/utils/permission';
import adminDashboard from './admin';
import editorDashboard from './editor';

export default {
  // name: 'Dashboard',
  components: { adminDashboard, editorDashboard },
  data() {
    return {
      currentRole: 'adminDashboard',
    };
  },
  computed: {
    ...mapState(useUserStore, ['roles']),
  },
  created() {
    // The full dashboard is for administrators and for any role that has been granted
    // "view admin dashboard" (the API behind it requires that permission for everyone else).
    const canSeeAdminDashboard = this.roles.includes('admin') ||
      this.roles.includes('assistant admin') ||
      checkPermission(['view admin dashboard']);
    if (!canSeeAdminDashboard) {
      this.currentRole = 'editorDashboard';
    }
  },
};
</script>
