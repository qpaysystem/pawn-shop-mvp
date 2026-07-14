import { useLocalSearchParams, useRouter } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { Alert, Linking, Text, View } from 'react-native';
import { useAuth } from '@/src/auth/AuthContext';
import { getPawnContractApi, pawnPrintUrl } from '@/src/api/pawnContracts';
import { Card, PrimaryButton, Screen, Subtitle, Title, colors } from '@/src/components/Screen';
import type { PawnContract } from '@/src/types/pawn';
import { formatMoney } from '@/src/utils/loan';
import { goBackOrHome } from '@/src/utils/navigation';

export default function PledgeDetailsScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { token } = useAuth();
  const router = useRouter();
  const [pawn, setPawn] = useState<PawnContract | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    if (!token || !id) return;
    setLoading(true);
    try {
      const data = await getPawnContractApi(token, Number(id));
      setPawn(data);
    } catch (e) {
      Alert.alert('Ошибка', e instanceof Error ? e.message : 'Не удалось загрузить');
      goBackOrHome(router);
    } finally {
      setLoading(false);
    }
  }, [token, id, router]);

  useEffect(() => {
    load();
  }, [load]);

  const openPrint = () => {
    if (!token || !pawn) return;
    const url = pawnPrintUrl(pawn.id, token);
    Linking.openURL(url).catch(() => {
      Alert.alert('Печать', 'TODO(backend): PDF или signed URL для печати договора');
    });
  };

  if (!pawn && !loading) return null;

  return (
    <Screen loading={loading}>
      {pawn ? (
        <>
          <Title>{pawn.contract_number}</Title>
          <Subtitle>
            {pawn.computed_status === 'overdue'
              ? 'Просрочен'
              : pawn.is_redeemed
                ? 'Выкуплен'
                : 'Активен'}
          </Subtitle>
          <Card>
            <Detail label="Клиент" value={pawn.client?.full_name ?? `#${pawn.client_id}`} />
            <Detail label="Телефон" value={pawn.client?.phone ?? '—'} />
            <Detail label="Предмет" value={pawn.item?.name ?? '—'} />
            <Detail label="Займ" value={formatMoney(pawn.loan_amount)} />
            <Detail label="Процент" value={`${pawn.loan_percent}%`} />
            <Detail
              label="Выкуп"
              value={formatMoney(pawn.buyback_amount ?? pawn.redemption_amount ?? 0)}
            />
            <Detail label="Выдан" value={pawn.loan_date} />
            <Detail label="Срок до" value={pawn.expiry_date} />
          </Card>
          {pawn.item?.photos?.length ? (
            <Card>
              <Text style={{ fontWeight: '600', marginBottom: 8 }}>Фото</Text>
              {pawn.item.photos.map((ph, i) => (
                <Text key={i} style={{ color: colors.primary }}>
                  {ph.url}
                </Text>
              ))}
            </Card>
          ) : null}
          <View style={{ gap: 12, marginTop: 8 }}>
            <PrimaryButton label="Печать договора" onPress={openPrint} />
            <PrimaryButton
              label="К списку залогов"
              onPress={() => router.push('/(app)/(tabs)/pledges')}
            />
          </View>
        </>
      ) : null}
    </Screen>
  );
}

function Detail({ label, value }: { label: string; value: string }) {
  return (
    <View style={{ marginBottom: 10 }}>
      <Text style={{ fontSize: 13, color: colors.muted }}>{label}</Text>
      <Text style={{ fontSize: 16, marginTop: 2 }}>{value}</Text>
    </View>
  );
}
