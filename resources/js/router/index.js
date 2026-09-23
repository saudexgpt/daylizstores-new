import { createRouter, createWebHistory } from 'vue-router';

/* Layout — lazy: an admin visitor never needs the storefront shell (and its cart/header/footer) and a
   storefront customer never needs the admin shell (and its Pusher/Echo real-time wiring, sidebar, etc.);
   eagerly importing both meant every visitor downloaded both, on top of the current page. */
const Layout = () => import('@/layout');
const PublicLayout = () => import('@/layout/Public');

import adminRoutes from './modules/admin';
import errorRoutes from './modules/error';
import InBoundRoutes from './modules/in-bound';
import OrderRoutes from './modules/orders';
import AccountingRoutes from './modules/accounting';
import ReportRoutes from './modules/reports';
/**
 * Sub-menu only appear when children.length>=1
 * @see https://doc.laravue.dev/guide/essentials/router-and-nav.html
 **/

/**
* hidden: true                   if `hidden:true` will not show in the sidebar(default is false)
* alwaysShow: true               if set true, will always show the root menu, whatever its child routes length
*                                if not set alwaysShow, only more than one route under the children
*                                it will becomes nested mode, otherwise not show the root menu
* redirect: noredirect           if `redirect:noredirect` will no redirect in the breadcrumb
* name:'router-name'             the name is used by <keep-alive> (must set!!!)
* meta : {
    roles: ['admin', 'editor']   Visible for these roles only
    permissions: ['view menu zip', 'manage user'] Visible for these permissions only
    title: 'title'               the name show in sub-menu and breadcrumb (recommend set)
    icon: 'DataBoard'            an icon name from utils/icons.js, shown in the sidebar
    noCache: true                if true, the page will no be cached(default is false)
    breadcrumb: false            if false, the item will hidden in breadcrumb (default is true)
    affix: true                  if true, the tag will affix in the tags-view
  }
**/

export const constantRoutes = [{
  path: '/redirect',
  component: Layout,
  hidden: true,
  children: [{
    path: '/redirect/:path*',
    component: () =>
      import ('@/views/redirect/index'),
  }],
},
{
  path: '/login',
  component: () =>
    import ('@/app/login/index'),
  hidden: true,
},
{
  path: '/reset-password',
  component: () => import('@/app/login/ResetPassword'),
  hidden: true,
},
{
  path: '/notifications',
  component: Layout,
  hidden: true,
  meta: {
    title: 'Notifications',
    icon: 'Bell',
    // permissions: ['view audit trail'],
  },
  children: [{
    path: '',
    component: () =>
      import ('@/app/reports/Notifications'),

  }],

},
{
  path: '/auth-redirect',
  component: () =>
    import ('@/app/login/AuthRedirect'),
  hidden: true,
},
{
  // Rendered directly. It used to redirect to the named route Page404, which only
  // exists after the permission routes are added — so signed-out visitors got
  // "No match for ...".
  path: '/404',
  component: () =>
    import ('@/views/error-page/404'),
  hidden: true,
},
{
  path: '/401',
  component: () =>
    import ('@/views/error-page/401'),
  hidden: true,
},
{
  path: '/dashboard',
  component: Layout,
  redirect: 'dashboard/index',
  children: [{
    path: 'index',
    component: () =>
      import ('@/app/dashboard/index'),
    name: 'Dashboard',
    meta: { title: 'dashboard', icon: 'DataBoard', noCache: false },
  },

  ],
},
{
  path: '',
  component: PublicLayout,
  redirect: 'home',
  hidden: true,
  children: [{
    path: 'home',
    component: () => import('@/pages/index'),
    name: 'Home',
  },

  ],
},
{
  path: '/product',
  component: PublicLayout,
  redirect: 'product/list',
  hidden: true,
  children: [{
    path: 'list',
    component: () =>
      import ('@/pages/ProductList'),
    name: 'Menu',
  },
  {
    path: 'check-out',
    component: () =>
      import ('@/pages/CheckOut'),
    name: 'CheckOut',
  },
  {
    path: 'details/:slug',
    component: () => import('@/pages/ItemDetails'),
    name: 'ProductDetails',
    meta: { title: 'Product Details' },
  },
  {
    path: 'search/:slug',
    component: () => import('@/pages/ProductSearch'),
    name: 'ProductSearch',
    meta: { title: 'Product Search' },
  },
  {
    path: 'category/:categoryId',
    component: () => import('@/pages/ProductList'),
    name: 'CategorizedItems',
  },

  ],
},
{
  path: '/track',
  component: PublicLayout,
  redirect: 'track/order',
  hidden: true,
  children: [{
    path: 'order',
    component: () =>
      import ('@/pages/TrackOrder'),
    name: 'TrackOrder',
  },

  ],
},
{
  path: '/about',
  component: PublicLayout,
  hidden: true,
  children: [{
    path: '',
    component: () =>
      import ('@/pages/About'),
    name: 'AboutUs',
  },

  ],
},
{
  path: '/profile',
  component: Layout,
  redirect: '/profile/edit',
  hidden: true,
  children: [{
    path: 'edit',
    component: () =>
      import ('@/app/users/SelfProfile'),
    name: 'SelfProfile',
    meta: { title: 'userProfile', icon: 'user', noCache: true },
  }],
},
{
  path: '/my-account',
  component: PublicLayout,
  redirect: '/my-account/edit',
  hidden: true,
  children: [{
    path: 'edit',
    component: () =>
      import ('@/app/users/SelfProfile'),
    name: 'MyAccount',
    meta: { title: 'userProfile', icon: 'user', noCache: true },
  }],
},
{
  path: '/default-password',
  component: Layout,
  redirect: '/default-password/change',
  hidden: true,
  children: [{
    path: 'change',
    // redirect: 'dashboard',
    component: () =>
      import ('@/app/users/ChangeDefaultPassword'),
    hidden: true,
  }],
},
];
// const adminRoutes = () => import('./modules/admin');
// const errorRoutes = () => import('./modules/error');
// const InBoundRoutes = () => import('./modules/in-bound');
// const OrderRoutes = () => import('./modules/orders');

export const asyncRoutes = [
  InBoundRoutes,
  OrderRoutes,
  AccountingRoutes,
  ReportRoutes,
  adminRoutes,
  errorRoutes,
  { path: '/:pathMatch(.*)*', redirect: '/404', hidden: true },
];

const router = createRouter({
  history: createWebHistory(import.meta.env.VITE_LARAVUE_PATH),
  scrollBehavior: (to) => (to.hash ? { el: to.hash, top: 120 } : { top: 0 }),
  routes: constantRoutes,
});

// vue-router 4 dropped router.addRoutes()/a matcher-swap reset (the old
// approach here). addRoute() now returns a callback that removes exactly
// that route, so track one per dynamically-added top-level route and call
// them all on reset instead of rebuilding the router.
let removeDynamicRoutes = [];

export function addDynamicRoutes(routes) {
  removeDynamicRoutes.push(...routes.map(route => router.addRoute(route)));
}

export function resetRouter() {
  removeDynamicRoutes.forEach(remove => remove());
  removeDynamicRoutes = [];
}

export default router;

