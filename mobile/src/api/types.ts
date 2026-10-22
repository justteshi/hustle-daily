export type Nutrition = {
  basis_amount: string;
  basis_unit: 'g' | 'ml' | 'serving';
  calories: string;
  protein: string;
  carbohydrates: string;
  fat: string;
  fibre: string | null;
  sugar: string | null;
  salt: string | null;
};

export type Portion = { id: string; label: string; base_amount: string; base_unit: 'g' | 'ml' | 'serving' };
export type Food = { id: string; name: string; brand: string; nutrition: Nutrition; portions: Portion[] };
export type MealCategory = { id: string; key: string; name: string; sort_order: number };
export type Recipe = { id: string; name: string; yield_servings: string; ingredients: { id: string; quantity: string; unit: string; food: Food }[] };
export type ConsumptionItem = { id: string; food_name: string; brand: string; quantity: string; unit: string; calories: string; protein: string };
export type Consumption = { id: string; source_name: string | null; calories: string; protein: string; eaten_at: string; items: ConsumptionItem[] };
export type Today = {
  local_date: string;
  time_zone: string;
  consumed: { calories: number; protein: number; carbohydrates: number; fat: number };
  consumptions: Consumption[];
  health: null;
};
export type User = { id: string; name: string; email: string; settings: { locale: string; time_zone: string } | null };
