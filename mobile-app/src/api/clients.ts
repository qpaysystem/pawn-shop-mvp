import { env } from '@/src/config/env';
import { mockApi } from '@/src/mocks/handlers';
import type {
  Client,
  ClientDetail,
  ClientListParams,
  ClientSearchResult,
  CreateClientPayload,
  PassportParseResult,
} from '@/src/types/client';
import type { PaginatedResponse } from '@/src/types/pawn';
import { apiRequest } from './http';
import { normalizeClientSearchQuery } from '@/src/utils/clientSearch';
import {
  mapPassportParseResponse,
  type PassportParseApiResponse,
} from '@/src/utils/passportParse';

/**
 * GET /api/v1/clients — paginated list (all clients in portal DB).
 */
export async function listClientsApi(
  token: string,
  params: ClientListParams = {},
): Promise<PaginatedResponse<Client>> {
  if (env.useMockData) {
    return mockApi.listClients(params);
  }
  const qs = new URLSearchParams();
  if (params.q) qs.set('q', params.q);
  if (params.blacklist) qs.set('blacklist', '1');
  if (params.page) qs.set('page', String(params.page));
  const query = qs.toString();
  return apiRequest<PaginatedResponse<Client>>(
    `/clients${query ? `?${query}` : ''}`,
    { token },
  );
}

export async function getClientApi(token: string, id: number): Promise<ClientDetail> {
  if (env.useMockData) {
    return mockApi.getClient(id);
  }
  return apiRequest<ClientDetail>(`/clients/${id}`, { token });
}

/**
 * GET /api/v1/clients/search — quick search (wizard, max 20).
 */
export async function searchClientsApi(
  token: string,
  q: string,
): Promise<ClientSearchResult[]> {
  const query = normalizeClientSearchQuery(q);
  if (env.useMockData) {
    return mockApi.searchClients(query);
  }
  const res = await apiRequest<{ data: ClientSearchResult[] } | ClientSearchResult[]>(
    `/clients/search?q=${encodeURIComponent(query)}`,
    { token },
  );
  return Array.isArray(res) ? res : res.data;
}

export async function createClientApi(
  token: string,
  payload: CreateClientPayload,
): Promise<Client> {
  if (env.useMockData) {
    return mockApi.createClient(payload);
  }
  return apiRequest<Client>('/clients', { method: 'POST', token, body: payload });
}

/** POST /api/v1/tools/parse-passport — распознавание паспорта по фото (как на портале). */
export async function parsePassportApi(
  token: string,
  uri: string,
): Promise<PassportParseResult> {
  if (env.useMockData) {
    await new Promise((r) => setTimeout(r, 600));
    return {
      last_name: 'ИВАНОВ',
      first_name: 'ИВАН',
      patronymic: 'ИВАНОВИЧ',
      passport_series: '50 19',
      passport_number: '961613',
      passport_data: '50 19 961613',
      birth_date: '01.01.1990',
      issued_by: 'УФМС',
      issued_at: '01.01.2015',
    };
  }

  const form = new FormData();
  form.append('photo', {
    uri,
    name: 'passport.jpg',
    type: 'image/jpeg',
  } as unknown as Blob);

  const data = await apiRequest<PassportParseApiResponse>('/tools/parse-passport', {
    method: 'POST',
    token,
    formData: form,
    timeoutMs: 90_000,
  });

  if (!data.success) {
    throw new Error(data.error ?? 'Не удалось распознать паспорт');
  }

  return mapPassportParseResponse(data);
}
