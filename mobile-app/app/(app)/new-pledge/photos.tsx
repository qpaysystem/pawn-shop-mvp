import * as ImagePicker from 'expo-image-picker';
import { useRouter } from 'expo-router';
import { Alert, Image, Pressable, Text, View } from 'react-native';
import {
  Card,
  FieldLabel,
  PrimaryButton,
  Screen,
  Subtitle,
  colors,
} from '@/src/components/Screen';
import { usePledgeWizardStore } from '@/src/store/pledgeWizardStore';

const MAX_PHOTOS = 5;

export default function PhotosScreen() {
  const router = useRouter();
  const photos = usePledgeWizardStore((s) => s.photos);
  const addPhoto = usePledgeWizardStore((s) => s.addPhoto);
  const removePhoto = usePledgeWizardStore((s) => s.removePhoto);

  const pick = async (useCamera: boolean) => {
    if (photos.length >= MAX_PHOTOS) {
      Alert.alert('Фото', `Максимум ${MAX_PHOTOS} фотографий`);
      return;
    }
    const perm = useCamera
      ? await ImagePicker.requestCameraPermissionsAsync()
      : await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      Alert.alert('Доступ', 'Разрешите доступ к камере или галерее');
      return;
    }
    const result = useCamera
      ? await ImagePicker.launchCameraAsync({ quality: 0.8 })
      : await ImagePicker.launchImageLibraryAsync({
          allowsMultipleSelection: true,
          selectionLimit: MAX_PHOTOS - photos.length,
          quality: 0.8,
        });
    if (result.canceled || !result.assets?.length) return;
    result.assets.forEach((asset) => {
      addPhoto({
        id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
        uri: asset.uri,
      });
    });
  };

  return (
    <Screen>
      <Subtitle>{`Шаг 4 из 6 — фото предмета (до ${MAX_PHOTOS})`}</Subtitle>
      <View style={{ flexDirection: 'row', gap: 8, marginBottom: 16 }}>
        <View style={{ flex: 1 }}>
          <PrimaryButton label="Камера" onPress={() => pick(true)} />
        </View>
        <View style={{ flex: 1 }}>
          <PrimaryButton label="Галерея" onPress={() => pick(false)} />
        </View>
      </View>
      {photos.length === 0 ? (
        <Card>
          <Text style={{ color: colors.muted }}>
            TODO(backend): POST multipart на /api/v1/pawn-contracts с photos[]
          </Text>
        </Card>
      ) : (
        photos.map((p) => (
          <Card key={p.id} style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
            <Image source={{ uri: p.uri }} style={{ width: 72, height: 72, borderRadius: 8 }} />
            <Pressable onPress={() => removePhoto(p.id)} style={{ marginLeft: 'auto' }}>
              <Text style={{ color: colors.danger }}>Удалить</Text>
            </Pressable>
          </Card>
        ))
      )}
      <Text style={{ fontSize: 13, color: colors.muted, marginTop: 8 }}>
        Загружено: {photos.length} / {MAX_PHOTOS}
      </Text>
      <PrimaryButton
        label="Далее: условия займа"
        onPress={() => router.push('/(app)/new-pledge/loan')}
      />
    </Screen>
  );
}
