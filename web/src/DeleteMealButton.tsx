import { useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { request } from './api';
import { t } from './i18n';

export default function DeleteMealButton({ id, date, onDeleted }: { id: string; date: string; onDeleted?: () => void }) {
  const client = useQueryClient();
  const key = useRef(crypto.randomUUID());
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function remove() {
    if (!window.confirm(t('confirmDeleteMeal'))) return;
    setBusy(true); setError('');
    try {
      await request<void>(`/consumptions/${id}`, { method: 'DELETE', headers: { 'Idempotency-Key': key.current } }, true);
      await Promise.all([
        client.invalidateQueries({ queryKey: ['today'] }),
        client.invalidateQueries({ queryKey: ['food-dates'] }),
        client.invalidateQueries({ queryKey: ['consumptions', date] }),
      ]);
      onDeleted?.();
    } catch (failure) { setError((failure as Error).message); } finally { setBusy(false); }
  }

  return <span className="inline-flex flex-col items-start gap-1"><button type="button" disabled={busy} className="text-red-700 underline disabled:opacity-50" onClick={remove}>{t('deleteMeal')}</button>{error && <span role="alert" className="text-sm text-red-700">{error}</span>}</span>;
}
