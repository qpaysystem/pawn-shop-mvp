import { useEffect, useState } from 'react';
import { Linking, Pressable, StyleSheet, Text, View } from 'react-native';
import { env } from '@/src/config/env';

/**
 * Диагностика: Metro (тик) и доступность API с телефона.
 */
export function DevStatusBar() {
  const [tick, setTick] = useState(0);
  const [apiStatus, setApiStatus] = useState<'…' | 'ok' | 'fail'>('…');
  const [apiDetail, setApiDetail] = useState('');

  useEffect(() => {
    const id = setInterval(() => setTick((n) => n + 1), 1000);
    return () => clearInterval(id);
  }, []);

  useEffect(() => {
    if (env.useMockAuth) {
      setApiStatus('ok');
      setApiDetail('mock');
      return;
    }
    let cancelled = false;
    (async () => {
      try {
        const controller = new AbortController();
        const t = setTimeout(() => controller.abort(), 8000);
        const res = await fetch(`${env.apiOrigin}/api/v1/auth/login`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify({
            email: 'appraiser@example.com',
            password: 'password',
            device_name: 'probe',
          }),
          signal: controller.signal,
        });
        clearTimeout(t);
        if (cancelled) return;
        if (res.status === 200 || res.status === 401 || res.status === 422) {
          setApiStatus('ok');
          setApiDetail(`HTTP ${res.status}`);
        } else {
          setApiStatus('fail');
          setApiDetail(`HTTP ${res.status}`);
        }
      } catch (e) {
        if (cancelled) return;
        setApiStatus('fail');
        const msg = e instanceof Error ? e.message : 'error';
        setApiDetail(msg === 'AbortError' || msg === 'timeout' ? 'таймаут' : msg);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const jsAlive = tick > 0;
  const apiOk = apiStatus === 'ok';

  return (
    <View style={[styles.box, (!jsAlive || !apiOk) && styles.boxBad]}>
      <Text style={[styles.line, jsAlive ? styles.ok : styles.bad]}>
        Metro / JS: {jsAlive ? `работает (${tick} сек)` : 'нет связи с Mac'}
      </Text>
      <Text style={[styles.line, apiOk ? styles.ok : styles.bad]}>
        API {env.apiOrigin}: {apiOk ? `доступен (${apiDetail})` : `недоступен (${apiDetail})`}
      </Text>
      {!apiOk ? (
        <>
          <Text style={styles.hint}>
            1. Tailscale на iPhone — Connected{'\n'}
            2. Настройки iPhone → Expo Go → Локальная сеть — ВКЛ{'\n'}
            3. Safari: {env.apiOrigin} — должна открыться главная{'\n'}
            4. Subnet 192.168.1.0/24 одобрен для 3apa3aserver в админке Tailscale
          </Text>
          <Pressable onPress={() => Linking.openURL(env.apiOrigin)}>
            <Text style={styles.link}>Открыть {env.apiOrigin} в Safari</Text>
          </Pressable>
        </>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  box: {
    backgroundColor: '#e8f8e8',
    borderWidth: 1,
    borderColor: '#2d8a4e',
    borderRadius: 8,
    padding: 10,
    marginBottom: 14,
  },
  boxBad: {
    backgroundColor: '#fde8e8',
    borderColor: '#c53030',
  },
  line: { fontSize: 13, lineHeight: 18, color: '#1a1a1a' },
  ok: { fontWeight: '700', color: '#2d8a4e' },
  bad: { fontWeight: '700', color: '#c53030' },
  hint: { fontSize: 12, color: '#444', marginTop: 8, lineHeight: 17 },
  link: { fontSize: 13, color: '#224d66', marginTop: 8, fontWeight: '600' },
});
