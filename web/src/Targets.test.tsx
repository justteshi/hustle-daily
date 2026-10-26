// @vitest-environment jsdom
import { afterEach, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import Targets from './Targets';

const trainingId = '11111111-1111-4111-8111-111111111111';
const restId = '22222222-2222-4222-8222-222222222222';

const dayTypes = [
    {
        id: trainingId,
        key: 'training',
        name: 'Training Day',
        color: '#2F6B4F',
        targets: {
            consumed_calories: { value: 2000, unit: 'kcal', comparison_rule: 'budget' },
            protein_g: { value: 160, unit: 'g', comparison_rule: 'minimum' },
            steps: { value: 10000, unit: 'steps', comparison_rule: 'minimum' },
        },
    },
    {
        id: restId,
        key: 'rest',
        name: 'Rest Day',
        color: '#2D5C91',
        targets: {
            consumed_calories: { value: 3000, unit: 'kcal', comparison_rule: 'budget' },
            protein_g: { value: 120, unit: 'g', comparison_rule: 'minimum' },
        },
    },
];

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

it('saves the selected day type targets and keeps the other day type separate', async () => {
    const writes: unknown[] = [];
    vi.stubGlobal('crypto', { randomUUID: () => 'idempotency-key' });
    vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL, options?: RequestInit) => {
        const path = String(input);
        if (path.endsWith('/day-types')) return new Response(JSON.stringify({ data: dayTypes }));
        if (path === '/session/csrf') return new Response(JSON.stringify({ data: { csrf_token: 'csrf' } }));
        if (path.endsWith(`/day-types/${trainingId}`) && options?.method === 'PUT') {
            writes.push(JSON.parse(String(options.body)));
            return new Response(JSON.stringify({
                data: {
                    ...dayTypes[0],
                    targets: {
                        ...dayTypes[0].targets,
                        consumed_calories: { value: 2200, unit: 'kcal', comparison_rule: 'budget' },
                    },
                },
            }));
        }
        throw new Error(`Unexpected request: ${path}`);
    }));
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    render(<QueryClientProvider client={client}><Targets /></QueryClientProvider>);

    expect(await screen.findByRole('tab', { name: 'Training Day' })).toBeTruthy();
    fireEvent.change(screen.getAllByRole('spinbutton')[0], { target: { value: '2200' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save Training Day' }));
    await waitFor(() => expect(writes).toHaveLength(1));
    expect(writes[0]).toEqual({ targets: { consumed_calories: 2200, protein_g: 160, steps: 10000 } });
    expect(await screen.findByRole('status')).toHaveProperty('textContent', '✓ Saved. These targets apply from today.');

    fireEvent.click(screen.getByRole('tab', { name: 'Rest Day' }));
    expect((screen.getAllByRole('spinbutton')[0] as HTMLInputElement).value).toBe('3000');
    expect(screen.getByRole('checkbox', { name: 'Set a steps target for this day type' })).toHaveProperty('checked', false);
});
