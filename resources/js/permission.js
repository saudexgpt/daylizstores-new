import router, { addDynamicRoutes } from './router';
import { pinia, useItemsStore, useAppStore, useOrderStore, useUserStore, usePermissionStore } from './store';
import { ElMessage } from 'element-plus';
import NProgress from 'nprogress'; // progress bar
import 'nprogress/nprogress.css'; // progress bar style
import { getToken } from '@/utils/auth'; // get token from cookie
import getPageTitle from '@/utils/get-page-title';

NProgress.configure({ showSpinner: false }); // NProgress Configuration

const whiteList = ['/login', '', 'home', '/reset-password', '/product', '/about', '/track', '/auth-redirect']; // no redirect whitelist

// This module's top-level calls run before app.js finishes app.use(pinia), so
// every store here is looked up with the pinia instance passed explicitly
// rather than relying on an already-active global pinia.
const itemsStore = useItemsStore(pinia);
const appStore = useAppStore(pinia);
const orderStore = useOrderStore(pinia);

itemsStore.fetchAllItems();
itemsStore.fetchCategories();
itemsStore.fetchLatestProducts();
appStore.setNecessaryParams();
itemsStore.loadPersistentData();
orderStore.loadOfflineData();
router.beforeEach(async(to, from, next) => {
  // start progress bar
  NProgress.start();
  // set page title
  document.title = getPageTitle(to.meta.title);

  // determine whether the user has logged in
  const hasToken = getToken();
  if (hasToken) {
    if (to.path === '/login') {
      // if is logged in, redirect to the home page
      next({ path: '/' });
      NProgress.done();
    } else {
      // check whether password status is default and redirect user to change their password
      const userStore = useUserStore(pinia);

      // determine whether the user has obtained his permission roles through getInfo
      const hasRoles = userStore.roles && userStore.roles.length > 0;
      if (hasRoles) {
        next();
      } else {
        try {
          // get user info
          // note: roles must be a object array! such as: ['admin'] or ,['manager','editor']
          const { roles, permissions } = await userStore.getInfo();

          // generate accessible routes map based on roles
          // Awaited (not a floating .then): this guard is async, and vue-router 4 rejects
          // an async guard that settles before next() was called ("Invalid navigation guard").
          const response = await usePermissionStore(pinia).generateRoutes({ roles, permissions });
          // dynamically add accessible routes
          addDynamicRoutes(response);
          // method to ensure that addRoute is complete
          // set the replace: true, so the navigation will not leave a history record
          next({ ...to, replace: true });
        } catch (error) {
          // remove token and go to login page to re-login
          await userStore.resetToken();
          ElMessage.error(error || 'Has Error');
          next(`/login`);
          NProgress.done();
        }
      }
    }
  } else {
    /* has no token*/
    if (whiteList.indexOf(to.matched[0] ? to.matched[0].path : '') !== -1) {
      // in the free login whitelist, go directly
      next();
    } else {
      // other pages that do not have permission to access are redirected to the login page.
      next(`/login`);
      NProgress.done();
    }
  }
});

router.afterEach(() => {
  // finish progress bar
  NProgress.done();
});
