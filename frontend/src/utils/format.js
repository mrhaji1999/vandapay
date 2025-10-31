export const formatCurrency = (value) =>
  new Intl.NumberFormat('fa-IR', { style: 'currency', currency: 'IRR', maximumFractionDigits: 0 }).format(
    Number(value || 0),
  );

export const formatDate = (value) => {
  if (!value) return '-';
  return new Intl.DateTimeFormat('fa-IR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date(value));
};
