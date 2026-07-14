import { env } from '@/src/config/env';

/** Проверка, что iPhone достучится до Laravel (без полного входа). */
export async function pingApiServer(): Promise<{ ok: boolean; message: string }> {
  const url = `${env.apiOrigin}/api/v1/auth/login`;
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 12_000);
  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        email: 'ping@test.local',
        password: 'ping',
        device_name: 'connectivity-check',
      }),
      signal: controller.signal,
    });
    // 401/422 = сервер ответил, сеть ок
    if (res.status === 401 || res.status === 422 || res.status === 200) {
      return { ok: true, message: 'Сервер доступен' };
    }
    return { ok: false, message: `Сервер ответил: HTTP ${res.status}` };
  } catch (e) {
    if (e instanceof Error && e.name === 'AbortError') {
      return {
        ok: false,
        message: 'Таймаут. Включите Tailscale или домашнюю Wi‑Fi',
      };
    }
      return {
        ok: false,
        message:
          'Нет связи с сервером. LTE: http://37.193.61.182:18082 · дома: Tailscale + 192.168.1.67:8000',
      };
  } finally {
    clearTimeout(timer);
  }
}
