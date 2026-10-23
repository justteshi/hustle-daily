// @vitest-environment jsdom
import { afterEach, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import DeleteMealButton from './DeleteMealButton';

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

it('asks for confirmation and keeps the meal when cancelled', () => {
    vi.stubGlobal('crypto', { randomUUID: () => 'test-key' });
    const fetch = vi.fn();
    vi.stubGlobal('fetch', fetch);
    render(
        <QueryClientProvider client={new QueryClient()}>
            <DeleteMealButton id="meal-1" date="2026-10-23" />
        </QueryClientProvider>,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Delete meal' }));
    expect(screen.getByRole('dialog')).toBeTruthy();
    fireEvent.click(screen.getByRole('button', { name: 'Keep meal' }));

    expect(screen.queryByRole('dialog')).toBeNull();
    expect(fetch).not.toHaveBeenCalled();
});
