import { env } from '@/src/config/env';
import { ApiError, type ApiErrorBody } from '@/src/types/api';

type HttpMethod = 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE';

export interface RequestOptions {
  method?: HttpMethod;
  token?: string | null;
  body?: unknown;
  formData?: FormData;
  signal?: AbortSignal;
  timeoutMs?: number;
}

async function parseJsonSafe(res: Response): Promise<unknown> {
  const text = await res.text();
  if (!text) return null;
  try {
    return JSON.parse(text) as unknown;
  } catch {
    return text;
  }
}

function mergeSignals(signals: AbortSignal[]): AbortSignal {
  if (signals.length === 1) return signals[0];
  const controller = new AbortController();
  for (const signal of signals) {
    if (signal.aborted) {
      controller.abort();
      return controller.signal;
    }
    signal.addEventListener('abort', () => controller.abort(), { once: true });
  }
  return controller.signal;
}

export async function apiRequest<T>(
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  const { method = 'GET', token, body, formData, signal, timeoutMs } = options;
  const url = path.startsWith('http') ? path : `${env.apiBaseUrl}${path}`;

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Accept-Language': 'ru',
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  if (body !== undefined && !formData) {
    headers['Content-Type'] = 'application/json';
  }

  const timeoutController = new AbortController();
  const timeout = setTimeout(() => timeoutController.abort(), timeoutMs ?? env.apiTimeoutMs);
  const mergedSignal = signal
    ? mergeSignals([signal, timeoutController.signal])
    : timeoutController.signal;

  let res: Response;
  try {
    res = await fetch(url, {
      method,
      headers,
      body: formData ?? (body !== undefined ? JSON.stringify(body) : undefined),
      signal: mergedSignal,
    });
  } catch (e) {
    if (e instanceof Error && e.name === 'AbortError') {
      if (timeoutController.signal.aborted && !signal?.aborted) {
        throw new Error('timeout');
      }
      throw e;
    }
    throw new Error('network_unavailable');
  } finally {
    clearTimeout(timeout);
  }

  const data = await parseJsonSafe(res);

  if (!res.ok) {
    const errBody = (typeof data === 'object' && data !== null
      ? data
      : { message: String(data) }) as ApiErrorBody;
    const errRecord = errBody as Record<string, unknown>;
    const message =
      (typeof errRecord.error === 'string' ? errRecord.error : undefined) ??
      errBody.message ??
      (res.status === 401
        ? 'Unauthorized'
        : res.status === 422
          ? 'Validation error'
          : res.status >= 500
            ? 'Server error'
            : `HTTP ${res.status}`);
    throw new ApiError(message, res.status, errBody);
  }

  return data as T;
}
