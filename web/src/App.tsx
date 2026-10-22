import { useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, Route, Routes, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { ApiError, login, logout, request, type Category, type Meal, type Recipe, type Today } from './api';
import { FoodCatalogue, FoodEditor } from './FoodCatalogue';
import History from './History';
import RecipeForm, { IngredientFields, RecipeCatalogue } from './RecipeForm';
import { t } from './i18n';

const button = 'rounded-xl bg-ink px-5 py-3 font-semibold text-white disabled:opacity-50';
const field = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2';
const card = 'rounded-2xl border border-slate-200 bg-white p-6 shadow-sm';

function Login({ onSuccess }: { onSuccess: () => void }) {
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const { register, handleSubmit } = useForm<{ email: string; password: string }>();
  return <main className="mx-auto flex min-h-screen max-w-md items-center px-4"><form className={`${card} w-full space-y-5`} onSubmit={handleSubmit(async data => {
    setBusy(true); setError('');
    try { await login(data.email, data.password); onSuccess(); } catch (failure) { setError((failure as Error).message); } finally { setBusy(false); }
  })}>
    <h1 className="text-3xl font-bold">{t('title')}</h1>
    <label className="block space-y-1">{t('email')}<input className={field} type="email" autoComplete="username" required {...register('email')} /></label>
    <label className="block space-y-1">{t('password')}<input className={field} type="password" autoComplete="current-password" required {...register('password')} /></label>
    {error && <p role="alert" className="text-red-700">{error}</p>}
    <button className={button} disabled={busy}>{t('signIn')}</button>
  </form></main>;
}

function Dashboard() {
  const query = useQuery({ queryKey: ['today'], queryFn: () => request<Today>('/dashboard/today') });
  if (query.isPending) return <p>{t('loading')}</p>;
  if (query.isError) return <div role="alert">{t('unavailable')} <button className={button} onClick={() => query.refetch()}>{t('retry')}</button></div>;
  return <div className="space-y-8">
    <div><h2 className="text-3xl font-bold">{t('today')}</h2><p className="text-slate-600">{query.data.local_date}</p></div>
    <div className="grid gap-4 sm:grid-cols-2"><div className={card}><p>{t('calories')}</p><strong className="text-4xl">{Number(query.data.consumed.calories).toFixed(0)}</strong> kcal</div><div className={card}><p>{t('protein')}</p><strong className="text-4xl">{Number(query.data.consumed.protein).toFixed(1)}</strong> g</div></div>
    <section className="space-y-4"><div className="flex flex-wrap items-center justify-between gap-3"><h3 className="text-2xl font-semibold">{t('meals')}</h3><Link className={button} to="/meals/new">{t('addMeal')}</Link></div>
      {query.data.consumptions.length === 0 ? <p>{t('noMeals')}</p> : query.data.consumptions.map(meal => <article className={card} key={meal.id}><div className="flex flex-wrap justify-between gap-3"><div><h4 className="font-semibold">{meal.source_name || meal.items.map(item => item.food_name).join(', ')}</h4><p>{Number(meal.calories).toFixed(0)} kcal · {Number(meal.protein).toFixed(1)} g {t('protein')}</p></div><Link className="text-emerald-800 underline" to={`/meals/${meal.id}/edit?date=${query.data.local_date}`}>{t('editMeal')}</Link></div></article>)}</section>
  </div>;
}

type Row = { food_id: string; quantity: number; unit: string; portion_id?: string };
function MealForm() {
  const { id } = useParams();
  const [searchParams] = useSearchParams();
  const date = searchParams.get('date');
  const navigate = useNavigate();
  const client = useQueryClient();
  const entries = useQuery({ queryKey: ['consumptions', date], queryFn: () => request<Meal[]>(`/consumptions?date=${encodeURIComponent(date!)}`), enabled: Boolean(id && date) });
  const categories = useQuery({ queryKey: ['categories'], queryFn: () => request<Category[]>('/meal-categories') });
  const recipes = useQuery({ queryKey: ['recipes'], queryFn: () => request<Recipe[]>('/recipes') });
  const meal = id ? entries.data?.find(entry => entry.id === id) : undefined;
  const [rows, setRows] = useState<Row[] | null>(null);
  const [category, setCategory] = useState('');
  const [notes, setNotes] = useState<string | null>(null);
  const [mode, setMode] = useState<'foods' | 'recipe'>('foods');
  const [recipe, setRecipe] = useState('');
  const [servings, setServings] = useState(1);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const retry = useRef({ input: '', payload: '', key: crypto.randomUUID() });
  const selectedRows = rows ?? (meal ? meal.items.map(item => ({ food_id: item.food_id, quantity: Number(item.quantity), unit: item.input_unit || item.unit, portion_id: item.food_portion_id || undefined })) : [{ food_id: '', quantity: 100, unit: 'g' }]);
  const selectedCategory = category || meal?.meal_category_id || categories.data?.[0]?.id || '';
  if (id && !date) return <p role="alert">{t('missingMeal')}</p>;
  if ((id && entries.isPending) || categories.isPending || recipes.isPending) return <p>{t('loading')}</p>;
  if ((id && entries.isError) || categories.isError || recipes.isError) return <p role="alert">{t('unavailable')}</p>;
  if (id && !meal) return <p role="alert">{t('missingMeal')}</p>;
  const updateRow = (index: number, patch: Partial<Row>) => setRows(selectedRows.map((row, position) => position === index ? { ...row, ...patch } : row));

  async function save(event: React.FormEvent) {
    event.preventDefault(); setBusy(true); setError('');
    try {
      const items = selectedRows.map(row => ({ ...row, ...(row.unit === 'portion' ? {} : { portion_id: undefined }) }));
      const input = JSON.stringify(id
        ? { meal_category_id: selectedCategory, notes: notes ?? meal?.notes ?? null, items }
        : { meal_category_id: selectedCategory, notes, ...(mode === 'recipe' ? { recipe_id: recipe, servings } : { items }) });
      if (retry.current.input !== input) {
        const payload = JSON.parse(input);
        if (!id) payload.eaten_at = new Date().toISOString().replace(/\.\d{3}Z$/, '+00:00');
        retry.current = { input, payload: JSON.stringify(payload), key: crypto.randomUUID() };
      }
      await request<Meal>(id ? `/consumptions/${id}` : '/consumptions', { method: id ? 'PATCH' : 'POST', body: retry.current.payload, headers: { 'Idempotency-Key': retry.current.key } }, true);
      await client.invalidateQueries({ queryKey: ['today'] });
      if (date) await client.invalidateQueries({ queryKey: ['consumptions', date] });
      navigate(date ? `/history/${date}` : '/');
    } catch (failure) { setError((failure as Error).message); } finally { setBusy(false); }
  }
  return <form className="space-y-5" onSubmit={save}><h2 className="text-3xl font-bold">{id ? t('editMeal') : t('addMeal')}</h2><p className="text-slate-600">{t('recent')}</p>
    <label className="block max-w-md space-y-1">{t('category')}<select className={field} value={selectedCategory} onChange={event => setCategory(event.target.value)}>{categories.data?.map(item => <option value={item.id} key={item.id}>{item.name}</option>)}</select></label>
    {!id && <div className="flex gap-5"><label><input type="radio" checked={mode === 'foods'} onChange={() => setMode('foods')} /> {t('individual')}</label><label><input type="radio" checked={mode === 'recipe'} onChange={() => setMode('recipe')} /> {t('recipe')}</label></div>}
    {mode === 'recipe' && !id ? <div className={card}><label className="block space-y-1">{t('recipe')}<select className={field} required value={recipe} onChange={event => setRecipe(event.target.value)}><option value="">—</option>{recipes.data?.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><label className="mt-3 block space-y-1">{t('servings')}<input className={field} type="number" min="0.01" step="any" value={servings} onChange={event => setServings(Number(event.target.value))} /></label></div> : <div className="space-y-3">{selectedRows.map((row, index) => <IngredientFields key={index} ingredient={row} onChange={patch => updateRow(index, patch)} onRemove={() => setRows(selectedRows.filter((_, position) => position !== index))} removable={selectedRows.length > 1} />)}<button type="button" className="text-emerald-800 underline" onClick={() => setRows([...selectedRows, { food_id: '', quantity: 100, unit: 'g' }])}>{t('addIngredient')}</button></div>}
    <label className="block max-w-2xl space-y-1">{t('notes')}<textarea className={field} value={notes ?? meal?.notes ?? ''} onChange={event => setNotes(event.target.value)} /></label>
    {error && <p role="alert" className="text-red-700">{error}</p>}<div className="flex gap-3"><button className={button} disabled={busy}>{t('save')}</button><Link className="px-4 py-3 underline" to={date ? `/history/${date}` : '/'}>{t('cancel')}</Link></div>
  </form>;
}

export default function App() {
  const client = useQueryClient(); const navigate = useNavigate();
  const me = useQuery({ queryKey: ['me'], queryFn: () => request<{ id: string; name: string }>('/me'), retry: false });
  if (me.isPending) return <p className="p-6">{t('loading')}</p>;
  if (me.isError && !(me.error instanceof ApiError && me.error.status === 401)) return <main className="mx-auto max-w-xl space-y-4 p-6" role="alert"><p>{t('unavailable')}</p><button className={button} onClick={() => me.refetch()}>{t('retry')}</button></main>;
  if (me.isError) return <Login onSuccess={() => { client.invalidateQueries(); navigate('/'); }} />;
  return <><header className="border-b border-slate-200 bg-white"><div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4"><Link to="/" className="text-xl font-bold">{t('title')}</Link><nav className="flex flex-wrap items-center gap-4"><Link to="/">{t('today')}</Link><Link to="/history">{t('history')}</Link><Link to="/foods">{t('foods')}</Link><Link to="/recipes">{t('recipes')}</Link><span className="text-slate-500">{me.data.name}</span><button onClick={async () => { await logout(); client.clear(); navigate('/'); }}>{t('signOut')}</button></nav></div></header><main className="mx-auto max-w-6xl px-5 py-9"><Routes><Route path="/" element={<Dashboard />} /><Route path="/history" element={<History />} /><Route path="/history/:date" element={<History />} /><Route path="/meals/new" element={<MealForm />} /><Route path="/meals/:id/edit" element={<MealForm />} /><Route path="/foods" element={<FoodCatalogue />} /><Route path="/foods/new" element={<FoodEditor />} /><Route path="/foods/:id/edit" element={<FoodEditor />} /><Route path="/recipes" element={<RecipeCatalogue />} /><Route path="/recipes/new" element={<RecipeForm />} /><Route path="/recipes/:id/edit" element={<RecipeForm />} /></Routes></main></>;
}
