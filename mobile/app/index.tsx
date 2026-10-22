import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { Text, View } from 'react-native';
import { z } from 'zod';
import { apiRequest } from '../src/api/client';
import type { Today, User } from '../src/api/types';
import { strings } from '../src/i18n';
import { useSession } from '../src/state/session';
import { Button, ErrorNotice, Input, Page, styles, Title } from '../src/ui';

const loginSchema = z.object({ email: z.email(), password: z.string().min(1) });
type LoginValues = z.infer<typeof loginSchema>;

export default function Home() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const { token, hydrated, hydrate, save, clear } = useSession();
  const [logoutError, setLogoutError] = useState<unknown>(null);
  const { control, handleSubmit, formState: { errors } } = useForm<LoginValues>({ resolver: zodResolver(loginSchema), defaultValues: { email: '', password: '' } });
  useEffect(() => { void hydrate(); }, [hydrate]);
  const login = useMutation({
    mutationFn: (values: LoginValues) => apiRequest<{ token: string; user: User }>('/auth/login', {
      method: 'POST', body: JSON.stringify({ ...values, device_name: 'Hustle Daily iPhone' }),
    }, false),
    onSuccess: async (result) => { await save(result.token); await queryClient.invalidateQueries(); },
  });
  const me = useQuery({ queryKey: ['me', token], queryFn: () => apiRequest<User>('/me'), enabled: hydrated && !!token });
  const today = useQuery({ queryKey: ['today', token], queryFn: () => apiRequest<Today>('/dashboard/today'), enabled: hydrated && !!token });
  const logout = async () => {
    setLogoutError(null);
    try {
      await apiRequest('/auth/logout', { method: 'POST' });
      await clear();
      queryClient.clear();
    } catch (error) { setLogoutError(error); }
  };

  if (!hydrated) return <Page><Title>{strings.title}</Title><Text style={styles.muted}>{strings.loading}</Text></Page>;
  if (!token) return <Page>
    <Title>{strings.title}</Title>
    <Text style={styles.muted}>{strings.serverOnly}</Text>
    <Controller control={control} name="email" render={({ field }) => <Input label={strings.email} value={field.value} onChangeText={field.onChange} keyboardType="email-address" />} />
    {errors.email && <Text style={styles.errorText}>{strings.errors.required}</Text>}
    <Controller control={control} name="password" render={({ field }) => <Input label={strings.password} value={field.value} onChangeText={field.onChange} secureTextEntry />} />
    {errors.password && <Text style={styles.errorText}>{strings.errors.required}</Text>}
    <ErrorNotice error={login.error} />
    <Button title={strings.signIn} onPress={handleSubmit((values) => login.mutate(values))} disabled={login.isPending} />
  </Page>;

  return <Page>
    <Title>{strings.today}</Title>
    <Text style={styles.muted}>{me.data?.name ?? strings.title} · {today.data?.local_date ?? strings.loading}</Text>
    <ErrorNotice error={me.error ?? today.error} onRetry={() => { void me.refetch(); void today.refetch(); }} />
    <View style={styles.card}>
      <Text style={styles.subtitle}>{strings.consumed}</Text>
      <Text style={styles.body}>{strings.calories}: {today.data?.consumed.calories ?? '—'}</Text>
      <Text style={styles.body}>{strings.protein}: {today.data?.consumed.protein ?? '—'} {strings.grams}</Text>
      <Text style={styles.muted}>{strings.carbohydrates}: {today.data?.consumed.carbohydrates ?? '—'} {strings.grams} · {strings.fat}: {today.data?.consumed.fat ?? '—'} {strings.grams}</Text>
    </View>
    <Text style={styles.muted}>{strings.healthPending}</Text>
    <Button title={strings.addMeal} onPress={() => router.push('/log')} />
    <Button title={strings.foods} onPress={() => router.push('/foods')} secondary />
    <Button title={strings.refresh} onPress={() => { void today.refetch(); }} secondary />
    <Text style={styles.subtitle}>{strings.items}</Text>
    {today.data?.consumptions.length === 0 && <Text style={styles.muted}>{strings.noMeals}</Text>}
    {today.data?.consumptions.map((entry) => <View key={entry.id} style={styles.card}>
      <Text style={styles.body}>{entry.source_name ?? entry.items.map((item) => item.food_name).join(', ')}</Text>
      <Text style={styles.muted}>{entry.calories} {strings.calories} · {entry.protein} {strings.grams} {strings.protein}</Text>
    </View>)}
    <ErrorNotice error={logoutError} onRetry={() => { void logout(); }} />
    <Button title={strings.signOut} onPress={() => { void logout(); }} secondary />
  </Page>;
}
