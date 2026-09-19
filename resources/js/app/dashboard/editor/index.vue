<template>
  <div class="staff-dashboard">
    <admin-page-header :title="`Welcome, ${firstName}`" :subtitle="roleLine" />

    <div v-if="links.length" class="staff-dashboard__grid">
      <router-link v-for="link in links" :key="link.to" :to="link.to" class="quick-link">
        <span class="quick-link__icon">
          <el-icon><component :is="link.icon" /></el-icon>
        </span>
        <span class="quick-link__text">
          <strong>{{ link.title }}</strong>
          <small>{{ link.description }}</small>
        </span>
        <el-icon class="quick-link__go"><IconArrowRight /></el-icon>
      </router-link>
    </div>

    <admin-card v-else>
      <admin-empty
        icon="Lock"
        title="No areas assigned yet"
        description="Your account doesn't have access to any admin sections. Ask an administrator to grant you a role."
      />
    </admin-card>
  </div>
</template>

<script>
import { mapState } from 'pinia';
import { useUserStore } from '@/store';
import checkPermission from '@/utils/permission';

export default {
  name: 'DashboardEditor',
  computed: {
    ...mapState(useUserStore, ['name', 'roles']),
    firstName() {
      return String(this.name || '').trim().split(/\s+/)[0] || 'there';
    },
    roleLine() {
      const roles = (this.roles || []).filter(role => role !== 'staff');
      return roles.length ? `Signed in as ${roles.join(', ')}` : 'Signed in';
    },
    // only what this person's permissions actually allow
    links() {
      return [
        { to: '/orders/view-orders', icon: 'IconShoppingBag', title: 'Orders', description: 'Review and fulfil customer orders', allowed: checkPermission(['view order']) },
        { to: '/food-menu/manage-items', icon: 'IconGoods', title: 'Products', description: 'Add products and manage stock', allowed: checkPermission(['create menu']) },
        { to: '/food-menu/category', icon: 'IconCollectionTag', title: 'Categories', description: 'Organise the catalogue', allowed: checkPermission(['create menu']) },
        { to: '/administrator/customers', icon: 'IconUser', title: 'Customers', description: 'Look up customer accounts', allowed: checkPermission(['manage user']) },
        { to: '/administrator/audit-trails', icon: 'IconMemo', title: 'Audit trail', description: 'See who changed what', allowed: checkPermission(['view audit trail']) },
      ].filter(link => link.allowed);
    },
  },
};
</script>

<style lang="scss" scoped>
.staff-dashboard {
  &__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 18px;
  }
}

.quick-link {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px 22px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-surface);
  box-shadow: var(--admin-shadow);
  transition: border-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;

  &:hover {
    border-color: var(--el-color-primary-light-7);
    transform: translateY(-2px);
    box-shadow: var(--admin-shadow-lg);

    .quick-link__go { transform: translateX(3px); color: var(--admin-primary); }
  }

  &__icon {
    flex: none;
    display: grid;
    place-items: center;
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: var(--admin-primary-soft);
    color: var(--admin-primary);
    font-size: 22px;
  }

  &__text {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 3px;
    min-width: 0;

    strong { font-size: 15px; font-weight: 650; color: var(--admin-text); }
    small { font-size: 13px; color: var(--admin-muted); }
  }

  &__go {
    flex: none;
    color: var(--admin-muted);
    transition: transform 0.15s ease, color 0.15s ease;
  }
}
</style>
