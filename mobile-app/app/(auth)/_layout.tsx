import { Redirect, Stack } from 'expo-router';
import { useAuth } from '@/src/auth/AuthContext';

/** Только экран входа — без параллельного (app) в стеке. */
export default function AuthLayout() {
  const { isAuthenticated, isRestoring } = useAuth();

  if (!isRestoring && isAuthenticated) {
    return <Redirect href="/(app)/(tabs)" />;
  }

  return (
    <Stack screenOptions={{ headerShown: false, animation: 'none', gestureEnabled: false }} />
  );
}
