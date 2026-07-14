import type { PassportParseResult } from '@/src/types/client';

export interface PassportParseApiFields {
  last_name?: string;
  first_name?: string;
  patronymic?: string;
  birth_date?: string;
  passport_series_number?: string;
  issued_by?: string;
  issued_at?: string;
}

export interface PassportParseApiResponse {
  success: boolean;
  error?: string;
  passport_data?: string;
  fields?: PassportParseApiFields;
  parsed_by?: string;
}

export function mapPassportParseResponse(data: PassportParseApiResponse): PassportParseResult {
  const f = data.fields ?? {};
  const passport_data =
    (data.passport_data && data.passport_data.trim()) ||
    buildPassportDataText(f);

  const seriesNumber = (f.passport_series_number ?? '').trim();
  const seriesParts = seriesNumber.split(/\s+/).filter(Boolean);

  return {
    last_name: f.last_name?.trim() || undefined,
    first_name: f.first_name?.trim() || undefined,
    patronymic: f.patronymic?.trim() || undefined,
    birth_date: f.birth_date?.trim() || undefined,
    issued_by: f.issued_by?.trim() || undefined,
    issued_at: f.issued_at?.trim() || undefined,
    passport_series: seriesParts.length >= 2 ? seriesParts.slice(0, 2).join(' ') : seriesParts[0],
    passport_number:
      seriesParts.length >= 3 ? seriesParts.slice(2).join('') : seriesParts[1] ?? undefined,
    passport_data,
  };
}

function buildPassportDataText(f: PassportParseApiFields): string {
  return [
    f.passport_series_number,
    f.issued_by,
    f.issued_at,
    f.birth_date ? `рожд. ${f.birth_date}` : '',
  ]
    .filter((p) => p && String(p).trim())
    .join(', ');
}

export function parsedByLabel(parsedBy?: string): string {
  if (!parsedBy) return '';
  if (parsedBy === 'gemini' || parsedBy.startsWith('gemini')) return 'Gemini';
  if (parsedBy === 'openai') return 'OpenAI';
  if (parsedBy === 'deepseek') return 'Deep Seek';
  if (parsedBy.includes('tesseract')) return 'Tesseract';
  if (parsedBy === 'regex') return 'шаблон';
  return parsedBy;
}
