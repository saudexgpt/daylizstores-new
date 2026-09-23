
const permissionRoutes = {
  path: '/food-menu',
  component: () => import('@/layout'),
  redirect: 'noredirect',
  alwaysShow: true, // will always show the root menu
  meta: {
    title: 'Manage Products',
    icon: 'Goods', // products & stock
    // permissions: ['view-menu-warehouse'],
    permissions: ['create menu'],
  },
  children: [

    {
      path: 'category',
      component: () => import('@/app/stock/item-category/ItemCategory'),
      name: 'ItemCategory',
      meta: {
        title: 'Categories',
      },
    },
    {
      path: 'manage-items',
      component: () => import('@/app/stock/item/ManageItem'),
      name: 'ManageItem',
      meta: {
        title: 'Products',
      },
    },
    {
      path: 'receive-stock',
      component: () => import('@/app/stock/receive/ReceiveStock'),
      name: 'ReceiveStock',
      meta: { title: 'Receive Stock', permissions: ['create menu'] },
    },
    {
      path: 'deliveries',
      component: () => import('@/app/stock/receive/Deliveries'),
      name: 'StockDeliveries',
      meta: { title: 'Deliveries', permissions: ['view cost'] },
    },
    {
      path: 'stock-adjustments',
      component: () => import('@/app/stock/receive/StockAdjustments'),
      name: 'StockAdjustments',
      meta: { title: 'Stock Adjustments', permissions: ['create menu'] },
    },
    {
      path: 'out-of-stock',
      component: () => import('@/app/stock/restock/OutOfStock'),
      name: 'OutOfStock',
      meta: {
        title: 'Out of Stock',
      },
    },

  ],
};

export default permissionRoutes;
