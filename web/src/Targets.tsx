import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { request, type DayType } from './api';
import { t } from './i18n';
import { ErrorState, LoadingState } from './UiStates';

type Draft = {
    calories: string;
    protein: string;
    steps: string;
    hasSteps: boolean;
};

function draftFrom(type: DayType): Draft {
    return {
        calories: String(type.targets.consumed_calories?.value ?? ''),
        protein: String(type.targets.protein_g?.value ?? ''),
        steps: String(type.targets.steps?.value ?? (type.key === 'rest' ? 6000 : 10000)),
        hasSteps: Boolean(type.targets.steps),
    };
}

function TargetField({
    label,
    rule,
    value,
    unit,
    onChange,
    hint,
    max,
}: {
    label: string;
    rule: string;
    value: string;
    unit: string;
    onChange: (value: string) => void;
    hint: string;
    max: number;
}) {
    return (
        <section className="targets-card">
            <div className="targets-card-heading">
                <h2>{label}</h2>
                <span className={rule === t('budgetRule') ? 'targets-rule targets-rule-budget' : 'targets-rule'}>{rule}</span>
            </div>
            <label className="targets-field">
                <span>{rule === t('budgetRule') ? t('stayAtOrUnder') : t('reachAtLeast')}</span>
                <span className="targets-input-wrap">
                    <input
                        type="number"
                        min="1"
                        max={max}
                        step={unit === 'steps' ? '1' : '0.001'}
                        value={value}
                        onChange={(event) => onChange(event.target.value)}
                        required
                    />
                    <span>{unit}</span>
                </span>
            </label>
            <small className="targets-example-label">{t('exampleProgress')}</small>
            <div className="targets-example-track"><span /></div>
            <p>{hint}</p>
        </section>
    );
}

export default function Targets() {
    const client = useQueryClient();
    const query = useQuery({
        queryKey: ['day-types'],
        queryFn: () => request<DayType[]>('/day-types'),
    });
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [drafts, setDrafts] = useState<Record<string, Draft>>({});
    const [error, setError] = useState('');
    const [saved, setSaved] = useState(false);
    const [busy, setBusy] = useState(false);

    if (query.isPending) return <LoadingState />;
    if (query.isError) return <ErrorState onRetry={() => void query.refetch()} />;

    const selected = query.data.find((type) => type.id === selectedId) ?? query.data[0];
    if (!selected) return <ErrorState onRetry={() => void query.refetch()} />;
    const draft = drafts[selected.id] ?? draftFrom(selected);

    function change(patch: Partial<Draft>) {
        setDrafts((previous) => ({
            ...previous,
            [selected.id]: { ...(previous[selected.id] ?? draftFrom(selected)), ...patch },
        }));
        setSaved(false);
        setError('');
    }

    async function save(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const calories = Number(draft.calories);
        const protein = Number(draft.protein);
        const steps = Number(draft.steps);
        if (
            !Number.isFinite(calories) || calories <= 0 || calories > 100000 ||
            !Number.isFinite(protein) || protein <= 0 || protein > 10000 ||
            (draft.hasSteps && (!Number.isInteger(steps) || steps <= 0 || steps > 1000000))
        ) {
            setError(t('invalidTargets'));
            return;
        }

        setBusy(true);
        setError('');
        setSaved(false);
        try {
            const updated = await request<DayType>(`/day-types/${selected.id}`, {
                method: 'PUT',
                body: JSON.stringify({
                    targets: {
                        consumed_calories: calories,
                        protein_g: protein,
                        steps: draft.hasSteps ? steps : null,
                    },
                }),
            }, true);
            client.setQueryData<DayType[]>(['day-types'], (types) =>
                types?.map((type) => type.id === updated.id ? updated : type),
            );
            await client.invalidateQueries({ queryKey: ['today'] });
            setDrafts((previous) => {
                const next = { ...previous };
                delete next[selected.id];
                return next;
            });
            setSaved(true);
        } catch (failure) {
            setError((failure as Error).message);
        } finally {
            setBusy(false);
        }
    }

    return (
        <form className="targets-page" onSubmit={(event) => void save(event)}>
            <header>
                <h1>{t('targetsAndDayTypes')}</h1>
                <p>{t('targetsEffectiveNote')}</p>
            </header>

            <div className="targets-tabs" role="tablist" aria-label={t('dayTypes')}>
                {query.data.map((type) => (
                    <button
                        key={type.id}
                        type="button"
                        role="tab"
                        aria-selected={selected.id === type.id}
                        className={selected.id === type.id ? 'active' : ''}
                        onClick={() => { setSelectedId(type.id); setError(''); setSaved(false); }}
                    >
                        {type.name}
                    </button>
                ))}
                <button type="button" className="targets-preview-button" disabled title={t('customDayTypesLater')}>
                    + {t('addDayType')} · {t('preview')}
                </button>
            </div>

            <div className="targets-grid">
                <TargetField
                    label={t('consumedCalories')}
                    rule={t('budgetRule')}
                    value={draft.calories}
                    unit="kcal"
                    onChange={(value) => change({ calories: value })}
                    hint={t('budgetHint')}
                    max={100000}
                />
                <TargetField
                    label={t('protein')}
                    rule={t('minimumRule')}
                    value={draft.protein}
                    unit="g"
                    onChange={(value) => change({ protein: value })}
                    hint={t('proteinHint')}
                    max={10000}
                />
                <section className="targets-card">
                    <div className="targets-card-heading">
                        <h2>{t('steps')}</h2>
                        <span className="targets-rule targets-rule-steps">{t('minimumRule')}</span>
                    </div>
                    <label className="targets-steps-toggle">
                        <input type="checkbox" checked={draft.hasSteps} onChange={(event) => change({ hasSteps: event.target.checked })} />
                        {t('setStepsTarget')}
                    </label>
                    {draft.hasSteps ? (
                        <div className="targets-field">
                            <label htmlFor="steps-target">{t('reachAtLeast')}</label>
                            <span className="targets-input-wrap">
                                <input
                                    id="steps-target"
                                    type="number"
                                    min="1"
                                    max="1000000"
                                    step="1"
                                    value={draft.steps}
                                    onChange={(event) => change({ steps: event.target.value })}
                                    required
                                />
                                <span>{t('steps')}</span>
                            </span>
                        </div>
                    ) : <p>{t('noStepsTargetHint')}</p>}
                </section>
            </div>

            <section className="targets-card targets-future-card">
                <div className="targets-card-heading">
                    <h2>{t('dayTypeSuggestions')}</h2>
                    <span className="today-sample-badge">{t('preview')}</span>
                </div>
                <p>{t('workoutSuggestionPreview')}</p>
                <p>{t('defaultDayTypePreview')}</p>
            </section>

            <div className="targets-actions">
                {error && <span role="alert" className="targets-error">{error}</span>}
                {saved && <span role="status" className="targets-saved">✓ {t('targetsSaved')}</span>}
                <button type="submit" className="hd-primary-button" disabled={busy}>
                    {busy ? t('loading') : `${t('save')} ${selected.name}`}
                </button>
            </div>
        </form>
    );
}
