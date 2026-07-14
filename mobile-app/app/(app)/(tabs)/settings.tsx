import { useRouter } from 'expo-router';
import { Alert, Switch, Text, View } from 'react-native';
import { useAuth } from '@/src/auth/AuthContext';
import { Card, PrimaryButton, Screen, Subtitle, Title, colors } from '@/src/components/Screen';
import { env } from '@/src/config/env';

export default function SettingsScreen() {
  const { user, logout } = useAuth();
  const router = useRouter();

  const onLogout = () => {
    Alert.alert('Выход', 'Выйти из приложения?', [
      { text: 'Отмена', style: 'cancel' },
      {
        text: 'Выйти',
        style: 'destructive',
        onPress: async () => {
          await logout();
          router.replace('/(auth)/login');
        },
      },
    ]);
  };

  return (
    <Screen>
      <Title>Профиль</Title>
      <Subtitle>{user?.email ?? ''}</Subtitle>
      <Card>
        <Row label="Имя" value={user?.name ?? '—'} />
        <Row label="Роль" value={user?.role ?? '—'} />
        <Row label="Точка" value={user?.store_name ?? '—'} />
      </Card>
      <Card>
        <Row label="API" value={env.apiOrigin} />
        <Row label="API v1" value={env.apiBaseUrl} />
        <View
          style={{
            flexDirection: 'row',
            justifyContent: 'space-between',
            alignItems: 'center',
            marginTop: 8,
          }}
        >
          <Text style={{ color: colors.muted }}>Mock auth</Text>
          <Switch value={env.useMockAuth} disabled />
        </View>
        <View
          style={{
            flexDirection: 'row',
            justifyContent: 'space-between',
            alignItems: 'center',
            marginTop: 8,
          }}
        >
          <Text style={{ color: colors.muted }}>Mock data (pledges…)</Text>
          <Switch value={env.useMockData} disabled />
        </View>
        <Text style={{ fontSize: 12, color: colors.muted, marginTop: 8 }}>
          Auth: EXPO_PUBLIC_USE_MOCK_API. Данные пока всегда mock.
        </Text>
      </Card>
      <View style={{ marginTop: 24 }}>
        <PrimaryButton label="Выйти" onPress={onLogout} />
      </View>
    </Screen>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <View style={{ marginBottom: 12 }}>
      <Text style={{ fontSize: 13, color: colors.muted }}>{label}</Text>
      <Text style={{ fontSize: 16, marginTop: 2 }}>{value}</Text>
    </View>
  );
}
