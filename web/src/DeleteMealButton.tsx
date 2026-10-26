import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useQueryClient } from '@tanstack/react-query';
import { request } from './api';
import { t } from './i18n';
import MealActionIcon from './MealActionIcon';

export default function DeleteMealButton({
    id,
    date,
    onDeleted,
    iconOnly = false,
}: {
    id: string;
    date: string;
    onDeleted?: () => void;
    iconOnly?: boolean;
}) {
    const client = useQueryClient();
    const key = useRef(crypto.randomUUID());
    const trigger = useRef<HTMLButtonElement>(null);
    const dialog = useRef<HTMLSpanElement>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [confirming, setConfirming] = useState(false);

    useEffect(() => {
        if (!confirming) return;
        const page = document.getElementById('root');
        if (page) page.inert = true;
        dialog.current?.querySelector<HTMLButtonElement>('button')?.focus();
        return () => {
            if (page) page.inert = false;
            trigger.current?.focus();
        };
    }, [confirming]);

    function trapFocus(event: React.KeyboardEvent) {
        if (event.key === 'Escape' && !busy) {
            setConfirming(false);
            return;
        }
        if (event.key !== 'Tab') return;
        const buttons = Array.from(
            dialog.current?.querySelectorAll<HTMLButtonElement>('button:not(:disabled)') ?? [],
        );
        if (buttons.length === 0) return;
        const current = buttons.indexOf(document.activeElement as HTMLButtonElement);
        const next = event.shiftKey
            ? (current - 1 + buttons.length) % buttons.length
            : (current + 1) % buttons.length;
        event.preventDefault();
        buttons[next].focus();
    }

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
                ref={trigger}
                type="button"
                disabled={busy}
                className={iconOnly ? 'today-icon-action today-icon-danger' : 'hd-touch-action hd-touch-danger disabled:opacity-50'}
                aria-label={t('deleteMeal')}
                title={iconOnly ? t('deleteMeal') : undefined}
                onClick={() => setConfirming(true)}
            >
                {iconOnly ? <MealActionIcon name="delete" /> : t('deleteMeal')}
            </button>
            {confirming &&
                createPortal(
                    <span className="confirm-overlay" onClick={() => !busy && setConfirming(false)}>
                        <span
                            className="confirm-dialog"
                            role="dialog"
                            aria-modal="true"
                            aria-labelledby={`delete-meal-${id}`}
                            onClick={(event) => event.stopPropagation()}
                            ref={dialog}
                            onKeyDown={trapFocus}
                        >
                            <strong id={`delete-meal-${id}`}>{t('deleteMeal')}?</strong>
                            <span>{t('confirmDeleteMeal')}</span>
                            <span className="confirm-actions">
                                <button
                                    type="button"
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
                    </span>,
                    document.body,
                )}
            {error && (
                <span role="alert" className="text-sm text-red-700">
                    {error}
                </span>
            )}
        </span>
    );
}
