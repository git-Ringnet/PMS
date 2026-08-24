import { useAuthStore } from '@/stores/auth-store'

/**
 * Composable kiểm tra phân quyền người dùng
 * @example
 * const { can, canAny, isSuperAdmin } = usePermission()
 * v-if="can('fo.booking.create')"
 * v-if="canAny(['fo.checkin', 'fo.checkout'])"
 */
export function usePermission() {
  const authStore = useAuthStore()

  return {
    can: (code) => authStore.hasPermission(code),
    canAny: (codes) => authStore.canAny(codes),
    canAll: (codes) => codes.every(c => authStore.hasPermission(c)),
    isSuperAdmin: authStore.isSuperAdmin,
    isAdmin: authStore.isAdmin,
    activeBranch: authStore.activeBranch,
    branches: authStore.branches,
    roles: authStore.roles,
  }
}
