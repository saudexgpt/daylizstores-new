import Layout from '@/layout';

/**
 * Accounting: is the business making a profit? Reading the books needs "view accounting";
 * recording and changing them needs "manage accounting" (admins have both).
 */
const accountingRoutes = {
  path: '/accounting',
  component: Layout,
  redirect: '/accounting/overview',
  alwaysShow: true,
  meta: {
    title: 'Accounting',
    icon: 'Wallet',
    permissions: ['view accounting'],
  },
  children: [
    {
      path: 'overview',
      component: () => import('@/app/accounting/Overview'),
      name: 'AccountingOverview',
      meta: { title: 'Overview', permissions: ['view accounting'] },
    },
    {
      path: 'transactions',
      component: () => import('@/app/accounting/Transactions'),
      name: 'AccountingTransactions',
      meta: { title: 'Income & Expenses', permissions: ['view accounting'] },
    },
    {
      path: 'statements',
      component: () => import('@/app/accounting/Statements'),
      name: 'AccountingStatements',
      meta: { title: 'Financial Statements', permissions: ['view accounting'] },
    },
    {
      path: 'accounts',
      component: () => import('@/app/accounting/Accounts'),
      name: 'AccountingAccounts',
      meta: { title: 'Chart of Accounts', permissions: ['view accounting'] },
    },
    {
      path: 'cost-setup',
      component: () => import('@/app/accounting/CostSetup'),
      name: 'AccountingCostSetup',
      meta: { title: 'Product Costing', permissions: ['manage accounting'] },
    },
    {
      path: 'settings',
      component: () => import('@/app/accounting/Settings'),
      name: 'AccountingSettings',
      meta: { title: 'Accounting Settings', permissions: ['manage accounting'] },
    },
  ],
};

export default accountingRoutes;
