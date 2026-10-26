// @vitest-environment jsdom
import { afterEach, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import App from './App';

const rest = {
    id: '11111111-1111-4111-8111-111111111111',
    key: 'rest',
    name: 'Rest Day',
    color: '#2D5C91',
    targets: {
        consumed_calories: { value: 3000, unit: 'kcal', comparison_rule: 'budget' },
        protein_g: { value: 120, unit: 'g', comparison_rule: 'minimum' },
    },
};
const training = {
    id: '22222222-2222-4222-8222-222222222222',
    key: 'training',
    name: 'Training Day',
    color: '#2F6B4F',
    targets: {
        consumed_calories: { value: 2000, unit: 'kcal', comparison_rule: 'budget' },
        protein_g: { value: 160, unit: 'g', comparison_rule: 'minimum' },
    },
};

function result(data: unknown): Response {
    return new Response(JSON.stringify({ data }));
}

function renderToday() {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    render(
        <QueryClientProvider client={client}>
            <MemoryRouter initialEntries={['/']}>
                <App />
            </MemoryRouter>
        </QueryClientProvider>,
    );
}

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

it('keeps the new day type selected while its save is pending', async () => {
    let completeSave: (response: Response) => void = () => {};
    const pendingSave = new Promise<Response>((resolve) => { completeSave = resolve; });
    let persisted = false;
    vi.stubGlobal('crypto', { randomUUID: () => 'idempotency-key' });
    vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL, options?: RequestInit) => {
        const path = String(input);
        if (path.endsWith('/me')) return result({ id: 'user', name: 'Teodor Hr' });
        if (path.endsWith('/day-types')) return result([training, rest]);
        if (path.endsWith('/dashboard/today')) return result({
            local_date: '2026-10-26',
            consumed: { calories: 100, protein: 10 },
            consumptions: [],
            day_type: persisted ? training : rest,
        });
        if (path === '/session/csrf') return result({ csrf_token: 'csrf' });
        if (path.endsWith('/days/2026-10-26') && options?.method === 'PATCH') return pendingSave;
        throw new Error(`Unexpected request: ${path}`);
    }));

    renderToday();
    const selector = await screen.findByRole('combobox', { name: 'Day type' }) as HTMLSelectElement;
    await waitFor(() => expect(selector.disabled).toBe(false));
    fireEvent.change(selector, { target: { value: training.id } });

    expect(selector.value).toBe(training.id);
    expect(screen.getByRole('heading', { name: 'Today' })).toBeTruthy();
    await waitFor(() => expect(screen.getByRole('progressbar', { name: 'Calorie budget progress' }).getAttribute('max')).toBe('2000'));

    persisted = true;
    completeSave(result({ day_type: training }));
    await waitFor(() => expect(selector.disabled).toBe(false));
    expect(selector.value).toBe(training.id);
});

it('restores the previous day type when saving fails', async () => {
    vi.stubGlobal('crypto', { randomUUID: () => 'idempotency-key' });
    vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL, options?: RequestInit) => {
        const path = String(input);
        if (path.endsWith('/me')) return result({ id: 'user', name: 'Teodor Hr' });
        if (path.endsWith('/day-types')) return result([training, rest]);
        if (path.endsWith('/dashboard/today')) return result({
            local_date: '2026-10-26',
            consumed: { calories: 100, protein: 10 },
            consumptions: [],
            day_type: rest,
        });
        if (path === '/session/csrf') return result({ csrf_token: 'csrf' });
        if (path.endsWith('/days/2026-10-26') && options?.method === 'PATCH') {
            throw new TypeError('Network unavailable');
        }
        throw new Error(`Unexpected request: ${path}`);
    }));

    renderToday();
    const selector = await screen.findByRole('combobox', { name: 'Day type' }) as HTMLSelectElement;
    await waitFor(() => expect(selector.disabled).toBe(false));
    fireEvent.change(selector, { target: { value: training.id } });

    await waitFor(() => expect(selector.value).toBe(rest.id));
    expect(screen.getByRole('alert').textContent).toContain('Day type could not be saved');
    expect(screen.getByRole('progressbar', { name: 'Calorie budget progress' }).getAttribute('max')).toBe('3000');
});
