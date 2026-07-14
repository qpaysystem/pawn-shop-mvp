import { mapAuthUser, type ApiAuthUserPayload } from '@/src/auth/mapUser';
import { env } from '@/src/config/env';
import { mockApi } from '@/src/mocks/handlers';
import type { AuthUser, LoginRequest, LoginResponse } from '@/src/types/auth';
import { apiRequest } from './http';

interface LoginResponsePayload {
  token: string;
  user: ApiAuthUserPayload;
}

/**
 * Mobile Auth API v1 (Laravel Sanctum).
 * @see MOBILE_BACKEND_INTEGRATION_PLAN.md — Implemented: Mobile Auth v1
 */
export async function loginApi(body: LoginRequest): Promise<LoginResponse> {
  if (env.useMockAuth) {
    return mockApi.login(body);
  }

  const res = await apiRequest<LoginResponsePayload>('/auth/login', {
    method: 'POST',
    body: {
      email: body.email,
      password: body.password,
      device_name: body.device_name,
    },
  });

  return {
    token: res.token,
    user: mapAuthUser(res.user),
  };
}

export async function meApi(token: string): Promise<AuthUser> {
  if (env.useMockAuth) {
    return mapAuthUser(await mockApi.me());
  }

  const raw = await apiRequest<ApiAuthUserPayload>('/auth/me', { token });
  return mapAuthUser(raw);
}

export async function logoutApi(token: string): Promise<void> {
  if (env.useMockAuth) {
    return;
  }

  await apiRequest<null>('/auth/logout', { method: 'POST', token });
}
