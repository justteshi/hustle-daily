import { formatLocalDate, t } from './i18n';
import type { FoodDate } from './api';

function monthDate(year: number, monthIndex: number, day: number): string {
    return new Date(Date.UTC(year, monthIndex, day)).toISOString().slice(0, 10);
}

function calendarDates(month: string): Array<string | null> {
    const [year, monthNumber] = month.split('-').map(Number);
    const firstWeekday = (new Date(Date.UTC(year, monthNumber - 1, 1)).getUTCDay() + 6) % 7;
    const days = new Date(Date.UTC(year, monthNumber, 0)).getUTCDate();
    const cells: Array<string | null> = Array(firstWeekday).fill(null);
    for (let day = 1; day <= days; day++) {
        cells.push(monthDate(year, monthNumber - 1, day));
    }
    while (cells.length % 7 !== 0) cells.push(null);
    return cells;
}

function monthTitle(month: string): string {
    const [year, monthNumber] = month.split('-').map(Number);
    return new Intl.DateTimeFormat(navigator.language.startsWith('bg') ? 'bg-BG' : 'en-GB', {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(year, monthNumber - 1, 1)));
}

const weekdayNames = Array.from({ length: 7 }, (_, index) =>
    new Intl.DateTimeFormat(navigator.language.startsWith('bg') ? 'bg-BG' : 'en-GB', {
        weekday: 'short',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(2026, 9, 26 + index))),
);

export function HistoryCalendar({
    month,
    today,
    selectedDate,
    dates,
    onMonthChange,
    onSelect,
}: {
    month: string;
    today: string;
    selectedDate?: string;
    dates: FoodDate[];
    onMonthChange: (month: string) => void;
    onSelect: (date: string) => void;
}) {
    const [year, monthNumber] = month.split('-').map(Number);
    const byDate = new Map(dates.map((day) => [day.local_date, day]));

    function shiftMonth(offset: number) {
        onMonthChange(monthDate(year, monthNumber - 1 + offset, 1).slice(0, 7));
    }

    return (
        <section className="history-calendar-card" aria-label={`${monthTitle(month)} ${t('calendar')}`}>
            <div className="history-calendar-heading">
                <h2>{monthTitle(month)}</h2>
                <div className="history-month-controls">
                    <button type="button" aria-label={t('previousMonth')} onClick={() => shiftMonth(-1)}>‹</button>
                    <button type="button" aria-label={t('nextMonth')} disabled={month >= today.slice(0, 7)} onClick={() => shiftMonth(1)}>›</button>
                </div>
            </div>
            <div className="history-calendar-grid history-weekdays">
                {weekdayNames.map((name, index) => <span key={index}>{name}</span>)}
            </div>
            <div className="history-calendar-grid">
                {calendarDates(month).map((day, index) => {
                    if (!day) return <span className="history-calendar-empty" key={`empty-${index}`} />;
                    const logged = byDate.get(day);
                    const future = day > today;
                    return (
                        <button
                            type="button"
                            key={day}
                            className={`history-calendar-day${day === selectedDate ? ' is-selected' : ''}${logged ? ' has-food' : ''}`}
                            disabled={future}
                            aria-label={`${formatLocalDate(day)}${logged ? `, ${logged.meal_count} ${t('meals')}` : ''}`}
                            aria-pressed={day === selectedDate}
                            onClick={() => onSelect(day)}
                        >
                            <strong>{Number(day.slice(-2))}</strong>
                            {logged && <span className="history-calendar-food-dot" aria-hidden="true" />}
                            <span className="history-calendar-detail">
                                {logged ? `${Number(logged.calories).toFixed(0)} kcal` : future ? '' : '—'}
                            </span>
                        </button>
                    );
                })}
            </div>
        </section>
    );
}

export function RecentFoodDays({
    today,
    selectedDate,
    dates,
    onSelect,
}: {
    today: string;
    selectedDate?: string;
    dates: FoodDate[];
    onSelect: (date: string) => void;
}) {
    const [year, month, day] = today.split('-').map(Number);
    const recentDays = Array.from({ length: 7 }, (_, index) => monthDate(year, month - 1, day - index));
    const byDate = new Map(dates.map((entry) => [entry.local_date, entry]));

    return (
        <section className="history-recent-card" aria-labelledby="history-recent-heading">
            <h2 id="history-recent-heading">{t('lastSevenDays')}</h2>
            <div className="history-recent-scroll">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">{t('day')}</th>
                            <th scope="col">{t('meals')}</th>
                            <th scope="col">{t('consumedCalories')}</th>
                            <th scope="col">{t('protein')}</th>
                            <th scope="col">{t('healthData')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {recentDays.map((date) => {
                            const logged = byDate.get(date);
                            return (
                                <tr key={date} className={date === selectedDate ? 'is-selected' : ''}>
                                    <td data-label={t('day')}><button type="button" onClick={() => onSelect(date)}>{formatLocalDate(date)}</button></td>
                                    <td data-label={t('meals')}>{logged?.meal_count ?? 0}</td>
                                    <td data-label={t('consumedCalories')}>{Number(logged?.calories ?? 0).toFixed(0)} kcal</td>
                                    <td data-label={t('protein')}>{Number(logged?.protein ?? 0).toFixed(1)} g</td>
                                    <td data-label={t('healthData')}><span className="history-unavailable">{t('comingLater')}</span></td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
