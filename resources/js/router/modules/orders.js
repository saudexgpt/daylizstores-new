import Layout from '@/layout';

const permissionRoutes = {
  path: '/orders',
  component: Layout,
  redirect: '/orders/view-orders',
  alwaysShow: true, // will always show the root menu
  meta: {
    title: 'Customer Orders',
    icon: 'ShoppingBag', // customer orders
    permissions: ['view order'],
    // roles: ['admin', 'stock officer'],
  },
  children: [

    {
      path: 'view-orders',
      component: () =>
        import ('@/app/order/index'),
      name: 'View',
      meta: {
        title: 'View Orders',
        permissions: ['view order'],
      },
    },
    // {
    //   path: 'details/:id',
    //   component: () => import('@/app/order/Details'),
    //   name: 'OrderDetails',
    //   meta: { title: 'Order Details', noCache: true, permissions: ['view order'] },
    //   hidden: true,
    // },
    // {
    //   path: 'create-new',
    //   component: () =>
    //     import ('@/app/order/Create'),
    //   name: 'Create',
    //   meta: {
    //     title: 'Create Orders',
    //     permissions: ['create order'],
    //   },
    // },
  ],
};

export default permissionRoutes;
