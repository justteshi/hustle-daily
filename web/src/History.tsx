import { useEffect, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { request, type DaySummary, type DayType, type FoodDate, type Today } from './api';
import DeleteMealButton from './DeleteMealButton';
import { HistoryCalendar, RecentFoodDays } from './HistoryCalendar';
import { formatLocalDate, t } from './i18n';
import { ErrorState, LoadingState } from './UiStates';

export default function History() {
    const { date } = useParams();
    const navigate = useNavigate();
    const client = useQueryClient();
    const [savingType, setSavingType] = useState(false);
    const [typeError, setTypeError] = useState('');
    const [correctingType, setCorrectingType] = useState(false);
    const [selectedTypeId, setSelectedTypeId] = useState('');
    const [month, setMonth] = useState(date?.slice(0, 7) ?? '');
    const todayQuery = useQuery({
        queryKey: ['today'],
        queryFn: () => request<Today>('/dashboard/today'),
        refetchInterval: 60_000,
    });
    const today = todayQuery.data?.local_date;

    useEffect(() => {
        setCorrectingType(false);
        setSelectedTypeId('');
        setTypeError('');
    }, [date]);

    useEffect(() => {
        if (date) {
            setMonth(date.slice(0, 7));
        } else if (today) {
            setMonth((previous) => previous || today.slice(0, 7));
        }
    }, [date, today]);
    const recentDates = useQuery({
        queryKey: ['food-dates'],
        queryFn: () => request<FoodDate[]>('/consumptions/dates'),
    });
    const monthDates = useQuery({
        queryKey: ['food-dates', month],
        queryFn: () => request<FoodDate[]>(`/consumptions/dates?month=${month}`),
        enabled: Boolean(month) && Boolean(today),
    });
    const summary = useQuery({
        queryKey: ['day-summary', date],
        queryFn: () => request<DaySummary>(`/days/${encodeURIComponent(date!)}`),
        enabled: Boolean(date),
    });
    const dayTypes = useQuery({
        queryKey: ['day-types'],
        queryFn: () => request<DayType[]>('/day-types'),
        enabled: Boolean(date) && correctingType,
    });

    useEffect(() => {
        if (!date && recentDates.data?.length) {
            navigate(`/history/${recentDates.data[0].local_date}`, { replace: true });
        }
    }, [date, navigate, recentDates.data]);

    function selectDate(selected: string) {
        setMonth(selected.slice(0, 7));
        navigate(`/history/${selected}`);
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
            setCorrectingType(false);
        } catch (error) {
            setTypeError((error as Error).message);
        } finally {
            setSavingType(false);
        }
    }
    return (
        <section className="history-page">
            <header className="history-heading">
                <div>
                    <h1>{t('history')}</h1>
                    <p>{t('historySummaryDescription')}</p>
                </div>
                <label className="history-date-jump">
                    <span>{t('chooseDate')}</span>
                    <input
                        type="date"
                        value={date || ''}
                        max={today}
                        disabled={!today}
                        onChange={(event) => event.target.value && selectDate(event.target.value)}
                    />
                </label>
            </header>
            <div className="history-legend">
                <span><i className="history-legend-dot" />{t('foodLogged')}</span>
                <span><i className="history-legend-outline" />{t('noFoodLogged')}</span>
                <span className="history-legend-future">{t('historyFutureNotice')}</span>
            </div>
            <div className="history-overview">
                <div>
                    {todayQuery.isPending && <LoadingState />}
                    {todayQuery.isError && <ErrorState onRetry={() => void todayQuery.refetch()} />}
                    {today && monthDates.isPending && <LoadingState />}
                    {monthDates.isError && <ErrorState onRetry={() => void monthDates.refetch()} />}
                    {today && monthDates.data && (
                        <HistoryCalendar
                            month={month}
                            today={today}
                            selectedDate={date}
                            dates={monthDates.data}
                            onMonthChange={setMonth}
                            onSelect={selectDate}
                        />
                    )}
                </div>
                <aside className="history-selected-card" aria-label={t('selectedDay')}>
                    <span className="history-card-eyebrow">{t('selectedDay')}</span>
                    {!date && <p>{t('chooseDate')}</p>}
                    {date && <h2>{formatLocalDate(date)}</h2>}
                    {summary.isPending && date && <LoadingState />}
                    {summary.isError && <ErrorState onRetry={() => void summary.refetch()} />}
                    {summary.data && (
                        <>
                            <div className="history-selected-chips">
                                <span>{summary.data.day_type.name}</span>
                                <span className="history-pending-chip">{t('reviewNotAvailable')}</span>
                            </div>
                            <dl className="history-selected-stats">
                                <dt>{t('eaten')}</dt><dd>{Number(summary.data.consumed.calories).toFixed(0)} kcal</dd>
                                <dt>{t('totalBurned')}</dt><dd>{t('notAvailable')}</dd>
                                <dt>{t('energyBalance')}</dt><dd>{t('notAvailable')}</dd>
                                <dt>{t('protein')}</dt><dd>{Number(summary.data.consumed.protein).toFixed(1)} g</dd>
                                <dt>{t('steps')}</dt><dd>{t('notAvailable')}</dd>
                            </dl>
                            <p className="history-selected-note">{t('historyHealthUnavailable')}</p>
                            <a className="history-open-day" href="#history-day-details">{t('openDay')}</a>
                        </>
                    )}
                </aside>
            </div>
            {recentDates.isPending && <LoadingState />}
            {recentDates.isError && <ErrorState onRetry={() => void recentDates.refetch()} />}
            {today && recentDates.data && (
                <RecentFoodDays
                    today={today}
                    selectedDate={date}
                    dates={recentDates.data}
                    onSelect={selectDate}
                />
            )}
            <section id="history-day-details" className="history-day-details">
                <div className="space-y-3">
                    {!date && <p>{t('chooseDate')}</p>}
                    {date && (
                        <>
                            <h3 className="text-2xl font-semibold">{formatLocalDate(date)}</h3>
                            {summary.isPending && <LoadingState />}
                            {summary.isError && (
                                <ErrorState
                                    onRetry={() => {
                                        void summary.refetch();
                                    }}
                                />
                            )}
                            {summary.data && (
                                <>
                                    <div className="rounded-2xl border border-slate-200 bg-white p-5 space-y-2">
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <span className="block text-sm text-slate-600">{t('dayType')}</span>
                                                <strong className="text-lg">{summary.data.day_type.name}</strong>
                                                <p className="text-sm text-slate-600">{summary.data.source === 'manual' ? t('manualDayType') : t('defaultDayType')}</p>
                                            </div>
                                            {!correctingType && (
                                                <button
                                                    type="button"
                                                    className="hd-touch-action"
                                                    onClick={() => {
                                                        setSelectedTypeId(summary.data!.day_type.id);
                                                        setTypeError('');
                                                        setCorrectingType(true);
                                                    }}
                                                >
                                                    {t('correctDayType')}
                                                </button>
                                            )}
                                        </div>
                                        {correctingType && (
                                            <div className="space-y-3 border-t border-slate-200 pt-3">
                                                <p className="text-sm text-slate-600">{t('correctDayTypeHint')}</p>
                                                {dayTypes.isPending && <LoadingState />}
                                                {dayTypes.isError && <ErrorState onRetry={() => void dayTypes.refetch()} />}
                                                {dayTypes.data && (
                                                    <label className="block max-w-xs space-y-1">
                                                        <span className="block font-semibold">{t('dayType')}</span>
                                                        <select
                                                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                                                            value={selectedTypeId}
                                                            disabled={savingType}
                                                            onChange={(event) => setSelectedTypeId(event.target.value)}
                                                        >
                                                            {dayTypes.data.map((type) => <option key={type.id} value={type.id}>{type.name}</option>)}
                                                        </select>
                                                    </label>
                                                )}
                                                <div className="flex flex-wrap gap-3">
                                                    <button
                                                        type="button"
                                                        className="hd-touch-action"
                                                        disabled={savingType || !dayTypes.data || selectedTypeId === summary.data.day_type.id}
                                                        onClick={() => void saveDayType()}
                                                    >
                                                        {t('saveCorrection')}
                                                    </button>
                                                    <button type="button" className="hd-touch-action" disabled={savingType} onClick={() => setCorrectingType(false)}>{t('cancel')}</button>
                                                </div>
                                                {typeError && <p role="alert" className="text-red-700">{typeError}</p>}
                                            </div>
                                        )}
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="rounded-2xl border border-slate-200 bg-white p-5">
                                            <p className="text-sm text-slate-600">{t('consumedCalories')} · {t('liveFood')}</p>
                                            <strong className="text-2xl">{Number(summary.data.consumed.calories).toFixed(0)} kcal</strong>
                                            <p className="text-sm text-slate-600">{t('calorieBudget')}: {summary.data.day_type.targets.consumed_calories?.value ?? '—'} kcal</p>
                                        </div>
                                        <div className="rounded-2xl border border-slate-200 bg-white p-5">
                                            <p className="text-sm text-slate-600">{t('protein')} · {t('liveFood')}</p>
                                            <strong className="text-2xl">{Number(summary.data.consumed.protein).toFixed(1)} g</strong>
                                            <p className="text-sm text-slate-600">{t('proteinMinimum')}: {summary.data.day_type.targets.protein_g?.value ?? '—'} g</p>
                                        </div>
                                    </div>
                                    <div className="rounded-2xl border border-dashed border-amber-400 bg-amber-50 p-5">
                                        <span className="text-xs font-semibold uppercase text-amber-800">{t('comingLater')}</span>
                                        <p className="text-sm text-amber-900">{t('historyHealthUnavailable')}</p>
                                    </div>
                                    {summary.data.consumptions.length === 0 && <p>{t('noMeals')}</p>}
                                    {summary.data.consumptions.map((meal) => (
                                        <article
                                            className="rounded-2xl border border-slate-200 bg-white p-5"
                                            key={meal.id}
                                        >
                                            <div className="flex flex-wrap justify-between gap-3">
                                                <div>
                                                    <h4 className="font-semibold">
                                                        {meal.source_name ||
                                                            meal.items
                                                                .map((item) => item.food_name)
                                                                .join(', ')}
                                                    </h4>
                                                    <p>
                                                        {Number(meal.calories).toFixed(0)} kcal ·{' '}
                                                        {Number(meal.protein).toFixed(1)} g{' '}
                                                        {t('protein')}
                                                    </p>
                                                    {meal.notes && (
                                                        <p className="text-sm text-slate-600">
                                                            {meal.notes}
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="flex flex-wrap items-start gap-4">
                                                    <Link
                                                        className="hd-touch-action"
                                                        to={`/meals/${meal.id}/edit?date=${date}`}
                                                    >
                                                        {t('editMeal')}
                                                    </Link>
                                                    <DeleteMealButton id={meal.id} date={date} />
                                                </div>
                                            </div>
                                        </article>
                                    ))}
                                </>
                            )}
                        </>
                    )}
                </div>
            </section>
        </section>
    );
}
