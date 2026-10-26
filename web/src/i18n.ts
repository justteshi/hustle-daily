import en from './locales/en.json';
import bg from './locales/bg.json';

export type TextKey = keyof typeof en;
const locale = navigator.language.startsWith('bg') ? bg : en;
export const t = (key: TextKey) => locale[key];

const dateFormatter = new Intl.DateTimeFormat(navigator.language.startsWith('bg') ? 'bg-BG' : 'en-GB', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
});

export function formatLocalDate(value: string): string {
    return dateFormatter.format(new Date(`${value}T00:00:00Z`));
}
