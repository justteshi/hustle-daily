import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { Controller, useForm } from 'react-hook-form';
import { Text, View } from 'react-native';
import { z } from 'zod';
import { apiRequest } from '../../src/api/client';
import type { Food } from '../../src/api/types';
import { useIdempotency } from '../../src/api/useIdempotency';
import { strings } from '../../src/i18n';
import { Button, ErrorNotice, Input, Page, styles, Title } from '../../src/ui';

const positive = z.string().refine((value) => Number(value) > 0 && Number.isFinite(Number(value)));
const nonnegative = z
    .string()
    .refine((value) => Number(value) >= 0 && Number.isFinite(Number(value)));
const foodSchema = z
    .object({
        name: z.string().trim().min(1),
        brand: z.string().trim().min(1),
        basis_amount: positive,
        basis_unit: z.enum(['g', 'ml', 'serving']),
        calories: nonnegative,
        protein: nonnegative,
        carbohydrates: nonnegative,
        fat: nonnegative,
        portion_label: z.string(),
        portion_amount: z.string(),
    })
    .refine((value) => !value.portion_label || Number(value.portion_amount) > 0, {
        path: ['portion_amount'],
    });
type FoodValues = z.infer<typeof foodSchema>;

export default function NewFood() {
    const router = useRouter();
    const queryClient = useQueryClient();
    const idempotency = useIdempotency();
    const {
        control,
        handleSubmit,
        watch,
        setValue,
        formState: { errors },
    } = useForm<FoodValues>({
        resolver: zodResolver(foodSchema),
        defaultValues: {
            name: '',
            brand: '',
            basis_amount: '100',
            basis_unit: 'g',
            calories: '',
            protein: '',
            carbohydrates: '',
            fat: '',
            portion_label: '',
            portion_amount: '',
        },
    });
    const unit = watch('basis_unit');
    const save = useMutation({
        mutationFn: (values: FoodValues) => {
            const payload = {
                name: values.name.trim(),
                brand: values.brand.trim(),
                nutrition: {
                    basis_amount: Number(values.basis_amount),
                    basis_unit: values.basis_unit,
                    calories: Number(values.calories),
                    protein: Number(values.protein),
                    carbohydrates: Number(values.carbohydrates),
                    fat: Number(values.fat),
                },
                portions: values.portion_label.trim()
                    ? [
                          {
                              label: values.portion_label.trim(),
                              base_amount: Number(values.portion_amount),
                              base_unit: values.basis_unit,
                          },
                      ]
                    : [],
            };
            return apiRequest<Food>('/foods', {
                method: 'POST',
                headers: { 'Idempotency-Key': idempotency.keyFor(payload) },
                body: JSON.stringify(payload),
            });
        },
        onSuccess: async () => {
            idempotency.clear();
            await queryClient.invalidateQueries({ queryKey: ['foods'] });
            router.back();
        },
    });

    return (
        <Page>
            <Title>{strings.createFood}</Title>
            <Button title={strings.cancel} onPress={() => router.back()} secondary />
            <Controller
                control={control}
                name="name"
                render={({ field }) => (
                    <Input
                        label={strings.foodName}
                        value={field.value}
                        onChangeText={field.onChange}
                    />
                )}
            />
            {errors.name && <Text style={styles.errorText}>{strings.errors.required}</Text>}
            <Controller
                control={control}
                name="brand"
                render={({ field }) => (
                    <Input
                        label={strings.brand}
                        value={field.value}
                        onChangeText={field.onChange}
                    />
                )}
            />
            {errors.brand && <Text style={styles.errorText}>{strings.errors.required}</Text>}
            <Controller
                control={control}
                name="basis_amount"
                render={({ field }) => (
                    <Input
                        label={strings.basisAmount}
                        value={field.value}
                        onChangeText={field.onChange}
                        keyboardType="decimal-pad"
                    />
                )}
            />
            {errors.basis_amount && <Text style={styles.errorText}>{strings.errors.number}</Text>}
            <Text style={styles.label}>{strings.basisUnit}</Text>
            <View style={styles.row}>
                {(['g', 'ml', 'serving'] as const).map((value) => (
                    <Button
                        key={value}
                        title={value}
                        onPress={() => setValue('basis_unit', value)}
                        secondary={unit !== value}
                    />
                ))}
            </View>
            {(['calories', 'protein', 'carbohydrates', 'fat'] as const).map((fieldName) => (
                <View key={fieldName}>
                    <Controller
                        control={control}
                        name={fieldName}
                        render={({ field }) => (
                            <Input
                                label={strings[fieldName]}
                                value={field.value}
                                onChangeText={field.onChange}
                                keyboardType="decimal-pad"
                            />
                        )}
                    />
                    {errors[fieldName] && (
                        <Text style={styles.errorText}>{strings.errors.number}</Text>
                    )}
                </View>
            ))}
            <Controller
                control={control}
                name="portion_label"
                render={({ field }) => (
                    <Input
                        label={strings.portion}
                        value={field.value}
                        onChangeText={field.onChange}
                    />
                )}
            />
            <Controller
                control={control}
                name="portion_amount"
                render={({ field }) => (
                    <Input
                        label={`${strings.quantity} (${unit})`}
                        value={field.value}
                        onChangeText={field.onChange}
                        keyboardType="decimal-pad"
                    />
                )}
            />
            {errors.portion_amount && <Text style={styles.errorText}>{strings.errors.number}</Text>}
            <ErrorNotice error={save.error} />
            <Text style={styles.muted}>{strings.serverOnly}</Text>
            <Button
                title={strings.save}
                onPress={handleSubmit((values) => save.mutate(values))}
                disabled={save.isPending}
            />
        </Page>
    );
}
