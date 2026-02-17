export function formatMoney(value, currency = "₱") {
  const num = Number(value);
  if (!Number.isFinite(num)) return `${currency} 0.00`;
  return `${currency} ${num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}



