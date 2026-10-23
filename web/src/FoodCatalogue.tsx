import { useEffect, useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { z } from 'zod';
import { request, type Food } from './api';
import { t } from './i18n';
import { ErrorState, LoadingState } from './UiStates';

const field = 'hd-field';
const button = 'hd-primary-button';
type Draft = {
    name: string;
    brand: string;
    barcode: string;
    basis_amount: string;
    basis_unit: 'g' | 'ml' | 'serving';
    calories: string;
    protein: string;
    carbohydrates: string;
    fat: string;
    fibre: string;
    sugar: string;
    salt: string;
    portions: { id?: string; label: string; base_amount: string }[];
};
const emptyDraft: Draft = {
    name: '',
    brand: '',
    barcode: '',
    basis_amount: '100',
    basis_unit: 'g',
    calories: '0',
    protein: '0',
    carbohydrates: '0',
    fat: '0',
    fibre: '',
    sugar: '',
    salt: '',
    portions: [],
};
const schema = z.object({
    name: z.string().trim().min(1),
    brand: z.string().trim().min(1),
    barcode: z.string(),
    basis_amount: z.coerce.number().positive(),
    basis_unit: z.enum(['g', 'ml', 'serving']),
    calories: z.coerce.number().nonnegative(),
    protein: z.coerce.number().nonnegative(),
    carbohydrates: z.coerce.number().nonnegative(),
    fat: z.coerce.number().nonnegative(),
    fibre: z.string(),
    sugar: z.string(),
    salt: z.string(),
    portions: z.array(
        z.object({
            id: z.string().optional(),
            label: z.string().trim().min(1),
            base_amount: z.coerce.number().positive(),
        }),
    ),
});

export function FoodCatalogue() {
    const [search, setSearch] = useState('');
    const [debounced, setDebounced] = useState('');
    useEffect(() => {
        const timer = window.setTimeout(() => setDebounced(search.trim()), 250);
        return () => window.clearTimeout(timer);
    }, [search]);
    const foods = useQuery({
        queryKey: ['food-search', debounced],
        queryFn: () => request<Food[]>(`/foods?query=${encodeURIComponent(debounced)}`),
    });
    return (
        <section className="space-y-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-3xl font-bold">{t('foods')}</h2>
                <Link className={button} to="/foods/new">
                    {t('createFood')}
                </Link>
            </div>
            <input
                className={`${field} max-w-xl`}
                aria-label={t('searchFoods')}
                placeholder={t('searchFoods')}
                value={search}
                onChange={(event) => setSearch(event.target.value)}
            />
            {foods.isPending && <LoadingState />}
            {foods.isError && (
                <ErrorState
                    onRetry={() => {
                        void foods.refetch();
                    }}
                />
            )}
            {foods.data?.length === 0 && (
                <div className="ui-empty">
                    <strong>{t('noFoodsMatch')}</strong>
                    <p>{t('noFoodsHelp')}</p>
                    <Link to="/foods/new">{t('createFood')}</Link>
                </div>
            )}
            <div className="grid gap-3 sm:grid-cols-2">
                {foods.data?.map((food) => (
                    <article
                        key={food.id}
                        className="rounded-2xl border border-slate-200 bg-white p-5"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <h3 className="font-semibold">
                                    {food.brand} {food.name}
                                </h3>
                                <p className="text-sm text-slate-600">
                                    {food.nutrition.calories} kcal / {food.nutrition.basis_amount}{' '}
                                    {food.nutrition.basis_unit}
                                </p>
                            </div>
                            <Link
                                className="text-emerald-800 underline"
                                to={`/foods/${food.id}/edit`}
                            >
                                {t('edit')}
                            </Link>
                        </div>
                    </article>
                ))}
            </div>
        </section>
    );
}

export function FoodEditor() {
    const { id } = useParams();
    const client = useQueryClient();
    const navigate = useNavigate();
    const food = useQuery({
        queryKey: ['food', id],
        queryFn: () => request<Food>(`/foods/${id}`),
        enabled: Boolean(id),
    });
    const [draft, setDraft] = useState<Draft>(emptyDraft);
    const [loadedId, setLoadedId] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const retry = useRef({ payload: '', key: crypto.randomUUID() });
    if (id && food.data && loadedId !== id) {
        const n = food.data.nutrition;
        setDraft({
            name: food.data.name,
            brand: food.data.brand,
            barcode: food.data.barcode ?? '',
            basis_amount: String(n.basis_amount),
            basis_unit: n.basis_unit,
            calories: String(n.calories),
            protein: String(n.protein),
            carbohydrates: String(n.carbohydrates),
            fat: String(n.fat),
            fibre: n.fibre == null ? '' : String(n.fibre),
            sugar: n.sugar == null ? '' : String(n.sugar),
            salt: n.salt == null ? '' : String(n.salt),
            portions: food.data.portions.map((portion) => ({
                id: portion.id,
                label: portion.label,
                base_amount: String(portion.base_amount),
            })),
        });
        setLoadedId(id);
    }
    if (id && food.isPending) return <LoadingState />;
    if (id && food.isError)
        return (
            <ErrorState
                onRetry={() => {
                    void food.refetch();
                }}
            />
        );
    const update = (patch: Partial<Draft>) => setDraft((current) => ({ ...current, ...patch }));
    async function save(event: React.FormEvent) {
        event.preventDefault();
        setError('');
        setBusy(true);
        try {
            const parsed = schema.parse(draft);
            const body = JSON.stringify({
                name: parsed.name,
                brand: parsed.brand,
                barcode: parsed.barcode || null,
                nutrition: {
                    basis_amount: parsed.basis_amount,
                    basis_unit: parsed.basis_unit,
                    calories: parsed.calories,
                    protein: parsed.protein,
                    carbohydrates: parsed.carbohydrates,
                    fat: parsed.fat,
                    fibre: parsed.fibre === '' ? null : Number(parsed.fibre),
                    sugar: parsed.sugar === '' ? null : Number(parsed.sugar),
                    salt: parsed.salt === '' ? null : Number(parsed.salt),
                },
                portions: parsed.portions.map((portion) => ({
                    ...portion,
                    base_unit: parsed.basis_unit,
                })),
            });
            if (retry.current.payload !== body)
                retry.current = { payload: body, key: crypto.randomUUID() };
            await request<Food>(
                id ? `/foods/${id}` : '/foods',
                {
                    method: id ? 'PATCH' : 'POST',
                    headers: { 'Idempotency-Key': retry.current.key },
                    body,
                },
                true,
            );
            await client.invalidateQueries({ queryKey: ['food-search'] });
            if (id) await client.invalidateQueries({ queryKey: ['food', id] });
            navigate('/foods');
        } catch (failure) {
            setError(
                failure instanceof z.ZodError
                    ? failure.issues[0]?.message || t('invalidForm')
                    : (failure as Error).message,
            );
        } finally {
            setBusy(false);
        }
    }
    return (
        <form onSubmit={save} className="space-y-5">
            <h2 className="text-3xl font-bold">{id ? t('editFood') : t('createFood')}</h2>
            <div className="grid gap-4 sm:grid-cols-2">
                {(['name', 'brand', 'barcode'] as const).map((key) => (
                    <label key={key}>
                        {t(key)}
                        <input
                            className={field}
                            value={draft[key]}
                            onChange={(event) => update({ [key]: event.target.value })}
                            required={key !== 'barcode'}
                        />
                    </label>
                ))}
            </div>
            <div className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2 lg:grid-cols-4">
                <label>
                    {t('basisAmount')}
                    <input
                        className={field}
                        type="number"
                        min="0.001"
                        step="any"
                        value={draft.basis_amount}
                        onChange={(event) => update({ basis_amount: event.target.value })}
                    />
                </label>
                <label>
                    {t('basisUnit')}
                    <select
                        className={field}
                        value={draft.basis_unit}
                        onChange={(event) =>
                            update({ basis_unit: event.target.value as Draft['basis_unit'] })
                        }
                    >
                        <option value="g">g</option>
                        <option value="ml">ml</option>
                        <option value="serving">serving</option>
                    </select>
                </label>
                {(
                    [
                        'calories',
                        'protein',
                        'carbohydrates',
                        'fat',
                        'fibre',
                        'sugar',
                        'salt',
                    ] as const
                ).map((key) => (
                    <label key={key}>
                        {t(key)}
                        <input
                            className={field}
                            type="number"
                            min="0"
                            step="any"
                            value={draft[key]}
                            onChange={(event) => update({ [key]: event.target.value })}
                        />
                    </label>
                ))}
            </div>
            <section className="space-y-3">
                <h3 className="text-xl font-semibold">{t('portions')}</h3>
                {draft.portions.map((portion, index) => (
                    <div
                        key={portion.id ?? index}
                        className="flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-white p-4"
                    >
                        <label className="min-w-48 flex-1">
                            {t('portionLabel')}
                            <input
                                className={field}
                                required
                                value={portion.label}
                                onChange={(event) =>
                                    update({
                                        portions: draft.portions.map((row, i) =>
                                            i === index
                                                ? { ...row, label: event.target.value }
                                                : row,
                                        ),
                                    })
                                }
                            />
                        </label>
                        <label className="w-40">
                            {t('baseAmount')} ({draft.basis_unit})
                            <input
                                className={field}
                                type="number"
                                min="0.001"
                                step="any"
                                required
                                value={portion.base_amount}
                                onChange={(event) =>
                                    update({
                                        portions: draft.portions.map((row, i) =>
                                            i === index
                                                ? { ...row, base_amount: event.target.value }
                                                : row,
                                        ),
                                    })
                                }
                            />
                        </label>
                        <button
                            type="button"
                            className="self-end text-red-700"
                            onClick={() =>
                                update({ portions: draft.portions.filter((_, i) => i !== index) })
                            }
                        >
                            {t('remove')}
                        </button>
                    </div>
                ))}
                <button
                    type="button"
                    className="text-emerald-800 underline"
                    onClick={() =>
                        update({ portions: [...draft.portions, { label: '', base_amount: '1' }] })
                    }
                >
                    {t('addPortion')}
                </button>
            </section>
            {error && (
                <p role="alert" className="text-red-700">
                    {error}
                </p>
            )}
            <div className="flex gap-3">
                <button className={button} disabled={busy}>
                    {t('save')}
                </button>
                <Link className="px-4 py-3 underline" to="/foods">
                    {t('cancel')}
                </Link>
            </div>
        </form>
    );
}
