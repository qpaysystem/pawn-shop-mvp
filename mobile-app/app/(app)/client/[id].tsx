import { useLocalSearchParams, useRouter } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Text, View } from 'react-native';
import { getClientApi } from '@/src/api/clients';
import { useAuth } from '@/src/auth/AuthContext';
import { Card, PrimaryButton, Screen, Subtitle, colors } from '@/src/components/Screen';
import type { ClientDetail } from '@/src/types/client';

export default function ClientDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const clientId = Number(id);
  const { token } = useAuth();
  const router = useRouter();
  const [client, setClient] = useState<ClientDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!token || !clientId) return;
    setLoading(true);
    setError(null);
    try {
      setClient(await getClientApi(token, clientId));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Не удалось загрузить клиента');
    } finally {
      setLoading(false);
    }
  }, [token, clientId]);

  useEffect(() => {
    load();
  }, [load]);

  if (loading) {
    return (
      <Screen>
        <ActivityIndicator size="large" color={colors.primary} />
      </Screen>
    );
  }

  if (error || !client) {
    return (
      <Screen>
        <Subtitle>{error ?? 'Клиент не найден'}</Subtitle>
        <PrimaryButton label="Назад" onPress={() => router.back()} />
      </Screen>
    );
  }

  return (
    <Screen>
      <Text style={{ fontSize: 22, fontWeight: '700', color: colors.primary, marginBottom: 8 }}>
        {client.full_name}
      </Text>
      {client.blacklist_flag ? (
        <Text style={{ color: colors.danger, marginBottom: 12, fontWeight: '600' }}>
          В чёрном списке
        </Text>
      ) : null}

      <Card>
        <Detail label="Телефон" value={client.phone} />
        <Detail label="Email" value={client.email ?? '—'} />
        <Detail label="Тип" value={client.client_type === 'legal' ? 'Юр. лицо' : 'Физ. лицо'} />
        {client.inn ? <Detail label="ИНН" value={client.inn} /> : null}
        <Detail label="Паспорт" value={client.passport_data ?? '—'} />
        {client.active_pawn_contracts_count != null ? (
          <Detail
            label="Активных залогов"
            value={String(client.active_pawn_contracts_count)}
          />
        ) : null}
        {client.notes ? <Detail label="Заметки" value={client.notes} /> : null}
      </Card>
    </Screen>
  );
}

function Detail({ label, value }: { label: string; value: string }) {
  return (
    <View style={{ marginBottom: 10 }}>
      <Text style={{ fontSize: 12, color: colors.muted, marginBottom: 2 }}>{label}</Text>
      <Text style={{ fontSize: 16 }}>{value}</Text>
    </View>
  );
}
