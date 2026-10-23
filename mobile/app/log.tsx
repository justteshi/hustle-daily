import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import { Controller, useFieldArray, useForm } from 'react-hook-form';
import { Text, View } from 'react-native';
import { z } from 'zod';
import { apiRequest, mutationKey } from '../src/api/client';
import type { Food, MealCategory, Recipe } from '../src/api/types';
import { useIdempotency } from '../src/api/useIdempotency';
import { strings } from '../src/i18n';
import { Button, ErrorNotice, Input, Page, styles, Title } from '../src/ui';

const mealSchema = z.object({
    meal_category_id: z.uuid(),
    items: z
        .array(
            z.object({
                food_id: z.uuid(),
                quantity: z
                    .string()
                    .refine((value) => Number(value) > 0 && Number.isFinite(Number(value))),
                unit: z.enum(['g', 'ml', 'serving', 'portion']),
                portion_id: z.string().optional(),
            }),
        )
        .min(1),
});
type MealValues = z.infer<typeof mealSchema>;

export default function LogMeal() {
    const router = useRouter();
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [query, setQuery] = useState('');
    const [selected, setSelected] = useState<Record<string, Food>>({});
    const [mode, setMode] = useState<'ready' | 'foods'>('ready');
    const [selectedRecipeId, setSelectedRecipeId] = useState('');
    const [servings, setServings] = useState('1');
    const [recipeName, setRecipeName] = useState('');
    const [recipeYield, setRecipeYield] = useState('1');
    const [recipeFormError, setRecipeFormError] = useState('');
    const recipeAttempt = useIdempotency();
    const attempt = useRef<{ input: string; key: string; eatenAt: string } | null>(null);
    const readyAttempt = useRef<{ input: string; key: string; eatenAt: string } | null>(null);
    useEffect(() => {
        const timer = setTimeout(() => setQuery(search), 250);
        return () => clearTimeout(timer);
    }, [search]);
    const foods = useQuery({
        queryKey: ['foods', query],
        queryFn: () => apiRequest<Food[]>(`/foods?query=${encodeURIComponent(query)}`),
    });
    const categories = useQuery({
        queryKey: ['meal-categories'],
        queryFn: () => apiRequest<MealCategory[]>('/meal-categories'),
    });
    const recipes = useQuery({
        queryKey: ['recipes'],
        queryFn: () => apiRequest<Recipe[]>('/recipes'),
    });
    const {
        control,
        getValues,
        handleSubmit,
        setValue,
        watch,
        formState: { errors },
    } = useForm<MealValues>({
        resolver: zodResolver(mealSchema),
        defaultValues: { meal_category_id: '', items: [] },
    });
    const { fields, append, remove } = useFieldArray({ control, name: 'items' });
    const selectedCategory = watch('meal_category_id');
    const save = useMutation({
        mutationFn: (values: MealValues) => {
            const input = JSON.stringify(values);
            if (attempt.current?.input !== input) {
                attempt.current = {
                    input,
                    key: mutationKey(),
                    eatenAt: new Date().toISOString().replace(/\.\d{3}Z$/, '+00:00'),
                };
            }
            const payload = {
                meal_category_id: values.meal_category_id,
                eaten_at: attempt.current.eatenAt,
                items: values.items.map((item) => ({
                    food_id: item.food_id,
                    quantity: Number(item.quantity),
                    unit: item.unit,
                    ...(item.unit === 'portion' ? { portion_id: item.portion_id } : {}),
                })),
            };
            return apiRequest('/consumptions', {
                method: 'POST',
                headers: { 'Idempotency-Key': attempt.current.key },
                body: JSON.stringify(payload),
            });
        },
        onSuccess: async () => {
            attempt.current = null;
            await queryClient.invalidateQueries({ queryKey: ['today'] });
            router.replace('/');
        },
    });
    const createRecipe = useMutation({
        mutationFn: (payload: {
            name: string;
            yield_servings: number;
            ingredients: { food_id: string; quantity: number; unit: string; portion_id?: string }[];
        }) =>
            apiRequest<Recipe>('/recipes', {
                method: 'POST',
                headers: { 'Idempotency-Key': recipeAttempt.keyFor(payload) },
                body: JSON.stringify(payload),
            }),
        onSuccess: async (recipe) => {
            recipeAttempt.clear();
            await queryClient.invalidateQueries({ queryKey: ['recipes'] });
            setSelectedRecipeId(recipe.id);
            setMode('ready');
        },
    });
    const saveReady = useMutation({
        mutationFn: (values: { meal_category_id: string; recipe_id: string; servings: number }) => {
            const input = JSON.stringify(values);
            if (readyAttempt.current?.input !== input) {
                readyAttempt.current = {
                    input,
                    key: mutationKey(),
                    eatenAt: new Date().toISOString().replace(/\.\d{3}Z$/, '+00:00'),
                };
            }
            return apiRequest('/consumptions', {
                method: 'POST',
                headers: { 'Idempotency-Key': readyAttempt.current.key },
                body: JSON.stringify({ ...values, eaten_at: readyAttempt.current.eatenAt }),
            });
        },
        onSuccess: async () => {
            readyAttempt.current = null;
            await queryClient.invalidateQueries({ queryKey: ['today'] });
            router.replace('/');
        },
    });
    const saveAsReadyMeal = () => {
        const name = recipeName.trim();
        const parsedItems = mealSchema.shape.items.safeParse(getValues('items'));
        const yieldServings = Number(recipeYield);
        if (!name) {
            setRecipeFormError(strings.errors.readyMealName);
            return;
        }
        if (!parsedItems.success) {
            setRecipeFormError(strings.errors.chooseFood);
            return;
        }
        if (!(yieldServings > 0) || !Number.isFinite(yieldServings)) {
            setRecipeFormError(strings.errors.number);
            return;
        }
        setRecipeFormError('');
        createRecipe.mutate({
            name,
            yield_servings: yieldServings,
            ingredients: parsedItems.data.map((item) => ({
                food_id: item.food_id,
                quantity: Number(item.quantity),
                unit: item.unit,
                ...(item.unit === 'portion' ? { portion_id: item.portion_id } : {}),
            })),
        });
    };
    const logReadyMeal = () => {
        const categoryId = getValues('meal_category_id');
        const count = Number(servings);
        if (!categoryId) {
            setRecipeFormError(strings.errors.chooseCategory);
            return;
        }
        if (!selectedRecipeId) {
            setRecipeFormError(strings.errors.chooseReadyMeal);
            return;
        }
        if (!(count > 0) || !Number.isFinite(count)) {
            setRecipeFormError(strings.errors.number);
            return;
        }
        setRecipeFormError('');
        saveReady.mutate({
            meal_category_id: categoryId,
            recipe_id: selectedRecipeId,
            servings: count,
        });
    };
    const addFood = (food: Food) => {
        setSelected((current) => ({ ...current, [food.id]: food }));
        append({
            food_id: food.id,
            quantity: food.nutrition.basis_unit === 'serving' ? '1' : '100',
            unit: food.nutrition.basis_unit,
        });
    };

    return (
        <Page>
            <Title>{strings.addMeal}</Title>
            <Button title={strings.cancel} onPress={() => router.back()} secondary />
            <View style={styles.row}>
                <Button
                    title={strings.savedMeals}
                    onPress={() => {
                        setMode('ready');
                        setRecipeFormError('');
                    }}
                    secondary={mode !== 'ready'}
                />
                <Button
                    title={strings.individualFoods}
                    onPress={() => {
                        setMode('foods');
                        setRecipeFormError('');
                    }}
                    secondary={mode !== 'foods'}
                />
            </View>
            <Text style={styles.label}>{strings.mealCategory}</Text>
            <ErrorNotice
                error={categories.error}
                onRetry={() => {
                    void categories.refetch();
                }}
            />
            <View style={styles.row}>
                {categories.data?.map((category) => (
                    <Button
                        key={category.id}
                        title={category.name}
                        onPress={() =>
                            setValue('meal_category_id', category.id, { shouldValidate: true })
                        }
                        secondary={selectedCategory !== category.id}
                    />
                ))}
            </View>
            {errors.meal_category_id && (
                <Text style={styles.errorText}>{strings.errors.chooseCategory}</Text>
            )}
            {mode === 'ready' ? (
                <>
                    <Text style={styles.subtitle}>{strings.savedMeals}</Text>
                    <ErrorNotice
                        error={recipes.error}
                        onRetry={() => {
                            void recipes.refetch();
                        }}
                    />
                    {recipes.data?.length === 0 && (
                        <Text style={styles.muted}>{strings.noSavedMeals}</Text>
                    )}
                    {recipes.data?.map((recipe) => (
                        <View key={recipe.id} style={styles.card}>
                            <Text style={styles.body}>{recipe.name}</Text>
                            <Text style={styles.muted}>
                                {recipe.ingredients
                                    .map(
                                        (ingredient) =>
                                            `${ingredient.food.name} · ${ingredient.food.brand}`,
                                    )
                                    .join(', ')}
                            </Text>
                            <Button
                                title={recipe.name}
                                onPress={() => setSelectedRecipeId(recipe.id)}
                                secondary={selectedRecipeId !== recipe.id}
                            />
                        </View>
                    ))}
                    <Input
                        label={strings.servingsToLog}
                        value={servings}
                        onChangeText={setServings}
                        keyboardType="decimal-pad"
                    />
                    {recipeFormError ? (
                        <Text style={styles.errorText}>{recipeFormError}</Text>
                    ) : null}
                    <ErrorNotice error={saveReady.error} onRetry={logReadyMeal} />
                    <Button
                        title={strings.logReadyMeal}
                        onPress={logReadyMeal}
                        disabled={saveReady.isPending}
                    />
                </>
            ) : (
                <>
                    <Text style={styles.subtitle}>{strings.individualFoods}</Text>
                    {fields.map((field, index) => {
                        const food = selected[field.food_id];
                        const chosenUnit = watch(`items.${index}.unit`);
                        return (
                            <View key={field.id} style={styles.card}>
                                <Text style={styles.body}>
                                    {food ? `${food.name} · ${food.brand}` : field.food_id}
                                </Text>
                                <Controller
                                    control={control}
                                    name={`items.${index}.quantity`}
                                    render={({ field: quantity }) => (
                                        <Input
                                            label={strings.quantity}
                                            value={quantity.value}
                                            onChangeText={quantity.onChange}
                                            keyboardType="decimal-pad"
                                        />
                                    )}
                                />
                                {errors.items?.[index]?.quantity && (
                                    <Text style={styles.errorText}>{strings.errors.number}</Text>
                                )}
                                <View style={styles.row}>
                                    {food && (
                                        <Button
                                            title={food.nutrition.basis_unit}
                                            onPress={() => {
                                                setValue(
                                                    `items.${index}.unit`,
                                                    food.nutrition.basis_unit,
                                                );
                                                setValue(`items.${index}.portion_id`, undefined);
                                            }}
                                            secondary={chosenUnit !== food.nutrition.basis_unit}
                                        />
                                    )}
                                    {food?.portions.map((portion) => (
                                        <Button
                                            key={portion.id}
                                            title={portion.label}
                                            onPress={() => {
                                                setValue(`items.${index}.unit`, 'portion');
                                                setValue(`items.${index}.portion_id`, portion.id);
                                            }}
                                            secondary={
                                                watch(`items.${index}.portion_id`) !== portion.id
                                            }
                                        />
                                    ))}
                                </View>
                                <Button
                                    title={strings.remove}
                                    onPress={() => remove(index)}
                                    secondary
                                />
                            </View>
                        );
                    })}
                    {errors.items?.root && (
                        <Text style={styles.errorText}>{strings.errors.chooseFood}</Text>
                    )}
                    <Input label={strings.searchFoods} value={search} onChangeText={setSearch} />
                    <ErrorNotice
                        error={foods.error}
                        onRetry={() => {
                            void foods.refetch();
                        }}
                    />
                    {foods.data?.map((food) => (
                        <View key={food.id} style={styles.card}>
                            <Text style={styles.body}>
                                {food.name} · {food.brand}
                            </Text>
                            <Button
                                title={strings.addMeal}
                                onPress={() => addFood(food)}
                                secondary
                            />
                        </View>
                    ))}
                    <Button
                        title={strings.createFood}
                        onPress={() => router.push('/foods/new')}
                        secondary
                    />
                    <Input
                        label={strings.readyMealName}
                        value={recipeName}
                        onChangeText={setRecipeName}
                    />
                    <Input
                        label={strings.servingsInRecipe}
                        value={recipeYield}
                        onChangeText={setRecipeYield}
                        keyboardType="decimal-pad"
                    />
                    {recipeFormError ? (
                        <Text style={styles.errorText}>{recipeFormError}</Text>
                    ) : null}
                    <ErrorNotice error={createRecipe.error} onRetry={saveAsReadyMeal} />
                    <Button
                        title={strings.saveAsReadyMeal}
                        onPress={saveAsReadyMeal}
                        disabled={createRecipe.isPending}
                        secondary
                    />
                    <ErrorNotice error={save.error} />
                    <Text style={styles.muted}>{strings.serverOnly}</Text>
                    <Button
                        title={strings.save}
                        onPress={handleSubmit((values) => save.mutate(values))}
                        disabled={save.isPending}
                    />
                </>
            )}
        </Page>
    );
}
