const base = import.meta.env.VITE_API_BASE_URL || '/api/v1';
let csrfToken: string | null = null;

export type FoodNutrition = {
    basis_amount: number;
    basis_unit: 'g' | 'ml' | 'serving';
    calories: number;
    protein: number;
    carbohydrates: number;
    fat: number;
    fibre: number | null;
    sugar: number | null;
    salt: number | null;
};
export type FoodPortion = { id?: string; label: string; base_amount: number; base_unit: string };
export type Food = {
    id: string;
    name: string;
    brand: string;
    barcode: string | null;
    nutrition: FoodNutrition;
    portions: FoodPortion[];
};
export type Category = { id: string; name: string; key: string };
export type Item = {
    food_id: string;
    food_name: string;
    brand: string;
    quantity: number;
    unit: string;
    input_unit: string | null;
    food_portion_id: string | null;
};
export type Meal = {
    id: string;
    local_date: string;
    meal_category_id: string;
    notes: string | null;
    eaten_at: string;
    source_name: string | null;
    calories: number;
    protein: number;
    items: Item[];
};
export type Today = {
    local_date: string;
    consumed: { calories: number; protein: number };
    consumptions: Meal[];
    day_type: DayType;
};
export type DayType = {
    id: string;
    key: string;
    name: string;
    color: string;
    targets: Record<string, { value: number; unit: string; comparison_rule: string }>;
};
export type Recipe = {
    id: string;
    name: string;
    yield_servings: number;
    ingredients: {
        food_id: string;
        quantity: number;
        unit: string;
        food_portion_id: string | null;
    }[];
};
export type FoodDate = {
    local_date: string;
    meal_count: number;
    calories: number;
    protein: number;
};

export class ApiError extends Error {
    constructor(
        message: string,
        public status: number,
    ) {
        super(message);
    }
}

async function decode<T>(response: Response): Promise<T> {
    if (response.status === 204) return undefined as T;
    const body = await response.json();
    if (!response.ok)
        throw new ApiError(
            body.error?.message || body.message || `Request failed (${response.status})`,
            response.status,
        );
    return body.data as T;
}

async function ensureCsrf() {
    if (!csrfToken)
        csrfToken = (
            await decode<{ csrf_token: string }>(
                await fetch('/session/csrf', {
                    credentials: 'include',
                    headers: { Accept: 'application/json' },
                }),
            )
        ).csrf_token;
}

export async function request<T>(
    path: string,
    options: RequestInit = {},
    mutation = false,
): Promise<T> {
    if (mutation) await ensureCsrf();
    const headers = new Headers(options.headers);
    headers.set('Accept', 'application/json');
    if (options.body) headers.set('Content-Type', 'application/json');
    if (mutation) {
        headers.set('X-CSRF-TOKEN', csrfToken!);
        if (!headers.has('Idempotency-Key')) headers.set('Idempotency-Key', crypto.randomUUID());
    }
    return decode<T>(await fetch(base + path, { credentials: 'include', ...options, headers }));
}

export async function login(email: string, password: string) {
    await ensureCsrf();
    const result = await decode<{ authenticated: boolean }>(
        await fetch('/session/login', {
            method: 'POST',
            credentials: 'include',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken!,
            },
            body: JSON.stringify({ email, password }),
        }),
    );
    csrfToken = null;
    return result;
}

export async function logout() {
    await ensureCsrf();
    await decode(
        await fetch('/session/logout', {
            method: 'POST',
            credentials: 'include',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken! },
        }),
    );
    csrfToken = null;
}
