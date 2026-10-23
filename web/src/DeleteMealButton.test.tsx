// @vitest-environment jsdom
import { afterEach, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import DeleteMealButton from './DeleteMealButton';

afterEach(() => {
    cleanup();
    document.getElementById('root')?.remove();
    vi.unstubAllGlobals();
});

it('asks for confirmation and keeps the meal when cancelled', () => {
    vi.stubGlobal('crypto', { randomUUID: () => 'test-key' });
    const fetch = vi.fn();
    vi.stubGlobal('fetch', fetch);
    const root = document.createElement('div');
    root.id = 'root';
    document.body.appendChild(root);
    render(
        <QueryClientProvider client={new QueryClient()}>
            <DeleteMealButton id="meal-1" date="2026-10-23" />
        </QueryClientProvider>,
        { container: root },
    );

    fireEvent.click(screen.getByRole('button', { name: 'Delete meal' }));
    expect(screen.getByRole('dialog')).toBeTruthy();
    expect(root.inert).toBe(true);
    const keep = screen.getByRole('button', { name: 'Keep meal' });
    const confirmDelete = screen
        .getByRole('dialog')
        .querySelector<HTMLButtonElement>('.confirm-danger')!;
    expect(document.activeElement).toBe(keep);
    fireEvent.keyDown(keep, { key: 'Tab' });
    expect(document.activeElement).toBe(confirmDelete);
    fireEvent.keyDown(confirmDelete, { key: 'Tab' });
    expect(document.activeElement).toBe(keep);
    fireEvent.click(screen.getByRole('button', { name: 'Keep meal' }));

    expect(screen.queryByRole('dialog')).toBeNull();
    expect(root.inert).toBe(false);
    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Delete meal' }));
    expect(fetch).not.toHaveBeenCalled();
});
