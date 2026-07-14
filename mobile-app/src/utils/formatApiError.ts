import { ApiError } from '@/src/types/api';

function validationMessage(err: ApiError): string {
  const errors = err.body?.errors;
  if (!errors) return err.message;
  const first = Object.values(errors).flat()[0];
  return first ?? err.message;
}

/** User-facing Russian message for login and auth flows. */
export function formatApiErrorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    switch (error.status) {
      case 401:
        return 'Неверный email или пароль';
      case 422:
        return validationMessage(error);
      case 500:
      case 502:
      case 503:
        return 'Ошибка сервера. Попробуйте позже';
      default:
        return error.message || `Ошибка (${error.status})`;
    }
  }

  if (error instanceof Error) {
    if (error.name === 'AbortError' || error.message === 'timeout') {
      return 'Превышено время ожидания ответа сервера';
    }
    if (error.message === 'network_unavailable') {
      return 'Нет связи с сервером. Проверьте сеть и адрес API';
    }
    return error.message;
  }

  return 'Неизвестная ошибка';
}

export function isSessionExpiredError(error: unknown): boolean {
  return error instanceof ApiError && error.status === 401;
}
