/**
 * RNGH должен импортироваться первым — иначе в Expo Go касания могут не работать вообще.
 * @see https://docs.swmansion.com/react-native-gesture-handler/docs/fundamentals/installation
 */
import 'react-native-gesture-handler';
import 'react-native-reanimated';

const debugTouch = process.env.EXPO_PUBLIC_DEBUG_TOUCH === 'true';

if (debugTouch) {
  const { registerRootComponent } = require('expo');
  const TouchDebugApp = require('./TouchDebugApp').default;
  registerRootComponent(TouchDebugApp);
} else {
  require('expo-router/entry');
}
