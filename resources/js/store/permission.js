import { defineStore } from 'pinia';
// Deliberately NOT a static top-level import: router/index.js's lazily-loaded
// page components import the store barrel (@/store), which re-exports this
// file — a static `import ... from '@/router'` here closes that into a real
// circular dependency, and once Rollup bundles the lazy routes into the same
// chunk as this file, ESM's TDZ semantics turn it into a hard
// "Cannot access 'useUserStore' before initialization" crash at runtime.
// Deferring to a dynamic import (only actually needed inside the action
// below, at runtime, long after bootstrap) breaks the cycle.

/**
 * Check if it matches the current user right by meta.role
 * @param {String[]} roles
 * @param {String[]} permissions
 * @param route
 */
function canAccess(roles, permissions, route) {
  if (route.meta) {
    let hasRole = true;
    let hasPermission = true;
    if (route.meta.roles || route.meta.permissions) {
      // If it has meta.roles or meta.permissions, accessible = hasRole || permission
      hasRole = false;
      hasPermission = false;
      if (route.meta.roles) {
        hasRole = roles.some(role => route.meta.roles.includes(role));
      }

      if (route.meta.permissions) {
        hasPermission = permissions.some(permission => route.meta.permissions.includes(permission));
      }
    }

    return hasRole || hasPermission;
  }

  // If no meta.roles/meta.permissions inputted - the route should be accessible
  return true;
}

/**
 * Find all routes of this role
 * @param routes asyncRoutes
 * @param roles
 */
function filterAsyncRoutes(routes, roles, permissions) {
  const res = [];

  routes.forEach(route => {
    const tmp = { ...route };
    if (canAccess(roles, permissions, tmp)) {
      if (tmp.children) {
        tmp.children = filterAsyncRoutes(
          tmp.children,
          roles,
          permissions,
        );
      }
      res.push(tmp);
    }
  });

  return res;
}

export const usePermissionStore = defineStore('permission', {
  state: () => ({
    routes: [],
    addRoutes: [],
  }),
  actions: {
    async generateRoutes({ roles, permissions }) {
      const { asyncRoutes, constantRoutes } = await import('@/router');
      let accessedRoutes;
      if (roles.includes('admin')) {
        accessedRoutes = asyncRoutes;
      } else {
        accessedRoutes = filterAsyncRoutes(asyncRoutes, roles, permissions);
      }

      this.addRoutes = accessedRoutes;
      this.routes = constantRoutes.concat(accessedRoutes);
      return accessedRoutes;
    },
  },
});
