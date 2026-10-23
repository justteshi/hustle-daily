import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { request, type Food } from './api';
import { t } from './i18n';

export default function FoodPicker({
    value,
    onChange,
}: {
    value: string;
    onChange: (food: Food) => void;
}) {
    const [search, setSearch] = useState('');
    const [debounced, setDebounced] = useState('');
    useEffect(() => {
        const timer = window.setTimeout(() => setDebounced(search.trim()), 250);
        return () => window.clearTimeout(timer);
    }, [search]);
    const results = useQuery({
        queryKey: ['food-search', debounced],
        queryFn: () => request<Food[]>(`/foods?query=${encodeURIComponent(debounced)}`),
    });
    const selected = useQuery({
        queryKey: ['food', value],
        queryFn: () => request<Food>(`/foods/${value}`),
        enabled: Boolean(value),
    });
    const choices = [...(results.data ?? [])];
    if (selected.data && !choices.some((food) => food.id === value)) choices.unshift(selected.data);

    return (
        <div className="space-y-1">
            <input
                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                aria-label={t('searchFoods')}
                placeholder={t('searchFoods')}
                value={search}
                onChange={(event) => setSearch(event.target.value)}
            />
            <select
                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                aria-label={t('food')}
                required
                value={value}
                onChange={(event) => {
                    const food = choices.find((choice) => choice.id === event.target.value);
                    if (food) onChange(food);
                }}
            >
                <option value="">—</option>
                {choices.map((food) => (
                    <option key={food.id} value={food.id}>
                        {food.brand} {food.name}
                    </option>
                ))}
            </select>
            {results.isFetching && <span className="text-sm text-slate-500">{t('loading')}</span>}
            {results.isError && (
                <button
                    type="button"
                    className="text-sm text-red-700 underline"
                    onClick={() => results.refetch()}
                >
                    {t('retry')}
                </button>
            )}
        </div>
    );
}
