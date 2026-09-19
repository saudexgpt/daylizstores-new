<template>
  <div class="profile">
    <admin-page-header title="My profile" subtitle="Your details and password." />

    <div v-loading="loading" class="profile__grid">
      <div class="stack">
        <user-card :user="user" />
        <user-bio :user="user" />
      </div>
      <user-activity :user="user" @updated="onUpdated" />
    </div>
  </div>
</template>

<script>
import UserBio from './components/UserBio';
import UserCard from './components/UserCard';
import UserActivity from './components/UserActivity';
import { useUserStore } from '@/store';

export default {
  name: 'SelfProfile',
  components: { UserBio, UserCard, UserActivity },
  data() {
    return {
      user: {},
      loading: false,
    };
  },
  created() {
    this.getUser();
  },
  methods: {
    getUser() {
      this.loading = true;
      return useUserStore().getInfo()
        .then(data => {
          this.user = data;
        })
        .catch(() => {})
        .finally(() => {
          this.loading = false;
        });
    },
    // keep the header's name in step with what was just saved
    onUpdated(updated) {
      this.user = updated;
      useUserStore().userData.name = updated.name;
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
