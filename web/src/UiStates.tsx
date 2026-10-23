import { t } from './i18n';

export function LoadingState() {
    return (
        <div className="ui-state ui-state-loading" role="status" aria-busy="true">
            <span className="ui-skeleton ui-skeleton-title" aria-hidden="true" />
            <span className="ui-skeleton ui-skeleton-metric" aria-hidden="true" />
            <span className="ui-skeleton ui-skeleton-line" aria-hidden="true" />
            <span>{t('loading')}</span>
        </div>
    );
}

export function ErrorState({ onRetry }: { onRetry: () => void }) {
    return (
        <div className="ui-state ui-state-error" role="alert">
            <strong>{t('connectionErrorTitle')}</strong>
            <p>{t('unavailable')}</p>
            <button type="button" onClick={onRetry}>
                {t('retry')}
            </button>
        </div>
    );
}
