import { useEffect, useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { request, type DayReview, type DaySummary, type DayType } from './api';
import DeleteMealButton from './DeleteMealButton';
import { formatLocalDate, t } from './i18n';
import { ErrorState, LoadingState } from './UiStates';

function titleDate(date: string): string {
    return new Intl.DateTimeFormat(navigator.language.startsWith('bg') ? 'bg-BG' : 'en-GB', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`));
}

export default function PastDay() {
    const { date } = useParams();
    const client = useQueryClient();
    const [reviewBusy, setReviewBusy] = useState(false);
    const [reviewError, setReviewError] = useState('');
    const [acknowledged, setAcknowledged] = useState(false);
    const [correctingType, setCorrectingType] = useState(false);
    const [selectedTypeId, setSelectedTypeId] = useState('');
    const [savingType, setSavingType] = useState(false);
    const [typeError, setTypeError] = useState('');
    const retryKey = useRef<string | null>(null);
    const summary = useQuery({
        queryKey: ['day-summary', date],
        queryFn: () => request<DaySummary>(`/days/${encodeURIComponent(date!)}`),
        enabled: Boolean(date),
    });
    const dayTypes = useQuery({
        queryKey: ['day-types'],
        queryFn: () => request<DayType[]>('/day-types'),
        enabled: correctingType,
    });

    useEffect(() => {
        retryKey.current = null;
        setReviewError('');
        setAcknowledged(false);
        setCorrectingType(false);
        setTypeError('');
    }, [date]);

    async function changeReview(action: 'conclude' | 'reopen') {
        if (!date) return;
        setReviewBusy(true);
        setReviewError('');
        retryKey.current ||= crypto.randomUUID();
        try {
            await request<DayReview>(`/days/${encodeURIComponent(date)}/${action}`, {
                method: 'POST',
                headers: { 'Idempotency-Key': retryKey.current },
                ...(action === 'conclude' ? { body: JSON.stringify({ acknowledge_missing_health: true }) } : {}),
            }, true);
            retryKey.current = null;
            setAcknowledged(false);
            setCorrectingType(false);
            await client.invalidateQueries({ queryKey: ['day-summary', date] });
            await client.invalidateQueries({ queryKey: ['today'] });
        } catch (error) {
            setReviewError((error as Error).message);
        } finally {
            setReviewBusy(false);
        }
    }

    async function saveDayType() {
        if (!date || !selectedTypeId) return;
        setSavingType(true);
        setTypeError('');
        try {
            await request(`/days/${encodeURIComponent(date)}`, {
                method: 'PATCH',
                body: JSON.stringify({ day_type_id: selectedTypeId }),
            }, true);
            await client.invalidateQueries({ queryKey: ['day-summary', date] });
            await client.invalidateQueries({ queryKey: ['today'] });
            setCorrectingType(false);
        } catch (error) {
            setTypeError((error as Error).message);
        } finally {
            setSavingType(false);
        }
    }

    const day = summary.data;
    const review = day?.review;
    const isConcluded = review?.status === 'concluded';
    const statusLabel = isConcluded ? t('concluded') : review?.status === 'reopened' ? t('reopened') : t('openStatus');

    return (
        <section className="past-day-page">
            <Link className="past-day-back" to={date ? `/history/${date}` : '/history'}>← {t('history')}</Link>
            {summary.isPending && <LoadingState />}
            {summary.isError && <ErrorState onRetry={() => void summary.refetch()} />}
            {day && date && (
                <>
                    <header className="past-day-header">
                        <div>
                            <h1>{titleDate(date)}</h1>
                            <div className="past-day-meta">
                                <span className="past-day-type-chip">{day.day_type.name}</span>
                                <span className={`past-day-status${isConcluded ? ' is-concluded' : review?.status === 'reopened' ? ' is-reopened' : ''}`}>{statusLabel}</span>
                                {review?.concluded_at && <span className="past-day-audit-time">{t('concluded')} {new Date(review.concluded_at).toLocaleString()}</span>}
                            </div>
                        </div>
                        {isConcluded && <button type="button" className="past-day-secondary" disabled={reviewBusy} onClick={() => void changeReview('reopen')}>{t('reopenToEdit')}</button>}
                    </header>

                    {review?.status === 'reopened' && <p className="past-day-reopened-note" role="status">{t('reopenedEditHint')}</p>}
                    {!isConcluded && (
                        <div className="past-day-confirm">
                            <p>{t('concludeHint')}</p>
                            <label>
                                <input type="checkbox" checked={acknowledged} onChange={(event) => setAcknowledged(event.target.checked)} />
                                <span>{t('acknowledgeMissingHealth')}</span>
                            </label>
                            <button type="button" className="past-day-primary" disabled={!acknowledged || reviewBusy} onClick={() => void changeReview('conclude')}>
                                {review?.status === 'reopened' ? t('concludeAgain') : t('concludeDay')}
                            </button>
                        </div>
                    )}
                    {reviewError && <p role="alert" className="past-day-error">{reviewError}</p>}

                    <section className="past-day-totals" aria-label={t('dayTotals')}>
                        <div className="past-day-total"><span>{t('eaten')}</span><p><strong>{Number(day.consumed.calories).toFixed(0)}</strong> / {day.day_type.targets.consumed_calories?.value ?? '—'} kcal</p><small>{t('liveFood')}</small></div>
                        <div className="past-day-total"><span>{t('protein')}</span><p><strong>{Number(day.consumed.protein).toFixed(1)}</strong> / {day.day_type.targets.protein_g?.value ?? '—'} g</p><small>{t('liveFood')}</small></div>
                        <div className="past-day-total"><span>{t('totalBurned')}</span><p><strong>—</strong> kcal</p><small>{t('healthData')} · {t('comingLater')}</small></div>
                        <div className="past-day-total past-day-balance"><span>{t('energyBalance')}</span><p><strong>—</strong> kcal</p><small>{t('healthData')} · {t('comingLater')}</small></div>
                    </section>

                    <div className="past-day-columns">
                        <section className="past-day-food" aria-labelledby="past-day-food-heading">
                            <div className="past-day-card-heading"><h2 id="past-day-food-heading">{t('food')}</h2><span>{isConcluded ? t('reopenHint') : t('mealEditHint')}</span></div>
                            {day.consumptions.length === 0 ? <p className="past-day-empty">{t('noMeals')}</p> : (
                                <div className="past-day-food-scroll">
                                    <table>
                                        <thead><tr><th>{t('meals')}</th><th>{t('food')}</th><th>{t('quantity')}</th><th>kcal</th><th>{t('protein')}</th><th><span className="sr-only">{t('actions')}</span></th></tr></thead>
                                        <tbody>
                                            {day.consumptions.map((meal) => (
                                                <tr key={meal.id}>
                                                    <td data-label={t('meals')}>{meal.source_name || formatLocalDate(meal.local_date)}</td>
                                                    <td data-label={t('food')}><strong>{meal.items.map((item) => item.food_name).join(', ') || meal.source_name}</strong>{meal.notes && <small>{meal.notes}</small>}</td>
                                                    <td data-label={t('quantity')}>{meal.items.map((item) => `${Number(item.quantity).toLocaleString()} ${item.unit}`).join(' + ') || '—'}</td>
                                                    <td data-label="kcal">{Number(meal.calories).toFixed(0)}</td>
                                                    <td data-label={t('protein')}>{Number(meal.protein).toFixed(1)} g</td>
                                                    <td>{!isConcluded && <div className="past-day-row-actions"><Link to={`/meals/${meal.id}/edit?date=${date}`}>{t('editMeal')}</Link><DeleteMealButton id={meal.id} date={date} /></div>}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>

                        <aside className="past-day-side">
                            <section className="past-day-side-card">
                                <div className="past-day-card-heading"><h2>{t('dayType')}</h2>{!isConcluded && !correctingType && <button type="button" onClick={() => { setSelectedTypeId(day.day_type.id); setCorrectingType(true); }}>{t('correctDayType')}</button>}</div>
                                <strong>{day.day_type.name}</strong>
                                <small>{day.source === 'manual' ? t('manualDayType') : t('defaultDayType')}</small>
                                {correctingType && (
                                    <div className="past-day-type-correction">
                                        <p>{t('correctDayTypeHint')}</p>
                                        {dayTypes.isPending && <LoadingState />}
                                        {dayTypes.isError && <ErrorState onRetry={() => void dayTypes.refetch()} />}
                                        {dayTypes.data && <label>{t('dayType')}<select value={selectedTypeId} disabled={savingType} onChange={(event) => setSelectedTypeId(event.target.value)}>{dayTypes.data.map((type) => <option key={type.id} value={type.id}>{type.name}</option>)}</select></label>}
                                        <div><button type="button" disabled={savingType || selectedTypeId === day.day_type.id} onClick={() => void saveDayType()}>{t('saveCorrection')}</button><button type="button" disabled={savingType} onClick={() => setCorrectingType(false)}>{t('cancel')}</button></div>
                                        {typeError && <p role="alert" className="past-day-error">{typeError}</p>}
                                    </div>
                                )}
                            </section>
                            <section className="past-day-side-card"><div className="past-day-card-heading"><h2>{t('workouts')}</h2><span>{t('healthData')}</span></div><p>{t('historyHealthUnavailable')}</p></section>
                            <section className="past-day-side-card"><div className="past-day-card-heading"><h2>{t('activityAndBody')}</h2><span>{t('healthData')}</span></div><dl><dt>{t('steps')}</dt><dd>—</dd><dt>{t('sleep')}</dt><dd>—</dd><dt>{t('weight')}</dt><dd>—</dd></dl><small>{t('comingLater')}</small></section>
                            <section className="past-day-side-card"><div className="past-day-card-heading"><h2>{t('notes')}</h2><span>{t('comingLater')}</span></div><p>{t('dayNotesUnavailable')}</p></section>
                            {!!review?.revisions.length && <section className="past-day-side-card"><h2>{t('reviewHistory')}</h2><ol>{review.revisions.map((revision, index) => <li key={`${revision.created_at}-${index}`}>{revision.action === 'reopen' ? t('reopened') : t('concluded')} · {new Date(revision.created_at).toLocaleString()}</li>)}</ol></section>}
                        </aside>
                    </div>
                </>
            )}
        </section>
    );
}
