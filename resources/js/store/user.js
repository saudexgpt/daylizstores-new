import { defineStore } from 'pinia';
import { login, logout, getInfo } from '@/api/auth';
import { getToken, setToken, removeToken } from '@/utils/auth';
import { usePermissionStore } from './permission';

// Deliberately NOT a static top-level import — see the comment in
// store/permission.js. router/index.js's lazily-loaded page components pull
// in the store barrel, which re-exports this file; a static import of
// '@/router' here closes that into a circular dependency that becomes a
// hard TDZ crash once Rollup bundles the lazy routes into the same chunk.
async function getRouter() {
  return import('@/router');
}

export const useUserStore = defineStore('user', {
  state: () => ({
    userData: {
      id: null,
      token: getToken(),
      name: '',
      email: '',
      phone: '',
      avatar: '',
      introduction: '',
      roles: [],
      permissions: [],
      p_status: '',
      notifications: [],
      address: '',
      nearest_bustop: '',
    },
  }),
  getters: {
    userId: state => state.userData.id,
    token: state => state.userData.token,
    avatar: state => state.userData.avatar,
    name: state => state.userData.name,
    pStatus: state => state.userData.p_status, // password status i.e default or custom
    introduction: state => state.userData.introduction,
    roles: state => state.userData.roles,
    permissions: state => state.userData.permissions,
    notifications: state => state.userData.notifications,
  },
  actions: {
    // user login
    login(userInfo) {
      const { email, password } = userInfo;
      return login({ email: email.trim(), password: password })
        .then(response => {
          Object.assign(this.userData, response);
          setToken(response.token);
          return response;
        });
    },

    // get user info
    getInfo() {
      return getInfo(this.userData.token)
        .then(response => {
          const { data } = response;

          if (!data) {
            return Promise.reject('Verification failed, please Login again.');
          }

          const { roles } = data;
          // roles must be a non-empty array
          if (!roles || roles.length <= 0) {
            return Promise.reject('getInfo: roles must be a non-null array!');
          }
          Object.assign(this.userData, data);
          return data;
        })
        .catch(error => Promise.reject('Error: ' + error));
    },

    // user logout
    async logout() {
      const { resetRouter } = await getRouter();
      return logout(this.userData.token)
        .then(() => {
          this.userData.token = '';
          this.userData.roles = [];
          removeToken();
          resetRouter();
        });
    },

    resetPasswordStatus(status) {
      const { p_status } = status;
      this.userData.p_status = p_status;
    },

    setNotifications(notifications) {
      // never store anything but an array: components read `.length` on it
      this.userData.notifications = Array.isArray(notifications) ? notifications : [];
    },
    // remove token
    resetToken() {
      this.userData.token = '';
      this.userData.roles = [];
      removeToken();
    },

    // Dynamically modify permissions
    async changeRoles(role) {
      const { default: router, resetRouter } = await getRouter();
      const roles = [role.name];
      const permissions = role.permissions.map(permission => permission.name);
      this.userData.roles = roles;
      this.userData.permissions = permissions;
      resetRouter();

      // generate accessible routes map based on roles
      const accessRoutes = await usePermissionStore().generateRoutes({ roles, permissions });

      // dynamically add accessible routes
      accessRoutes.forEach(route => router.addRoute(route));
    },
  },
});
