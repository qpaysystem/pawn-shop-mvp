import { env } from '@/src/config/env';
import { mockApi } from '@/src/mocks/handlers';
import type {
  CreatePawnContractPayload,
  PaginatedResponse,
  PawnContract,
  PawnContractListParams,
} from '@/src/types/pawn';
import { apiRequest } from './http';

export async function listPawnContractsApi(
  token: string,
  params: PawnContractListParams = {},
): Promise<PaginatedResponse<PawnContract>> {
  if (env.useMockData) {
    return mockApi.listPawns({
      status: params.status === 'all' ? undefined : params.status,
      q: params.q,
    });
  }
  const qs = new URLSearchParams();
  if (params.store_id) qs.set('store_id', String(params.store_id));
  if (params.status && params.status !== 'all') qs.set('status', params.status);
  if (params.q) qs.set('q', params.q);
  if (params.page) qs.set('page', String(params.page));
  if (params.per_page) qs.set('per_page', String(params.per_page));
  const query = qs.toString();
  return apiRequest<PaginatedResponse<PawnContract>>(
    `/pawn-contracts${query ? `?${query}` : ''}`,
    { token },
  );
}

/** Загружает все страницы списка (для экрана залогов). */
export async function listAllPawnContractsApi(
  token: string,
  params: Omit<PawnContractListParams, 'page' | 'per_page'> = {},
): Promise<PaginatedResponse<PawnContract>> {
  const perPage = 100;
  const first = await listPawnContractsApi(token, { ...params, page: 1, per_page: perPage });
  const all = [...first.data];
  const lastPage = first.meta?.last_page ?? 1;
  if (lastPage > 1) {
    const rest = await Promise.all(
      Array.from({ length: lastPage - 1 }, (_, i) =>
        listPawnContractsApi(token, { ...params, page: i + 2, per_page: perPage }),
      ),
    );
    for (const page of rest) {
      all.push(...page.data);
    }
  }
  return {
    data: all,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: all.length,
      total: first.meta?.total ?? all.length,
    },
  };
}

export async function getPawnContractApi(
  token: string,
  id: number,
): Promise<PawnContract> {
  if (env.useMockData) return mockApi.getPawn(id);
  return apiRequest<PawnContract>(`/pawn-contracts/${id}`, { token });
}

export async function createPawnContractApi(
  token: string,
  payload: CreatePawnContractPayload,
  photoUris: string[] = [],
): Promise<PawnContract> {
  if (env.useMockData) {
    return mockApi.createPawn(payload);
  }

  if (photoUris.length === 0) {
    return apiRequest<PawnContract>('/pawn-contracts', {
      method: 'POST',
      token,
      body: payload,
    });
  }

  const form = new FormData();
  form.append('payload', JSON.stringify(payload));
  photoUris.forEach((uri, index) => {
    form.append('photos[]', {
      uri,
      name: `photo-${index}.jpg`,
      type: 'image/jpeg',
    } as unknown as Blob);
  });

  return apiRequest<PawnContract>('/pawn-contracts', {
    method: 'POST',
    token,
    formData: form,
  });
}

export async function redeemPawnContractApi(
  token: string,
  id: number,
): Promise<PawnContract> {
  if (env.useMockData) return mockApi.redeemPawn(id);
  return apiRequest<PawnContract>(`/pawn-contracts/${id}/redeem`, {
    method: 'POST',
    token,
    body: {},
  });
}

export async function payInterestPawnContractApi(
  token: string,
  id: number,
  extendDays = 30,
): Promise<PawnContract> {
  if (env.useMockData) return mockApi.payInterestPawn(id, extendDays);
  return apiRequest<PawnContract>(`/pawn-contracts/${id}/pay-interest`, {
    method: 'POST',
    token,
    body: { extend_days: extendDays },
  });
}

export function pawnPrintUrl(id: number, token: string): string {
  const base = env.apiBaseUrl.replace(/\/$/, '');
  // TODO: backend may require token query or cookie; prefer signed URL
  return `${base}/pawn-contracts/${id}/print?access_token=${encodeURIComponent(token)}`;
}
