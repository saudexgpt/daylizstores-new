import { useUserStore } from '@/store';

/**
 * @param {Array} value
 * @returns {Boolean}
 * @example see @/views/permission/Directive.vue
 */
export default function checkPermission(value) {
  if (value && value instanceof Array && value.length > 0) {
    const permissions = useUserStore().permissions;
    const requiredPermissions = value;

    const hasPermission = permissions.some(permission => {
      return requiredPermissions.includes(permission);
    });

    return hasPermission;
  } else {
    console.error(`Need permissions! Like v-permission="['manage permission','edit article']"`);
    return false;
  }
}

/**
 * Whether the signed-in user holds one permission. Admins hold every permission (the server
 * grants them all), so they pass without it being listed — same rule as the API.
 * Use it to show or hide buttons; the API still enforces the permission itself.
 */
export function can(permission) {
  const store = useUserStore();
  return store.roles.includes('admin') || store.permissions.includes(permission);
}
