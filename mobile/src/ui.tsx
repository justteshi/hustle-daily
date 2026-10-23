import type { ReactNode } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { strings } from './i18n';

export const colors = {
    background: '#f5f3ee',
    ink: '#17201b',
    muted: '#4d5751',
    accent: '#2f6b4f',
    border: '#e3e0d7',
    danger: '#a0322b',
    white: '#ffffff',
};

export function Page({ children }: { children: ReactNode }) {
    return (
        <ScrollView
            style={styles.page}
            contentContainerStyle={styles.content}
            keyboardShouldPersistTaps="handled"
        >
            {children}
        </ScrollView>
    );
}

export function Title({ children }: { children: ReactNode }) {
    return <Text style={styles.title}>{children}</Text>;
}

export function Button({
    title,
    onPress,
    disabled,
    secondary = false,
}: {
    title: string;
    onPress: () => void;
    disabled?: boolean;
    secondary?: boolean;
}) {
    return (
        <Pressable
            accessibilityRole="button"
            onPress={onPress}
            disabled={disabled}
            style={[styles.button, secondary && styles.secondary, disabled && styles.disabled]}
        >
            <Text style={[styles.buttonText, secondary && styles.secondaryText]}>{title}</Text>
        </Pressable>
    );
}

export function Input({
    label,
    value,
    onChangeText,
    keyboardType,
    secureTextEntry,
    placeholder,
}: {
    label: string;
    value: string;
    onChangeText: (value: string) => void;
    keyboardType?: 'default' | 'email-address' | 'decimal-pad';
    secureTextEntry?: boolean;
    placeholder?: string;
}) {
    return (
        <View style={styles.field}>
            <Text style={styles.label}>{label}</Text>
            <TextInput
                style={styles.input}
                value={value}
                onChangeText={onChangeText}
                keyboardType={keyboardType}
                secureTextEntry={secureTextEntry}
                placeholder={placeholder}
                autoCapitalize={
                    secureTextEntry || keyboardType === 'email-address' ? 'none' : 'sentences'
                }
                autoCorrect={!secureTextEntry && keyboardType !== 'email-address'}
            />
        </View>
    );
}

export function ErrorNotice({ error, onRetry }: { error: unknown; onRetry?: () => void }) {
    if (!error) return null;
    const message = error instanceof Error ? error.message : strings.errors.request;
    return (
        <View style={styles.error}>
            <Text style={styles.errorText}>{message}</Text>
            {onRetry && <Button title={strings.retry} onPress={onRetry} secondary />}
        </View>
    );
}

export const styles = StyleSheet.create({
    page: { flex: 1, backgroundColor: colors.background },
    content: { padding: 24, paddingTop: 64, paddingBottom: 56, gap: 16 },
    title: { fontSize: 34, fontWeight: '700', color: colors.ink },
    subtitle: { fontSize: 17, fontWeight: '600', color: colors.ink },
    body: { fontSize: 17, lineHeight: 24, color: colors.ink },
    muted: { fontSize: 13, lineHeight: 19, color: colors.muted },
    card: {
        padding: 18,
        backgroundColor: colors.white,
        borderRadius: 16,
        borderWidth: 1,
        borderColor: colors.border,
        gap: 8,
    },
    row: { flexDirection: 'row', gap: 10, alignItems: 'center', flexWrap: 'wrap' },
    field: { gap: 6 },
    label: { fontSize: 14, fontWeight: '600', color: colors.ink },
    input: {
        minHeight: 48,
        borderWidth: 1,
        borderColor: colors.border,
        backgroundColor: colors.white,
        borderRadius: 10,
        paddingHorizontal: 14,
        color: colors.ink,
    },
    button: {
        minHeight: 48,
        borderRadius: 10,
        backgroundColor: colors.accent,
        justifyContent: 'center',
        alignItems: 'center',
        paddingHorizontal: 16,
        paddingVertical: 10,
    },
    secondary: { backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border },
    disabled: { opacity: 0.5 },
    buttonText: { color: colors.white, fontWeight: '700', fontSize: 15 },
    secondaryText: { color: colors.ink },
    error: { padding: 14, borderRadius: 12, backgroundColor: '#fbe9e7', gap: 10 },
    errorText: { color: colors.danger, fontSize: 14 },
});
