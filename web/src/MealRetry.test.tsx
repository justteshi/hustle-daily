// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import App from './App';

const categoryId = '11111111-1111-4111-8111-111111111111';
const recipeId = '22222222-2222-4222-8222-222222222222';

function result(data: unknown, status = 200): Response {
  return { ok: status < 400, status, json: async () => ({ data }) } as Response;
}

function showMealForm() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  render(<QueryClientProvider client={client}><MemoryRouter initialEntries={['/meals/new']}><App /></MemoryRouter></QueryClientProvider>);
}

beforeEach(() => {
  vi.stubGlobal('crypto', { randomUUID: vi.fn(() => `${Math.random()}`) });
});

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
});

it('shows Retry when meal categories fail to load and opens the form after retry', async () => {
  let categoryRequests = 0;
  vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL): Promise<Response> => {
    const path = String(input);
    if (path.endsWith('/me')) return result({ id: 'user', name: 'QA' });
    if (path.endsWith('/meal-categories')) {
      categoryRequests++;
      if (categoryRequests === 1) throw new TypeError('Network unavailable');
      return result([{ id: categoryId, name: 'Breakfast', key: 'breakfast' }]);
    }
    if (path.endsWith('/recipes')) return result([]);
    if (path.includes('/foods?')) return result([]);
    throw new Error(`Unexpected request: ${path}`);
  }));

  showMealForm();

  expect((await screen.findByRole('alert')).textContent).toContain('Cannot load data');
  fireEvent.click(screen.getByRole('button', { name: 'Retry' }));
  expect(await screen.findByRole('heading', { name: 'Add meal' })).toBeTruthy();
  expect(categoryRequests).toBe(2);
});

it('retries a lost meal-save response with the same payload and idempotency key', async () => {
  const writes: { key: string | null; body: string | null }[] = [];
  const created = new Set<string>();
  vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL, options?: RequestInit): Promise<Response> => {
    const path = String(input);
    if (path.endsWith('/me')) return result({ id: 'user', name: 'QA' });
    if (path.endsWith('/meal-categories')) return result([{ id: categoryId, name: 'Breakfast', key: 'breakfast' }]);
    if (path.endsWith('/recipes')) return result([{ id: recipeId, name: 'Breakfast bowl', yield_servings: 1, ingredients: [] }]);
    if (path.includes('/foods?')) return result([]);
    if (path === '/session/csrf') return result({ csrf_token: 'csrf' });
    if (path.endsWith('/consumptions') && options?.method === 'POST') {
      const key = new Headers(options.headers).get('Idempotency-Key');
      writes.push({ key, body: String(options.body) });
      created.add(key!);
      if (writes.length === 1) throw new TypeError('Response lost after save');
      return result({ id: 'meal' });
    }
    if (path.endsWith('/dashboard/today')) return result({ local_date: '2026-10-23', consumed: { calories: 120, protein: 20 }, consumptions: [] });
    throw new Error(`Unexpected request: ${path}`);
  }));

  showMealForm();
  expect(await screen.findByRole('heading', { name: 'Add meal' })).toBeTruthy();
  fireEvent.click(screen.getByRole('radio', { name: 'Ready meal' }));
  fireEvent.change(screen.getByRole('combobox', { name: 'Ready meal' }), { target: { value: recipeId } });
  fireEvent.click(screen.getByRole('button', { name: 'Save' }));
  expect((await screen.findByRole('alert')).textContent).toContain('Response lost after save');

  fireEvent.click(screen.getByRole('button', { name: 'Save' }));
  await waitFor(() => expect(screen.getByRole('heading', { name: 'Today' })).toBeTruthy());

  expect(writes).toHaveLength(2);
  expect(writes[0].key).toBe(writes[1].key);
  expect(writes[0].body).toBe(writes[1].body);
  expect(created.size).toBe(1);
});
