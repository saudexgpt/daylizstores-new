import { createPinia } from 'pinia';

// Exported so modules that run outside a component context (e.g.
// resources/js/permission.js's router guard, which fires before any
// component exists) can pass it explicitly to useXStore(pinia) rather than
// depending on setActivePinia() having already run.
export const pinia = createPinia();

export { useAppStore } from './app';
export { useItemsStore } from './items';
export { useOrderStore } from './order';
export { usePermissionStore } from './permission';
export { useSettingsStore } from './settings';
export { useTagsViewStore } from './tagsView';
export { useUserStore } from './user';
