import { useQuery } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { Text, View } from 'react-native';
import { apiRequest } from '../../src/api/client';
import type { Food } from '../../src/api/types';
import { strings } from '../../src/i18n';
import { Button, ErrorNotice, Input, Page, styles, Title } from '../../src/ui';

export default function Foods() {
    const router = useRouter();
    const [search, setSearch] = useState('');
    const [query, setQuery] = useState('');
    useEffect(() => {
        const timer = setTimeout(() => setQuery(search), 250);
        return () => clearTimeout(timer);
    }, [search]);
    const foods = useQuery({
        queryKey: ['foods', query],
        queryFn: () => apiRequest<Food[]>(`/foods?query=${encodeURIComponent(query)}`),
    });

    return (
        <Page>
            <Title>{strings.foods}</Title>
            <Button title={strings.today} onPress={() => router.back()} secondary />
            <Input label={strings.searchFoods} value={search} onChangeText={setSearch} />
            <Button title={strings.createFood} onPress={() => router.push('/foods/new')} />
            <ErrorNotice
                error={foods.error}
                onRetry={() => {
                    void foods.refetch();
                }}
            />
            {foods.isLoading && <Text style={styles.muted}>{strings.loading}</Text>}
            {foods.data?.length === 0 && <Text style={styles.muted}>{strings.noFoods}</Text>}
            {foods.data?.map((food) => (
                <View key={food.id} style={styles.card}>
                    <Text style={styles.subtitle}>{food.name}</Text>
                    <Text style={styles.muted}>{food.brand}</Text>
                    <Text style={styles.body}>
                        {food.nutrition.calories} {strings.calories} · {food.nutrition.protein}{' '}
                        {strings.grams} {strings.protein} / {food.nutrition.basis_amount}{' '}
                        {food.nutrition.basis_unit}
                    </Text>
                </View>
            ))}
        </Page>
    );
}
