export function calculateBuybackAmount(
  loanAmount: number,
  loanPercent: number,
): number {
  return Math.round((loanAmount + (loanAmount * loanPercent) / 100) * 100) / 100;
}

export function calculateInterestAmount(
  loanAmount: number | string,
  loanPercent: number | string,
  buybackAmount?: number | string | null,
): number {
  const loan = typeof loanAmount === 'string' ? parseFloat(loanAmount) : loanAmount;
  const percent = typeof loanPercent === 'string' ? parseFloat(loanPercent) : loanPercent;
  const buyback =
    buybackAmount != null && buybackAmount !== ''
      ? typeof buybackAmount === 'string'
        ? parseFloat(buybackAmount)
        : buybackAmount
      : calculateBuybackAmount(loan, percent);
  return Math.round(Math.max(0, buyback - loan) * 100) / 100;
}

export function formatMoney(value: number | string): string {
  const n = typeof value === 'string' ? parseFloat(value) : value;
  if (Number.isNaN(n)) return '—';
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  }).format(n);
}
