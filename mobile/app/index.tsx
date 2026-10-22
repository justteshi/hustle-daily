import Constants from 'expo-constants';
import * as Localization from 'expo-localization';
import { StyleSheet, Text, View } from 'react-native';
import bg from '../locales/bg.json';
import en from '../locales/en.json';

const language = Localization.getLocales()[0]?.languageCode === 'bg' ? bg : en;
const apiBaseUrl = process.env.EXPO_PUBLIC_API_BASE_URL ?? Constants.expoConfig?.extra?.apiBaseUrl;

export default function Home() {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>{language.title}</Text>
      <Text style={styles.body}>{language.placeholder}</Text>
      <Text style={styles.caption}>{language.api}: {apiBaseUrl}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24, backgroundColor: '#faf8f3' },
  title: { fontSize: 30, fontWeight: '700', color: '#172322' },
  body: { marginTop: 12, fontSize: 16, color: '#43514e', textAlign: 'center' },
  caption: { marginTop: 28, fontSize: 12, color: '#6b7673', textAlign: 'center' },
});
