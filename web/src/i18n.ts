import en from './locales/en.json';
import bg from './locales/bg.json';

export type TextKey = keyof typeof en;
const locale = navigator.language.startsWith('bg') ? bg : en;
export const t = (key: TextKey) => locale[key];
