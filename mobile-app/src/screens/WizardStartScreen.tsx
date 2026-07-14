import { useRouter } from 'expo-router';
import { Alert, Pressable, Text, View } from 'react-native';
import { useAuth } from '@/src/auth/AuthContext';
import { Card, FieldLabel, PrimaryButton, Screen, Subtitle, colors } from '@/src/components/Screen';
import { usePledgeWizardStore } from '@/src/store/pledgeWizardStore';
import type { VisitPurpose } from '@/src/types/pawn';

const purposes: { key: VisitPurpose; label: string }[] = [
  { key: 'appraisal', label: 'Оценка и залог' },
  { key: 'redemption', label: 'Выкуп' },
  { key: 'identification', label: 'Идентификация' },
  { key: 'non_target', label: 'Нецелевой визит' },
];

export default function WizardStartScreen() {
  const router = useRouter();
  const { catalogs, user, refreshCatalogs, isRestoring } = useAuth();
  const visitPurpose = usePledgeWizardStore((s) => s.visitPurpose);
  const storeId = usePledgeWizardStore((s) => s.storeId);
  const setVisitPurpose = usePledgeWizardStore((s) => s.setVisitPurpose);
  const setStoreId = usePledgeWizardStore((s) => s.setStoreId);

  const stores = catalogs?.stores ?? [];
  const effectiveStoreId = storeId ?? user?.store_id ?? stores[0]?.id ?? null;

  const onNext = () => {
    if (!effectiveStoreId) {
      Alert.alert(
        'Точка не выбрана',
        'Нет списка точек. Нажмите «Обновить справочники» или выйдите и войдите снова.',
      );
      return;
    }
    setStoreId(effectiveStoreId);
    router.push('/(app)/new-pledge/customer');
  };

  return (
    <Screen loading={isRestoring && catalogs === null}>
      <Subtitle>Шаг 1 из 6 — цель визита и точка</Subtitle>
      <FieldLabel>Цель визита</FieldLabel>
      {purposes.map((p) => (
        <Pressable key={p.key} onPress={() => setVisitPurpose(p.key)} style={{ marginBottom: 8 }}>
          <Card
            style={{
              borderColor: visitPurpose === p.key ? colors.primary : undefined,
              borderWidth: visitPurpose === p.key ? 2 : 1,
            }}
          >
            <Text>{p.label}</Text>
          </Card>
        </Pressable>
      ))}
      <FieldLabel>Точка</FieldLabel>
      {stores.length === 0 ? (
        <View style={{ marginBottom: 12 }}>
          <Text style={{ color: colors.muted, marginBottom: 8 }}>
            {catalogs === null
              ? 'Загрузка справочников…'
              : 'Справочник точек пуст. Нажмите «Обновить справочники».'}
          </Text>
          <PrimaryButton label="Обновить справочники" onPress={() => refreshCatalogs()} />
        </View>
      ) : (
        stores.map((s) => (
          <Pressable key={s.id} onPress={() => setStoreId(s.id)} style={{ marginBottom: 8 }}>
            <Card
              style={{
                borderColor: effectiveStoreId === s.id ? colors.primary : undefined,
                borderWidth: effectiveStoreId === s.id ? 2 : 1,
              }}
            >
              <Text style={{ fontWeight: '600' }}>{s.name}</Text>
              {s.address ? <Text style={{ color: colors.muted }}>{s.address}</Text> : null}
            </Card>
          </Pressable>
        ))
      )}
      <View style={{ marginTop: 16 }}>
        <PrimaryButton label="Далее: клиент" onPress={onNext} />
      </View>
    </Screen>
  );
}
