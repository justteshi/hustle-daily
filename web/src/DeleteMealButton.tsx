import { useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { request } from './api';
import { t } from './i18n';

export default function DeleteMealButton({
    id,
    date,
    onDeleted,
}: {
    id: string;
    date: string;
    onDeleted?: () => void;
}) {
    const client = useQueryClient();
    const key = useRef(crypto.randomUUID());
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [confirming, setConfirming] = useState(false);

    async function remove() {
        setBusy(true);
        setError('');
        try {
            await request<void>(
                `/consumptions/${id}`,
                { method: 'DELETE', headers: { 'Idempotency-Key': key.current } },
                true,
            );
            await Promise.all([
                client.invalidateQueries({ queryKey: ['today'] }),
                client.invalidateQueries({ queryKey: ['food-dates'] }),
                client.invalidateQueries({ queryKey: ['consumptions', date] }),
            ]);
            setConfirming(false);
            onDeleted?.();
        } catch (failure) {
            setError((failure as Error).message);
        } finally {
            setBusy(false);
        }
    }

    return (
        <span className="inline-flex flex-col items-start gap-1">
            <button
                type="button"
                disabled={busy}
                className="text-red-700 underline disabled:opacity-50"
                onClick={() => setConfirming(true)}
            >
                {t('deleteMeal')}
            </button>
            {confirming && (
                <span className="confirm-overlay" onClick={() => !busy && setConfirming(false)}>
                    <span
                        className="confirm-dialog"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby={`delete-meal-${id}`}
                        onClick={(event) => event.stopPropagation()}
                        onKeyDown={(event) => {
                            if (event.key === 'Escape' && !busy) setConfirming(false);
                        }}
                    >
                        <strong id={`delete-meal-${id}`}>{t('deleteMeal')}?</strong>
                        <span>{t('confirmDeleteMeal')}</span>
                        <span className="confirm-actions">
                            <button
                                type="button"
                                autoFocus
                                disabled={busy}
                                onClick={() => setConfirming(false)}
                            >
                                {t('keepMeal')}
                            </button>
                            <button
                                type="button"
                                disabled={busy}
                                className="confirm-danger"
                                onClick={() => {
                                    void remove();
                                }}
                            >
                                {busy ? t('loading') : t('deleteMeal')}
                            </button>
                        </span>
                    </span>
                </span>
            )}
            {error && (
                <span role="alert" className="text-sm text-red-700">
                    {error}
                </span>
            )}
        </span>
    );
}
