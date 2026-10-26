import { t } from './i18n';
import { formatNumber } from './format';

function SampleBadge() {
    return <span className="today-sample-badge">{t('sampleData')}</span>;
}

export function EnergyPreview({ consumedCalories }: { consumedCalories: number }) {
    return (
        <section className="today-card today-sample-card" aria-labelledby="today-energy-heading">
            <div className="today-card-heading">
                <h2 id="today-energy-heading">{t('energySoFar')}</h2>
                <SampleBadge />
            </div>
            <p className="today-sample-note">{t('healthSampleExplanation')}</p>
            <dl className="today-energy-list">
                <div><dt>{t('restingEnergy')}</dt><dd>1,960 kcal</dd></div>
                <div><dt>+ {t('activeEnergy')}</dt><dd>892 kcal</dd></div>
                <div className="today-energy-total"><dt>= {t('totalBurned')}</dt><dd>2,852 kcal</dd></div>
                <div><dt>− {t('consumed')}</dt><dd>{formatNumber(consumedCalories, 0)} kcal <span className="today-live-inline">{t('liveFood')}</span></dd></div>
            </dl>
            <div className="today-sample-balance">
                <span>{t('energyBalance')}</span>
                <strong>{t('sampleOnly')}</strong>
            </div>
        </section>
    );
}

export function ActivityPreview({ stepsTarget }: { stepsTarget?: number }) {
    return (
        <section className="today-card today-sample-card" aria-labelledby="today-activity-heading">
            <div className="today-card-heading">
                <h2 id="today-activity-heading">{t('activityAndBody')}</h2>
                <SampleBadge />
            </div>
            <div className="today-sample-steps">
                <div className="today-metric">
                    <span>{t('steps')} · {stepsTarget ? t('minimum') : t('noTarget')}</span>
                    <strong>8,420{stepsTarget ? ` / ${formatNumber(stepsTarget, 0)}` : ''}</strong>
                </div>
                <div className="today-sample-track"><span style={{ width: `${stepsTarget ? Math.min(100, 8420 / stepsTarget * 100) : 0}%` }} /></div>
                <small>{t('sampleStepsNote')}</small>
            </div>
            <div className="today-body-grid">
                <div><span>{t('sleep')}</span><strong>7 h 14 min</strong><small>{t('sampleSleepTime')}</small></div>
                <div><span>{t('weight')}</span><strong>104.0 kg</strong><small>{t('sampleWeightTime')}</small></div>
            </div>
        </section>
    );
}

export function TodaySidePanels() {
    return (
        <aside className="today-side-panels" aria-label={t('todayDetails')}>
            <section className="today-card today-sample-card" aria-labelledby="today-workouts-heading">
                <div className="today-card-heading">
                    <h2 id="today-workouts-heading">{t('workouts')}</h2>
                    <SampleBadge />
                </div>
                <div className="today-workout-example">
                    <div><strong>{t('sampleWorkout')}</strong><span>07:18–08:02</span></div>
                    <p><strong>44</strong> min · <strong>438</strong> kcal {t('active')}</p>
                    <div className="today-sample-track today-workout-track"><span /></div>
                    <small>{t('workoutNotDoubleCounted')}</small>
                </div>
            </section>
            <section className="today-card today-sample-card" aria-labelledby="today-sources-heading">
                <div className="today-card-heading">
                    <h2 id="today-sources-heading">{t('dataSources')}</h2>
                    <SampleBadge />
                </div>
                <p><strong>Apple Health</strong>, {t('viaIphone')}<br /><span>{t('sampleSyncTime')}</span></p>
                <p>{t('browserCannotReadHealth')}</p>
                <div className="today-source-food"><strong>{t('food')}</strong>, {t('enteredInApp')}<br /><span>{t('foodOnlyForNow')}</span></div>
            </section>
            <section className="today-card today-sample-card" aria-labelledby="today-notes-heading">
                <div className="today-card-heading">
                    <h2 id="today-notes-heading">{t('notes')}</h2>
                    <SampleBadge />
                </div>
                <textarea aria-label={t('notes')} value={t('sampleNoteText')} readOnly />
                <small>{t('notesNotSaved')}</small>
            </section>
        </aside>
    );
}
