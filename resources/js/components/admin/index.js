import PageHeader from './PageHeader';
import AdminCard from './AdminCard';
import StatCard from './StatCard';
import StatusTag from './StatusTag';
import AdminEmpty from './AdminEmpty';

/**
 * The admin design system, registered globally so any admin page can use it
 * without an import block: <AdminPageHeader>, <AdminCard>, <AdminStatCard>,
 * <AdminStatusTag>, <AdminEmpty>. (Prefixed so they can't collide with the
 * storefront's components/ui set.)
 */
export function installAdminComponents(app) {
  app.component('AdminPageHeader', PageHeader);
  app.component('AdminCard', AdminCard);
  app.component('AdminStatCard', StatCard);
  app.component('AdminStatusTag', StatusTag);
  app.component('AdminEmpty', AdminEmpty);
}
