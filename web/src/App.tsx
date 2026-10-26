import { useLayoutEffect, useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
    Link,
    NavLink,
    Route,
    Routes,
    useLocation,
    useNavigate,
    useNavigationType,
    useParams,
    useSearchParams,
} from 'react-router-dom';
import { useForm } from 'react-hook-form';
import {
    ApiError,
    login,
    logout,
    request,
    type Category,
    type Meal,
    type Recipe,
    type Today,
    type DayType,
} from './api';
import { FoodCatalogue, FoodEditor } from './FoodCatalogue';
import DeleteMealButton from './DeleteMealButton';
import History from './History';
import RecipeForm, { IngredientFields, RecipeCatalogue } from './RecipeForm';
import { ErrorState, LoadingState } from './UiStates';
import { t } from './i18n';
import { formatNumber } from './format';
import SidebarIcon from './SidebarIcon';
import MealActionIcon from './MealActionIcon';
import { ActivityPreview, EnergyPreview, TodaySidePanels } from './TodayPreview';

const button = 'hd-primary-button';
const field = 'hd-field';
const card = 'hd-card';

function ScrollOnNavigation() {
    const location = useLocation();
    const navigationType = useNavigationType();
    const previousPath = useRef(location.pathname);
    const positions = useRef(new Map<string, number>());
    useLayoutEffect(() => {
        if (previousPath.current !== location.pathname) {
            window.scrollTo(
                0,
                navigationType === 'POP' ? (positions.current.get(location.key) ?? 0) : 0,
            );
        }
        previousPath.current = location.pathname;
        return () => {
            positions.current.set(location.key, window.scrollY);
        };
    }, [location.key, location.pathname, navigationType]);
    return null;
}

function Login({ onSuccess }: { onSuccess: () => void }) {
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const { register, handleSubmit } = useForm<{ email: string; password: string }>();
    return (
        <div className="sign-in-page">
            <div className="sign-in-container">
                <section className="sign-in-intro" aria-label={t('title')}>
                    <div className="sign-in-brand">
                        <img src="/branding/lockup-colour.png" alt={t('title')} />
                    </div>
                    <div className="sign-in-story">
                        <h1>{t('signInHeadline')}</h1>
                        <p>{t('signInDescription')}</p>
                        <div className="sign-in-preview" aria-label={t('signInPreview')}>
                            <span className="sign-in-preview-label">{t('signInPreview')}</span>
                            <div className="sign-in-preview-row">
                                <div>
                                    <span>{t('calories')}</span>
                                    <strong>
                                        1,540 <small>kcal</small>
                                    </strong>
                                </div>
                                <div>
                                    <span>{t('protein')}</span>
                                    <strong>
                                        112 <small>g</small>
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p className="sign-in-privacy">{t('signInPrivacy')}</p>
                </section>
                <main className="sign-in-main">
                    <form
                        className="sign-in-form"
                        onSubmit={handleSubmit(async (data) => {
                            setBusy(true);
                            setError('');
                            try {
                                await login(data.email, data.password);
                                onSuccess();
                            } catch (failure) {
                                setError((failure as Error).message);
                            } finally {
                                setBusy(false);
                            }
                        })}
                    >
                        <div className="sign-in-heading">
                            <h2>{t('signIn')}</h2>
                            <p>{t('signInSubheading')}</p>
                        </div>
                        <label>
                            {t('email')}
                            <input
                                type="email"
                                autoComplete="username"
                                required
                                {...register('email')}
                            />
                        </label>
                        <label>
                            {t('password')}
                            <input
                                type="password"
                                autoComplete="current-password"
                                required
                                {...register('password')}
                            />
                        </label>
                        {error && (
                            <p role="alert" className="sign-in-error">
                                {error}
                            </p>
                        )}
                        <button type="submit" disabled={busy}>
                            {busy ? t('loading') : t('signIn')}
                        </button>
                        <p className="sign-in-help">{t('signInHelp')}</p>
                    </form>
                </main>
            </div>
        </div>
    );
}

