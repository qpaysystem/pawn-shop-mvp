import { Redirect, Stack } from 'expo-router';
import { useAuth } from '@/src/auth/AuthContext';

export default function AppLayout() {
  const { isAuthenticated } = useAuth();

  if (!isAuthenticated) {
    return <Redirect href="/(auth)/login" />;
  }

  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="(tabs)" />
      <Stack.Screen name="pledge/[id]" options={{ headerShown: true, title: 'Залог' }} />
      <Stack.Screen name="client/[id]" options={{ headerShown: true, title: 'Клиент' }} />
      <Stack.Screen name="new-pledge" options={{ headerShown: false }} />
    </Stack>
  );
}
