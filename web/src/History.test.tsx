// @vitest-environment jsdom
import { afterEach, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import History from './History';
import PastDay from './PastDay';
import { formatLocalDate } from './i18n';

const restId = '22222222-2222-4222-8222-222222222222';
const trainingId = '11111111-1111-4111-8111-111111111111';
const dayTypes = [
    { id: restId, name: 'Rest Day', key: 'rest', color: '#2D5C91', targets: {} },
    { id: trainingId, name: 'Training Day', key: 'training', color: '#2F6B4F', targets: {} },
];

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

it('formats a local date without changing its day', () => {
    expect(formatLocalDate('2026-06-25')).toBe('25 June 2026');
});

it('opens a separate past-day page for review and correction', async () => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-26T12:00:00Z'));
    const writes: unknown[] = [];
    let selectedType = dayTypes[0];
    vi.stubGlobal('crypto', { randomUUID: () => 'history-correction' });
    vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL, options?: RequestInit) => {
        const path = String(input);
        if (path.endsWith('/dashboard/today')) {
            return new Response(JSON.stringify({ data: { local_date: '2026-10-25' } }));
        }
        if (path.endsWith('/consumptions/dates') || path.endsWith('/consumptions/dates?month=2026-10')) {
            return new Response(JSON.stringify({ data: [{
                local_date: '2026-10-25', meal_count: 1, calories: 120, protein: 20,
            }] }));
        }
        if (path.endsWith('/consumptions/dates?month=2026-09')) {
            return new Response(JSON.stringify({ data: [] }));
        }
        if (path.endsWith('/days/2026-10-25') && options?.method === 'PATCH') {
            writes.push(JSON.parse(String(options.body)));
            selectedType = dayTypes[1];
            return new Response(JSON.stringify({ data: { day_type: selectedType } }));
        }
        if (path.endsWith('/days/2026-10-25')) {
            return new Response(JSON.stringify({ data: {
                local_date: '2026-10-25',
                source: selectedType.id === restId ? 'default' : 'manual',
                day_type: selectedType,
                consumed: { calories: 120, protein: 20 },
                consumptions: [],
                health: null,
            } }));
        }
        if (path.endsWith('/day-types')) return new Response(JSON.stringify({ data: dayTypes }));
        if (path === '/session/csrf') return new Response(JSON.stringify({ data: { csrf_token: 'csrf' } }));
        throw new Error(`Unexpected request: ${path}`);
    }));

    render(
        <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
            <MemoryRouter initialEntries={['/history/2026-10-25']}>
                <Routes>
                    <Route path="/history/:date" element={<History />} />
                    <Route path="/history/:date/day" element={<PastDay />} />
                </Routes>
            </MemoryRouter>
        </QueryClientProvider>,
    );

    expect((await screen.findAllByText('Rest Day')).length).toBeGreaterThan(0);
    expect(screen.getByLabelText('Choose date')).toHaveProperty('max', '2026-10-25');
    expect(await screen.findByRole('button', { name: '26 October 2026' })).toHaveProperty('disabled', true);
    expect(within(await screen.findByRole('table')).queryByText('26 October 2026')).toBeNull();
    expect(screen.getAllByText('25 October 2026').length).toBeGreaterThan(1);
    expect(screen.queryByRole('button', { name: 'Correct day type' })).toBeNull();
    fireEvent.click(screen.getByRole('button', { name: 'Previous month' }));
    expect(await screen.findByRole('region', { name: 'September 2026 calendar' })).toBeTruthy();
    fireEvent.click(screen.getByRole('link', { name: 'Open day' }));
    expect((await screen.findByRole('link', { name: /History/ })).getAttribute('href'))
        .toBe('/history/2026-10-25');
    fireEvent.click(screen.getByRole('button', { name: 'Correct day type' }));
    expect(await screen.findByRole('combobox', { name: 'Day type' })).toBeTruthy();
    fireEvent.change(screen.getByRole('combobox', { name: 'Day type' }), { target: { value: trainingId } });
    expect(writes).toHaveLength(0);
    fireEvent.click(screen.getByRole('button', { name: 'Save correction' }));

    await waitFor(() => expect(writes).toEqual([{ day_type_id: trainingId }]));
    expect((await screen.findAllByText('Training Day')).length).toBeGreaterThan(0);
    expect(screen.queryByRole('combobox', { name: 'Day type' })).toBeNull();
});

it('concludes and reopens a day with separate idempotency keys', async () => {
    let keyNumber = 0;
    vi.stubGlobal('crypto', { randomUUID: () => `review-key-${++keyNumber}` });
    let status: 'open' | 'concluded' | 'reopened' = 'open';
    const writes: { path: string; key: string | null }[] = [];
    vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL, options?: RequestInit) => {
        const path = String(input);
        if (path === '/session/csrf') return new Response(JSON.stringify({ data: { csrf_token: 'csrf' } }));
        if (path.endsWith('/dashboard/today')) return new Response(JSON.stringify({ data: { local_date: '2026-10-26' } }));
        if (path.includes('/consumptions/dates')) return new Response(JSON.stringify({ data: [] }));
        if (path.endsWith('/days/2026-10-25/conclude') || path.endsWith('/days/2026-10-25/reopen')) {
            writes.push({ path, key: new Headers(options?.headers).get('Idempotency-Key') });
            status = path.endsWith('/conclude') ? 'concluded' : 'reopened';
            return new Response(JSON.stringify({ data: { status } }));
        }
        if (path.endsWith('/days/2026-10-25')) return new Response(JSON.stringify({ data: {
            local_date: '2026-10-25', source: 'default', day_type: dayTypes[0],
            consumed: { calories: 120, protein: 20 }, consumptions: [], health: null,
            review: { status, concluded_at: null, last_conclusion: null, revisions: [] },
        } }));
        throw new Error(`Unexpected request: ${path}`);
    }));

    render(
        <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
            <MemoryRouter initialEntries={['/history/2026-10-25/day']}>
                <Routes><Route path="/history/:date/day" element={<PastDay />} /></Routes>
            </MemoryRouter>
        </QueryClientProvider>,
    );

    const conclude = await screen.findByRole('button', { name: 'Conclude day' });
    expect(conclude).toHaveProperty('disabled', true);
    fireEvent.click(screen.getByRole('checkbox'));
    fireEvent.click(conclude);
    expect(await screen.findByRole('button', { name: 'Reopen to edit' })).toBeTruthy();
    fireEvent.click(screen.getByRole('button', { name: 'Reopen to edit' }));
    expect(await screen.findByRole('button', { name: 'Conclude again' })).toBeTruthy();
    expect(writes).toEqual([
        { path: '/api/v1/days/2026-10-25/conclude', key: 'review-key-1' },
        { path: '/api/v1/days/2026-10-25/reopen', key: 'review-key-2' },
    ]);
});
