<template>
  <error-state
    code="404"
    title="We can't find that page"
    message="The page may have moved, or the link might be wrong. Let's get you back on track."
  >
    <el-button @click="back">Go back</el-button>
    <el-button type="primary" @click="$router.push('/home')">Back to the store</el-button>
  </error-state>
</template>

<script>
import ErrorState from './ErrorState';

export default {
  name: 'Page404',
  components: { ErrorState },
  methods: {
    back() {
      // a fresh tab has no history to go back to
      window.history.length > 1 ? this.$router.go(-1) : this.$router.push('/home');
    },
  },
};
</script>
