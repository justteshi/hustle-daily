import { useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { request, type Food, type Recipe } from './api';
import FoodPicker from './FoodPicker';
import { t } from './i18n';

type Ingredient = { food_id: string; quantity: number; unit: string; portion_id?: string };
const field = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2';
const button = 'rounded-xl bg-ink px-5 py-3 font-semibold text-white disabled:opacity-50';

export function IngredientFields({ ingredient, onChange, onRemove, removable }: { ingredient: Ingredient; onChange: (patch: Partial<Ingredient>) => void; onRemove: () => void; removable: boolean }) {
  const food = useQuery({ queryKey: ['food', ingredient.food_id], queryFn: () => request<Food>(`/foods/${ingredient.food_id}`), enabled: Boolean(ingredient.food_id) });
  return <div className="grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-[minmax(0,2fr)_1fr_1fr_auto]">
    <label>{t('food')}<FoodPicker value={ingredient.food_id} onChange={selected => onChange({ food_id: selected.id, unit: selected.nutrition.basis_unit, portion_id: undefined })} /></label>
    <label>{t('quantity')}<input className={field} required min="0.001" step="any" type="number" value={ingredient.quantity} onChange={event => onChange({ quantity: Number(event.target.value) })} /></label>
    <label>{t('unit')}<select className={field} value={ingredient.unit === 'portion' ? `portion:${ingredient.portion_id}` : ingredient.unit} onChange={event => event.target.value.startsWith('portion:') ? onChange({ unit: 'portion', portion_id: event.target.value.slice(8) }) : onChange({ unit: event.target.value, portion_id: undefined })}>
      {!food.data && <option value={ingredient.unit}>{ingredient.unit}</option>}
      {food.data && <option value={food.data.nutrition.basis_unit}>{food.data.nutrition.basis_unit}</option>}
      {food.data?.portions.map(portion => <option key={portion.id} value={`portion:${portion.id}`}>{portion.label}</option>)}
    </select></label>
    <button type="button" disabled={!removable} className="self-end text-red-700 disabled:opacity-40" onClick={onRemove}>{t('remove')}</button>
  </div>;
}

export function RecipeCatalogue() {
  const recipes = useQuery({ queryKey: ['recipes'], queryFn: () => request<Recipe[]>('/recipes') });
  return <section className="space-y-5"><div className="flex flex-wrap items-center justify-between gap-3"><h2 className="text-3xl font-bold">{t('recipes')}</h2><Link className={button} to="/recipes/new">{t('createRecipe')}</Link></div>
    {recipes.isPending && <p>{t('loading')}</p>}{recipes.isError && <p role="alert">{t('unavailable')} <button onClick={() => recipes.refetch()}>{t('retry')}</button></p>}
    <div className="grid gap-3 sm:grid-cols-2">{recipes.data?.map(recipe => <article key={recipe.id} className="rounded-2xl border border-slate-200 bg-white p-5"><div className="flex items-start justify-between gap-3"><div><h3 className="font-semibold">{recipe.name}</h3><p className="text-sm text-slate-600">{recipe.ingredients.length} {t('ingredients')} · {recipe.yield_servings} {t('servings')}</p></div><Link to={`/recipes/${recipe.id}/edit`} className="text-emerald-800 underline">{t('edit')}</Link></div></article>)}</div>
  </section>;
}

export default function RecipeForm() {
  const { id } = useParams();
  const recipe = useQuery({ queryKey: ['recipe', id], queryFn: () => request<Recipe>(`/recipes/${id}`), enabled: Boolean(id) });
  const client = useQueryClient();
  const navigate = useNavigate();
  const [name, setName] = useState('');
  const [yieldServings, setYieldServings] = useState(1);
  const [ingredients, setIngredients] = useState<Ingredient[]>([{ food_id: '', quantity: 100, unit: 'g' }]);
  const [loadedId, setLoadedId] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const retry = useRef({ payload: '', key: crypto.randomUUID() });
  if (id && recipe.data && loadedId !== id) {
    setName(recipe.data.name);
    setYieldServings(Number(recipe.data.yield_servings));
    setIngredients(recipe.data.ingredients.map(item => ({ food_id: item.food_id, quantity: Number(item.quantity), unit: item.unit, portion_id: item.food_portion_id || undefined })));
    setLoadedId(id);
  }
  if (id && recipe.isPending) return <p>{t('loading')}</p>;
  if (id && recipe.isError) return <p role="alert">{t('unavailable')} <button onClick={() => recipe.refetch()}>{t('retry')}</button></p>;
  const change = (index: number, patch: Partial<Ingredient>) => setIngredients(ingredients.map((ingredient, position) => position === index ? { ...ingredient, ...patch } : ingredient));

  async function save(event: React.FormEvent) {
    event.preventDefault(); setError(''); setBusy(true);
    const body = JSON.stringify({ name, yield_servings: yieldServings, ingredients });
    if (retry.current.payload !== body) retry.current = { payload: body, key: crypto.randomUUID() };
    try {
      await request<Recipe>(id ? `/recipes/${id}` : '/recipes', { method: id ? 'PATCH' : 'POST', headers: { 'Idempotency-Key': retry.current.key }, body }, true);
      await client.invalidateQueries({ queryKey: ['recipes'] });
      if (id) await client.invalidateQueries({ queryKey: ['recipe', id] });
      navigate('/recipes');
    } catch (failure) { setError((failure as Error).message); } finally { setBusy(false); }
  }

  return <form onSubmit={save} className="space-y-5"><h2 className="text-3xl font-bold">{id ? t('editRecipe') : t('createRecipe')}</h2>
    <div className="grid max-w-2xl gap-4 sm:grid-cols-2"><label>{t('name')}<input className={field} required value={name} onChange={event => setName(event.target.value)} /></label><label>{t('yieldServings')}<input className={field} required min="0.01" step="any" type="number" value={yieldServings} onChange={event => setYieldServings(Number(event.target.value))} /></label></div>
    <div className="space-y-3">{ingredients.map((ingredient, index) => <IngredientFields key={index} ingredient={ingredient} onChange={patch => change(index, patch)} onRemove={() => setIngredients(ingredients.filter((_, position) => position !== index))} removable={ingredients.length > 1} />)}</div>
    <button type="button" className="text-emerald-800 underline" onClick={() => setIngredients([...ingredients, { food_id: '', quantity: 100, unit: 'g' }])}>{t('addIngredient')}</button>
    {error && <p role="alert" className="text-red-700">{error}</p>}<div className="flex gap-3"><button disabled={busy} className={button}>{t('save')}</button><Link to="/recipes" className="px-4 py-3 underline">{t('cancel')}</Link></div>
  </form>;
}
