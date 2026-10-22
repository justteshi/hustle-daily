import * as Localization from 'expo-localization';
import bg from '../../locales/bg.json';
import en from '../../locales/en.json';

export const strings = Localization.getLocales()[0]?.languageCode === 'bg' ? bg : en;
