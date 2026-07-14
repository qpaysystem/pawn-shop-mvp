import type { Router } from 'expo-router';

/** Безопасный «Назад»: иначе на главную (избегаем GO_BACK was not handled). */
export function goBackOrHome(router: Router): void {
  if (router.canGoBack()) {
    router.back();
    return;
  }
  router.replace('/(app)/(tabs)');
}
