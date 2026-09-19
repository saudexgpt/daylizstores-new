<template>
  <div class="profile">
    <admin-page-header title="User profile" :subtitle="user.email || ''">
      <el-button @click="$router.back()">
        <el-icon><IconArrowLeft /></el-icon>
        Back
      </el-button>
    </admin-page-header>

    <div v-loading="loading" class="profile__grid">
      <div class="stack">
        <user-card :user="user" />
        <user-bio :user="user" />
      </div>
      <user-activity :user="user" @updated="user = $event" />
    </div>
  </div>
</template>

<script>
import Resource from '@/api/resource';
import UserBio from './components/UserBio';
import UserCard from './components/UserCard';
import UserActivity from './components/UserActivity';
import { useUserStore } from '@/store';

const userResource = new Resource('users');
export default {
  name: 'EditUser',
  components: { UserBio, UserCard, UserActivity },
  data() {
    return {
      user: {},
      loading: false,
    };
  },
  watch: {
    // (this used to watch the whole $route and pass the route object to getUser as the id)
    '$route.params.id'(id) {
      if (id) {
        this.getUser(id);
      }
    },
  },
  created() {
    const id = this.$route.params && this.$route.params.id;
    // ids from the router are strings, the store's is a number — compare as numbers
    if (Number(id) === Number(useUserStore().userId)) {
      this.$router.replace('/profile/edit');
      return;
    }
    this.getUser(id);
  },
  methods: {
    getUser(id) {
      this.loading = true;
      return userResource.get(id)
        .then(({ data }) => {
          this.user = data;
        })
        .catch(() => {
          // e.g. 403/404 — the interceptor has shown why
          this.$router.replace('/administrator/customers');
        })
        .finally(() => {
          this.loading = false;
        });
    },
  },
};
</script>

<style lang="scss" scoped>
.profile {
  &__grid {
    display: grid;
    grid-template-columns: 320px minmax(0, 1fr);
    gap: 20px;
    align-items: start;

    .admin-card { margin-bottom: 0; }
  }
}

@media (max-width: 991px) {
  .profile__grid { grid-template-columns: minmax(0, 1fr); }
}
</style>
