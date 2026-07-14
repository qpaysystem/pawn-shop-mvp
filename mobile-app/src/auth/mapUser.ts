import type { AuthUser, UserPermissions, UserRole } from '@/src/types/auth';

/** Payload from POST /auth/login (user) or GET /auth/me. */
export interface ApiAuthUserPayload {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  store_id?: number | null;
  store_name?: string | null;
  permissions?: UserPermissions;
}

/** Mirrors App\Models\User permission helpers until backend sends permissions. */
export function permissionsForRole(role: UserRole): UserPermissions {
  return {
    can_create_contracts: ['super-admin', 'manager', 'appraiser'].includes(role),
    can_process_sales: ['super-admin', 'manager', 'cashier'].includes(role),
    can_manage_storage: ['super-admin', 'manager', 'storekeeper'].includes(role),
  };
}

export function mapAuthUser(payload: ApiAuthUserPayload): AuthUser {
  return {
    id: payload.id,
    name: payload.name,
    email: payload.email,
    role: payload.role,
    store_id: payload.store_id ?? null,
    store_name: payload.store_name ?? null,
    permissions: payload.permissions ?? permissionsForRole(payload.role),
  };
}
