<template>
  <error-state
    code="403"
    title="You don't have access to this page"
    message="Your account isn't allowed to open this area. If you think that's a mistake, ask an administrator to update your role."
  >
    <el-button @click="back">Go back</el-button>
    <el-button type="primary" @click="$router.push('/dashboard')">Go to the dashboard</el-button>
  </error-state>
</template>

<script>
import ErrorState from './ErrorState';

export default {
  name: 'Page401',
  components: { ErrorState },
  methods: {
    back() {
      if (this.$route.query.noGoBack || window.history.length <= 1) {
        this.$router.push({ path: '/dashboard' });
      } else {
        this.$router.go(-1);
      }
    },
  },
};
</script>
