import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { request, type FoodDate, type Meal } from './api';
import { t } from './i18n';

export default function History() {
  const { date } = useParams();
  const navigate = useNavigate();
  const dates = useQuery({ queryKey: ['food-dates'], queryFn: () => request<FoodDate[]>('/consumptions/dates') });
  const entries = useQuery({ queryKey: ['consumptions', date], queryFn: () => request<Meal[]>(`/consumptions?date=${encodeURIComponent(date!)}`), enabled: Boolean(date) });
  const calories = entries.data?.reduce((total, meal) => total + Number(meal.calories), 0) ?? 0;
  const protein = entries.data?.reduce((total, meal) => total + Number(meal.protein), 0) ?? 0;
  return <section className="space-y-6"><h2 className="text-3xl font-bold">{t('history')}</h2><p className="text-slate-600">{t('foodHistoryOnly')}</p>
    <div className="grid gap-4 lg:grid-cols-[260px_1fr]"><aside className="space-y-3"><label className="block">{t('chooseDate')}<input className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2" type="date" value={date || ''} onChange={event => event.target.value && navigate(`/history/${event.target.value}`)} /></label>
      {dates.isPending && <p>{t('loading')}</p>}{dates.isError && <p role="alert">{t('unavailable')} <button onClick={() => dates.refetch()}>{t('retry')}</button></p>}
      <div className="space-y-2">{dates.data?.map(day => <Link key={day.local_date} to={`/history/${day.local_date}`} className={`block rounded-xl border px-4 py-3 ${date === day.local_date ? 'border-emerald-700 bg-mint' : 'border-slate-200 bg-white'}`}><strong>{day.local_date}</strong><span className="block text-sm text-slate-600">{day.meal_count} {t('meals')} · {Math.round(Number(day.calories))} kcal</span></Link>)}</div>
    </aside><div className="space-y-3">{!date && <p>{t('chooseDate')}</p>}{date && <><h3 className="text-2xl font-semibold">{date}</h3>{entries.isPending && <p>{t('loading')}</p>}{entries.isError && <p role="alert">{t('unavailable')} <button onClick={() => entries.refetch()}>{t('retry')}</button></p>}
      {entries.data && <><div className="rounded-2xl border border-slate-200 bg-white p-5"><strong>{calories.toFixed(0)} kcal · {protein.toFixed(1)} g {t('protein')}</strong></div>{entries.data.length === 0 && <p>{t('noMeals')}</p>}{entries.data.map(meal => <article className="rounded-2xl border border-slate-200 bg-white p-5" key={meal.id}><div className="flex justify-between gap-3"><div><h4 className="font-semibold">{meal.source_name || meal.items.map(item => item.food_name).join(', ')}</h4><p>{Number(meal.calories).toFixed(0)} kcal · {Number(meal.protein).toFixed(1)} g {t('protein')}</p>{meal.notes && <p className="text-sm text-slate-600">{meal.notes}</p>}</div><Link className="text-emerald-800 underline" to={`/meals/${meal.id}/edit?date=${date}`}>{t('editMeal')}</Link></div></article>)}</>}</>}</div></div>
  </section>;
}