function Dashboard() {
    const client = useQueryClient();
    const [dayTypeError, setDayTypeError] = useState(false);
    const [savingDayType, setSavingDayType] = useState(false);
    const query = useQuery({
        queryKey: ['today'],
        queryFn: () => request<Today>('/dashboard/today'),
    });
    const typesQuery = useQuery({
        queryKey: ['day-types'],
        queryFn: () => request<DayType[]>('/day-types'),
    });
    if (query.isPending) return <LoadingState />;
    if (query.isError)
        return (
            <ErrorState
                onRetry={() => {
                    void query.refetch();
                }}
            />
        );
    const day = new Date(`${query.data.local_date}T12:00:00`);
    const dateLabel = new Intl.DateTimeFormat(navigator.language, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }).format(day);
    const meals = query.data.consumptions;
    const localDate = query.data.local_date;
    const calorieTarget = query.data.day_type.targets.consumed_calories?.value;
    const proteinTarget = query.data.day_type.targets.protein_g?.value;
    async function changeDayType(dayTypeId: string) {
        setDayTypeError(false);
        setSavingDayType(true);
        try {
            await request(`/days/${localDate}`, {
                method: 'PATCH',
                body: JSON.stringify({ day_type_id: dayTypeId }),
            }, true);
            await client.invalidateQueries({ queryKey: ['today'] });
        } catch {
            setDayTypeError(true);
        } finally {
            setSavingDayType(false);
        }
    }
    return (
        <div className="today-page">
            <header className="today-heading">
                <div>
                    <span className="today-eyebrow">{t('todayInProgress')}</span>
                    <h1>{t('today')}</h1>
                    <p>{dateLabel}</p>
                    <div className="today-heading-details">
                        <label className="today-day-type">
                            {t('dayType')}
                            <select
                                value={query.data.day_type.id}
                                disabled={savingDayType || typesQuery.isPending || typesQuery.isError}
                                onChange={(event) => void changeDayType(event.target.value)}
                            >
                                {(typesQuery.data || [query.data.day_type]).map((type) => (
                                    <option key={type.id} value={type.id}>{type.name}</option>
                                ))}
                            </select>
                        </label>
                        <span className="today-open-pill">{t('openStatus')} · {t('sampleOnly')}</span>
                    </div>
                    {dayTypeError && <p role="alert">{t('dayTypeSaveError')}</p>}
                </div>
                <div className="today-heading-actions">
                    <button className="today-review-preview" type="button" disabled title={t('reviewNotAvailable')}>
                        {t('reviewAndConclude')} · {t('preview')}
                    </button>
                    <Link className="today-primary" to="/meals/new">+ {t('addMeal')}</Link>
                </div>
            </header>

            <div className="today-preview-notice" role="note">
                <strong>{t('previewNoticeTitle')}</strong>
                <span>{t('previewNoticeBody')}</span>
            </div>

            <section className="today-summary" aria-label={t('summary')}>
                <div className="today-card today-food-summary">
                    <div className="today-card-heading">
                        <h2>{t('food')}</h2>
                        <span>
                            {meals.length} {t('entries')}
                        </span>
                    </div>
                    <div className="today-big-number">
                        <strong>{Number(query.data.consumed.calories).toFixed(0)}</strong>
                        <span>/ {formatNumber(calorieTarget, 0)} kcal {t('budget')}</span>
                    </div>
                    <progress className="today-progress" max={calorieTarget} value={Math.min(Number(query.data.consumed.calories), calorieTarget)} aria-label={t('calorieBudget')} />
                    <small className="today-target-note">
                        {calorieTarget >= Number(query.data.consumed.calories)
                            ? `${formatNumber(calorieTarget - Number(query.data.consumed.calories), 0)} kcal ${t('remaining')}`
                            : `${formatNumber(Number(query.data.consumed.calories) - calorieTarget, 0)} kcal ${t('overBudget')}`}
                    </small>
                    <div className="today-divider" />
                    <div className="today-metric">
                        <span>{t('protein')}</span>
                        <strong>{Number(query.data.consumed.protein).toFixed(1)} / {formatNumber(proteinTarget, 0)} g · {t('minimum')}</strong>
                    </div>
                    <progress className="today-progress today-progress-protein" max={proteinTarget} value={Math.min(Number(query.data.consumed.protein), proteinTarget)} aria-label={t('proteinMinimum')} />
                    <small className="today-target-note">
                        {Number(query.data.consumed.protein) >= proteinTarget
                            ? t('minimumMet')
                            : `${formatNumber(proteinTarget - Number(query.data.consumed.protein), 1)} g ${t('toGo')}`}
                    </small>
                    <div className="today-food-macros">
                        <span>{t('carbohydrates')} <strong>{query.data.consumed.carbohydrates == null ? '—' : `${formatNumber(query.data.consumed.carbohydrates, 1)} g`}</strong></span>
                        <span>{t('fat')} <strong>{query.data.consumed.fat == null ? '—' : `${formatNumber(query.data.consumed.fat, 1)} g`}</strong></span>
                    </div>
                </div>
                <EnergyPreview consumedCalories={Number(query.data.consumed.calories)} />
                <ActivityPreview stepsTarget={query.data.day_type.targets.steps?.value} />
            </section>

            <div className="today-detail-grid">
            <section className="today-card today-meals" aria-labelledby="today-meals-heading">
                <div className="today-meals-heading">
                    <div>
                        <h2 id="today-meals-heading">{t('todaysFood')}</h2>
                        <p>{t('mealEditHint')}</p>
                    </div>
                    <span>
                        {meals.length} {t('entries')}
                    </span>
                </div>
                {meals.length === 0 ? (
                    <div className="today-empty">
                        <p>{t('noMeals')}</p>
                        <Link to="/meals/new">+ {t('addMeal')}</Link>
                    </div>
                ) : (
                    <div className="today-table-wrap">
                        <table className="today-table">
                            <thead>
                                <tr>
                                    <th>{t('food')}</th>
                                    <th>{t('calories')}</th>
                                    <th>{t('protein')}</th>
                                    <th>{t('carbohydrates')}</th>
                                    <th>{t('fat')}</th>
                                    <th>
                                        <span className="sr-only">{t('actions')}</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {meals.map((meal) => (
                                    <tr key={meal.id}>
                                        <td>
                                            <strong>
                                                {meal.source_name ||
                                                    meal.items
                                                        .map((item) => item.food_name)
                                                        .join(', ')}
                                            </strong>
                                            <small>
                                                {meal.items
                                                    .map(
                                                        (item) =>
                                                            `${formatNumber(item.quantity)} ${item.input_unit || item.unit} ${item.food_name}`,
                                                    )
                                                    .join(' · ')}
                                            </small>
                                        </td>
                                        <td>{Number(meal.calories).toFixed(0)} kcal</td>
                                        <td>{Number(meal.protein).toFixed(1)} g</td>
                                        <td>{meal.carbohydrates == null ? '—' : `${formatNumber(meal.carbohydrates, 1)} g`}</td>
                                        <td>{meal.fat == null ? '—' : `${formatNumber(meal.fat, 1)} g`}</td>
                                        <td>
                                            <div className="today-row-actions">
                                                <Link
                                                    className="today-icon-action"
                                                    to={`/meals/${meal.id}/edit?date=${query.data.local_date}`}
                                                    aria-label={t('editMeal')}
                                                    title={t('editMeal')}
                                                >
                                                    <MealActionIcon name="edit" />
                                                </Link>
                                                <DeleteMealButton
                                                    id={meal.id}
                                                    date={query.data.local_date}
                                                    iconOnly
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                {meals.length > 0 && (
                    <div className="today-mobile-meals">
                        {meals.map((meal) => (
                            <article className="today-mobile-meal" key={meal.id}>
                                <strong>
                                    {meal.source_name ||
                                        meal.items.map((item) => item.food_name).join(', ')}
                                </strong>
                                <p>
                                    {meal.items
                                        .map(
                                            (item) =>
                                                `${formatNumber(item.quantity)} ${item.input_unit || item.unit} ${item.food_name}`,
                                        )
                                        .join(' · ')}
                                </p>
                                <div className="today-mobile-totals">
                                    <span>
                                        <b>{formatNumber(meal.calories, 0)}</b> kcal
                                    </span>
                                    <span>
                                        <b>{formatNumber(meal.protein, 1)}</b> g {t('protein')}
                                    </span>
                                    <span>
                                        <b>{meal.carbohydrates == null ? '—' : `${formatNumber(meal.carbohydrates, 1)} g`}</b> {t('carbohydrates')}
                                    </span>
                                    <span>
                                        <b>{meal.fat == null ? '—' : `${formatNumber(meal.fat, 1)} g`}</b> {t('fat')}
                                    </span>
                                </div>
                                <div className="today-row-actions">
                                    <Link
                                        to={`/meals/${meal.id}/edit?date=${query.data.local_date}`}
                                    >
                                        {t('editMeal')}
                                    </Link>
                                    <DeleteMealButton id={meal.id} date={query.data.local_date} />
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </section>
            <TodaySidePanels />
            </div>
        </div>
    );
}

function Account() {
    const client = useQueryClient();
    const navigate = useNavigate();
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    async function download() {
        setBusy(true);
        setError('');
        try {
            const data = await request<Record<string, unknown>>('/account/export');
            const url = URL.createObjectURL(
                new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' }),
            );
            const link = document.createElement('a');
            link.href = url;
            link.download = 'hustle-daily-data.json';
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch (failure) {
            setError((failure as Error).message);
        } finally {
            setBusy(false);
        }
    }

    async function deleteAccount(event: React.FormEvent) {
        event.preventDefault();
        if (!window.confirm(t('confirmDeleteAccount'))) return;
        setBusy(true);
        setError('');
        try {
            await request<void>(
                '/account',
                { method: 'DELETE', body: JSON.stringify({ password }) },
                true,
            );
            client.clear();
            navigate('/');
        } catch (failure) {
            setError((failure as Error).message);
        } finally {
            setBusy(false);
        }
    }

    return (
        <section className="max-w-2xl space-y-8">
            <h2 className="text-3xl font-bold">{t('account')}</h2>
            <div className={card}>
                <h3 className="text-xl font-semibold">{t('exportData')}</h3>
                <p className="my-3 text-slate-600">{t('exportDescription')}</p>
                <button className={button} type="button" disabled={busy} onClick={download}>
                    {t('downloadData')}
                </button>
            </div>
            <form className={card + ' space-y-4'} onSubmit={deleteAccount}>
                <h3 className="text-xl font-semibold text-red-700">{t('deleteAccount')}</h3>
                <p className="text-slate-600">{t('deleteAccountDescription')}</p>
                <label className="block space-y-1">
                    {t('password')}
                    <input
                        className={field}
                        type="password"
                        autoComplete="current-password"
                        required
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                    />
                </label>
                <button
                    className="rounded-xl bg-red-700 px-5 py-3 font-semibold text-white disabled:opacity-50"
                    disabled={busy}
                >
                    {t('deleteAccount')}
                </button>
            </form>
            {error && (
                <p role="alert" className="text-red-700">
                    {error}
                </p>
            )}
        </section>
    );
}

type Row = { food_id: string; quantity: number; unit: string; portion_id?: string };
function MealForm() {
    const { id } = useParams();
    const [searchParams] = useSearchParams();
    const date = searchParams.get('date');
    const navigate = useNavigate();
    const client = useQueryClient();
    const entries = useQuery({
        queryKey: ['consumptions', date],
        queryFn: () => request<Meal[]>(`/consumptions?date=${encodeURIComponent(date!)}`),
        enabled: Boolean(id && date),
    });
    const categories = useQuery({
        queryKey: ['categories'],
        queryFn: () => request<Category[]>('/meal-categories'),
    });
    const recipes = useQuery({
        queryKey: ['recipes'],
        queryFn: () => request<Recipe[]>('/recipes'),
    });
    const meal = id ? entries.data?.find((entry) => entry.id === id) : undefined;
    const [rows, setRows] = useState<Row[] | null>(null);
    const [category, setCategory] = useState('');
    const [notes, setNotes] = useState<string | null>(null);
    const [mode, setMode] = useState<'foods' | 'recipe'>('foods');
    const [recipe, setRecipe] = useState('');
    const [servings, setServings] = useState(1);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const retry = useRef({ input: '', payload: '', key: crypto.randomUUID() });
    const selectedRows =
        rows ??
        (meal
            ? meal.items.map((item) => ({
                  food_id: item.food_id,
                  quantity: Number(item.quantity),
                  unit: item.input_unit || item.unit,
                  portion_id: item.food_portion_id || undefined,
              }))
            : [{ food_id: '', quantity: 100, unit: 'g' }]);
    const selectedCategory = category || meal?.meal_category_id || categories.data?.[0]?.id || '';
    if (id && !date) return <p role="alert">{t('missingMeal')}</p>;
    if ((id && entries.isPending) || categories.isPending || recipes.isPending)
        return <LoadingState />;
    if ((id && entries.isError) || categories.isError || recipes.isError)
        return (
            <ErrorState
                onRetry={() => {
                    void Promise.all([
                        ...(id && entries.isError ? [entries.refetch()] : []),
                        ...(categories.isError ? [categories.refetch()] : []),
                        ...(recipes.isError ? [recipes.refetch()] : []),
                    ]);
                }}
            />
        );
    if (id && !meal) return <p role="alert">{t('missingMeal')}</p>;
    const updateRow = (index: number, patch: Partial<Row>) =>
        setRows(
            selectedRows.map((row, position) => (position === index ? { ...row, ...patch } : row)),
        );

    async function save(event: React.FormEvent) {
        event.preventDefault();
        setBusy(true);
        setError('');
        try {
            const items = selectedRows.map((row) => ({
                ...row,
                ...(row.unit === 'portion' ? {} : { portion_id: undefined }),
            }));
            const input = JSON.stringify(
                id
                    ? {
                          meal_category_id: selectedCategory,
                          notes: notes ?? meal?.notes ?? null,
                          items,
                      }
                    : {
                          meal_category_id: selectedCategory,
                          notes,
                          ...(mode === 'recipe' ? { recipe_id: recipe, servings } : { items }),
                      },
            );
            if (retry.current.input !== input) {
                const payload = JSON.parse(input);
                if (!id) payload.eaten_at = new Date().toISOString().replace(/\.\d{3}Z$/, '+00:00');
                retry.current = {
                    input,
                    payload: JSON.stringify(payload),
                    key: crypto.randomUUID(),
                };
            }
            await request<Meal>(
                id ? `/consumptions/${id}` : '/consumptions',
                {
                    method: id ? 'PATCH' : 'POST',
                    body: retry.current.payload,
                    headers: { 'Idempotency-Key': retry.current.key },
                },
                true,
            );
            await client.invalidateQueries({ queryKey: ['today'] });
            if (date) await client.invalidateQueries({ queryKey: ['consumptions', date] });
            navigate(date ? `/history/${date}` : '/');
        } catch (failure) {
            setError((failure as Error).message);
        } finally {
            setBusy(false);
        }
    }
    return (
        <form className="space-y-5" onSubmit={save}>
            <h2 className="text-3xl font-bold">{id ? t('editMeal') : t('addMeal')}</h2>
            <p className="text-slate-600">{t('recent')}</p>
            <label className="block max-w-md space-y-1">
                {t('category')}
                <select
                    className={field}
                    value={selectedCategory}
                    onChange={(event) => setCategory(event.target.value)}
                >
                    {categories.data?.map((item) => (
                        <option value={item.id} key={item.id}>
                            {item.name}
                        </option>
                    ))}
                </select>
            </label>
            {!id && (
                <div className="flex gap-5">
                    <label>
                        <input
                            type="radio"
                            checked={mode === 'foods'}
                            onChange={() => setMode('foods')}
                        />{' '}
                        {t('individual')}
                    </label>
                    <label>
                        <input
                            type="radio"
                            checked={mode === 'recipe'}
                            onChange={() => setMode('recipe')}
                        />{' '}
                        {t('recipe')}
                    </label>
                </div>
            )}
            {mode === 'recipe' && !id ? (
                <div className={card}>
                    <label className="block space-y-1">
                        {t('recipe')}
                        <select
                            className={field}
                            required
                            value={recipe}
                            onChange={(event) => setRecipe(event.target.value)}
                        >
                            <option value="">—</option>
                            {recipes.data?.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="mt-3 block space-y-1">
                        {t('servings')}
                        <input
                            className={field}
                            type="number"
                            min="0.01"
                            step="any"
                            value={servings}
                            onChange={(event) => setServings(Number(event.target.value))}
                        />
                    </label>
                </div>
            ) : (
                <div className="space-y-3">
                    {selectedRows.map((row, index) => (
                        <IngredientFields
                            key={index}
                            ingredient={row}
                            onChange={(patch) => updateRow(index, patch)}
                            onRemove={() =>
                                setRows(selectedRows.filter((_, position) => position !== index))
                            }
                            removable={selectedRows.length > 1}
                        />
                    ))}
                    <button
                        type="button"
                        className="text-emerald-800 underline"
                        onClick={() =>
                            setRows([...selectedRows, { food_id: '', quantity: 100, unit: 'g' }])
                        }
                    >
                        {t('addIngredient')}
                    </button>
                </div>
            )}
            <label className="block max-w-2xl space-y-1">
                {t('notes')}
                <textarea
                    className={field}
                    value={notes ?? meal?.notes ?? ''}
                    onChange={(event) => setNotes(event.target.value)}
                />
            </label>
            {error && (
                <p role="alert" className="text-red-700">
                    {error}
                </p>
            )}
            <div className="flex flex-wrap items-center gap-3">
                <button className={button} disabled={busy}>
                    {t('save')}
                </button>
                <Link className="px-4 py-3 underline" to={date ? `/history/${date}` : '/'}>
                    {t('cancel')}
                </Link>
                {id && date && (
                    <DeleteMealButton
                        id={id}
                        date={date}
                        onDeleted={() => navigate(`/history/${date}`)}
                    />
                )}
            </div>
        </form>
    );
}

export default function App() {
    const client = useQueryClient();
    const navigate = useNavigate();
    const me = useQuery({
        queryKey: ['me'],
        queryFn: () => request<{ id: string; name: string }>('/me'),
        retry: false,
    });
    if (me.isPending) return <p className="p-6">{t('loading')}</p>;
    if (me.isError && !(me.error instanceof ApiError && me.error.status === 401))
        return (
            <main className="mx-auto max-w-xl space-y-4 p-6" role="alert">
                <p>{t('unavailable')}</p>
                <button className={button} onClick={() => me.refetch()}>
                    {t('retry')}
                </button>
            </main>
        );
    if (me.isError)
        return (
            <Login
                onSuccess={() => {
                    client.invalidateQueries();
                    navigate('/');
                }}
            />
        );
    return (
        <div className="app-shell">
            <ScrollOnNavigation />
            <aside className="app-sidebar">
                <Link to="/" className="app-brand">
                    <img src="/branding/lockup-colour.png" alt={t('title')} />
                </Link>
                <Link to="/meals/new" className="sidebar-add">
                    + {t('addMeal')}
                </Link>
                <nav aria-label={t('mainNavigation')} className="sidebar-nav">
                    <NavLink to="/" end>
                        <SidebarIcon name="today" />
                        {t('today')}
                    </NavLink>
                    <NavLink to="/history"><SidebarIcon name="history" />{t('history')}</NavLink>
                    <NavLink to="/foods"><SidebarIcon name="foods" />{t('foods')}</NavLink>
                    <NavLink to="/recipes"><SidebarIcon name="recipes" />{t('recipes')}</NavLink>
                </nav>
                <div className="sidebar-sync-preview" role="note">
                    <strong><span className="sidebar-sync-dot" />Apple Health {t('viaIphone')}</strong>
                    <span>{t('sampleSyncTime')} · {t('sampleOnly')}</span>
                </div>
                <nav aria-label={t('account')} className="sidebar-bottom">
                    <NavLink to="/account" className="sidebar-bottom-item">
                        <SidebarIcon name="account" />
                        {me.data.name}
                    </NavLink>
                    <button
                        className="sidebar-bottom-item"
                        onClick={async () => {
                            await logout();
                            client.clear();
                            navigate('/');
                        }}
                    >
                        <SidebarIcon name="signOut" />
                        {t('signOut')}
                    </button>
                    <a className="sidebar-api-link" href="/api/docs">
                        {t('apiDocs')}
                    </a>
                </nav>
            </aside>
            <main className="app-content">
                <Routes>
                    <Route path="/" element={<Dashboard />} />
                    <Route path="/history" element={<History />} />
                    <Route path="/history/:date" element={<History />} />
                    <Route path="/meals/new" element={<MealForm />} />
                    <Route path="/meals/:id/edit" element={<MealForm />} />
                    <Route path="/foods" element={<FoodCatalogue />} />
                    <Route path="/foods/new" element={<FoodEditor />} />
                    <Route path="/foods/:id/edit" element={<FoodEditor />} />
                    <Route path="/recipes" element={<RecipeCatalogue />} />
                    <Route path="/recipes/new" element={<RecipeForm />} />
                    <Route path="/recipes/:id/edit" element={<RecipeForm />} />
                    <Route path="/account" element={<Account />} />
                </Routes>
            </main>
        </div>
    );
}
