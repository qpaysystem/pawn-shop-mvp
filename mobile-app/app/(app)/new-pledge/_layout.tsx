import { Stack, useRouter } from 'expo-router';
import { Pressable, Text } from 'react-native';
import { colors } from '@/src/components/Screen';
import { goBackOrHome } from '@/src/utils/navigation';

function HeaderBack() {
  const router = useRouter();
  return (
    <Pressable
      onPress={() => goBackOrHome(router)}
      style={{ paddingHorizontal: 8, paddingVertical: 4 }}
      hitSlop={8}
    >
      <Text style={{ color: '#fff', fontSize: 16 }}>Назад</Text>
    </Pressable>
  );
}

export default function NewPledgeLayout() {
  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: colors.primary },
        headerTintColor: '#fff',
        headerTitleStyle: { fontWeight: '600' },
        headerBackVisible: false,
        gestureEnabled: false,
        headerLeft: () => <HeaderBack />,
      }}
    >
      <Stack.Screen name="index" options={{ title: 'Новый залог' }} />
      <Stack.Screen name="customer" options={{ title: 'Клиент' }} />
      <Stack.Screen name="item" options={{ title: 'Предмет' }} />
      <Stack.Screen name="photos" options={{ title: 'Фото' }} />
      <Stack.Screen name="loan" options={{ title: 'Условия займа' }} />
      <Stack.Screen name="review" options={{ title: 'Проверка' }} />
    </Stack>
  );
}
