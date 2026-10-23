export function formatNumber(value: number | string, maximumFractionDigits = 3): string {
    const number = Number(value);
    return Number.isFinite(number)
        ? new Intl.NumberFormat(navigator.language, { maximumFractionDigits }).format(number)
        : String(value);
}
