/** Минимальная длина запроса: 3 цифры для телефона, иначе 2 символа. */
export function clientSearchMinLength(q: string): number {
  const trimmed = q.trim();
  const digits = trimmed.replace(/\D/g, '');
  const compact = trimmed.replace(/\s+/g, '');
  if (digits.length >= 3 && digits.length >= compact.length) {
    return 3;
  }
  return 2;
}

/** Нормализует строку поиска (пробелы); цифры телефона API обрабатывает на backend. */
export function normalizeClientSearchQuery(q: string): string {
  return q.trim();
}
