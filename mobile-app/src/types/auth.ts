/** Mirrors App\Models\User role constants. */
export type UserRole =
  | 'super-admin'
  | 'manager'
  | 'appraiser'
  | 'cashier'
  | 'storekeeper';

export interface UserPermissions {
  can_create_contracts: boolean;
  can_process_sales: boolean;
  can_manage_storage: boolean;
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  store_id: number | null;
  /** Populated by /api/v1/auth/me when backend adds it. */
  store_name?: string | null;
  permissions: UserPermissions;
}

export interface LoginRequest {
  email: string;
  password: string;
  device_name: string;
}

export interface LoginResponse {
  token: string;
  user: AuthUser;
}
