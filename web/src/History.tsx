import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { request, type DaySummary, type FoodDate, type Today } from './api';
import { HistoryCalendar, RecentFoodDays } from './HistoryCalendar';
import { formatLocalDate, t } from './i18n';
import { ErrorState, LoadingState } from './UiStates';

export default function History() {
    const { date } = useParams();
    const navigate = useNavigate();
    const [month, setMonth] = useState(date?.slice(0, 7) ?? '');
    const todayQuery = useQuery({
        queryKey: ['today'],
        queryFn: () => request<Today>('/dashboard/today'),
        refetchInterval: 60_000,
    });
    const today = todayQuery.data?.local_date;
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

    useEffect(() => {
        if (date) setMonth(date.slice(0, 7));
        else if (today) setMonth((previous) => previous || today.slice(0, 7));
    }, [date, today]);

    useEffect(() => {
        if (!date && recentDates.data?.length) {
            navigate(`/history/${recentDates.data[0].local_date}`, { replace: true });
        }
    }, [date, navigate, recentDates.data]);

    function selectDate(selected: string) {
        setMonth(selected.slice(0, 7));
        navigate(`/history/${selected}`);
    }

    const reviewStatus = summary.data?.review?.status;

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
                    {summary.data && date && (
                        <>
                            <div className="history-selected-chips">
                                <span>{summary.data.day_type.name}</span>
                                <span className="history-pending-chip">
                                    {reviewStatus === 'concluded' ? t('concluded') : reviewStatus === 'reopened' ? t('reopened') : t('openStatus')}
                                </span>
                            </div>
                            <dl className="history-selected-stats">
                                <dt>{t('eaten')}</dt><dd>{Number(summary.data.consumed.calories).toFixed(0)} kcal</dd>
                                <dt>{t('totalBurned')}</dt><dd>{t('notAvailable')}</dd>
                                <dt>{t('energyBalance')}</dt><dd>{t('notAvailable')}</dd>
                                <dt>{t('protein')}</dt><dd>{Number(summary.data.consumed.protein).toFixed(1)} g</dd>
                                <dt>{t('steps')}</dt><dd>{t('notAvailable')}</dd>
                            </dl>
                            <p className="history-selected-note">{t('historyHealthUnavailable')}</p>
                            <Link className="history-open-day" to={`/history/${date}/day`}>{t('openDay')}</Link>
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
        </section>
    );
}
