import { Tabs } from 'expo-router';
import { colors } from '@/src/components/Screen';

export default function TabLayout() {
  return (
    <Tabs
      screenOptions={{
        headerShown: true,
        headerStyle: { backgroundColor: colors.primary },
        headerTintColor: '#fff',
        tabBarActiveTintColor: colors.primary,
        sceneStyle: { flex: 1, backgroundColor: colors.bg },
      }}
    >
      <Tabs.Screen name="index" options={{ title: 'Главная', tabBarLabel: 'Главная' }} />
      <Tabs.Screen name="pledges" options={{ title: 'Залоги', tabBarLabel: 'Залоги' }} />
      <Tabs.Screen name="clients" options={{ title: 'Клиенты', tabBarLabel: 'Клиенты' }} />
      <Tabs.Screen name="reports" options={{ title: 'Отчёты', tabBarLabel: 'Отчёты' }} />
      <Tabs.Screen name="new-pledge" options={{ title: 'Приём', tabBarLabel: 'Приём' }} />
      <Tabs.Screen name="settings" options={{ title: 'Настройки', tabBarLabel: 'Профиль' }} />
    </Tabs>
  );
}
