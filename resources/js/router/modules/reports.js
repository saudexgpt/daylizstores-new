
// One sidebar entry. Which reports appear inside it depends on the user's permissions
// (sales / stock / customers need "view reports"; the financial statements "view accounting").
const reportRoutes = {
  path: '/reports',
  component: () => import('@/layout'),
  redirect: '/reports/index',
  meta: { permissions: ['view reports', 'view accounting'] },
  children: [
    {
      path: 'index',
      component: () => import('@/app/reports/ReportCentre'),
      name: 'ReportCentre',
      meta: { title: 'Reports', icon: 'DataAnalysis', permissions: ['view reports', 'view accounting'] },
    },
  ],
};

export default reportRoutes;
